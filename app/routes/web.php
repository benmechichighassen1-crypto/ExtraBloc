<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DirectionController;
use App\Http\Controllers\TechnicianController;

Route::redirect('/', '/technicien');
Route::middleware('guest')->group(function (): void {
    Route::get('/connexion', [AuthController::class, 'create'])->name('login');
    Route::post('/connexion', [AuthController::class, 'store'])->name('login.store');
});
Route::middleware('auth')->group(function (): void {
    Route::post('/deconnexion', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/technicien', [TechnicianController::class, 'index'])->name('technician.index');
    Route::post('/technicien/declarations', [TechnicianController::class, 'store'])->name('technician.declarations.store');
    Route::middleware('direction')->group(function (): void {
        Route::get('/direction', [DirectionController::class, 'index'])->name('direction.index');
        Route::patch('/direction/declarations/{declaration}', [DirectionController::class, 'decide'])->name('direction.declarations.decide');
        Route::get('/direction/pointages/{matricule}/{date}', [DirectionController::class, 'pointageDetail'])->name('direction.pointages.detail');
    });
});
