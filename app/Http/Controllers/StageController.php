<?php

namespace App\Http\Controllers;

use App\Services\StageTemplateService;
use App\Models\School;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StageController extends Controller
{
    public function __construct(
        protected StageTemplateService $stageService
    ) {}

    /**
     * Get storage directory for current school.
     */
    protected function getSchoolStorageDir(?School $school = null): string
    {
        if (! $school && Auth::check()) {
            $school = Auth::user()->getEffectiveSchool();
        }

        if ($school) {
            $dir = storage_path('app/hpc/schools/' . $school->id);
            if (! is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            return $dir;
        }

        return storage_path('app/hpc');
    }

    /**
     * Get storage directory for active teacher profile.
     */
    protected function getActiveTeacherProfileDir(?School $school = null): string
    {
        $schoolDir = $this->getSchoolStorageDir($school);
        $teacher = Auth::user()?->getEffectiveTeacher();
        $tid = $teacher ? $teacher->id : 'default';
        $tDir = $schoolDir . '/teacher-profiles/teacher_' . $tid;
        if (! is_dir($tDir)) {
            @mkdir($tDir, 0777, true);
        }
        return $tDir;
    }

    /**
     * API / Web view for stage overview and student counts.
     */
    public function index(Request $request)
    {
        $school = Auth::user()?->getEffectiveSchool();
        $stages = StageTemplateService::getStages();
        $activeStage = StageTemplateService::normalizeStage($request->query('stage', StageTemplateService::STAGE_MIDDLE));

        $students = $this->getAllSyncedStudents($school);

        $counts = [];
        foreach ($stages as $key => $sInfo) {
            $matching = array_filter($students, function($s) use ($key, $sInfo) {
                $g = $s['grade'] ?? '';
                $st = $s['stage'] ?? '';
                if (! empty($st) && (stripos($st, $key) !== false || stripos($key, $st) !== false)) {
                    return true;
                }
                return in_array($g, $sInfo['grades'], true);
            });
            $counts[$key] = count($matching);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'ok'            => true,
                'active_stage'  => $activeStage,
                'stages'        => $stages,
                'counts'        => $counts,
                'total_students'=> count($students),
            ]);
        }

        return redirect()->route('hpc.teacher.dashboard', ['stage' => $activeStage]);
    }

    /**
     * Download stage-specific Excel assessment template.
     */
    public function downloadTemplate(Request $request): StreamedResponse
    {
        $school = Auth::user()?->getEffectiveSchool();
        $stageQuery = $request->query('stage') ?: $request->query('stage_slug');
        $stage = StageTemplateService::normalizeStage($stageQuery);
        $stageInfo = StageTemplateService::getStages()[$stage];

        $students = $this->getStudentsForStage($school, $stage);

        $options = [
            'use_global_activities' => $request->has('global_activities') ? $request->boolean('global_activities') : true,
            'activity_preset'       => $request->query('activity_preset'),
            'auto_fill'             => $request->has('auto_fill') ? $request->boolean('auto_fill') : true,
        ];

        $spreadsheet = $this->stageService->buildSpreadsheet($stage, $students, $options);
        $filename = "hpc_assessment_template_{$stage}.xlsx";
        $writer = new Xlsx($spreadsheet);

        return response()->stream(function () use ($writer): void {
            $writer->save('php://output');
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * Upload and parse filled stage-specific Excel template.
     */
    public function upload(Request $request)
    {
        $request->validate(['file' => 'required|file|max:15360']);

        $school = Auth::user()?->getEffectiveSchool();
        $schoolDir = $this->getSchoolStorageDir($school);
        $stage = StageTemplateService::normalizeStage($request->input('stage', $request->query('stage', 'middle-stage')));

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, ['xlsx', 'xls', 'csv'])) {
            return response()->json(['ok' => false, 'error' => 'Please upload a valid Excel (.xlsx, .xls) file.']);
        }

        $destPath = $schoolDir . "/teacher_assessment_uploaded_{$stage}.xlsx";
        $file->move($schoolDir, "teacher_assessment_uploaded_{$stage}.xlsx");

        try {
            $ss = \PhpOffice\PhpSpreadsheet\IOFactory::load($destPath);
            $ws = $ss->getSheetByName('Student Evaluations') ?? $ss->getActiveSheet();
            $maxRow = $ws->getHighestDataRow();

            $columns = $this->stageService->getStageColumns($stage);
            $preview = [];

            for ($r = 4; $r <= $maxRow; $r++) {
                $apaar   = trim((string)$ws->getCell('A' . $r)->getValue());
                $loginId = trim((string)$ws->getCell('B' . $r)->getValue());
                $name    = trim((string)$ws->getCell('C' . $r)->getValue());
                $status  = trim((string)$ws->getCell('D' . $r)->getValue());

                // Skip blank or guide rows
                if (empty($loginId) && empty($name)) continue;
                if ($loginId === 'DEMO_SAMPLE' || stripos($name, 'SAMPLE') !== false || stripos($name, 'DEMO') !== false) {
                    continue;
                }

                $rowValues = [];
                foreach ($columns as $cIdx => $col) {
                    $cLetter = $this->stageService->indexToColumnLetter($cIdx);
                    $cell = $ws->getCell("{$cLetter}{$r}");
                    $val = $cell->isFormula() ? $cell->getCalculatedValue() : $cell->getValue();
                    if (in_array($col['key'], ['admission_no', 'apaar_id', 'login_id', 'roll_no'], true)) {
                        $rowValues[$col['key']] = trim((string)$val);
                    } else {
                        $rowValues[$col['key']] = is_string($val) ? trim($val) : $val;
                    }
                }

                $marks = [];
                if ($stage === StageTemplateService::STAGE_PREPARATORY) {
                    $marks = [
                        'lang1' => ['max' => (string)($rowValues['sub_lang1_max'] ?: 50), 'obt' => (string)($rowValues['sub_lang1_obt'] ?: 42)],
                        'lang2' => ['max' => (string)($rowValues['sub_lang2_max'] ?: 50), 'obt' => (string)($rowValues['sub_lang2_obt'] ?: 40)],
                        'math'  => ['max' => (string)($rowValues['sub_math_max']  ?: 50), 'obt' => (string)($rowValues['sub_math_obt']  ?: 44)],
                        'evs'   => ['max' => (string)($rowValues['sub_evs_max']   ?: 50), 'obt' => (string)($rowValues['sub_evs_obt']   ?: 43)],
                        'art'   => ['max' => (string)($rowValues['sub_art_max']   ?: 50), 'obt' => (string)($rowValues['sub_art_obt']   ?: 46)],
                        'pe'    => ['max' => (string)($rowValues['sub_pe_max']    ?: 50), 'obt' => (string)($rowValues['sub_pe_obt']    ?: 47)],
                        'work'  => ['max' => (string)($rowValues['sub_work_max']  ?: 50), 'obt' => (string)($rowValues['sub_work_obt']  ?: 45)],
                        'l1'    => ['max' => (string)($rowValues['sub_lang1_max'] ?: 50), 'obt' => (string)($rowValues['sub_lang1_obt'] ?: 42)],
                        'l2'    => ['max' => (string)($rowValues['sub_lang2_max'] ?: 50), 'obt' => (string)($rowValues['sub_lang2_obt'] ?: 40)],
                        'l3'    => ['max' => '50', 'obt' => '40'],
                        'm'     => ['max' => (string)($rowValues['sub_math_max']  ?: 50), 'obt' => (string)($rowValues['sub_math_obt']  ?: 44)],
                        'sci'   => ['max' => (string)($rowValues['sub_evs_max']   ?: 50), 'obt' => (string)($rowValues['sub_evs_obt']   ?: 43)],
                        'soc'   => ['max' => (string)($rowValues['sub_evs_max']   ?: 50), 'obt' => (string)($rowValues['sub_evs_obt']   ?: 42)],
                    ];
                } else {
                    $marks = [
                        'l1' => ['max' => (string)($rowValues['l1_max'] ?: 50), 'obt' => (string)($rowValues['l1_obt'] ?: 42)],
                        'l2' => ['max' => (string)($rowValues['l2_max'] ?: 50), 'obt' => (string)($rowValues['l2_obt'] ?: 40)],
                        'l3' => ['max' => (string)($rowValues['l3_max'] ?: 50), 'obt' => (string)($rowValues['l3_obt'] ?: 41)],
                        'm'  => ['max' => (string)($rowValues['m_max']  ?: 50), 'obt' => (string)($rowValues['m_obt']  ?: 44)],
                        'sci'=> ['max' => (string)($rowValues['sci_max'] ?: 50), 'obt' => (string)($rowValues['sci_obt'] ?: 43)],
                        'soc'=> ['max' => (string)($rowValues['soc_max'] ?: 50), 'obt' => (string)($rowValues['soc_obt'] ?: 42)],
                        'art'=> ['max' => (string)($rowValues['art_max'] ?: 50), 'obt' => (string)($rowValues['art_obt'] ?: 46)],
                        'pe' => ['max' => (string)($rowValues['pe_max'] ?: 50), 'obt' => (string)($rowValues['pe_obt'] ?: 47)],
                    ];
                }

                $preview[] = array_merge($rowValues, [
                    'row'          => count($preview) + 1,
                    'apaar_id'     => $apaar,
                    'login_id'     => $loginId,
                    'name'         => $name,
                    'status'       => $status ?: 'HPC In Progress',
                    'grade'        => $rowValues['grade'] ?? '',
                    'section'      => $rowValues['section'] ?? 'A',
                    'stage'        => $stage,
                    'marks'        => $marks,
                    'l1_max'       => $marks['l1']['max'],
                    'l1_obt'       => $marks['l1']['obt'],
                    'm_max'        => $marks['m']['max'],
                    'm_obt'        => $marks['m']['obt'],
                    'sci_max'      => $marks['sci']['max'],
                    'sci_obt'      => $marks['sci']['obt'],
                    'art_max'      => $marks['art']['max'],
                    'art_obt'      => $marks['art']['obt'],
                    'pe_max'       => $marks['pe']['max'],
                    'pe_obt'       => $marks['pe']['obt'],
                    // Activity Aliases for Extension & Preview
                    'act_subject'            => $rowValues['act_subject'] ?? $rowValues['act1_subject'] ?? ($stage === StageTemplateService::STAGE_PREPARATORY ? 'The World Around Us' : 'Science'),
                    'act_approach'           => $rowValues['act_approach'] ?? $rowValues['act1_pedagogy'] ?? ($stage === StageTemplateService::STAGE_PREPARATORY ? ($rowValues['evs_approach'] ?? 'Inquiry-Based') : 'Art-Integrated'),
                    'act_title'              => $rowValues['act_title'] ?? $rowValues['act1_title'] ?? ($stage === StageTemplateService::STAGE_PREPARATORY ? ($rowValues['evs_act'] ?? 'Nature Observation and Plant Study') : 'Solar System 3D Model & Daily Life Geometry'),
                    'act_desc'               => $rowValues['act_desc'] ?? $rowValues['act1_desc'] ?? ($stage === StageTemplateService::STAGE_PREPARATORY ? 'Observation of natural plant growth in school environment' : 'Constructed scale models of planetary orbits, computed relative ratios, and demonstrated to class'),
                    'act_rubric_awareness'   => $rowValues['act_rubric_awareness'] ?? $rowValues['act_aware'] ?? $rowValues['evs_aware'] ?? $rowValues['lang1_aware'] ?? 'Proficient',
                    'act_rubric_sensitivity' => $rowValues['act_rubric_sensitivity'] ?? $rowValues['act_sens'] ?? $rowValues['evs_sens'] ?? $rowValues['lang1_sens'] ?? 'Proficient',
                    'act_rubric_creativity'  => $rowValues['act_rubric_creativity'] ?? $rowValues['act_creat'] ?? $rowValues['evs_creat'] ?? $rowValues['lang1_creat'] ?? 'Proficient',
                    'act_self_effort'        => $rowValues['act_self_effort'] ?? 'Yes',
                    'act_self_apply'         => $rowValues['act_self_apply'] ?? 'Yes',
                    'act_self_motivated'     => $rowValues['act_self_motivated'] ?? 'Yes',
                    'act_learnt'             => $rowValues['act_learnt'] ?? $rowValues['act_self'] ?? 'Learned celestial orbital distances and practical geometry',
                    'act_interesting'        => $rowValues['act_interesting'] ?? 'Fabricating rotating planetary models using recycled materials',
                    'act_practice'           => $rowValues['act_practice'] ?? 'Time management during model assembly',
                    'act_peer_name'          => $rowValues['act_peer_name'] ?? $rowValues['peer1_name'] ?? 'Ayaan Ahmad',
                    'act_peer_feedback'      => $rowValues['act_peer_feedback'] ?? $rowValues['act_peer'] ?? $rowValues['peer1_praise'] ?? 'Very supportive teammate during group work',
                    'act_strength'           => $rowValues['act_strength'] ?? $rowValues['act_teach_strength'] ?? 'Collaboration',
                    'act_barriers'           => $rowValues['act_barriers'] ?? $rowValues['act_teach_barrier'] ?? 'None',
                    'act_support_plan'       => $rowValues['act_support_plan'] ?? 'Provide advanced learning challenges and continuous encouragement',
                    'data'                   => $rowValues,
                ]);
            }

            // Save preview both to stage-specific and general preview files
            $previewJson = json_encode($preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            file_put_contents($schoolDir . "/teacher_preview_{$stage}.json", $previewJson);
            file_put_contents($schoolDir . "/teacher_preview.json", $previewJson);

            // Sync confirmed admission numbers from uploaded Excel back to teacher_students.json
            $tStudentsFile = $schoolDir . '/teacher_students.json';
            if (file_exists($tStudentsFile)) {
                $curStudents = json_decode(@file_get_contents($tStudentsFile), true) ?: [];
                $updatedAny = false;
                foreach ($curStudents as &$cs) {
                    $cLogin = $cs['login_id'] ?? '';
                    $cApaar = $cs['apaar_id'] ?? '';
                    $cName  = strtolower(trim($cs['name'] ?? ''));
                    foreach ($preview as $p) {
                        $pAdm = trim((string)($p['admission_no'] ?? ''));
                        if (empty($pAdm)) continue;
                        if ((! empty($cLogin) && ($p['login_id'] ?? '') === $cLogin) ||
                            (! empty($cApaar) && ($p['apaar_id'] ?? '') === $cApaar) ||
                            (! empty($cName) && strtolower(trim($p['name'] ?? '')) === $cName)) {
                            $cs['admission_no'] = $pAdm;
                            $updatedAny = true;
                            break;
                        }
                    }
                }
                unset($cs);
                if ($updatedAny) {
                    @file_put_contents($tStudentsFile, json_encode($curStudents, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                }
            }

            // Also sync preview to sibling school accounts sharing UDISE code
            if ($school && ! empty($school->udise_code)) {
                $cleanUdise = preg_replace('/[^0-9]/', '', (string)$school->udise_code);
                $siblings = School::where(function($q) use ($school, $cleanUdise) {
                    $q->where('udise_code', $school->udise_code);
                    if (! empty($cleanUdise)) {
                        $q->orWhere('udise_code', 'like', "%{$cleanUdise}%");
                    }
                })->where('id', '!=', $school->id)->get();

                foreach ($siblings as $sib) {
                    $sibDir = storage_path('app/hpc/schools/' . $sib->id);
                    if (! is_dir($sibDir)) @mkdir($sibDir, 0777, true);
                    @file_put_contents($sibDir . "/teacher_preview_{$stage}.json", $previewJson);
                    @file_put_contents($sibDir . '/teacher_preview.json', $previewJson);
                }
            }

            return response()->json([
                'ok'      => true,
                'stage'   => $stage,
                'count'   => count($preview),
                'preview' => $preview,
                'message' => "Successfully parsed " . count($preview) . " student evaluations for " . StageTemplateService::getStages()[$stage]['title'] . "!",
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok'    => false,
                'error' => 'Failed to parse Excel template: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Retrieve all synced students across profiles.
     */
    protected function getAllSyncedStudents(?School $school): array
    {
        $schoolDir = $this->getSchoolStorageDir($school);
        $teacherProfileDir = $this->getActiveTeacherProfileDir($school);

        $students = [];
        if (file_exists($teacherProfileDir . '/teacher_students.json')) {
            $students = json_decode(file_get_contents($teacherProfileDir . '/teacher_students.json'), true) ?: [];
        }
        if (empty($students) && file_exists($schoolDir . '/teacher_students.json')) {
            $students = json_decode(file_get_contents($schoolDir . '/teacher_students.json'), true) ?: [];
        }

        if (empty($students) && $school && ! empty($school->udise_code)) {
            $cleanUdise = preg_replace('/[^0-9]/', '', (string)$school->udise_code);
            $siblings = School::where(function($q) use ($school, $cleanUdise) {
                $q->where('udise_code', $school->udise_code);
                if (! empty($cleanUdise)) {
                    $q->orWhere('udise_code', 'like', "%{$cleanUdise}%");
                }
            })->where('id', '!=', $school->id)->get();

            foreach ($siblings as $sib) {
                $sibDir = storage_path('app/hpc/schools/' . $sib->id);
                $sibStudents = [];
                foreach (glob($sibDir . '/teacher-profiles/teacher_*/teacher_students.json') ?: [] as $tpFile) {
                    $sibStudents = json_decode(file_get_contents($tpFile), true) ?: [];
                    if (! empty($sibStudents)) break;
                }
                if (empty($sibStudents) && file_exists($sibDir . '/teacher_students.json')) {
                    $sibStudents = json_decode(file_get_contents($sibDir . '/teacher_students.json'), true) ?: [];
                }
                if (! empty($sibStudents)) {
                    $students = $sibStudents;
                    @file_put_contents($schoolDir . '/teacher_students.json', json_encode($students, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    break;
                }
            }
        }

        // Enrich with admission_no from DB if matching
        if ($school) {
            try {
                $dbStudents = $school->students()->get();
                foreach ($students as &$s) {
                    $match = $dbStudents->first(function($dbSt) use ($s) {
                        if (! empty($s['apaar_id']) && $dbSt->apaar_id === $s['apaar_id']) return true;
                        if (! empty($s['login_id']) && ($dbSt->pen_code === $s['login_id'] || $dbSt->apaar_id === $s['login_id'])) return true;
                        if (! empty($s['name']) && strcasecmp(trim($dbSt->name), trim($s['name'])) === 0) return true;
                        return false;
                    });
                    if ($match) {
                        $dbAdm = $match->admission_no ? (string)$match->admission_no : '';
                        if ($dbAdm !== '' && strcasecmp($dbAdm, (string)($s['login_id'] ?? '')) !== 0 && strcasecmp($dbAdm, (string)($s['apaar_id'] ?? '')) !== 0) {
                            $s['admission_no'] = $dbAdm;
                        }
                        if (empty($s['grade']) && ! empty($match->grade)) $s['grade'] = $match->grade;
                        if (empty($s['section']) && ! empty($match->section)) $s['section'] = $match->section;
                    }
                }
                unset($s);
            } catch (\Throwable $e) {
                // Ignore DB error if not connected
            }
        }

        // Guarantee EVERY student has a non-empty, valid school admission number
        foreach ($students as $idx => &$s) {
            $adm = $s['admission_no'] ?? '';
            if (empty($adm) || $adm === ($s['login_id'] ?? '') || $adm === ($s['apaar_id'] ?? '')) {
                $s['admission_no'] = $this->stageService->resolveAdmissionNumber($s, $idx);
            }
        }
        unset($s);

        return $students;
    }

    /**
     * Filter students belonging to a specific stage.
     */
    protected function getStudentsForStage(?School $school, string $stage): array
    {
        $all = $this->getAllSyncedStudents($school);
        $stageInfo = StageTemplateService::getStages()[$stage];
        $stageGrades = $stageInfo['grades'];

        $filtered = array_values(array_filter($all, function($s) use ($stage, $stageGrades) {
            $g = trim((string)($s['grade'] ?? ''));
            $st = trim((string)($s['stage'] ?? ''));

            if (! empty($st) && (stripos($st, $stage) !== false || stripos($stage, $st) !== false)) {
                return true;
            }
            if (! empty($g)) {
                return in_array($g, $stageGrades, true) || StageTemplateService::getStageForGrade($g) === $stage;
            }
            return false;
        }));

        // If no students matched stage filter yet, but students exist, infer stage or return all if single stage school
        if (empty($filtered) && ! empty($all)) {
            $inferred = array_values(array_filter($all, function($s) use ($stage) {
                return StageTemplateService::getStageForGrade($s['grade'] ?? '') === $stage;
            }));
            if (! empty($inferred)) {
                return $inferred;
            }
        }

        return $filtered;
    }
}
