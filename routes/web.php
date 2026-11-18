<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\ProjectController;
use App\Http\Middleware\ActiveAccount;
use Illuminate\Support\Facades\Route;

Route::get('login', [AuthController::class, 'loginPage'])->middleware('guest')->name('login');
Route::post('login', [AuthController::class, 'login'])->middleware('guest');
Route::get('forgot-password', [AuthController::class, 'forgotPage'])->middleware('guest')->name('password.request');
Route::post('forgot-password', [AuthController::class, 'forgot'])->middleware(['guest', 'throttle:5,1'])->name('password.email');
Route::get('reset-password/{token}', [AuthController::class, 'resetPage'])->middleware('guest')->name('password.reset');
Route::post('reset-password', [AuthController::class, 'reset'])->middleware(['guest', 'throttle:5,1'])->name('password.update');
Route::get('invitation/{invitation}', [OrganizationController::class, 'invitationPage'])->middleware('signed')->name('invitation.show');
Route::post('invitation/{invitation}', [OrganizationController::class, 'accept'])->middleware(['signed', 'throttle:10,1']);

Route::middleware(['auth', ActiveAccount::class])->group(function (): void {
    Route::get('/', [OrganizationController::class, 'home'])->name('home');
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('org', [OrganizationController::class, 'create']);
    Route::patch('accounts/{account}', [OrganizationController::class, 'account']);

    Route::prefix('org/{organization}')->group(function (): void {
        Route::get('access', [OrganizationController::class, 'directory'])->name('org.access');
        Route::put('access/permissions/{role}', [OrganizationController::class, 'permissions']);
        Route::post('access/invitations', [OrganizationController::class, 'invite']);
        Route::patch('access/members/{member}', [OrganizationController::class, 'membership']);
        Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
        Route::post('projects', [ProjectController::class, 'create']);

        Route::prefix('projects/{project}')->group(function (): void {
            Route::get('/', [ProjectController::class, 'show'])->name('projects.show');
            Route::put('/', [ProjectController::class, 'update']);
            Route::put('members', [ProjectController::class, 'members']);
            Route::post('documents', [ProjectController::class, 'upload']);
            Route::get('documents/{document}', [ProjectController::class, 'download']);
        });
    });
});
