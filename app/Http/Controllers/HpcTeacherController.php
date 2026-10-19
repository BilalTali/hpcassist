<?php

namespace App\Http\Controllers;

use App\Models\SchoolWorkingDay;
use App\Models\SchoolMaxMark;
use App\Services\StageTemplateService;
use App\Services\HpcMiddleStageSchema;
use App\Services\HpcPreparatoryStageSchema;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

class HpcTeacherController extends Controller
{
    // ─── Dashboard View ───────────────────────────────────────────────────────

    public function getSchoolStorageDir($school = null): string
    {
        if (!$school && \Illuminate\Support\Facades\Auth::check()) {
            $school = \Illuminate\Support\Facades\Auth::user()->getEffectiveSchool();
        }

        if ($school) {
            $dir = storage_path('app/hpc/schools/' . $school->id);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            return $dir;
        }

        return storage_path('app/hpc');
    }

    public function dashboard(Request $request)
    {
        // Auto-run any pending migrations on production server seamlessly
        try {
            if (! \Illuminate\Support\Facades\Schema::hasColumn('users', 'role') || 
                ! \Illuminate\Support\Facades\Schema::hasColumn('students', 'udise_code')) {
                \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('HPC Dashboard auto-migrate notice: ' . $e->getMessage());
        }

        $storagePath = config('hpc.storage_path');
        $schoolsData = $this->getSchoolsData();
        $activeSchool = $this->getActiveSchool();
        $activeTeacher = $this->getActiveTeacher();
        $teacherProfileDir = $this->getActiveTeacherProfileDir();

        $sessionActiveFile = $teacherProfileDir . '/session_active.txt';
        $hasSession = false;
        if (file_exists($sessionActiveFile)) {
            $ts = strtotime(trim(file_get_contents($sessionActiveFile)));
            if ($ts && (time() - $ts < 10800)) {
                $hasSession = true;
            }
        }

        $allStageDefs = StageTemplateService::getStages();
        $stages = array_values(array_map(function ($s) {
            return [
                'title' => $s['title'] . ' (' . $s['grade_range'] . ')',
                'slug' => $s['slug'],
                'url' => $s['portal_url'],
                'color' => $s['color'],
                'description' => $s['description'],
                'grades' => $s['grades'],
            ];
        }, $allStageDefs));

        $school = \Illuminate\Support\Facades\Auth::user()?->getEffectiveSchool();
        $schoolId = $school?->id ?? (int) preg_replace('/[^0-9]/', '', (string) ($activeSchool['id'] ?? '')) ?: null;
        $schoolDir = $this->getSchoolStorageDir($school);

        $teacherClass = null;
        if (\Illuminate\Support\Facades\Auth::check() && \Illuminate\Support\Facades\Auth::user()->isTeacher()) {
            $userTeacher = \Illuminate\Support\Facades\Auth::user()->getEffectiveTeacher();
            if ($userTeacher && !empty($userTeacher->classes) && strcasecmp(trim($userTeacher->classes), 'All Classes') !== 0) {
                $teacherClass = trim($userTeacher->classes);
            }
        }

        $selectedStage = $request->query('stage', 'all');
        $selectedGrade = $request->query('grade', $teacherClass);

        $students = [];
        if (file_exists($teacherProfileDir . '/teacher_students.json')) {
            $students = json_decode(file_get_contents($teacherProfileDir . '/teacher_students.json'), true) ?? [];
        }
        if (empty($students) && file_exists($schoolDir . '/teacher_students.json')) {
            $students = json_decode(file_get_contents($schoolDir . '/teacher_students.json'), true) ?? [];
        }
        if (empty($students)) {
            $students = $this->getTeacherTemplateStudents($selectedGrade, $selectedStage);
        }

        $previewFile = $schoolDir . '/teacher_preview.json';
        $preview = file_exists($previewFile) ? (json_decode(file_get_contents($previewFile), true) ?? []) : [];
        if (!empty($preview)) {
            $preview = array_values(array_filter($preview, function ($p) {
                if (!is_array($p)) return false;
                $name = is_array($p['name'] ?? null) ? implode(' ', $p['name']) : (string)($p['name'] ?? '');
                $loginId = is_array($p['login_id'] ?? null) ? '' : (string)($p['login_id'] ?? '');
                return empty($p['is_demo']) &&
                    $loginId !== 'DEMO_SAMPLE' &&
                    stripos($name, 'SAMPLE') === false &&
                    stripos($name, 'DEMO') === false;
            }));
        }

        $resultsFile = $schoolDir . '/teacher_run_results.json';
        $lastRun = file_exists($resultsFile) ? json_decode(file_get_contents($resultsFile), true) : null;

        $workingDays = SchoolWorkingDay::getDefault();
        $maxMarks = SchoolMaxMark::getDefault();

        return view('hpc.teacher-dashboard', compact(
            'schoolsData',
            'activeSchool',
            'activeTeacher',
            'school',
            'schoolId',
            'teacherClass',
            'selectedStage',
            'selectedGrade',
            'hasSession',
            'stages',
            'students',
            'preview',
            'lastRun',
            'workingDays',
            'maxMarks'
        ));
    }

    /**
     * Download Filled Roster Template.
     *
     * @param Request|null $request
     * @return \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\RedirectResponse
     */
    public function downloadTemplate(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $school = \Illuminate\Support\Facades\Auth::user()?->getEffectiveSchool();
        $teacherProfileDir = $this->getActiveTeacherProfileDir($school);
        $teacherSessionFile = $teacherProfileDir . '/session_active.txt';
        $hasTeacherProfile = false;
        if (file_exists($teacherSessionFile)) {
            $ts = strtotime(trim(file_get_contents($teacherSessionFile)));
            if ($ts && (time() - $ts < 10800)) {
                $hasTeacherProfile = true;
            }
        }

        $grade = $request->input('grade', request('grade'));
        $stage = $request->input('stage', request('stage'));
        if ($grade === 'all' || trim((string) $grade) === '') {
            $grade = null;
        }
        if ($stage === 'all' || trim((string) $stage) === '') {
            $stage = null;
        }

        if (!$stage && $grade) {
            $stage = StageTemplateService::getStageForGrade($grade);
        }
        $stage = StageTemplateService::normalizeStage($stage ?: 'middle-stage');

        $students = $this->getTeacherTemplateStudents($grade, $stage);

        if (empty($students)) {
            $stageTitle = StageTemplateService::getStages()[$stage]['title'] ?? 'Selected Stage';
            $msg = "⚠️ No students fetched from PARAKH portal yet for {$stageTitle}" . ($grade ? " ({$grade})" : '') . ". Please log in to PARAKH portal, open the {$stageTitle} page, and click \"📥 Fetch Students from This Page\" in the HPC Assist extension first.";
            return redirect()->to(route('hpc.teacher.dashboard', ['stage' => $stage]))->with('error', $msg);
        }

        $options = [
            'use_global_activities' => $request->has('global_activities') ? $request->boolean('global_activities') : true,
            'activity_preset' => $request->query('activity_preset'),
            'auto_fill' => $request->has('auto_fill') ? $request->boolean('auto_fill') : true,
        ];

        return $this->generateSpreadsheetResponse($students, $grade, $stage, $options);
    }

    public function getCorsHeaders(Request $request): array
    {
        $origin = $request->header('Origin');
        if (!$origin || $origin === 'null') {
            $origin = '*';
        }

        $headers = [
            'Access-Control-Allow-Origin' => $origin,
            'Access-Control-Allow-Methods' => 'POST, GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Accept, X-HPC-Token, X-CSRF-TOKEN, X-Requested-With',
        ];

        if ($origin !== '*') {
            $headers['Access-Control-Allow-Credentials'] = 'true';
        }

        return $headers;
    }

    public function syncRoster(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $cors = $this->getCorsHeaders($request);
        if ($request->isMethod('OPTIONS')) {
            return response('', 200, $cors);
        }

        $school = null;
        if ($request->filled('udise_code')) {
            $cleanCode = preg_replace('/[^0-9]/', '', (string) $request->input('udise_code'));
            if (!empty($cleanCode)) {
                $school = \App\Models\School::where('udise_code', $cleanCode)->first()
                    ?: \App\Models\School::where('udise_code', 'like', "%{$cleanCode}%")->first();
            }
        }
        if (!$school) {
            $school = \Illuminate\Support\Facades\Auth::user()?->getEffectiveSchool();
        }
        if (!$school && $request->filled('school_id')) {
            $school = \App\Models\School::find($request->input('school_id'));
        }
        if (!$school) {
            $activeSchool = $this->getActiveSchool();
            $schoolId = (int) preg_replace('/[^0-9]/', '', (string) ($activeSchool['id'] ?? '')) ?: null;
            if ($schoolId) {
                $school = \App\Models\School::find($schoolId);
            }
        }
        if (!$school) {
            return response()->json(['ok' => false, 'error' => 'School not found for requested UDISE code.'], 404);
        }

        $incoming = $request->input('students', []);
        if (!is_array($incoming) || empty($incoming)) {
            return response()->json(['ok' => false, 'error' => 'No student rows provided to sync.'], 422);
        }

        $schoolDir = $this->getSchoolStorageDir($school);
        $teacherProfileDir = $this->getActiveTeacherProfileDir($school);
        if (!is_dir($schoolDir))
            @mkdir($schoolDir, 0777, true);
        if (!is_dir($teacherProfileDir))
            @mkdir($teacherProfileDir, 0777, true);

        $dbStudents = $school->students()->get();

        $synced = [];
        foreach ($incoming as $idx => $st) {
            $name = trim($st['name'] ?? '');
            $loginId = trim($st['login_id'] ?? '');
            $apaarId = trim($st['apaar_id'] ?? '');
            $status = trim($st['status'] ?? 'HPC In Progress');
            $stage = trim($st['stage'] ?? '');

            // Match with DB record if exists for admission_no
            $match = $dbStudents->first(function ($dbSt) use ($apaarId, $loginId, $name) {
                if (!empty($apaarId) && $dbSt->apaar_id === $apaarId)
                    return true;
                if (!empty($loginId) && ($dbSt->pen_code === $loginId || $dbSt->apaar_id === $loginId))
                    return true;
                if (!empty($name) && strcasecmp(trim($dbSt->name), trim($name)) === 0)
                    return true;
                return false;
            });

            // Derive stage/grade
            $grade = $st['grade'] ?? ($match?->grade ?? '');
            if (empty($grade)) {
                $grade = match (strtolower($stage)) {
                    'middle-stage', 'middle' => 'Grade 6',
                    'preparatory-stage', 'preparatory' => 'Grade 4',
                    'foundational-stage', 'foundational' => 'Grade 1',
                    default => 'Grade 6'
                };
            }

            $admNo = $match?->admission_no ? (string) $match->admission_no : '';
            // Guard: School admission number must NEVER be HPC Login ID, APAAR ID, or PEN
            if ($admNo !== '' && ($admNo === $loginId || $admNo === $apaarId || $admNo === ($match?->pen_code ?? ''))) {
                $admNo = '';
            }
            if (empty($admNo)) {
                $stageService = app(StageTemplateService::class);
                $admNo = $stageService->resolveAdmissionNumber([
                    'name' => $name,
                    'apaar_id' => $apaarId,
                    'login_id' => $loginId,
                    'row' => $idx + 1,
                ], $idx);
            }

            $synced[] = [
                'row' => $idx + 1,
                'name' => $name ?: ($match?->name ?? 'Student ' . ($idx + 1)),
                'admission_no' => $admNo,
                'grade' => $grade,
                'section' => $match?->section ?: 'A',
                'apaar_id' => $apaarId ?: ($match?->apaar_id ?? ''),
                'login_id' => $loginId ?: ($match?->pen_code ?? ''),
                'status' => $status,
                'stage' => $stage,
            ];
        }

        file_put_contents($schoolDir . '/teacher_students.json', json_encode($synced, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        file_put_contents($teacherProfileDir . '/teacher_students.json', json_encode($synced, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Mark teacher profile as active
        @file_put_contents($teacherProfileDir . '/session_active.txt', now()->toIso8601String());

        // Also sync to all registered teachers in this school
        if ($school) {
            foreach ($school->teachers()->get() as $schT) {
                $schTDir = storage_path('app/hpc/schools/' . $school->id . '/teacher-profiles/teacher_' . $schT->id);
                if (!is_dir($schTDir))
                    @mkdir($schTDir, 0777, true);
                @file_put_contents($schTDir . '/teacher_students.json', json_encode($synced, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                @file_put_contents($schTDir . '/session_active.txt', now()->toIso8601String());
            }

            // Sync to any sibling school accounts sharing the same UDISE code
            if (!empty($school->udise_code)) {
                $cleanUdise = preg_replace('/[^0-9]/', '', (string) $school->udise_code);
                $siblingSchools = \App\Models\School::where(function ($q) use ($school, $cleanUdise) {
                    $q->where('udise_code', $school->udise_code);
                    if (!empty($cleanUdise)) {
                        $q->orWhere('udise_code', 'like', "%{$cleanUdise}%");
                    }
                })->where('id', '!=', $school->id)->get();

                foreach ($siblingSchools as $sib) {
                    $sibDir = storage_path('app/hpc/schools/' . $sib->id);
                    if (!is_dir($sibDir))
                        @mkdir($sibDir, 0777, true);
                    @file_put_contents($sibDir . '/teacher_students.json', json_encode($synced, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    foreach ($sib->teachers()->get() as $sibT) {
                        $sibTDir = $sibDir . '/teacher-profiles/teacher_' . $sibT->id;
                        if (!is_dir($sibTDir))
                            @mkdir($sibTDir, 0777, true);
                        @file_put_contents($sibTDir . '/teacher_students.json', json_encode($synced, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                        @file_put_contents($sibTDir . '/session_active.txt', now()->toIso8601String());
                    }
                }
            }
        }

        return response()->json([
            'ok' => true,
            'count' => count($synced),
            'students' => $synced,
            'message' => 'Successfully synced ' . count($synced) . ' student(s) from PARAKH portal!',
        ], 200, $cors);
    }

    private function generateSpreadsheetResponse(array $students, ?string $grade = null, ?string $stage = null, array $options = []): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $stage = StageTemplateService::normalizeStage($stage ?: 'middle-stage');
        $stageService = app(StageTemplateService::class);
        $spreadsheet = $stageService->buildSpreadsheet($stage, $students, $options);

        $gradeSuffix = ($grade && $grade !== 'all') ? '_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $grade) : '';
        $filename = "hpc_assessment_{$stage}{$gradeSuffix}.xlsx";
        /** @var Xlsx $writer */
        $writer = new Xlsx($spreadsheet);

        return response()->stream(function () use ($writer): void {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    public function getStageGrades(?string $stage): array
    {
        return match (strtolower(trim((string) $stage))) {
            'secondary-stage', 'secondary' => ['Grade 9', 'Grade 10', 'Grade 11', 'Grade 12'],
            'middle-stage', 'middle' => ['Grade 6', 'Grade 7', 'Grade 8'],
            'preparatory-stage', 'preparatory' => ['Grade 3', 'Grade 4', 'Grade 5'],
            'foundational-stage', 'foundational' => ['Balvatika 1', 'Balvatika 2', 'Balvatika 3', 'Grade 1', 'Grade 2'],
            default => []
        };
    }

    private function getTeacherTemplateStudents(?string $grade = null, ?string $stage = null): array
    {
        $school = \Illuminate\Support\Facades\Auth::user()?->getEffectiveSchool();
        $schoolDir = $this->getSchoolStorageDir($school);
        $teacherProfileDir = $this->getActiveTeacherProfileDir($school);

        // Check teacher profile first, then school dir
        $students = [];
        if (file_exists($teacherProfileDir . '/teacher_students.json')) {
            $students = json_decode(file_get_contents($teacherProfileDir . '/teacher_students.json'), true) ?? [];
        }
        if (empty($students) && file_exists($schoolDir . '/teacher_students.json')) {
            $students = json_decode(file_get_contents($schoolDir . '/teacher_students.json'), true) ?? [];
        }

        if (empty($students) && $school && !empty($school->udise_code)) {
            $cleanUdise = preg_replace('/[^0-9]/', '', (string) $school->udise_code);
            $siblings = \App\Models\School::where(function ($q) use ($school, $cleanUdise) {
                $q->where('udise_code', $school->udise_code);
                if (!empty($cleanUdise)) {
                    $q->orWhere('udise_code', 'like', "%{$cleanUdise}%");
                }
            })->where('id', '!=', $school->id)->get();

            foreach ($siblings as $sib) {
                $sibDir = storage_path('app/hpc/schools/' . $sib->id);
                $sibStudents = [];
                foreach (glob($sibDir . '/teacher-profiles/teacher_*/teacher_students.json') ?: [] as $tpFile) {
                    $sibStudents = json_decode(file_get_contents($tpFile), true) ?: [];
                    if (!empty($sibStudents))
                        break;
                }
                if (empty($sibStudents) && file_exists($sibDir . '/teacher_students.json')) {
                    $sibStudents = json_decode(file_get_contents($sibDir . '/teacher_students.json'), true) ?: [];
                }
                if (!empty($sibStudents)) {
                    $students = $sibStudents;
                    @file_put_contents($schoolDir . '/teacher_students.json', json_encode($students, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    break;
                }
            }
        }

        // Workflow requirement: Students MUST be fetched from PARAKH portal first.
        // DO NOT fall back to local database.
        if (empty($students)) {
            return [];
        }

        $stageGrades = (!empty($stage) && $stage !== 'all') ? $this->getStageGrades($stage) : [];

        // Enrich with real DB values (admission_no, section) if matched
        if ($school) {
            $dbStudents = $school->students()->get();
            foreach ($students as &$s) {
                $match = $dbStudents->first(function ($dbSt) use ($s) {
                    if (!empty($s['apaar_id']) && $dbSt->apaar_id === $s['apaar_id'])
                        return true;
                    if (!empty($s['login_id']) && ($dbSt->pen_code === $s['login_id'] || $dbSt->apaar_id === $s['login_id']))
                        return true;
                    if (!empty($s['name']) && strcasecmp(trim($dbSt->name), trim($s['name'])) === 0)
                        return true;
                    return false;
                });
                if ($match) {
                    $dbAdm = $match->admission_no ? (string) $match->admission_no : '';
                    if ($dbAdm !== '' && ($dbAdm === ($s['login_id'] ?? '') || $dbAdm === ($s['apaar_id'] ?? '') || $dbAdm === ($match->pen_code ?? ''))) {
                        $dbAdm = '';
                    }
                    $s['admission_no'] = $dbAdm ?: ($s['admission_no'] ?? '');
                    if ($s['admission_no'] === ($s['login_id'] ?? '') || $s['admission_no'] === ($s['apaar_id'] ?? '')) {
                        $s['admission_no'] = '';
                    }
                    if (empty($s['grade']) && !empty($match->grade))
                        $s['grade'] = $match->grade;
                    if (empty($s['section']) && !empty($match->section))
                        $s['section'] = $match->section;
                } else {
                    $s['admission_no'] = $s['admission_no'] ?? '';
                    if ($s['admission_no'] === ($s['login_id'] ?? '') || $s['admission_no'] === ($s['apaar_id'] ?? '')) {
                        $s['admission_no'] = '';
                    }
                }
            }
            unset($s);

            // Filter by grade or stage
            if (!empty($grade) && $grade !== 'all') {
                $normGrade = strtolower(trim(str_ireplace('grade', '', $grade)));
                $students = array_values(array_filter($students, function ($s) use ($grade, $normGrade) {
                    $stGrade = trim((string) ($s['grade'] ?? ''));
                    if (empty($stGrade))
                        return true;
                    return strcasecmp($stGrade, trim($grade)) === 0 || strtolower(trim(str_ireplace('grade', '', $stGrade))) === $normGrade;
                }));
            } elseif (!empty($stageGrades)) {
                $students = array_values(array_filter($students, function ($s) use ($stageGrades, $stage) {
                    $stGrade = trim((string) ($s['grade'] ?? ''));
                    if (empty($stGrade)) {
                        $stStage = trim((string) ($s['stage'] ?? ''));
                        if (!empty($stStage) && (stripos($stStage, (string) $stage) !== false || stripos((string) $stage, $stStage) !== false)) {
                            return true;
                        }
                        return true;
                    }
                    return in_array($stGrade, $stageGrades, true);
                }));
            }
        }

        // Guarantee EVERY student has a non-empty, valid school admission number
        $stageService = app(StageTemplateService::class);
        foreach ($students as $idx => &$s) {
            $adm = $s['admission_no'] ?? '';
            if (empty($adm) || $adm === ($s['login_id'] ?? '') || $adm === ($s['apaar_id'] ?? '')) {
                $s['admission_no'] = $stageService->resolveAdmissionNumber($s, $idx);
            }
        }
        unset($s);

        return $students;
    }

    private function buildInstructionsSheet(Spreadsheet $spreadsheet): void
    {
        $instructionSheet = $spreadsheet->getActiveSheet();
        $instructionSheet->setTitle('Instructions & Rules');

        $instructionSheet->setCellValue('A1', 'HPCAssist - Evaluation Template Instructions & Validation Rules');
        $instructionSheet->mergeCells('A1:E1');
        $instructionSheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E293B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $instructionSheet->getRowDimension(1)->setRowHeight(32);

        $rules = [
            ['Rule #', 'Section / Page', 'Field Type', 'Rule Description', 'Permitted Values / Dragdown Options'],
            ['1', 'Student Metadata', 'Locked String', 'APAAR ID, HPC Login ID, Student Name, and Status. HPC Login ID is the unique identifier on the PARAKH portal.', 'Pre-filled from school portal roster.'],
            ['2', 'Status Protection', 'System Rule', 'Students with status "HPC Completed" will be protected and left untouched during update runs.', 'HPC Completed (skipped), HPC Pending / In Progress (updated)'],
            ['3', 'Row 4 Guide Row', 'Guide Reference', 'Row 4 (DEMO_SAMPLE) provides a complete, realistic model of teacher evaluation for guidance. Do not delete or alter.', 'All 147 fields pre-filled for visual demonstration.'],
            ['4', 'Part-A(1) Monthly Attendance', 'Integer & Formulas', 'Working Days and Present Days are arranged neck-to-neck for each month (Apr to Mar). Total Working, Total Present, and Attendance % calculate automatically via Excel formulas.', 'Monthly Working (0-31), Monthly Present (0-Working). Present <= Working.'],
            ['5', 'Part-A(2) All About Me', 'Text & Dragdown', 'Living arrangement, leisure activities, self-responsibility, improvement areas, friend info, academic & personal goals with rationale & steps, learnings in/outside school.', 'Responsibility: all the time, most times, sometimes.'],
            ['6', 'Part-A(3) Ambition Card', 'Text & Dragdown', 'Student career ambition, path to achieve, 5 key skills, focus subjects, habits to cultivate, teacher mentor name & guidance, emotional reflections.', 'Mentor guidance and structured skill acquisition.'],
            ['7', 'Part-A(4) Parent Partnership', 'Checkbox (Yes/No) & Radio', 'Home resources (individual Yes/No columns), Understanding of child Q1-Q4 (Radio smileys: Yes, Sometimes, No, Not Sure), Support needs (individual Yes/No columns), and Home support plan text.', 'Checkboxes: Yes / No; Radio: Yes, Sometimes, No, Not Sure; Specify text.'],
            ['8', 'Part-B Holistic Activities', 'Dragdown & Text', 'Choose activity subject/domain (Science, Maths, Languages, Social Sci, Art, PE, Skill, etc.), pedagogical approach, activity title, description, rubric ratings (Awareness, Sensitivity, Creativity), student reflection, peer review, teacher strength/barrier areas & support plan.', 'Subject: Science, Maths, Languages...; Approach: Art-Integrated...; Rubrics: Beginner, Proficient, Advanced.'],
            ['9', 'Part-C Curricular Assessment', 'Dragdown (Level)', 'Year-end formative and summative performance levels across curricular domains.', 'Beginner, Proficient, Advanced'],
            ['10', 'HPC Credit (Marks Grid)', 'Integer (0-50)', 'Summative Assessment marks for each of the 8 curricular subjects.', 'Max Marks (default 50), Marks Obtained (0 to Max Marks).'],
            ['11', 'Declaration & Remarks', 'Dragdown & Text', 'Teacher declaration certifying entered evaluation and holistic summary observation.', 'Declaration: Yes, No; Holistic remarks.'],
        ];

        foreach ($rules as $rIdx => $row) {
            $rowNum = $rIdx + 3;
            foreach ($row as $cIdx => $val) {
                $colLetter = chr(65 + $cIdx);
                $instructionSheet->setCellValue("{$colLetter}{$rowNum}", $val);
            }
            if ($rIdx === 0) {
                $instructionSheet->getStyle("A{$rowNum}:E{$rowNum}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF334155']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
                $instructionSheet->getRowDimension($rowNum)->setRowHeight(24);
            } else {
                $instructionSheet->getStyle("A{$rowNum}:E{$rowNum}")->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE2E8F0']]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $instructionSheet->getRowDimension($rowNum)->setRowHeight(22);
            }
        }

        $instructionSheet->getColumnDimension('A')->setWidth(10);
        $instructionSheet->getColumnDimension('B')->setWidth(26);
        $instructionSheet->getColumnDimension('C')->setWidth(22);
        $instructionSheet->getColumnDimension('D')->setWidth(55);
        $instructionSheet->getColumnDimension('E')->setWidth(45);
    }

    private function buildEvaluationsSheet(Spreadsheet $spreadsheet, array $students): void
    {
        $evalSheet = $spreadsheet->createSheet();
        $evalSheet->setTitle('Student Evaluations');

        $columns = $this->getEvaluationColumns();
        $sections = $this->getEvaluationSections();

        // Row 1: Category Super-Headers (Color Banners)
        foreach ($sections as $sec) {
            $startCol = $this->indexToColumnLetter($sec['start']);
            $endCol = $this->indexToColumnLetter($sec['end']);
            $range = "{$startCol}1:{$endCol}1";

            $evalSheet->mergeCells($range);
            $evalSheet->setCellValue("{$startCol}1", $sec['title']);
            $evalSheet->getStyle($range)->applyFromArray([
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 10],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $sec['bg']]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFFFFFFF']]],
            ]);
        }

        // Map column indices to sections
        $colToSection = [];
        foreach ($sections as $sec) {
            for ($i = $sec['start']; $i <= $sec['end']; $i++) {
                $colToSection[$i] = $sec;
            }
        }

        $evalSheet->getRowDimension(1)->setRowHeight(32);

        // Row 2: Field Names & Row 3: Guidance Notes
        foreach ($columns as $idx => $col) {
            $colLetter = $this->indexToColumnLetter($idx);
            $isObt = str_ends_with($col['key'], '_obt');
            $isLocked = in_array($col['type'], ['locked', 'calc']);

            $sec = $colToSection[$idx] ?? null;
            $prefix = $sec ? StageTemplateService::getSectionShortPrefix($sec['title']) : '';

            $baseLabel = $isObt ? '✏️ ' . $col['label'] : $col['label'];
            if (!empty($prefix) && $idx >= 4 && !str_starts_with($baseLabel, '[')) {
                $label = "{$prefix}\n{$baseLabel}";
            } else {
                $label = $baseLabel;
            }

            $evalSheet->setCellValue("{$colLetter}2", $label);
            $evalSheet->setCellValue("{$colLetter}3", $col['note'] ?? '');
            $colWidth = max($col['width'], 22);
            $evalSheet->getColumnDimension($colLetter)->setWidth($colWidth);

            $headerBg = $isObt ? 'FFFEF08A' : ($isLocked ? 'FFE2E8F0' : 'FF1E293B');
            $headerColor = $isObt ? 'FF854D0E' : ($isLocked ? 'FF334155' : 'FFFFFFFF');

            $evalSheet->getStyle("{$colLetter}2")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['argb' => $headerColor], 'size' => 9.5],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $headerBg]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCBD5E1']]],
            ]);

            $evalSheet->getStyle("{$colLetter}3")->applyFromArray([
                'font' => ['italic' => true, 'color' => ['argb' => 'FF64748B'], 'size' => 8.5],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF8FAFC']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE2E8F0']]],
            ]);
        }
        $evalSheet->getRowDimension(2)->setRowHeight(42);
        $evalSheet->getRowDimension(3)->setRowHeight(24);

        // Rows 4 onwards: Real Enrolled Students (No dummy demo row)
        $defaultWd = SchoolWorkingDay::getDefault();
        $defaultWorkMap = $defaultWd->toMonthMap();
        $defaultMax = SchoolMaxMark::getDefault()->toSubjectMap();

        foreach ($students as $sIdx => $st) {
            $rowNum = $sIdx + 4;
            $starterRow = $this->getStudentStarterProfile($sIdx, $st, $defaultWorkMap, $defaultMax, $rowNum);

            foreach ($columns as $cIdx => $col) {
                $colLetter = $this->indexToColumnLetter($cIdx);
                $val = $starterRow[$cIdx] ?? '';

                if (in_array($col['key'], ['admission_no', 'apaar_id', 'login_id'], true)) {
                    $evalSheet->setCellValueExplicit("{$colLetter}{$rowNum}", (string) $val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                } else {
                    $evalSheet->setCellValue("{$colLetter}{$rowNum}", $val);
                }

                if ($col['type'] === 'list') {
                    $validation = $evalSheet->getCell("{$colLetter}{$rowNum}")->getDataValidation();
                    $validation->setType(DataValidation::TYPE_LIST);
                    $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
                    $validation->setAllowBlank(true);
                    $validation->setShowDropDown(true);
                    $validation->setShowInputMessage(true);
                    $validation->setPromptTitle('Select Option');
                    $validation->setPrompt('Click the dropdown arrow to pick an option.');
                    $validation->setFormula1($col['options']);
                }

                $isObt = str_ends_with($col['key'], '_obt');
                $isLocked = in_array($col['type'], ['locked', 'calc']);

                $cellBg = $isObt ? 'FFFEFCE8' : ($isLocked ? 'FFF1F5F9' : 'FFFFFFFF');
                $cellColor = $isObt ? 'FF0F172A' : ($isLocked ? 'FF475569' : 'FF1E293B');

                $evalSheet->getStyle("{$colLetter}{$rowNum}")->applyFromArray([
                    'font' => ['bold' => ($isObt || $isLocked), 'color' => ['argb' => $cellColor]],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $cellBg]],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $isObt ? 'FFFDE047' : 'FFE2E8F0']]],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);
            }

            $evalSheet->getRowDimension($rowNum)->setRowHeight(22);
        }

        $lastColLetter = $this->indexToColumnLetter(count($columns) - 1);
        $maxRow = max(5, count($students) + 4);
        $evalDataRange = "A5:{$lastColLetter}{$maxRow}";

        $blankRule = new Conditional();
        $blankRule->setConditionType(Conditional::CONDITION_CONTAINSBLANKS);
        $blankRule->getStyle()->getFill()->setFillType(Fill::FILL_SOLID);
        $blankRule->getStyle()->getFill()->getStartColor()->setARGB('FFFEE2E2');
        $blankRule->getStyle()->getFill()->getEndColor()->setARGB('FFFEE2E2');
        $blankRule->getStyle()->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFFCA5A5');
        $blankRule->getStyle()->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFFCA5A5');
        $blankRule->getStyle()->getBorders()->getLeft()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFFCA5A5');
        $blankRule->getStyle()->getBorders()->getRight()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFFCA5A5');

        $evalSheet->getStyle($evalDataRange)->setConditionalStyles([$blankRule]);

        // Outlines & Focus Mode
        $evalSheet->setShowSummaryRight(false);
        $evalSheet->setShowSummaryBelow(false);

        // 1. Locked Left Side Focus Mode:
        // Columns A (APAAR ID), B (HPC Login ID), D (Status) collapsed under Outline Level 1.
        // Column C (Student Name) is visible at Outline Level 0 with width 28.
        $evalSheet->getColumnDimension('A')->setOutlineLevel(1)->setVisible(false)->setCollapsed(true);
        $evalSheet->getColumnDimension('B')->setOutlineLevel(1)->setVisible(false)->setCollapsed(true);
        $evalSheet->getColumnDimension('C')->setOutlineLevel(0)->setVisible(true)->setWidth(28);
        $evalSheet->getColumnDimension('D')->setOutlineLevel(1)->setVisible(false)->setCollapsed(true);

        // 2. Part Outlines: Group assessment sections
        foreach ($sections as $sec) {
            if ($sec['start'] >= 4 && $sec['end'] > $sec['start']) {
                $startLtr = $this->indexToColumnLetter($sec['start']);
                $evalSheet->getColumnDimension($startLtr)->setOutlineLevel(0)->setVisible(true);
                for ($c = $sec['start'] + 1; $c <= $sec['end']; $c++) {
                    $cLtr = $this->indexToColumnLetter($c);
                    $evalSheet->getColumnDimension($cLtr)->setOutlineLevel(1)->setVisible(true)->setCollapsed(false);
                }
            }
        }

        $evalSheet->freezePane('E4');
    }

    public function indexToColumnLetter(int $index): string
    {
        $letter = '';
        while ($index >= 0) {
            $letter = chr($index % 26 + 65) . $letter;
            $index = intval($index / 26) - 1;
        }
        return $letter;
    }

    private function setValidation($ws, string $cell, string $formula): void
    {
        $v = $ws->getCell($cell)->getDataValidation();
        $v->setType(DataValidation::TYPE_LIST);
        $v->setErrorStyle(DataValidation::STYLE_INFORMATION);
        $v->setAllowBlank(false);
        $v->setShowDropDown(true);
        $v->setFormula1($formula);
    }

    // ─── Step 2: Upload Filled Template ───────────────────────────────────────

    public function upload(Request $request)
    {
        try {
            $request->validate(['file' => 'required|file|max:15360']);

            $uploadedFile = $request->file('file');
            $ext = strtolower($uploadedFile->getClientOriginalExtension());
            if (!in_array($ext, ['xlsx', 'xls', 'csv'])) {
                return response()->json(['ok' => false, 'error' => 'Please upload a valid Excel (.xlsx, .xls) file.']);
            }

            $school = \Illuminate\Support\Facades\Auth::user()?->getEffectiveSchool();
            $dir = $this->getSchoolStorageDir($school);
            if (!is_dir($dir))
                @mkdir($dir, 0777, true);

            $fullPath = $dir . '/teacher_assessment_uploaded.xlsx';
            $uploadedFile->move($dir, 'teacher_assessment_uploaded.xlsx');

            $errors = [];
            $preview = [];

            $ss = \PhpOffice\PhpSpreadsheet\IOFactory::load($fullPath);

            // Intelligently select sheet (Sheet 1 Manual Entry vs Sheet 2 Pre-Filled vs Legacy)
            $s1 = $ss->getSheetByName('Manual Entry (Blank)');
            $s2 = $ss->getSheetByName('Pre-Filled (Auto-Fill)');
            $legacy = $ss->getSheetByName('Student Evaluations');

            $ws = null;
            if ($s1 && $s2) {
                // Check if Sheet 1 was filled by teacher (check sample evaluation cells)
                $hasS1Data = false;
                $maxR1 = $s1->getHighestDataRow();
                for ($r = 4; $r <= min(15, $maxR1); $r++) {
                    $sampleVal1 = trim((string) $s1->getCell('M' . $r)->getValue());  // Apr Present Days
                    $sampleVal2 = trim((string) $s1->getCell('AQ' . $r)->getValue()); // Part A2
                    if ($sampleVal1 !== '' || $sampleVal2 !== '') {
                        $hasS1Data = true;
                        break;
                    }
                }
                $activeTitle = $ss->getActiveSheet()->getTitle();
                if ($activeTitle === 'Pre-Filled (Auto-Fill)' || !$hasS1Data) {
                    $ws = $s2;
                } else {
                    $ws = $s1;
                }
            } else {
                $ws = $s1 ?? $s2 ?? $legacy ?? $ss->getActiveSheet();
            }

            $max = $ws->getHighestDataRow();

            $stageService = app(StageTemplateService::class);
            $reqStage = $request->input('stage') ?: $request->query('stage');
            $detectedStage = null;
            if (!empty($reqStage)) {
                $detectedStage = StageTemplateService::normalizeStage($reqStage);
            }

            // Inspect Instructions sheet header
            $instSheet = $ss->getSheetByName('Instructions & Rules');
            if ($instSheet) {
                $instTitle = (string) $instSheet->getCell('A1')->getValue();
                if (stripos($instTitle, 'preparatory') !== false) {
                    $detectedStage = StageTemplateService::STAGE_PREPARATORY;
                } elseif (stripos($instTitle, 'foundational') !== false) {
                    $detectedStage = StageTemplateService::STAGE_FOUNDATIONAL;
                } elseif (stripos($instTitle, 'secondary') !== false) {
                    $detectedStage = StageTemplateService::STAGE_SECONDARY;
                } elseif (stripos($instTitle, 'middle') !== false) {
                    $detectedStage = StageTemplateService::STAGE_MIDDLE;
                }
            }

            // Fallback: detect by grade in column H (Row 4) or F (Row 4)
            if (!$detectedStage) {
                $gSample = (string) ($ws->getCell('H4')->getValue() ?: $ws->getCell('F4')->getValue());
                if (!empty($gSample)) {
                    $detectedStage = StageTemplateService::getStageForGrade($gSample);
                }
            }
            $detectedStage = $detectedStage ?: StageTemplateService::STAGE_MIDDLE;

            // Fetch exact columns for detected stage
            $columns = $stageService->getStageColumns($detectedStage);

            $portalStudents = [];
            $portalStudentsByName = [];
            $portalStudentsByApaar = [];
            $tStudentsFile = $dir . '/teacher_students.json';
            if (file_exists($tStudentsFile)) {
                $tsData = json_decode(file_get_contents($tStudentsFile), true) ?: [];
                foreach ($tsData as $tSt) {
                    if (!empty($tSt['login_id'])) {
                        $portalStudents[$tSt['login_id']] = $tSt;
                    }
                    if (!empty($tSt['apaar_id'])) {
                        $portalStudentsByApaar[$tSt['apaar_id']] = $tSt;
                    }
                    if (!empty($tSt['name'])) {
                        $clean = strtolower(preg_replace('/[^a-z0-9]/i', '', $tSt['name']));
                        if (!empty($clean)) {
                            $portalStudentsByName[$clean] = $tSt;
                        }
                    }
                }
            }

            $defaultMax = SchoolMaxMark::getDefault()->toSubjectMap();

            for ($r = 4; $r <= $max; $r++) {
                $rowValues = [];
                foreach ($columns as $cIdx => $col) {
                    $colLetter = $this->indexToColumnLetter($cIdx);
                    $cell = $ws->getCell("{$colLetter}{$r}");
                    try {
                        $val = $cell->isFormula() ? $cell->getCalculatedValue() : $cell->getValue();
                    } catch (\Throwable) {
                        $val = $cell->getValue();
                    }
                    $k = $col['key'];
                    if (in_array($k, ['Student_Name', 'admission_no', 'apaar_id', 'login_id', 'roll_no', 'School_Admission_No', 'APAAR_ID', 'HPC_Login_ID', 'UDID_Disability_ID'], true)) {
                        $rowValues[$k] = trim((string) $val);
                    } else {
                        $rowValues[$k] = is_string($val) ? trim($val) : $val;
                    }
                }

                $loginId = (string) ($rowValues['HPC_Login_ID'] ?? $rowValues['login_id'] ?? '');
                $apaarId = (string) ($rowValues['APAAR_ID'] ?? $rowValues['apaar_id'] ?? '');
                $name    = (string) ($rowValues['Student_Name'] ?? $rowValues['name'] ?? $rowValues['AboutMe_Student_Name_Tag'] ?? '');
                $status  = (string) ($rowValues['status'] ?? '');

                // Match against portal student roster by Login ID, APAAR ID, or Student Name
                $matchedPortal = null;
                if (!empty($loginId) && isset($portalStudents[$loginId])) {
                    $matchedPortal = $portalStudents[$loginId];
                } elseif (!empty($apaarId) && isset($portalStudentsByApaar[$apaarId])) {
                    $matchedPortal = $portalStudentsByApaar[$apaarId];
                } elseif (!empty($name)) {
                    $cleanName = strtolower(preg_replace('/[^a-z0-9]/i', '', $name));
                    if (!empty($cleanName) && isset($portalStudentsByName[$cleanName])) {
                        $matchedPortal = $portalStudentsByName[$cleanName];
                    }
                }

                if ($matchedPortal) {
                    if (empty($name) && !empty($matchedPortal['name'])) {
                        $name = $matchedPortal['name'];
                    }
                    if (empty($loginId) && !empty($matchedPortal['login_id'])) {
                        $loginId = $matchedPortal['login_id'];
                    }
                    if (empty($apaarId) && !empty($matchedPortal['apaar_id'])) {
                        $apaarId = $matchedPortal['apaar_id'];
                    }
                    if (!empty($matchedPortal['status'])) {
                        $status = $matchedPortal['status'];
                    }
                }

                if ($name === '' && $loginId === '')
                    continue;
                if (str_contains($loginId, 'Login ID') || str_contains($name, 'Student Name'))
                    continue;

                $rowValues['name']     = $name;
                $rowValues['login_id'] = $loginId;
                $rowValues['apaar_id'] = $apaarId;
                $rowValues['status']   = $status;

                $isDemo = ($loginId === 'DEMO_SAMPLE' || stripos($name, 'SAMPLE') !== false || stripos($name, 'DEMO') !== false);
                if ($isDemo) {
                    continue; // Skip guide / demo student so only real enrolled students are processed
                }
                $isCompleted = (strcasecmp($status, 'HPC Completed') === 0 || stripos($status, 'completed') !== false);

                $rowErrors = [];
                if (!$isDemo && !$isCompleted) {
                    if ($name === '')
                        $rowErrors[] = 'Student Name missing';
                    if ($loginId === '')
                        $rowErrors[] = 'HPC Login ID missing';
                }

                // Apply schema normalizer for detected stage
                $normalized = ($detectedStage === StageTemplateService::STAGE_PREPARATORY)
                    ? HpcPreparatoryStageSchema::normalizeStudentRow($rowValues)
                    : HpcMiddleStageSchema::normalizeStudentRow($rowValues);

                $marks = $normalized['marks'] ?? [];

                $rowItem = array_merge($rowValues, [
                    'row' => $r,
                    'stage' => $detectedStage,
                    'is_demo' => $isDemo,
                    'is_completed' => $isCompleted,
                    'errors' => $rowErrors,
                    'marks' => $marks,
                    'l1_max' => $marks['l1']['max'] ?? $marks['lang1']['max'] ?? 50,
                    'l1_obt' => $marks['l1']['obt'] ?? $marks['lang1']['obt'] ?? 42,
                    'm_max' => $marks['m']['max'] ?? $marks['math']['max'] ?? 50,
                    'm_obt' => $marks['m']['obt'] ?? $marks['math']['obt'] ?? 44,
                    'sci_max' => $marks['sci']['max'] ?? $marks['evs']['max'] ?? $marks['twc']['max'] ?? 50,
                    'sci_obt' => $marks['sci']['obt'] ?? $marks['evs']['obt'] ?? $marks['twc']['obt'] ?? 43,
                    'art_max' => $marks['art']['max'] ?? 50,
                    'art_obt' => $marks['art']['obt'] ?? 46,
                    'pe_max' => $marks['pe']['max'] ?? 50,
                    'pe_obt' => $marks['pe']['obt'] ?? 47,
                    // Activity Aliases for Extension & Preview
                    'act_subject' => $rowValues['act_subject'] ?? $rowValues['act1_subject'] ?? ($detectedStage === StageTemplateService::STAGE_PREPARATORY ? 'The World Around Us' : 'Science'),
                    'act_approach' => $rowValues['act_approach'] ?? $rowValues['act1_pedagogy'] ?? ($detectedStage === StageTemplateService::STAGE_PREPARATORY ? ($rowValues['evs_approach'] ?? 'Inquiry-Based') : 'Art-Integrated'),
                    'act_title' => $rowValues['act_title'] ?? $rowValues['act1_title'] ?? ($detectedStage === StageTemplateService::STAGE_PREPARATORY ? ($rowValues['evs_act'] ?? 'Nature Observation and Plant Study') : 'Solar System 3D Model & Daily Life Geometry'),
                    'act_desc' => $rowValues['act_desc'] ?? $rowValues['act1_desc'] ?? ($detectedStage === StageTemplateService::STAGE_PREPARATORY ? 'Observation of natural plant growth in school environment' : 'Constructed scale models of planetary orbits, computed relative ratios, and demonstrated to class'),
                    'act_rubric_awareness' => $rowValues['act_rubric_awareness'] ?? $rowValues['act_aware'] ?? $rowValues['evs_aware'] ?? $rowValues['lang1_aware'] ?? 'Proficient',
                    'act_rubric_sensitivity' => $rowValues['act_rubric_sensitivity'] ?? $rowValues['act_sens'] ?? $rowValues['evs_sens'] ?? $rowValues['lang1_sens'] ?? 'Proficient',
                    'act_rubric_creativity' => $rowValues['act_rubric_creativity'] ?? $rowValues['act_creat'] ?? $rowValues['evs_creat'] ?? $rowValues['lang1_creat'] ?? 'Proficient',
                    'act_self_effort' => $rowValues['act_self_effort'] ?? 'Yes',
                    'act_self_apply' => $rowValues['act_self_apply'] ?? 'Yes',
                    'act_self_motivated' => $rowValues['act_self_motivated'] ?? 'Yes',
                    'act_learnt' => $rowValues['act_learnt'] ?? $rowValues['act_self'] ?? 'Learned celestial orbital distances and practical geometry',
                    'act_interesting' => $rowValues['act_interesting'] ?? 'Fabricating rotating planetary models using recycled materials',
                    'act_practice' => $rowValues['act_practice'] ?? 'Time management during model assembly',
                    'act_peer_name' => $rowValues['act_peer_name'] ?? $rowValues['peer1_name'] ?? 'Ayaan Ahmad',
                    'act_peer_feedback' => $rowValues['act_peer_feedback'] ?? $rowValues['act_peer'] ?? $rowValues['peer1_praise'] ?? 'Very supportive teammate during group work',
                    'act_strength' => $rowValues['act_strength'] ?? $rowValues['act_teach_strength'] ?? 'Collaboration',
                    'act_barriers' => $rowValues['act_barriers'] ?? $rowValues['act_teach_barrier'] ?? 'None',
                    'act_support_plan' => $rowValues['act_support_plan'] ?? 'Provide advanced learning challenges and continuous encouragement',
                    // Hand of Self-Reflection & All About Me (Preparatory & Middle)
                    'live_with'        => $rowValues['live_with'] ?? 'My parents, siblings, and grandparents',
                    'fav_dish'         => $rowValues['fav_dish'] ?? $rowValues['fav_food'] ?? 'Dal Chawal, Apples, and seasonal fresh fruits',
                    'fav_game'         => $rowValues['fav_game'] ?? 'Kho-Kho and Badminton',
                    'free_time'        => $rowValues['free_time'] ?? $rowValues['like_to'] ?? 'Reading storybooks and sketching',
                    'do_well'          => $rowValues['do_well'] ?? $rowValues['good_at'] ?? 'Solving arithmetic puzzles and recitation',
                    'want_to_improve'  => $rowValues['want_to_improve'] ?? $rowValues['do_better'] ?? 'Neat cursive handwriting and fast mental math',
                    'responsible'      => $rowValues['responsible'] ?? 'all the time',
                    'proud_of'         => $rowValues['proud_of'] ?? $rowValues['feel_proud'] ?? 'Helping my classmate with reading',
                    'goal_acad'        => $rowValues['goal_acad'] ?? $rowValues['acad_goal'] ?? 'Read 15 storybooks in English and Urdu',
                    'goal_step'        => $rowValues['goal_step'] ?? $rowValues['acad_step1'] ?? 'Read 2 pages every evening before bed',
                    'good_deed'        => $rowValues['good_deed'] ?? 'Shared my lunch with a friend who forgot hers',
                    'my_name'          => $rowValues['my_name'] ?? $name,
                    'my_age'           => $rowValues['my_age'] ?? '9',
                    'dont_like'        => $rowValues['dont_like'] ?? 'Fighting, shouting, and loud noises',
                    'like_to'          => $rowValues['like_to'] ?? $rowValues['free_time'] ?? 'Reading storybooks, drawing nature, and playing outdoors',
                    'not_good_at'      => $rowValues['not_good_at'] ?? 'Drawing complex diagrams and spelling long words',
                    'good_at'          => $rowValues['good_at'] ?? $rowValues['do_well'] ?? 'Solving arithmetic puzzles and reciting poems',
                    'fav_food'         => $rowValues['fav_food'] ?? $rowValues['fav_dish'] ?? 'Dal Chawal, Apples, and seasonal fresh fruits',
                    'fav_festival'     => $rowValues['fav_festival'] ?? 'Eid-ul-Fitr and National Festivals',
                    'grow_up_be'       => $rowValues['grow_up_be'] ?? $rowValues['ambition'] ?? 'Doctor / Space Scientist',
                    'person_inspires'  => $rowValues['person_inspires_and_learn'] ?? $rowValues['person_inspires'] ?? 'Dr. A.P.J. Abdul Kalam and my teacher',
                    'learn_this_year'  => $rowValues['person_inspires_and_learn'] ?? $rowValues['learn_this_year'] ?? '1. Science models 2. Fast division math 3. Fluent English reading',
                    // Preparatory Part A(3) Peer & Parent Feedback
                    'peer1_name'       => $rowValues['peer1_name'] ?? 'Ayaan Ahmad',
                    'peer1_helpful'    => $rowValues['peer1_helpful'] ?? $rowValues['peer1_feedback'] ?? 'Always',
                    'peer1_listens'    => $rowValues['peer1_listens'] ?? 'Always',
                    'peer1_praise'     => $rowValues['peer1_praise'] ?? 'Very cheerful and always shares pencils',
                    'peer1_feedback'   => $rowValues['peer1_feedback'] ?? $rowValues['peer1_helpful'] ?? 'Yes',
                    'peer2_name'       => $rowValues['peer2_name'] ?? 'Zaid Farooq',
                    'peer2_helpful'    => $rowValues['peer2_helpful'] ?? $rowValues['peer2_feedback'] ?? 'Always',
                    'peer2_praise'     => $rowValues['peer2_praise'] ?? 'Explains maths questions very patiently',
                    'peer2_feedback'   => $rowValues['peer2_feedback'] ?? $rowValues['peer2_helpful'] ?? 'Yes',
                    'parent_reads_with'=> $rowValues['parent_reads_with'] ?? 'Daily',
                    'parent_study_space'=> $rowValues['parent_study_space'] ?? 'Yes',
                    'parent_social_habits'=> $rowValues['parent_social_habits'] ?? 'Always',
                    'home_resources'   => $rowValues['parent_home_resources'] ?? 'Books, Newspaper, Toys, Phone/Computer, Internet, TV',
                    'parent_home_resources' => $rowValues['parent_home_resources'] ?? 'Books, Newspaper, Toys, Phone/Computer, Internet, TV',
                    'child_needs_support' => $rowValues['parent_support_areas'] ?? 'Reading, Numbers and Math, Oral communication',
                    'parent_support_areas' => $rowValues['parent_support_areas'] ?? 'Reading, Numbers and Math, Oral communication',
                    'parent_support_needed' => $rowValues['parent_support_needed'] ?? $rowValues['parent_support_specify'] ?? 'Provide 30 minutes of guided reading and arithmetic practice at home',
                    'parent_support_specify' => $rowValues['parent_support_specify'] ?? $rowValues['parent_support_needed'] ?? 'Provide 30 minutes of guided reading and arithmetic practice at home',
                    'parent_signature_status' => $rowValues['parent_signature_status'] ?? 'Completed',
                    'data'             => $rowValues,
                ]);

                $preview[] = $rowItem;

                if (!empty($rowErrors)) {
                    $errors[] = "Row {$r} ({$name}): " . implode(', ', $rowErrors);
                }
            }

            $schoolDir = $this->getSchoolStorageDir();
            $previewJson = json_encode($preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            file_put_contents($schoolDir . '/teacher_preview.json', $previewJson);
            if (!empty($detectedStage)) {
                file_put_contents($schoolDir . "/teacher_preview_{$detectedStage}.json", $previewJson);
            }

            // Sync confirmed admission numbers from uploaded Excel back to teacher_students.json
            $tStudentsFile = $schoolDir . '/teacher_students.json';
            if (file_exists($tStudentsFile)) {
                $curStudents = json_decode(@file_get_contents($tStudentsFile), true) ?: [];
                $updatedAny = false;
                foreach ($curStudents as &$cs) {
                    $cLogin = $cs['login_id'] ?? '';
                    $cApaar = $cs['apaar_id'] ?? '';
                    $cName = strtolower(trim($cs['name'] ?? ''));
                    foreach ($preview as $p) {
                        $pAdm = trim((string) ($p['admission_no'] ?? ''));
                        if (empty($pAdm))
                            continue;
                        if (
                            (!empty($cLogin) && ($p['login_id'] ?? '') === $cLogin) ||
                            (!empty($cApaar) && ($p['apaar_id'] ?? '') === $cApaar) ||
                            (!empty($cName) && strtolower(trim($p['name'] ?? '')) === $cName)
                        ) {
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

            // Also sync preview to any sibling school accounts sharing the same UDISE code
            if ($school && !empty($school->udise_code)) {
                $cleanUdise = preg_replace('/[^0-9]/', '', (string) $school->udise_code);
                $siblings = \App\Models\School::where(function ($q) use ($school, $cleanUdise) {
                    $q->where('udise_code', $school->udise_code);
                    if (!empty($cleanUdise)) {
                        $q->orWhere('udise_code', 'like', "%{$cleanUdise}%");
                    }
                })->where('id', '!=', $school->id)->get();

                foreach ($siblings as $sib) {
                    $sibDir = storage_path('app/hpc/schools/' . $sib->id);
                    if (!is_dir($sibDir))
                        @mkdir($sibDir, 0777, true);
                    @file_put_contents($sibDir . '/teacher_preview.json', $previewJson);
                    if (!empty($detectedStage)) {
                        @file_put_contents($sibDir . "/teacher_preview_{$detectedStage}.json", $previewJson);
                    }
                }
            }

            return response()->json([
                'ok' => true,
                'total' => count($preview),
                'valid' => count(array_filter($preview, fn($r) => empty($r['errors']))),
                'invalid' => count(array_filter($preview, fn($r) => !empty($r['errors']))),
                'errors' => $errors,
                'preview' => $preview,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Teacher upload error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'ok' => false,
                'error' => 'Could not read Excel file: ' . $e->getMessage(),
            ]);
        }
    }

    // ─── Step 3: Start Bulk HPC Assessment Runner ─────────────────────────────

    public function runStart(Request $request)
    {
        $schoolDir = $this->getSchoolStorageDir();
        $previewFile = $schoolDir . '/teacher_preview.json';
        if (!file_exists($previewFile)) {
            return response()->json(['ok' => false, 'error' => 'No student evaluation data found. Please upload the filled Excel file first.']);
        }

        $allStudents = json_decode(file_get_contents($previewFile), true) ?? [];
        $validStudents = array_values(array_filter($allStudents, fn($s) => empty($s['errors'])));

        if (empty($validStudents)) {
            return response()->json(['ok' => false, 'error' => 'All rows have validation errors. Please fix and re-upload.']);
        }

        $total = count($validStudents);
        $resultsFile = $schoolDir . '/teacher_run_results.json';
        $init = [
            'started' => true,
            'done' => false,
            'login_wait' => true,
            'total' => $total,
            'processed' => 0,
            'results' => [],
            'started_at' => now()->toIso8601String(),
        ];
        file_put_contents($resultsFile, json_encode($init, JSON_PRETTY_PRINT));

        $school = \Illuminate\Support\Facades\Auth::user()?->getEffectiveSchool();
        $schoolArg = $school ? "--school={$school->id}" : "";
        $teacher = $this->getActiveTeacher();
        $tArg = !empty($teacher['id']) ? "--teacher={$teacher['id']}" : "";

        $artisan = base_path('artisan');
        $logFile = storage_path('logs/hpc_teacher_run.log');
        $php = PHP_BINARY;
        $launchedCli = false;
        if (function_exists('exec')) {
            @exec("nohup {$php} {$artisan} hpc:teacher-run {$schoolArg} {$tArg} > {$logFile} 2>&1 &");
            $launchedCli = true;
        }

        return response()->json([
            'ok' => true,
            'total' => $total,
            'launched_cli' => $launchedCli,
            'students' => $validStudents,
            'message' => $launchedCli ? 'Background teacher assessment runner started.' : 'Evaluation queue ready.',
        ]);
    }

    public function runStatus()
    {
        $file = $this->getSchoolStorageDir() . '/teacher_run_results.json';
        if (!file_exists($file)) {
            return response()->json(['started' => false, 'results' => []]);
        }
        $data = json_decode(file_get_contents($file), true) ?? [];
        return response()->json($data);
    }

    public function runReset()
    {
        $schoolDir = $this->getSchoolStorageDir();
        $resultsFile = $schoolDir . '/teacher_run_results.json';
        if (file_exists($resultsFile))
            @unlink($resultsFile);
        $previewFile = $schoolDir . '/teacher_preview.json';
        if (file_exists($previewFile))
            @unlink($previewFile);
        $uploadedXlsx = $schoolDir . '/teacher_assessment_uploaded.xlsx';
        if (file_exists($uploadedXlsx))
            @unlink($uploadedXlsx);
        return response()->json(['ok' => true]);
    }

    public function evaluationsQueue(Request $request)
    {
        $cors = $this->getCorsHeaders($request);
        if ($request->isMethod('OPTIONS')) {
            return response('', 200, $cors);
        }

        $schoolId = $request->input('school_id');
        $school = $schoolId ? \App\Models\School::find($schoolId) : \Illuminate\Support\Facades\Auth::user()?->getEffectiveSchool();

        $schoolDir = $this->getSchoolStorageDir($school);
        $previewFile = $schoolDir . '/teacher_preview.json';

        if (!file_exists($previewFile) && $school && !empty($school->udise_code)) {
            $cleanUdise = preg_replace('/[^0-9]/', '', (string) $school->udise_code);
            $siblings = \App\Models\School::where(function ($q) use ($school, $cleanUdise) {
                $q->where('udise_code', $school->udise_code);
                if (!empty($cleanUdise)) {
                    $q->orWhere('udise_code', 'like', "%{$cleanUdise}%");
                }
            })->where('id', '!=', $school->id)->get();

            foreach ($siblings as $sib) {
                $sibFile = storage_path('app/hpc/schools/' . $sib->id . '/teacher_preview.json');
                if (file_exists($sibFile)) {
                    $previewFile = $sibFile;
                    @file_put_contents($schoolDir . '/teacher_preview.json', file_get_contents($sibFile));
                    break;
                }
            }
        }

        if (!file_exists($previewFile)) {
            $basePreview = storage_path('app/hpc/teacher_preview.json');
            if (file_exists($basePreview)) {
                $previewFile = $basePreview;
            }
        }

        if (!file_exists($previewFile)) {
            return response()->json([
                'ok' => false,
                'error' => 'No student evaluations uploaded yet. Please upload the filled Excel template first.',
                'students' => []
            ], 200, $cors);
        }

        $all = json_decode(file_get_contents($previewFile), true) ?: [];
        $validStudents = array_values(array_filter($all, function ($s) {
            if (!is_array($s)) return false;
            $name = is_array($s['name'] ?? null) ? implode(' ', $s['name']) : (string)($s['name'] ?? '');
            $loginId = is_array($s['login_id'] ?? null) ? '' : (string)($s['login_id'] ?? '');
            return empty($s['errors']) &&
                empty($s['is_demo']) &&
                $loginId !== 'DEMO_SAMPLE' &&
                stripos($name, 'SAMPLE') === false &&
                stripos($name, 'DEMO') === false;
        }));

        return response()->json([
            'ok' => true,
            'school_id' => $school?->id,
            'school_name' => $school?->name ?? 'My School',
            'total' => count($validStudents),
            'students' => $validStudents,
        ], 200, $cors);
    }

    public function evaluationsProgress(Request $request)
    {
        $cors = $this->getCorsHeaders($request);
        if ($request->isMethod('OPTIONS')) {
            return response('', 200, $cors);
        }

        $schoolId = $request->input('school_id');
        $school = $schoolId ? \App\Models\School::find($schoolId) : \Illuminate\Support\Facades\Auth::user()?->getEffectiveSchool();
        $schoolDir = $this->getSchoolStorageDir($school);
        $resultsFile = $schoolDir . '/teacher_run_results.json';

        $current = (int) $request->input('current', 1);
        $total = (int) $request->input('total', 1);
        $name = (string) $request->input('name', '');
        $loginId = (string) $request->input('login_id', '');
        $status = (string) $request->input('status', 'completed');
        $message = (string) $request->input('message', '');
        $done = (bool) $request->input('done', false);

        $existing = file_exists($resultsFile) ? (json_decode(file_get_contents($resultsFile), true) ?: []) : [];
        $results = $existing['results'] ?? [];

        if ($loginId || $name) {
            $found = false;
            foreach ($results as &$r) {
                if (
                    (!empty($r['login_id']) && $r['login_id'] === $loginId) ||
                    (!empty($r['name']) && strcasecmp(trim($r['name']), trim($name)) === 0)
                ) {
                    $r['status'] = $status;
                    $r['message'] = $message;
                    $found = true;
                    break;
                }
            }
            unset($r);
            if (!$found) {
                $results[] = [
                    'row' => count($results) + 1,
                    'name' => $name,
                    'login_id' => $loginId,
                    'status' => $status,
                    'message' => $message,
                ];
            }
        }

        $payload = [
            'started' => true,
            'done' => $done || ($current >= $total && $status === 'completed'),
            'login_wait' => false,
            'total' => $total,
            'processed' => $current,
            'results' => $results,
            'updated_at' => now()->toIso8601String(),
        ];
        file_put_contents($resultsFile, json_encode($payload, JSON_PRETTY_PRINT));

        return response()->json(['ok' => true], 200, $cors);
    }

    public function evaluationsLog(Request $request)
    {
        $cors = $this->getCorsHeaders($request);
        if ($request->isMethod('OPTIONS')) {
            return response('', 200, $cors);
        }
        $msg = $request->input('message') ?? $request->input('msg') ?? 'Heartbeat';
        $url = $request->input('url', '');
        $time = now()->format('Y-m-d H:i:s');
        $logLine = "[{$time}] [URL: {$url}] {$msg}\n";
        @file_put_contents(storage_path('logs/extension_live.log'), $logLine, FILE_APPEND);
        return response()->json(['ok' => true], 200, $cors);
    }

    // ─── Discovery Trigger ────────────────────────────────────────────────────

    public function discover(Request $request)
    {
        $stage = trim((string) $request->input('stage'));
        $grade = trim((string) $request->input('grade'));
        $school = \Illuminate\Support\Facades\Auth::user()?->getEffectiveSchool();
        $schoolDir = $this->getSchoolStorageDir($school);
        $teacherProfileDir = $this->getActiveTeacherProfileDir($school);

        $studentsFile = null;
        if (file_exists($teacherProfileDir . '/teacher_students.json')) {
            $studentsFile = $teacherProfileDir . '/teacher_students.json';
        } elseif (file_exists($schoolDir . '/teacher_students.json')) {
            $studentsFile = $schoolDir . '/teacher_students.json';
        }

        $students = $studentsFile ? (json_decode(file_get_contents($studentsFile), true) ?: []) : [];

        // Workflow requirement: Students MUST be fetched from PARAKH portal first.
        if (empty($students)) {
            return response()->json([
                'ok' => false,
                'total' => 0,
                'students' => [],
                'error' => 'No students fetched from PARAKH portal yet. Please login to PARAKH portal and click "📥 Fetch Students from This Page" in the HPC Assist extension.'
            ]);
        }

        if (!empty($grade) && $grade !== 'all') {
            $normGrade = strtolower(trim(str_ireplace('grade', '', $grade)));
            $students = array_values(array_filter($students, function ($s) use ($grade, $normGrade) {
                $stGrade = trim((string) ($s['grade'] ?? ''));
                if (empty($stGrade))
                    return true;
                return strcasecmp($stGrade, trim($grade)) === 0 || strtolower(trim(str_ireplace('grade', '', $stGrade))) === $normGrade;
            }));
        } elseif (!empty($stage) && $stage !== 'all') {
            $stageGrades = $this->getStageGrades($stage);
            if (!empty($stageGrades)) {
                $students = array_values(array_filter($students, function ($s) use ($stageGrades, $stage) {
                    $stGrade = trim((string) ($s['grade'] ?? ''));
                    if (empty($stGrade)) {
                        $stStage = trim((string) ($s['stage'] ?? ''));
                        if (!empty($stStage) && (stripos($stStage, $stage) !== false || stripos($stage, $stStage) !== false)) {
                            return true;
                        }
                        return true;
                    }
                    return in_array($stGrade, $stageGrades, true);
                }));
            }
        }

        return response()->json([
            'ok' => true,
            'total' => count($students),
            'students' => $students,
            'message' => count($students) . ' student(s) loaded for this school.'
        ]);
    }

    // ─── School Working Days & Curricular Max Marks Settings ──────────────────

    public function academicSettings(Request $request)
    {
        $school = \Illuminate\Support\Facades\Auth::user()->getEffectiveSchool();
        $middleWd = SchoolWorkingDay::getDefault('middle');
        $prepWd = SchoolWorkingDay::getDefault('preparatory');
        $middleMm = SchoolMaxMark::getDefault('middle');
        $prepMm = SchoolMaxMark::getDefault('preparatory');

        return view('school.academic-settings', compact(
            'school',
            'middleWd',
            'prepWd',
            'middleMm',
            'prepMm'
        ));
    }

    public function getWorkingDays(Request $request)
    {
        $stage = $request->query('stage', 'middle');
        $wd = SchoolWorkingDay::getDefault($stage);
        return response()->json([
            'ok' => true,
            'data' => $wd,
        ]);
    }

    public function updateWorkingDays(Request $request)
    {
        $data = $request->validate([
            'stage' => 'nullable|string|in:middle,preparatory',
            'academic_year' => 'nullable|string|max:20',
            'apr' => 'required|integer|min:0|max:31',
            'may' => 'required|integer|min:0|max:31',
            'jun' => 'required|integer|min:0|max:31',
            'jul' => 'required|integer|min:0|max:31',
            'aug' => 'required|integer|min:0|max:31',
            'sep' => 'required|integer|min:0|max:31',
            'oct' => 'required|integer|min:0|max:31',
            'nov' => 'required|integer|min:0|max:31',
            'dec' => 'required|integer|min:0|max:31',
            'jan' => 'required|integer|min:0|max:31',
            'feb' => 'required|integer|min:0|max:31',
            'mar' => 'required|integer|min:0|max:31',
            'remarks' => 'nullable|string|max:255',
        ]);

        $stage = $data['stage'] ?? 'middle';
        $wd = SchoolWorkingDay::getDefault($stage);
        $data['stage'] = $stage;
        $data['academic_year'] = $data['academic_year'] ?? $wd->academic_year ?? '2025-2026';
        $data['total_days'] = array_sum([
            $data['apr'],
            $data['may'],
            $data['jun'],
            $data['jul'],
            $data['aug'],
            $data['sep'],
            $data['oct'],
            $data['nov'],
            $data['dec'],
            $data['jan'],
            $data['feb'],
            $data['mar']
        ]);
        $data['is_default'] = true;

        $wd->update($data);

        return response()->json([
            'ok' => true,
            'message' => ucfirst($stage) . ' Stage working days (' . $data['total_days'] . ' days) saved successfully!',
            'data' => $wd->fresh(),
        ]);
    }

    // ─── Curricular Max Marks Defaults Management ─────────────────────────────

    public function getMaxMarks(Request $request)
    {
        $stage = $request->query('stage', 'middle');
        $mm = SchoolMaxMark::getDefault($stage);
        return response()->json([
            'ok' => true,
            'data' => $mm,
            'map' => $mm->toSubjectMap(),
            'labels' => SchoolMaxMark::subjectLabels($stage),
        ]);
    }

    public function updateMaxMarks(Request $request)
    {
        $data = $request->validate([
            'stage' => 'nullable|string|in:middle,preparatory',
            'academic_year' => 'nullable|string|max:20',
            'l1_max' => 'required|integer|min:1|max:100',
            'l2_max' => 'required|integer|min:1|max:100',
            'l3_max' => 'required|integer|min:1|max:100',
            'm_max' => 'required|integer|min:1|max:100',
            'sci_max' => 'required|integer|min:1|max:100',
            'soc_max' => 'required|integer|min:1|max:100',
            'art_max' => 'required|integer|min:1|max:100',
            'pe_max' => 'required|integer|min:1|max:100',
            'remarks' => 'nullable|string|max:255',
        ]);

        $stage = $data['stage'] ?? 'middle';
        $mm = SchoolMaxMark::getDefault($stage);
        $data['stage'] = $stage;
        $data['academic_year'] = $data['academic_year'] ?? $mm->academic_year ?? '2025-2026';
        $data['is_default'] = true;

        $mm->update($data);

        return response()->json([
            'ok' => true,
            'message' => ucfirst($stage) . ' Stage curricular max marks updated successfully!',
            'data' => $mm->fresh(),
            'map' => $mm->fresh()->toSubjectMap(),
        ]);
    }

    // ─── Multi-Teacher Session Management ─────────────────────────────────────

    public function getSchoolsData(): array
    {
        if (\Illuminate\Support\Facades\Auth::check()) {
            $user = \Illuminate\Support\Facades\Auth::user();
            $dbSchool = $user->getEffectiveSchool();
            if ($dbSchool) {
                $dbTeachers = $dbSchool->teachers()->where('is_active', true)->get()->map(function ($t) use ($dbSchool) {
                    return [
                        'id' => 'teacher_' . $t->id,
                        'name' => $t->name . ($t->designation ? ' (' . $t->designation . ')' : ''),
                        'dir' => 'schools/' . $dbSchool->id . '/teacher-profiles/teacher_' . $t->id,
                        'db_id' => $t->id,
                    ];
                })->toArray();

                if (empty($dbTeachers)) {
                    $dbTeachers = [
                        ['id' => 'teacher_default', 'name' => $dbSchool->principal_name ?: 'Teacher', 'dir' => 'schools/' . $dbSchool->id . '/teacher-profiles/teacher_default']
                    ];
                }

                $activeTeacherId = session('active_teacher_id', $dbTeachers[0]['id']);

                return [
                    'active' => 'school_' . $dbSchool->id,
                    'schools' => [
                        [
                            'id' => 'school_' . $dbSchool->id,
                            'name' => $dbSchool->name,
                            'udise' => $dbSchool->udise_code,
                            'dir' => 'schools/' . $dbSchool->id . '/chrome-profile',
                            'teachers' => $dbTeachers,
                            'active_teacher' => $activeTeacherId,
                        ]
                    ]
                ];
            }
        }

        $file = storage_path('app/hpc/schools.json');
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if (is_array($data) && isset($data['schools']))
                return $data;
        }

        $default = [
            'active' => 'default',
            'schools' => [
                [
                    'id' => 'default',
                    'name' => 'BMS PWC Sugin',
                    'dir' => 'chrome-profile',
                    'teachers' => [
                        ['id' => 'teacher_default', 'name' => 'Bilal Ahmad Tali', 'dir' => 'teacher-profile']
                    ],
                    'active_teacher' => 'teacher_default'
                ]
            ]
        ];
        file_put_contents($file, json_encode($default, JSON_PRETTY_PRINT));
        return $default;
    }

    public function getActiveSchool(): array
    {
        $dbSchool = \Illuminate\Support\Facades\Auth::user()?->getEffectiveSchool();
        $data = $this->getSchoolsData();
        $activeId = $data['active'] ?? 'default';

        if ($dbSchool) {
            foreach ($data['schools'] ?? [] as $school) {
                if (($school['udise'] ?? '') === $dbSchool->udise_code || ($school['id'] ?? '') === (string) $dbSchool->id) {
                    return $school;
                }
            }
            return [
                'id' => (string) $dbSchool->id,
                'name' => $dbSchool->name,
                'udise' => $dbSchool->udise_code,
                'teachers' => $dbSchool->teachers()->get()->map(fn($t) => [
                    'id' => 'teacher_' . $t->id,
                    'name' => $t->name,
                    'dir' => 'teacher-profiles/teacher_' . $t->id,
                ])->all(),
            ];
        }

        foreach ($data['schools'] ?? [] as $school) {
            if ($school['id'] === $activeId)
                return $school;
        }
        return $data['schools'][0] ?? ['id' => 'default', 'name' => 'School', 'teachers' => []];
    }

    public function getActiveTeacher(): array
    {
        if (\Illuminate\Support\Facades\Auth::check() && \Illuminate\Support\Facades\Auth::user()->isTeacher()) {
            $userTeacher = \Illuminate\Support\Facades\Auth::user()->getEffectiveTeacher();
            if ($userTeacher) {
                return [
                    'id' => 'teacher_' . $userTeacher->id,
                    'name' => $userTeacher->name,
                    'dir' => 'teacher-profiles/teacher_' . $userTeacher->id,
                    'classes' => $userTeacher->classes,
                    'email' => $userTeacher->email,
                ];
            }
        }

        $school = $this->getActiveSchool();
        $activeTeacherId = $school['active_teacher'] ?? 'teacher_default';
        foreach ($school['teachers'] ?? [] as $t) {
            if ($t['id'] === $activeTeacherId)
                return $t;
        }
        return $school['teachers'][0] ?? ['id' => 'teacher_default', 'name' => 'Teacher', 'dir' => 'teacher-profile'];
    }

    public function getActiveTeacherProfileDir(?\App\Models\School $school = null): string
    {
        if (!$school && \Illuminate\Support\Facades\Auth::check()) {
            $school = \Illuminate\Support\Facades\Auth::user()->getEffectiveSchool();
        }

        $teacher = $this->getActiveTeacher();
        $dir = $teacher['dir'] ?? 'teacher-profile';

        if ($school && !str_starts_with($dir, 'schools/')) {
            $dir = 'schools/' . $school->id . '/' . $dir;
        }

        // If school is #1 and legacy teacher-profile directory exists, fallback for compatibility
        if ($school && $school->id === 1 && !is_dir(storage_path('app/hpc/' . $dir)) && is_dir(storage_path('app/hpc/teacher-profile'))) {
            return storage_path('app/hpc/teacher-profile');
        }

        return storage_path('app/hpc/' . $dir);
    }

    public function switchTeacher(Request $request)
    {
        $teacherId = trim((string) $request->input('id'));
        session(['active_teacher_id' => $teacherId]);

        $file = storage_path('app/hpc/schools.json');
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true) ?: [];
            $activeSchoolId = $data['active'] ?? 'default';

            foreach ($data['schools'] ?? [] as &$sch) {
                if ($sch['id'] === $activeSchoolId) {
                    $sch['active_teacher'] = $teacherId;
                    break;
                }
            }
            file_put_contents(storage_path('app/hpc/schools.json'), json_encode($data, JSON_PRETTY_PRINT));
        }

        return response()->json(['ok' => true]);
    }

    public function addTeacher(Request $request)
    {
        $name = trim((string) $request->input('name'));
        if ($name === '')
            return response()->json(['ok' => false, 'error' => 'Please provide a valid teacher name.']);

        $data = $this->getSchoolsData();
        $activeSchoolId = $data['active'] ?? 'default';
        $id = 'teacher_' . time();
        $dir = 'teacher-profiles/' . $id;

        foreach ($data['schools'] as &$sch) {
            if ($sch['id'] === $activeSchoolId) {
                if (!isset($sch['teachers']))
                    $sch['teachers'] = [];
                $sch['teachers'][] = ['id' => $id, 'name' => $name, 'dir' => $dir];
                $sch['active_teacher'] = $id;
                break;
            }
        }

        @mkdir(storage_path('app/hpc/' . $dir), 0777, true);
        file_put_contents(storage_path('app/hpc/schools.json'), json_encode($data, JSON_PRETTY_PRINT));

        return response()->json(['ok' => true, 'id' => $id, 'name' => $name]);
    }

    public function deleteTeacher(Request $request)
    {
        $id = trim((string) $request->input('id'));
        if ($id === 'teacher_default') {
            return response()->json(['ok' => false, 'error' => 'Default teacher profile cannot be deleted.']);
        }

        $data = $this->getSchoolsData();
        $activeSchoolId = $data['active'] ?? 'default';

        foreach ($data['schools'] as &$sch) {
            if ($sch['id'] === $activeSchoolId) {
                $sch['teachers'] = array_values(array_filter($sch['teachers'] ?? [], fn($t) => $t['id'] !== $id));
                if (($sch['active_teacher'] ?? '') === $id) {
                    $sch['active_teacher'] = $sch['teachers'][0]['id'] ?? 'teacher_default';
                }
                break;
            }
        }
        file_put_contents(storage_path('app/hpc/schools.json'), json_encode($data, JSON_PRETTY_PRINT));
        return response()->json(['ok' => true]);
    }

    public function startSession()
    {
        $loginUrl = config('hpc.login_url', 'https://hpc.parakh.ncert.gov.in/en/login');
        $profileDir = $this->getActiveTeacherProfileDir();
        @mkdir($profileDir, 0777, true);
        @file_put_contents($profileDir . '/session_active.txt', now()->toIso8601String());

        $teacher = $this->getActiveTeacher();
        return response()->json([
            'ok' => true,
            'url' => $loginUrl,
            'message' => "Session activated for {$teacher['name']}. Opening PARAKH portal login page…"
        ]);
    }

    public function endSession()
    {
        $profileDir = $this->getActiveTeacherProfileDir();
        $sessionFile = $profileDir . '/session_active.txt';
        if (file_exists($sessionFile)) {
            @unlink($sessionFile);
        }
        return response()->json(['ok' => true, 'message' => "Session ended for active teacher profile."]);
    }

    public function getEvaluationSections(): array
    {
        return HpcMiddleStageSchema::getSections();
    }

    public function getEvaluationColumns(): array
    {
        return HpcMiddleStageSchema::getColumns();
    }

    public function getDemoStudentRow(): array
    {
        $wd = SchoolWorkingDay::getDefault();
        $m = $wd->toMonthMap();
        $defaultMax = SchoolMaxMark::getDefault()->toSubjectMap();

        return [
            // Metadata
            'SAMPLE999',
            'DEMO_SAMPLE',
            '[SAMPLE DEMO STUDENT - GUIDE ONLY]',
            'Sample Demo',
            // Part-A(1)
            'ADM-2026-089',
            'Grade 6',
            'A',
            'Rural',
            'English',
            'Kashmiri',
            'Father',
            'Mohammad Ahmad Wani',
            // Monthly Attendance Neck-to-Neck (12 Months: Apr to Mar - Cols M to AJ)
            $m['apr'],
            min(19, $m['apr']),
            $m['may'],
            min(21, $m['may']),
            $m['jun'],
            min(10, $m['jun']),
            $m['jul'],
            min(18, $m['jul']),
            $m['aug'],
            min(20, $m['aug']),
            $m['sep'],
            min(20, $m['sep']),
            $m['oct'],
            min(18, $m['oct']),
            $m['nov'],
            min(17, $m['nov']),
            $m['dec'],
            min(14, $m['dec']),
            $m['jan'],
            min(13, $m['jan']),
            $m['feb'],
            min(19, $m['feb']),
            $m['mar'],
            min(17, $m['mar']),
            // Total Working Days (Col AK4) & Total Present Days (Col AL4)
            '=SUM(M4,O4,Q4,S4,U4,W4,Y4,AA4,AC4,AE4,AG4,AI4)',
            '=SUM(N4,P4,R4,T4,V4,X4,Z4,AB4,AD4,AF4,AH4,AJ4)',
            // Attendance % (Col AM4) & Absence Info
            '=IF(AK4>0, ROUND((AL4/AK4)*100, 1) & "%", "")',
            16,
            'Viral fever during seasonal change in November',
            'Reading',
            // Part-A(2)
            'Family',
            'Sugin, Budgam',
            'Reading books and drawing',
            'Learning new skills and actively participating',
            'most times',
            'Time management in exams',
            'Helping peers with notes',
            'Solving challenging problems independently',
            'Ayaan Ahmad',
            12,
            'Score above 90% in Mathematics and Science',
            'Builds solid foundation for pursuing higher studies in Engineering',
            'Dedicate 45 minutes daily to numerical problem solving and formula revision',
            'Clarify doubts with subject teachers immediately and practice weekly mock tests',
            'Develop regular physical exercise and disciplined reading habits',
            'Keeps mind alert, improves stamina, and enhances daily concentration',
            'Wake up at 6:00 AM every morning and do 30 minutes of yoga and jogging',
            'Read at least 15 pages of non-academic literature before bedtime',
            'Laws of reflection and refraction in science laboratory',
            'Formulating and solving linear equations in single variable',
            'Constitutional preamble and fundamental rights in Social Science',
            'Organic kitchen gardening, composting, and soil preparation',
            'Bicycle maintenance, tire inflation, and chain lubrication',
            'Basic household budgeting and grocery calculation',
            'Hands-on science experiments and advanced mathematical puzzles',
            'I am enthusiastic about collaborative group projects and active discussions',
            // Part-A(3)
            'Astrophysicist & Space Research Scientist',
            'Deep conceptual focus in Mathematics, regular physics experiments, and continuous reading',
            'Critical Thinking, Problem Solving, Data Analysis, Scientific Curiosity, Persistence',
            'Mathematics, Physics, Chemistry, English, Computer Science',
            'Consistent daily study routine, punctual time management, curious questioning, and regular science reading',
            'Bilal Ahmad Tali',
            'Mentoring in Olympiad preparations and recommending advanced reference materials',
            'Scientific problem-solving strategies and computational simulations',
            'Immensely fulfilled, proud, and inspired to contribute to the nation space mission',
            'Overjoyed, deeply proud, and grateful',
            // Part-A(4) Parent-Teacher Partnership Card (Cols CA onwards)
            // 1. Home Resources Available Checkboxes (Yes/No)
            'Yes', // Books and Magazines
            'Yes', // Newspaper
            'Yes', // Toys, Games and Sports
            'Yes', // Phone and Computer
            'Yes', // Internet
            'No',  // Radio
            'No',  // Broadcast
            'Yes', // T.V.
            'No',  // Resources for CWD
            '',    // Any other specify
            // 2. Understanding of my Child (Radio)
            'Yes', // Motivated to learn
            'Yes', // Follows schedule
            'No',  // Curriculum difficult
            'Yes', // Making good progress
            // 3. Child Needs Support With Checkboxes (Yes/No)
            'No',  // Languages
            'Yes', // Mathematics
            'Yes', // Science
            'No',  // Social Science
            'No',  // Building self-belief
            'No',  // Developing social skills
            'No',  // Managing emotions
            'Yes', // Developing study skills
            'No',  // Skill Guidance
            'No',  // Any Other
            '',    // Any Other Specify
            // 4. Support Plan at Home
            'Provide quiet study space, supervise 1.5 hours of evening revision, monitor screen time, and discuss daily learnings',
            // Part-B (Subject, Approach, Title, Rubrics, Reflection, Feedback)
            'Science',
            'Art-Integrated',
            'Solar System 3D Model & Daily Life Geometry',
            'Constructed scale models of planetary orbits, computed relative ratios, and demonstrated to class',
            'Advanced',
            'Proficient',
            'Advanced',
            'Yes',
            'Yes',
            'Yes',
            'Learned celestial orbital distances, 3D scale geometry, and public presentation skills',
            'Fabricating rotating planetary models using recycled materials and LED lighting',
            'Time management during hands-on model assembly and fine scale calibrations',
            'Calculating complex elliptical orbit equations and angular measurements',
            'Ayaan Ahmad',
            'Yes',
            'Yes',
            'Color shading and finer model labeling',
            'Mathematical formula application during group work',
            'Very supportive and collaborative teammate; explained difficult astronomical concepts clearly',
            'Critical Thinking',
            'None',
            'Encourage participation in inter-school science competitions, provide advanced telescope observation sessions, and recommend for student mentorship roles',
            // Part-C
            'Advanced',
            'Proficient',
            // HPC Credit Marks
            $defaultMax['l1'] ?? 50,
            46,
            $defaultMax['l2'] ?? 50,
            45,
            $defaultMax['l3'] ?? 50,
            44,
            $defaultMax['m'] ?? 50,
            48,
            $defaultMax['sci'] ?? 50,
            47,
            $defaultMax['soc'] ?? 50,
            44,
            $defaultMax['art'] ?? 50,
            48,
            $defaultMax['pe'] ?? 50,
            49,
            // Declaration & Remarks
            'Yes',
            'Faizan is an exceptionally motivated, intellectually curious, and responsible student. Demonstrates superior grasp in Mathematics and Sciences, with remarkable peer collaboration and positive leadership in class activities.'
        ];
    }

    public function getStudentStarterProfile(int $sIdx, array $st, array $defaultWorkMap, array $defaultMax, int $rowNum): array
    {
        $stageService = app(StageTemplateService::class);
        $stage = StageTemplateService::getStageForGrade($st['grade'] ?? 'Grade 6');
        return $stageService->getStarterRowForStage($stage, $sIdx, $st, $defaultWorkMap, $defaultMax, $rowNum, ['auto_fill' => false]);
    }
}
