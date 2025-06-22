<?php

use App\Http\Controllers\BusinessController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\UserController;
use App\Services\NLQueryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('products', ProductController::class);
    Route::apiResource('businesses', BusinessController::class);
    Route::apiResource('customers', CustomerController::class);
    Route::apiResource('sales', controller: SaleController::class);

    Route::get('/users', [UserController::class, 'allUsers']);
    Route::patch('/update-profile', [UserController::class, 'updateProfile']);
    Route::patch('/update-password', [UserController::class, 'updatePassword']);
    Route::delete('/delete-account', [UserController::class, 'deleteAccount']);
    Route::post('/logout', [UserController::class, 'logout']);

    Route::post('/query-nl', [NLQueryService::class, 'handle']);
});

Route::post('/register/step/one', [UserController::class, 'registerStepOne']);
Route::post('/register/verify-otp', action: [UserController::class, 'verifyOTP']);
Route::post('/register/resend-otp', action: [UserController::class, 'resendOTP']);
Route::post('/register/step/two', action: [UserController::class, 'registerStepTwo']);
Route::post('/login', [UserController::class, 'login']);

// forgot password
Route::post('/forgot-password', [UserController::class, 'forgotPassword']);
Route::post('/reset-password', [UserController::class, 'resetPassword']);
Route::post('/verify-reset-otp', [UserController::class, 'verifyPasswordResetOtp']);

