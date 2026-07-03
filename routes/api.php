<?php
// routes/api.php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\OrderController as UserOrderController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// ==================== PUBLIC ROUTES ====================
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// ==================== PUBLIC CATEGORY ROUTES ====================
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{id}', [CategoryController::class, 'show']);
Route::get('/categories/{id}/products', [CategoryController::class, 'products']);

// ==================== PUBLIC PRODUCT ROUTES ====================
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::get('/products/featured', [ProductController::class, 'featured']);
Route::get('/products/popular', [ProductController::class, 'popular']);

// ==================== PROTECTED ROUTES (User) ====================
Route::middleware('auth:sanctum')->group(function () {
    // Profile
    Route::get('/user', [AuthController::class, 'user']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/profile/avatar', [AuthController::class, 'updateAvatar']);

    // Logout
    Route::post('/logout', [AuthController::class, 'logout']);

    // ✅ Cart Routes - FIXED: clear and count BEFORE {productId}
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart', [CartController::class, 'store']);
    Route::delete('/cart/clear', [CartController::class, 'clear']);      // ← ត្រូវមុន {productId}
    Route::get('/cart/count', [CartController::class, 'count']);        // ← ត្រូវមុន {productId}
    Route::put('/cart/{productId}', [CartController::class, 'update']);
    Route::delete('/cart/{productId}', [CartController::class, 'destroy']);

    // ✅ User Order Routes
    Route::get('/orders', [UserOrderController::class, 'index']);
    Route::get('/orders/{id}', [UserOrderController::class, 'show']);
    Route::post('/orders', [UserOrderController::class, 'store']);
    Route::put('/orders/{id}/cancel', [UserOrderController::class, 'cancel']);
    Route::get('/orders/track/{id}', [UserOrderController::class, 'track']);
});

// ==================== ADMIN ROUTES ====================
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    // ==================== USERS ====================
    Route::get('/users', [AdminUserController::class, 'index']);
    Route::get('/users/{id}', [AdminUserController::class, 'show']);
    Route::put('/users/{id}', [AdminUserController::class, 'update']);
    Route::delete('/users/{id}', [AdminUserController::class, 'destroy']);
    Route::patch('/users/{id}/status', [AdminUserController::class, 'updateStatus']);

    // ==================== CATEGORIES ====================
    Route::get('/categories', [AdminCategoryController::class, 'index']);
    Route::get('/categories/active', [AdminCategoryController::class, 'getActiveCategories']);
    Route::get('/categories/max-order', [AdminCategoryController::class, 'getMaxOrder']);
    Route::post('/categories', [AdminCategoryController::class, 'store']);
    Route::get('/categories/{id}', [AdminCategoryController::class, 'show']);
    Route::put('/categories/{id}', [AdminCategoryController::class, 'update']);
    Route::delete('/categories/{id}', [AdminCategoryController::class, 'destroy']);
    Route::patch('/categories/{id}/status', [AdminCategoryController::class, 'updateStatus']);
    Route::post('/categories/reorder', [AdminCategoryController::class, 'reorder']);

    // ==================== PRODUCTS ====================
    Route::get('/products', [AdminProductController::class, 'index']);
    Route::get('/products/{id}', [AdminProductController::class, 'show']);
    Route::post('/products', [AdminProductController::class, 'store']);
    Route::put('/products/{id}', [AdminProductController::class, 'update']);
    Route::delete('/products/{id}', [AdminProductController::class, 'destroy']);
    Route::patch('/products/{id}/stock', [AdminProductController::class, 'updateStock']);
    Route::patch('/products/{id}/availability', [AdminProductController::class, 'updateAvailability']);
    Route::get('/products/category/{categoryId}', [AdminProductController::class, 'getByCategory']);
    Route::get('/products/stats', [AdminProductController::class, 'stats']);
    Route::post('/products/upload-image', [AdminProductController::class, 'uploadImage']);

    // ==================== ORDERS ====================
    Route::get('/orders', [AdminOrderController::class, 'index']);
    Route::get('/orders/{id}', [AdminOrderController::class, 'show']);
    Route::put('/orders/{id}/status', [AdminOrderController::class, 'updateStatus']);
    Route::delete('/orders/{id}/cancel', [AdminOrderController::class, 'cancel']);
    Route::get('/orders/stats', [AdminOrderController::class, 'getOrderStats']);

    // ==================== DASHBOARD STATISTICS ====================
    Route::get('/stats', [AdminOrderController::class, 'stats']);
});
    