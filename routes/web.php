<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ObituaryController;

Route::get('/', [ObituaryController::class, 'index'])->name('obituaries.index');
Route::post('/scrape-now', [ObituaryController::class, 'scrapeNow'])->name('obituaries.scrape_now');
Route::view('/implementation', 'implementation.index')->name('implementation.index');
