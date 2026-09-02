<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CrudController;
use App\Http\Controllers\TimetableSessionController;
use App\Http\Controllers\TimetableQualityController;
use App\Http\Controllers\TimetableExportController;
use App\Http\Controllers\ProfessorController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\ExcelImportController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');
Route::get('/login', [AuthController::class, 'create'])->name('login');
Route::post('/login', [AuthController::class, 'store'])->name('login.store');
Route::get('/register', [RegisterController::class, 'create'])->name('register');
Route::post('/register', [RegisterController::class, 'store'])->name('register.store');
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Gestion des utilisateurs & rôles (super_admin / sous_admin uniquement)
    Route::middleware('role:super_admin,sous_admin')->group(function () {
        Route::get('/gestion/utilisateurs', [UserManagementController::class, 'index'])->name('users.index');
        Route::get('/gestion/utilisateurs/{user}/modifier', [UserManagementController::class, 'edit'])->name('users.edit');
        Route::put('/gestion/utilisateurs/{user}', [UserManagementController::class, 'update'])->name('users.update');
        Route::delete('/gestion/utilisateurs/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');

        // Paramètres de l'établissement (nom, adresse, contact, logo)
        Route::get('/gestion/parametres', [\App\Http\Controllers\SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/gestion/parametres', [\App\Http\Controllers\SettingsController::class, 'update'])->name('settings.update');
        Route::delete('/gestion/parametres/logo', [\App\Http\Controllers\SettingsController::class, 'removeLogo'])->name('settings.logo.remove');
    });

    // Gestion fine des rôles & permissions (super_admin uniquement)
    Route::middleware('role:super_admin')->group(function () {
        Route::get('/gestion/roles', [\App\Http\Controllers\RolePermissionController::class, 'index'])->name('roles.index');
        Route::get('/gestion/roles/{role}/permissions', [\App\Http\Controllers\RolePermissionController::class, 'edit'])->name('roles.edit');
        Route::put('/gestion/roles/{role}/permissions', [\App\Http\Controllers\RolePermissionController::class, 'update'])->name('roles.update');
    });

    Route::middleware('role:super_admin,sous_admin,chef_departement,chef_filiere')->group(function () {
        Route::get('/gestion/professeurs', [ProfessorController::class, 'index'])->name('professors.index');
        Route::get('/gestion/professeurs/import/template', [ExcelImportController::class, 'professorsTemplate'])->name('professors.import.template');
        Route::post('/gestion/professeurs/import', [ExcelImportController::class, 'professors'])->name('professors.import');
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
        Route::get('/gestion/modules/import/template', [ExcelImportController::class, 'modulesTemplate'])->name('modules.import.template');
        Route::post('/gestion/modules/import', [ExcelImportController::class, 'modules'])->name('modules.import');
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
    Route::delete('/timetable/generate/{generation}', [DashboardController::class, 'destroy'])->middleware('role:super_admin,sous_admin,chef_departement,chef_filiere')->name('timetable.generate.destroy');
    Route::get('/etat', [TimetableExportController::class, 'etat'])->name('etat.index');
    Route::get('/etat/pdf/global', [TimetableExportController::class, 'globalPdf'])->name('etat.pdf.global');
    Route::get('/etat/pdf/semestre', [TimetableExportController::class, 'semesterPdf'])->name('etat.pdf.semester');
    Route::get('/etat/pdf/filiere', [TimetableExportController::class, 'programPdf'])->name('etat.pdf.program');
    Route::get('/etat/pdf/professeur', [TimetableExportController::class, 'professorPdf'])->name('etat.pdf.professor');
    Route::get('/timetable/semestre/{number}', [DashboardController::class, 'showByNumber'])->name('timetable.semester-number');
    Route::get('/timetable/{semester}', [DashboardController::class, 'show'])->name('timetable.show');
    Route::get('/timetable/{semester}/export-pdf', [TimetableExportController::class, 'pdf'])->name('timetable.export.pdf');
    Route::get('/timetable/{semester}/export-excel', [TimetableExportController::class, 'excel'])->name('timetable.export.excel');
    
    // Timetable Quality Reports
    Route::get('/timetable/{semesterId}/quality', [TimetableQualityController::class, 'show'])->name('timetable.quality');
    Route::get('/api/timetable/{semesterId}/quality/summary', [TimetableQualityController::class, 'summary'])->name('timetable.quality.summary');
    
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
});
