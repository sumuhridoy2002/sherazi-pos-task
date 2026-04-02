<?php

use App\Http\Controllers\{LoginController, ProductController, OrderController};
use Illuminate\Support\Facades\Route;

Route::post('/login', LoginController::class);

//'auth:sanctum'
Route::domain('{tenant}.test')->group(function () {
    Route::middleware(['tenant'])->group(function () {
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
    });
});