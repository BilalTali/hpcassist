<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\HpcController;
use App\Http\Controllers\HpcTeacherController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard/hpc',           [HpcController::class, 'dashboard'])->name('hpc.dashboard');
Route::get('/hpc/template/download',   [HpcController::class, 'downloadTemplate'])->name('hpc.template.download');
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

// ── Main app ──────────────────────────────────────────────────────────────────
Route::get('/dashboard', fn() => view('dashboard'))
    ->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile',    [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile',  [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

