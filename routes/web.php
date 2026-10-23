<?php

use App\Http\Controllers\MeetController;
use Illuminate\Support\Facades\Route;

// 1. Landing & Meet flow
Route::get('/', [MeetController::class, 'index'])->name('home');
Route::post('/match', [MeetController::class, 'match'])->name('match');
Route::get('/room/{id}', [MeetController::class, 'room'])->name('room.show');

// 2. Realtime Room actions & WebRTC signaling
Route::post('/room/{id}/message', [MeetController::class, 'sendMessage'])->name('room.message');
Route::post('/room/{id}/signal', [MeetController::class, 'signal'])->name('room.signal');
Route::post('/room/{id}/skip', [MeetController::class, 'skip'])->name('room.skip');
Route::post('/room/{id}/report', [MeetController::class, 'report'])->name('room.report');
Route::post('/room/{id}/keep', [MeetController::class, 'keep'])->name('room.keep');

// 3. Moderation appeals (08-workflow.md Section 5)
Route::post('/appeal', [MeetController::class, 'appeal'])->name('appeal');
