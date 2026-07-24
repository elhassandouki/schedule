<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CrudController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');
Route::get('/login', [AuthController::class, 'create'])->name('login');
Route::post('/login', [AuthController::class, 'store'])->name('login.store');
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::middleware('role:super_admin,sous_admin,chef_departement,chef_filiere')->group(function () {
        Route::get('/gestion/{resource}', [CrudController::class, 'index'])->name('crud.index');
        Route::get('/gestion/{resource}/ajouter', [CrudController::class, 'create'])->name('crud.create');
        Route::post('/gestion/{resource}', [CrudController::class, 'store'])->name('crud.store');
        Route::get('/gestion/{resource}/{id}/modifier', [CrudController::class, 'edit'])->name('crud.edit');
        Route::put('/gestion/{resource}/{id}', [CrudController::class, 'update'])->name('crud.update');
        Route::delete('/gestion/{resource}/{id}', [CrudController::class, 'destroy'])->name('crud.destroy');
    });
    Route::post('/schedules/generate', [DashboardController::class, 'generate'])->middleware('role:super_admin,sous_admin,chef_departement,chef_filiere')->name('schedules.generate');
    Route::get('/schedules/{schedule}', [DashboardController::class, 'show'])->name('schedules.show');
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
});
