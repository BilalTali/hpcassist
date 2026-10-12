<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\HpcController;
use App\Http\Controllers\HpcTeacherController;
use App\Http\Controllers\SchoolManageController;
use App\Http\Controllers\UdiseController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (\Illuminate\Support\Facades\Auth::check()) {
        return redirect()->route('dashboard');
    }
    return view('welcome');
});

Route::middleware('auth')->group(function () {
    // ── School Central Dashboard ──────────────────────────────────────────────
    Route::get('/dashboard', function () {
        $user = \Illuminate\Support\Facades\Auth::user();
        $school = $user ? $user->getEffectiveSchool() : null;
        $teachers = $school ? $school->teachers()->orderBy('id')->get() : collect();

        $hpcController = app(HpcController::class);
        $udiseStudents = $hpcController->getUdiseStudents();
        $udiseCount = count($udiseStudents);
        $udiseApaarCount = count(array_filter($udiseStudents, fn($s) => !empty($s['apaarId']) && ctype_digit((string)$s['apaarId']) && strlen((string)$s['apaarId']) === 12));

        $previewFile = storage_path('app/hpc/preview.json');
        $preview = file_exists($previewFile) ? (json_decode(file_get_contents($previewFile), true) ?? []) : [];

        $resultsFile = storage_path('app/hpc/run_results.json');
        $lastRun = file_exists($resultsFile) ? json_decode(file_get_contents($resultsFile), true) : null;

        $hasFields = file_exists(storage_path('app/hpc/fields.json'));
        $schoolProfileDir = $hpcController->getActiveProfileDir();
        $hasSchoolProfile = is_dir($schoolProfileDir) && count(scandir($schoolProfileDir)) > 2;
        $currentFormUrl = file_exists(storage_path('app/hpc/current_form.txt'))
            ? trim(file_get_contents(storage_path('app/hpc/current_form.txt')))
            : config('hpc.add_student_url');

        $hpcTeacherController = app(HpcTeacherController::class);
        $activeTeacher = $hpcTeacherController->getActiveTeacher();
        $teacherProfileDir = $hpcTeacherController->getActiveTeacherProfileDir();
        $hasTeacherProfile = is_dir($teacherProfileDir) && count(scandir($teacherProfileDir)) > 2;

        return view('dashboard', compact(
            'school', 'teachers', 'udiseCount', 'udiseApaarCount', 'udiseStudents',
            'preview', 'lastRun', 'hasFields', 'hasSchoolProfile', 'currentFormUrl',
            'activeTeacher', 'hasTeacherProfile'
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

    // ── HPC Universal Student & Excel Routes ──────────────────────────────────
    Route::get('/dashboard/hpc',           [HpcController::class, 'dashboard'])->name('hpc.dashboard');
    Route::get('/hpc/template/download',   [HpcController::class, 'downloadTemplate'])->name('hpc.template.download');
    Route::get('/hpc/template/download/udise', [HpcController::class, 'downloadTemplate'])->name('hpc.template.download_udise');
    Route::post('/hpc/upload',             [HpcController::class, 'upload'])->name('hpc.upload');
    Route::post('/hpc/run/start',          [HpcController::class, 'runStart'])->name('hpc.run.start');
    Route::get('/hpc/run/status',          [HpcController::class, 'runStatus'])->name('hpc.run.status');
    Route::post('/hpc/run/reset',          [HpcController::class, 'runReset'])->name('hpc.run.reset');
    Route::post('/hpc/session/end',        [HpcController::class, 'endSession'])->name('hpc.session.end');
    Route::post('/hpc/session/start',      [HpcController::class, 'startSession'])->name('hpc.session.start');
    Route::post('/hpc/form/discover',      [HpcController::class, 'discoverForm'])->name('hpc.form.discover');
    Route::post('/hpc/school/switch',      [HpcController::class, 'switchSchool'])->name('hpc.school.switch');
    Route::post('/hpc/school/add',         [HpcController::class, 'addSchool'])->name('hpc.school.add');
    Route::post('/hpc/school/delete',      [HpcController::class, 'deleteSchool'])->name('hpc.school.delete');
    Route::post('/hpc/students/preload-udise', [HpcController::class, 'preloadUdise'])->name('hpc.students.preload_udise');
    Route::post('/hpc/students/save',          [HpcController::class, 'saveStudent'])->name('hpc.students.save');
    Route::post('/hpc/students/delete',        [HpcController::class, 'deleteStudent'])->name('hpc.students.delete');
    Route::post('/hpc/students/update-all',    [HpcController::class, 'updateAllStudents'])->name('hpc.students.update_all');

    // ── Teacher Assessment Portal Routes ──────────────────────────────────────
    Route::get('/dashboard/hpc/teacher',             [HpcTeacherController::class, 'dashboard'])->name('hpc.teacher.dashboard');
    Route::get('/hpc/teacher/template/download',     [HpcTeacherController::class, 'downloadTemplate'])->name('hpc.teacher.template.download');
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
    Route::get('/hpc/teacher/working-days',          [HpcTeacherController::class, 'getWorkingDays'])->name('hpc.teacher.working_days.get');
    Route::post('/hpc/teacher/working-days/update',  [HpcTeacherController::class, 'updateWorkingDays'])->name('hpc.teacher.working_days.update');
    Route::get('/hpc/teacher/max-marks',             [HpcTeacherController::class, 'getMaxMarks'])->name('hpc.teacher.max_marks.get');
    Route::post('/hpc/teacher/max-marks/update',     [HpcTeacherController::class, 'updateMaxMarks'])->name('hpc.teacher.max_marks.update');

    // ── UDISE+ SDMS Automation Routes ─────────────────────────────────────────
    Route::post('/udise/session/start',             [UdiseController::class, 'startSession'])->name('udise.session.start');
    Route::post('/udise/students/fetch',            [UdiseController::class, 'fetchStudents'])->name('udise.students.fetch');
    Route::match(['GET', 'POST'], '/udise/session/check-and-fetch', [UdiseController::class, 'checkLoginAndFetch'])->name('udise.session.check_fetch');
    Route::get('/udise/session/check',                               [UdiseController::class, 'checkLoginAndFetch'])->name('udise.session.check');
    Route::get('/udise/status',                     [UdiseController::class, 'status'])->name('udise.status');
    Route::get('/udise/students/download',          [UdiseController::class, 'download'])->name('udise.students.download');

    // ── User Profile ──────────────────────────────────────────────────────────
    Route::get('/profile',    [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile',  [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

