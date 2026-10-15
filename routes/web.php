<?php

use App\Http\Controllers\HpcController;
use App\Http\Controllers\HpcTeacherController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SchoolManageController;
use App\Http\Controllers\UdiseController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (\Illuminate\Support\Facades\Auth::check()) {
        return redirect()->route('dashboard');
    }
    return view('welcome');
});

// ── Public UDISE bridge & ingest (no auth required) ─────────────────────────
// Bridge page: opened in user's own browser tab to handle UDISE login & fetch
Route::get('/udise/bridge', [UdiseController::class, 'bridge'])->name('udise.bridge');
// Extension zip download
Route::get('/extension/download', [UdiseController::class, 'downloadExtension'])->name('extension.download');
// Ingest: called by bridge page JS or extension after fetching students (CSRF-exempt via bootstrap/app.php)
Route::match(['post','options'], '/udise/ingest', [UdiseController::class, 'ingest'])->name('udise.ingest');
// HPC Portal Queue & Progress: called by extension or browser automation on PARAKH portal
Route::match(['get', 'options'],  '/hpc/students/queue',             [HpcController::class, 'getStudentQueue'])->name('hpc.students.queue');
Route::match(['post', 'options'], '/hpc/run/progress',               [HpcController::class, 'runProgress'])->name('hpc.run.progress');
// Extension-facing: receive students already on HPC portal (token-protected)
Route::match(['post', 'options'], '/hpc/students/sync-from-portal',  [HpcController::class, 'syncFromPortal'])->name('hpc.students.sync_from_portal');

