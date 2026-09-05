<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\TransactionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::apiResource('accounts', AccountController::class);
    Route::get('accounts/{account}/balance', [AccountController::class, 'balance'])
        ->name('accounts.balance');

    Route::get('categories/tree', [CategoryController::class, 'tree'])
        ->name('categories.tree');
    Route::apiResource('categories', CategoryController::class);

    Route::apiResource('transactions', TransactionController::class);
    Route::post('transactions/transfer', [TransactionController::class, 'transfer'])
        ->name('transactions.transfer');
    Route::get('transactions-statistics', [TransactionController::class, 'statistics'])
        ->name('transactions.statistics');
    Route::post('accounts/{account}/recalculate-balance', [TransactionController::class, 'recalculateBalance'])
        ->name('accounts.recalculate-balance');
});
