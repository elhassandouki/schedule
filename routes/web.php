<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CrudController;
use App\Http\Controllers\TimetableSessionController;
use App\Http\Controllers\TimetableQualityController;
use App\Http\Controllers\TimetableExportController;
use App\Http\Controllers\ProfessorController;
use App\Http\Controllers\RegisterController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');
Route::get('/login', [AuthController::class, 'create'])->name('login');
Route::post('/login', [AuthController::class, 'store'])->name('login.store');
Route::get('/register', [RegisterController::class, 'create'])->name('register');
Route::post('/register', [RegisterController::class, 'store'])->name('register.store');
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::middleware('role:super_admin,sous_admin,chef_departement,chef_filiere')->group(function () {
        Route::get('/gestion/professeurs', [ProfessorController::class, 'index'])->name('professors.index');
        Route::get('/gestion/professeurs/ajouter', [ProfessorController::class, 'create'])->name('professors.create');
        Route::post('/gestion/professeurs', [ProfessorController::class, 'store'])->name('professors.store');
                Route::get('/gestion/professeurs/{professor}/disponibilites', [ProfessorController::class, 'availabilities'])->name('professors.availabilities');
        Route::post('/gestion/professeurs/{professor}/disponibilites', [ProfessorController::class, 'updateAvailabilities'])->name('professors.availabilities.update');
        Route::delete('/gestion/professeurs/{professor}/disponibilites/{availability}', [ProfessorController::class, 'deleteAvailability'])->name('professors.availabilities.destroy');
        Route::get('/gestion/professeurs/{professor}/modifier', [ProfessorController::class, 'edit'])->name('professors.edit');
        Route::put('/gestion/professeurs/{professor}', [ProfessorController::class, 'update'])->name('professors.update');
        Route::resource('timetable/sessions', TimetableSessionController::class)
            ->parameters(['sessions' => 'timetableSession'])
            ->names('timetable')
            ->except('show');
        Route::get('/gestion/{resource}', [CrudController::class, 'index'])->name('crud.index');
        Route::get('/gestion/{resource}/ajouter', [CrudController::class, 'create'])->name('crud.create');
        Route::post('/gestion/{resource}', [CrudController::class, 'store'])->name('crud.store');
                Route::get('/gestion/{resource}/{id}/conditions', [CrudController::class, 'showGroupConditions'])->name('crud.group-conditions');
        Route::post('/gestion/{resource}/{id}/conditions', [CrudController::class, 'storeGroupCondition'])->name('crud.group-conditions.store');
        Route::delete('/gestion/{resource}/{id}/conditions/{condition}', [CrudController::class, 'destroyGroupCondition'])->name('crud.group-conditions.destroy');
        Route::get('/gestion/{resource}/{id}/modifier', [CrudController::class, 'edit'])->name('crud.edit');
        Route::put('/gestion/{resource}/{id}', [CrudController::class, 'update'])->name('crud.update');
        Route::delete('/gestion/{resource}/{id}', [CrudController::class, 'destroy'])->name('crud.destroy');
    });
    Route::post('/timetable/generate', [DashboardController::class, 'generate'])->middleware('role:super_admin,sous_admin,chef_departement,chef_filiere')->name('timetable.generate');
    Route::get('/timetable/{semester}', [DashboardController::class, 'show'])->name('timetable.show');
    Route::get('/timetable/{semester}/export-pdf', [TimetableExportController::class, 'pdf'])->name('timetable.export.pdf');
    Route::get('/timetable/{semester}/export-excel', [TimetableExportController::class, 'excel'])->name('timetable.export.excel');
    
    // Timetable Quality Reports
    Route::get('/timetable/{semesterId}/quality', [TimetableQualityController::class, 'show'])->name('timetable.quality');
    Route::get('/api/timetable/{semesterId}/quality/summary', [TimetableQualityController::class, 'summary'])->name('timetable.quality.summary');
    
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
});
