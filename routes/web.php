<?php

use App\Http\Controllers\AgroDiagnosisController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AgroDiagnosisController::class, 'index'])->name('home');
Route::get('/agro-slm', [AgroDiagnosisController::class, 'index'])->name('agro-slm.dashboard');
Route::get('/agro-slm/diagnose', fn () => redirect()->route('agro-slm.dashboard'));
Route::post('/agro-slm/diagnose', [AgroDiagnosisController::class, 'diagnose'])->name('agro-slm.diagnose');
Route::get('/agro-slm/status', [AgroDiagnosisController::class, 'status'])->name('agro-slm.status');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
