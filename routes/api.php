<?php

use App\Http\Controllers\BusinessController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\UserController;
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
});

Route::post('/register', [UserController::class, 'register']);
Route::post('/login', [UserController::class, 'login']);
