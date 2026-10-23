<?php

use App\Http\Controllers\MeetController;
use Illuminate\Support\Facades\Route;

// 1. Landing & Meet flow
Route::get('/', [MeetController::class, 'index'])->name('home');
Route::post('/match', [MeetController::class, 'match'])->name('match');
Route::get('/room/{id}', [MeetController::class, 'room'])->name('room.show');
