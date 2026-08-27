<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DirectionController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MajorController;
use App\Http\Controllers\TechnicianController;
use App\Http\Controllers\RegistryController;
use App\Http\Controllers\AnapathRequestController;
use App\Http\Controllers\AnapathSuiviController;

Route::redirect('/', '/accueil');
Route::middleware('guest')->group(function (): void {
    Route::get('/connexion', [AuthController::class, 'create'])->name('login');
    Route::post('/connexion', [AuthController::class, 'store'])->name('login.store');
});
Route::middleware('auth')->group(function (): void {
    Route::post('/deconnexion', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/accueil', [HomeController::class, 'index'])->name('home');

    Route::get('/technicien', [TechnicianController::class, 'index'])->name('technician.index');
    Route::post('/technicien/declarations', [TechnicianController::class, 'store'])->name('technician.declarations.store');
    Route::get('/historique', [TechnicianController::class, 'history'])->name('technician.history');

    Route::get('/registre-bloc', [RegistryController::class, 'index'])->name('registry.index');
    Route::get('/registre-bloc/export', [RegistryController::class, 'export'])->name('registry.export');
    Route::get('/registre-bloc/anapath/{acte}', [AnapathRequestController::class, 'create'])->name('registry.anapath.create');
    Route::post('/registre-bloc/anapath/{acte}', [AnapathRequestController::class, 'store'])->name('registry.anapath.store');
    Route::get('/registre-bloc/anapath/demandes/{demande}', [AnapathRequestController::class, 'show'])->name('registry.anapath.show');
    Route::get('/registre-bloc/anapath/demandes/{demande}/modifier', [AnapathRequestController::class, 'edit'])->name('registry.anapath.edit');
    Route::patch('/registre-bloc/anapath/demandes/{demande}', [AnapathRequestController::class, 'update'])->name('registry.anapath.update');
    Route::delete('/registre-bloc/anapath/demandes/{demande}', [AnapathRequestController::class, 'cancel'])->name('registry.anapath.cancel');
    Route::get('/registre-bloc/anapath/demandes/{demande}/imprimer', [AnapathRequestController::class, 'print'])->name('registry.anapath.print');

    Route::get('/anapath-suivi', [AnapathSuiviController::class, 'index'])->name('anapath-suivi');
    Route::get('/anapath-suivi/export', [AnapathSuiviController::class, 'exportExcel'])->name('anapath-suivi.export');
    Route::post('/anapath-suivi/{demande}/resultat', [AnapathSuiviController::class, 'recevoirResultat'])->name('anapath-suivi.resultat');
    Route::post('/anapath-suivi/{demande}/paiement', [AnapathSuiviController::class, 'setPaiement'])->name('anapath-suivi.paiement');
    Route::get('/anapath-suivi/{demande}/trace', [AnapathSuiviController::class, 'trace'])->name('anapath-suivi.trace');
    Route::get('/anapath-suivi/pj/{pj}', [AnapathSuiviController::class, 'consulterPj'])->name('anapath-suivi.pj');

    Route::middleware('major')->group(function (): void {
        Route::get('/prevalidation', [MajorController::class, 'index'])->name('major.index');
        Route::patch('/prevalidation/declarations/{declaration}', [MajorController::class, 'decide'])->name('major.declarations.decide');
        Route::get('/prevalidation/pointages/{matricule}/{date}', [DirectionController::class, 'pointageDetail'])->name('major.pointages.detail');
    });
    Route::middleware('direction')->group(function (): void {
        Route::get('/direction', [DirectionController::class, 'index'])->name('direction.index');
        Route::get('/direction/export', [DirectionController::class, 'exportExcel'])->name('direction.export');
        Route::patch('/direction/declarations/{declaration}', [DirectionController::class, 'decide'])->name('direction.declarations.decide');
        Route::patch('/direction/declarations/{declaration}/devalider', [DirectionController::class, 'invalidate'])->name('direction.declarations.invalidate');
        Route::patch('/direction/declarations/{declaration}/montant', [DirectionController::class, 'updateMontant'])->name('direction.declarations.montant');
        Route::get('/direction/declarations/{declaration}/audits', [DirectionController::class, 'audits'])->name('direction.declarations.audits');
        Route::get('/direction/pointages/{matricule}/{date}', [DirectionController::class, 'pointageDetail'])->name('direction.pointages.detail');
    });
});
