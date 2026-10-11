<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\HpcController;
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

// ── Main app ──────────────────────────────────────────────────────────────────
Route::get('/dashboard', fn() => view('dashboard'))
    ->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile',    [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile',  [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