Route::middleware('auth')->group(function () {
    // ── School Central Dashboard ──────────────────────────────────────────────
    Route::get('/dashboard', function () {
        $user = \Illuminate\Support\Facades\Auth::user();
        $school = $user ? $user->getEffectiveSchool() : null;
        $teachers = $school ? $school->teachers()->orderBy('id')->get() : collect();

        $hpcController = app(HpcController::class);
        $preview       = [];  // Step 2: pending students (UDISE/Excel, not yet submitted)
        $step3Students = [];  // Step 3: already confirmed on HPC portal
        $udiseCount      = 0;
        $udiseApaarCount = 0;
        $step3Count      = 0;

        if ($school) {
            if (\Illuminate\Support\Facades\Schema::hasTable('students')) {
                // Step 2: UDISE + Excel students still pending
                $step2Query = $school->students()->step2()->orderBy('id');
                $step2Students = $step2Query->get();
                if ($step2Students->isNotEmpty()) {
                    $preview = $step2Students->map(fn($st, $idx) => $st->toPreviewArray($idx + 1))->values()->all();
                }

                // Step 3: already on HPC (fetched or submitted)
                $step3Students = $school->students()->step3()
                    ->orderByDesc('hpc_submitted_at')
                    ->orderByDesc('hpc_fetched_at')
                    ->get()
                    ->map(fn($st, $idx) => $st->toPreviewArray($idx + 1))
                    ->values()
                    ->all();
                $step3Count = count($step3Students);
            }

            $udiseCount      = count($preview);
            $udiseApaarCount = count(array_filter($preview, fn($s) => ! empty($s['apaar_id']) && strlen((string)$s['apaar_id']) === 12));
        }

        $udiseStudents = $school ? $hpcController->getUdiseStudents($school) : [];

        $schoolStorageDir = $school ? $hpcController->getSchoolStorageDir($school) : storage_path('app/hpc');
        $resultsFile = $schoolStorageDir . '/run_results.json';
        $lastRun = file_exists($resultsFile) ? json_decode(file_get_contents($resultsFile), true) : null;

        $hasFields = file_exists(storage_path('app/hpc/fields.json'));
        $schoolProfileDir = $hpcController->getActiveProfileDir($school);
        $hasSchoolProfile = is_dir($schoolProfileDir) && (
            file_exists($schoolProfileDir . '/session_active.txt') || (count(scandir($schoolProfileDir)) > 2)
        );
        $currentFormUrl = file_exists(storage_path('app/hpc/current_form.txt'))
            ? trim(file_get_contents(storage_path('app/hpc/current_form.txt')))
            : config('hpc.add_student_url');

        $hpcTeacherController = app(HpcTeacherController::class);
        $activeTeacher = $hpcTeacherController->getActiveTeacher();
        $teacherProfileDir = $hpcTeacherController->getActiveTeacherProfileDir($school);
        $hasTeacherProfile = is_dir($teacherProfileDir) && (
            file_exists($teacherProfileDir . '/session_active.txt') || (count(scandir($teacherProfileDir)) > 2)
        );

        $schoolToken = $school ? substr(hash('sha256', $school->id . '_' . $school->udise_code . '_' . config('app.key')), 0, 32) : '';

        $workingDays = \App\Models\SchoolWorkingDay::getDefault();
        $maxMarks    = \App\Models\SchoolMaxMark::getDefault();
        $hpcGrades   = $school ? $school->students()->step3()->selectRaw('grade, count(*) as count')->groupBy('grade')->orderBy('grade')->get() : collect();

        return view('dashboard', compact(
            'school', 'teachers', 'udiseCount', 'udiseApaarCount', 'udiseStudents',
            'preview', 'step3Students', 'step3Count', 'lastRun', 'hasFields',
            'hasSchoolProfile', 'currentFormUrl', 'activeTeacher', 'hasTeacherProfile',
            'schoolToken', 'workingDays', 'maxMarks', 'hpcGrades'
        ));
    })->name('dashboard');

    // ── School & Multi-Teacher Management ─────────────────────────────────────
    Route::post('/school/update',               [SchoolManageController::class, 'updateSchool'])->name('school.update');
    Route::get('/school/teachers',              [SchoolManageController::class, 'getTeachers'])->name('school.teachers');
    Route::post('/school/teachers',             [SchoolManageController::class, 'storeTeacher'])->name('school.teachers.store');
    Route::post('/school/teachers/{id}/update', [SchoolManageController::class, 'updateTeacher'])->name('school.teachers.update');
    Route::post('/school/teachers/{id}/delete', [SchoolManageController::class, 'deleteTeacher'])->name('school.teachers.delete');
    Route::post('/school/udise/skip',            [SchoolManageController::class, 'skipUdise'])->name('school.udise.skip');
    Route::post('/school/udise/reset',           [SchoolManageController::class, 'resetUdise'])->name('school.udise.reset');
    Route::post('/school/udise/sync-existing',   [SchoolManageController::class, 'syncExistingUdise'])->name('school.udise.sync_existing');
    Route::post('/school/sessions/kill-all',     [SchoolManageController::class, 'killAllSessions'])->name('school.sessions.kill_all');
    Route::match(['get', 'post'], '/school/system/migrate', function () {
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        $output = \Illuminate\Support\Facades\Artisan::output();
        return response()->json([
            'ok'     => true,
            'output' => $output,
        ]);
    })->name('school.system.migrate');


    // ── HPC Universal Student & Excel Routes ──────────────────────────────────
    Route::get('/dashboard/hpc',           [HpcController::class, 'dashboard'])->name('hpc.dashboard');
    Route::get('/hpc/template/download',   [HpcController::class, 'downloadTemplate'])->name('hpc.template.download');
    Route::get('/hpc/template/download/udise', [HpcController::class, 'downloadTemplate'])->name('hpc.template.download_udise');
    Route::get('/hpc/run/status',          [HpcController::class, 'runStatus'])->name('hpc.run.status');
    Route::post('/hpc/run/start',          [HpcController::class, 'runStart'])->name('hpc.run.start');
    Route::post('/hpc/run/reset',          [HpcController::class, 'runReset'])->name('hpc.run.reset');
    Route::post('/hpc/session/end',        [HpcController::class, 'endSession'])->name('hpc.session.end');
    Route::post('/hpc/session/start',      [HpcController::class, 'startSession'])->name('hpc.session.start');
    Route::post('/hpc/form/discover',      [HpcController::class, 'discoverForm'])->name('hpc.form.discover');
    Route::post('/hpc/school/switch',      [HpcController::class, 'switchSchool'])->name('hpc.school.switch');
    Route::post('/hpc/school/add',         [HpcController::class, 'addSchool'])->name('hpc.school.add');
    Route::post('/hpc/school/delete',      [HpcController::class, 'deleteSchool'])->name('hpc.school.delete');

    // Student mutation routes: rate-limited to 30 requests/minute per user (Fix 1.5)
    Route::middleware('throttle:30,1')->group(function () {
        Route::post('/hpc/upload',                    [HpcController::class, 'upload'])->name('hpc.upload');
        Route::post('/hpc/students/preload-udise',    [HpcController::class, 'preloadUdise'])->name('hpc.students.preload_udise');
        Route::post('/hpc/students/save',             [HpcController::class, 'saveStudent'])->name('hpc.students.save');
        Route::post('/hpc/students/delete',           [HpcController::class, 'deleteStudent'])->name('hpc.students.delete');
        Route::post('/hpc/students/clear-all',        [HpcController::class, 'clearAllStudents'])->name('hpc.students.clear_all');
        Route::post('/hpc/students/update-all',       [HpcController::class, 'updateAllStudents'])->name('hpc.students.update_all');
    });

    // Step 3: HPC-confirmed students list (auth-guarded, dashboard-facing)
    Route::get('/hpc/students/step3',  [HpcController::class, 'getStep3Students'])->name('hpc.students.step3');


    // ── Teacher Assessment Portal Routes ──────────────────────────────────────
    Route::get('/dashboard/hpc/teacher',             [HpcTeacherController::class, 'dashboard'])->name('hpc.teacher.dashboard');
    Route::get('/hpc/teacher/template/download',     [HpcTeacherController::class, 'downloadTemplate'])->name('hpc.teacher.template.download');
    Route::post('/hpc/teacher/sync-roster',          [HpcTeacherController::class, 'syncRoster'])->name('hpc.teacher.sync_roster');
    Route::post('/hpc/teacher/upload',               [HpcTeacherController::class, 'upload'])->name('hpc.teacher.upload');
    Route::post('/hpc/teacher/discover',             [HpcTeacherController::class, 'discover'])->name('hpc.teacher.discover');
    Route::post('/hpc/teacher/run/start',            [HpcTeacherController::class, 'runStart'])->name('hpc.teacher.run.start');
    Route::get('/hpc/teacher/run/status',            [HpcTeacherController::class, 'runStatus'])->name('hpc.teacher.run.status');
    Route::post('/hpc/teacher/run/reset',            [HpcTeacherController::class, 'runReset'])->name('hpc.teacher.run.reset');
    Route::post('/hpc/teacher/session/start',        [HpcTeacherController::class, 'startSession'])->name('hpc.teacher.session.start');
    Route::post('/hpc/teacher/session/end',          [HpcTeacherController::class, 'endSession'])->name('hpc.teacher.session.end');
    Route::post('/hpc/teacher/switch',               [HpcTeacherController::class, 'switchTeacher'])->name('hpc.teacher.switch');
    Route::post('/hpc/teacher/add',                  [HpcTeacherController::class, 'addTeacher'])->name('hpc.teacher.add');
    Route::post('/hpc/teacher/delete',               [HpcTeacherController::class, 'deleteTeacher'])->name('hpc.teacher.delete');
    Route::get('/school/academic-settings',          [HpcTeacherController::class, 'academicSettings'])->name('school.academic_settings');
    Route::get('/hpc/teacher/working-days',          [HpcTeacherController::class, 'getWorkingDays'])->name('hpc.teacher.working_days.get');
    Route::post('/hpc/teacher/working-days/update',  [HpcTeacherController::class, 'updateWorkingDays'])->name('hpc.teacher.working_days.update');
    Route::get('/hpc/teacher/max-marks',             [HpcTeacherController::class, 'getMaxMarks'])->name('hpc.teacher.max_marks.get');
    Route::post('/hpc/teacher/max-marks/update',     [HpcTeacherController::class, 'updateMaxMarks'])->name('hpc.teacher.max_marks.update');

    // ── UDISE+ Auth-Required Routes ───────────────────────────────────────────
    Route::post('/udise/session/start',  function() { return response()->json(['ok'=>true]); })->name('udise.session.start');
    Route::match(['GET','POST'], '/udise/session/check-and-fetch', function() {
        return response()->json(['ok'=>true,'logged_in'=>false,'state'=>'no_chrome','message'=>'Use the UDISE+ Auto-Sync button.']);
    })->name('udise.session.check_fetch');
    Route::get('/udise/ingest-poll',     [UdiseController::class, 'ingestPoll'])->name('udise.ingest_poll');
    Route::get('/udise/status',          [UdiseController::class, 'status'])->name('udise.status');
    Route::get('/udise/students/download',[UdiseController::class, 'download'])->name('udise.students.download');

    // ── User Profile ──────────────────────────────────────────────────────────
    Route::get('/profile',    [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile',  [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

