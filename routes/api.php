<?php

use App\Http\Controllers\ApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('auth/tokens', [ApiController::class, 'issue'])->middleware('throttle:10,1');
    Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function (): void {
        Route::delete('auth/tokens/current', [ApiController::class, 'revoke']);
        Route::get('me', [ApiController::class, 'me']);
        Route::get('projects', [ApiController::class, 'projects']);
        Route::get('projects/{project}', [ApiController::class, 'project']);
        Route::get('projects/{project}/wbs', [ApiController::class, 'wbs']);
        Route::get('wbs-items/{item}', [ApiController::class, 'item']);
    });
});
