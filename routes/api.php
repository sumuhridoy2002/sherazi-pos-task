<?php

use App\Http\Controllers\{ProductController, OrderController};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::controller(ProductController::class)
    ->prefix('products')
    ->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/search', 'search');
        Route::get('/dashboard', 'dashboard');
        Route::get('/sales-report', 'salesReport');
    });

Route::controller(OrderController::class)
    ->prefix('orders')
    ->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/filter', 'filterByStatus');
    });

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});