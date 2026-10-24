<?php

use App\Http\Controllers\CircleController;
use App\Http\Controllers\MeetController;
use App\Http\Controllers\PlazaController;
use Illuminate\Support\Facades\Route;

// 1. Landing & Meet flow
Route::get('/', [MeetController::class, 'index'])->name('home');
Route::post('/match', [MeetController::class, 'match'])->middleware('throttle:matching')->name('match');
Route::get('/match/status', [MeetController::class, 'matchStatus'])->name('match.status');
Route::get('/room/{id}', [MeetController::class, 'room'])->name('room.show');

// 2. Realtime Room actions & WebRTC signaling
Route::post('/room/{id}/message', [MeetController::class, 'sendMessage'])->name('room.message');
Route::post('/room/{id}/signal', [MeetController::class, 'signal'])->name('room.signal');
Route::post('/room/{id}/skip', [MeetController::class, 'skip'])->name('room.skip');
Route::post('/room/{id}/report', [MeetController::class, 'report'])->middleware('throttle:reports')->name('room.report');
Route::post('/room/{id}/keep', [MeetController::class, 'keep'])->name('room.keep');

// 3. Collaborative Room Activities (FR-23 & Phase 5)
Route::get('/room/{id}/activity/{kind}', [MeetController::class, 'getActivity'])->name('room.activity.get');
Route::post('/room/{id}/activity', [MeetController::class, 'updateActivity'])->name('room.activity.update');

// 4. Moderation appeals (08-workflow.md Section 5)
Route::post('/appeal', [MeetController::class, 'appeal'])->name('appeal');

// 5. Circle & Keeps (01-context.md Section 5 & 06-ui-ux-structure.md)
Route::get('/circle', [CircleController::class, 'index'])->name('circle.index');
Route::get('/circle/{id}', [CircleController::class, 'show'])->name('circle.show');
Route::post('/circle/{id}/message', [CircleController::class, 'sendMessage'])->name('circle.message');
Route::post('/circle/{id}/end', [CircleController::class, 'end'])->name('circle.end');

// 6. Plaza (Group rooms, events, daily prompt, badges - Phase 6)
Route::get('/plaza', [PlazaController::class, 'index'])->name('plaza.index');
Route::post('/plaza/rooms', [PlazaController::class, 'createRoom'])->name('plaza.rooms.create');
Route::get('/plaza/rooms/{id}', [PlazaController::class, 'show'])->name('plaza.rooms.show');
Route::post('/plaza/rooms/{id}/message', [PlazaController::class, 'message'])->name('plaza.rooms.message');
Route::post('/plaza/rooms/{id}/leave', [PlazaController::class, 'leave'])->name('plaza.rooms.leave');
Route::post('/plaza/rooms/{id}/moderate', [PlazaController::class, 'moderate'])->name('plaza.rooms.moderate');
Route::post('/plaza/prompt/answer', [PlazaController::class, 'answerDailyPrompt'])->name('plaza.prompt.answer');
Route::post('/plaza/events/{id}/rsvp', [PlazaController::class, 'rsvpEvent'])->name('plaza.events.rsvp');
