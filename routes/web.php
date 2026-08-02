<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CrudController;
use App\Http\Controllers\TimetableSessionController;
use App\Http\Controllers\ProfessorController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');
Route::get('/login', [AuthController::class, 'create'])->name('login');
Route::post('/login', [AuthController::class, 'store'])->name('login.store');
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::middleware('role:super_admin,sous_admin,chef_departement,chef_filiere')->group(function () {
        Route::get('/gestion/professeurs', [ProfessorController::class, 'index'])->name('professors.index');
        Route::get('/gestion/professeurs/ajouter', [ProfessorController::class, 'create'])->name('professors.create');
        Route::post('/gestion/professeurs', [ProfessorController::class, 'store'])->name('professors.store');
        Route::get('/gestion/professeurs/{professor}/modifier', [ProfessorController::class, 'edit'])->name('professors.edit');
        Route::put('/gestion/professeurs/{professor}', [ProfessorController::class, 'update'])->name('professors.update');
        Route::resource('timetable/sessions', TimetableSessionController::class)
            ->parameters(['sessions' => 'timetableSession'])
            ->names('timetable')
            ->except('show');
        Route::get('/gestion/{resource}', [CrudController::class, 'index'])->name('crud.index');
        Route::get('/gestion/{resource}/ajouter', [CrudController::class, 'create'])->name('crud.create');
        Route::post('/gestion/{resource}', [CrudController::class, 'store'])->name('crud.store');
        Route::get('/gestion/{resource}/{id}/modifier', [CrudController::class, 'edit'])->name('crud.edit');
        Route::put('/gestion/{resource}/{id}', [CrudController::class, 'update'])->name('crud.update');
        Route::delete('/gestion/{resource}/{id}', [CrudController::class, 'destroy'])->name('crud.destroy');
    });
    Route::post('/timetable/generate', [DashboardController::class, 'generate'])->middleware('role:super_admin,sous_admin,chef_departement,chef_filiere')->name('timetable.generate');
    Route::get('/timetable/{semester}', [DashboardController::class, 'show'])->name('timetable.show');
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
});
