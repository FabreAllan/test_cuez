<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EpisodeController;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
Route::post('/episodes/{episode}/duplicate', [EpisodeController::class, 'duplicate']);
Route::get('/episode-duplications/{duplication}', [EpisodeController::class, 'duplicationStatus']);
