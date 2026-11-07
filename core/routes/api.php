<?php

use App\Http\Controllers\Api\Agent\CommandController;
use App\Http\Controllers\Api\Agent\EventController;
use App\Http\Controllers\Api\Agent\HeartbeatController;
use App\Http\Controllers\Api\Agent\RegisterController;
use Illuminate\Support\Facades\Route;

/*
| API Agent ke Core (PRD bagian 9). Semua endpoint memakai header
| Authorization: Bearer <token agent>, kecuali pendaftaran.
*/
Route::prefix('v1/agent')->group(function () {
    Route::post('register', RegisterController::class)->middleware('throttle:agent-register');

    Route::middleware(['auth:sanctum', 'agent', 'throttle:agent-api'])->group(function () {
        Route::post('heartbeat', HeartbeatController::class);
        Route::post('events', EventController::class);
        Route::get('commands', [CommandController::class, 'index']);
        Route::post('commands/{id}/result', [CommandController::class, 'result'])->whereNumber('id');
    });
});
