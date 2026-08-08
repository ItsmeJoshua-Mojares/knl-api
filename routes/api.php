<?php
// routes/api.php
// ─────────────────────────────────────────────────────────────
// CONCEPT: API Route Groups
//
// Route::prefix('auth') groups all auth routes under /api/auth/
// Route::middleware('auth:api') protects routes — the JWT guard
//   checks the Bearer token before the controller runs. If the
//   token is missing or invalid, it returns 401 automatically.
//
// Route::apiResource creates these 5 routes at once:
//   GET    /products         → index
//   POST   /products         → store
//   GET    /products/{id}    → show
//   PUT    /products/{id}    → update
//   DELETE /products/{id}    → destroy
//
// Route naming: route('products.index') generates the URL.
// Use names in code so if you change the URL you only fix one place.
// ─────────────────────────────────────────────────────────────

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\NewsletterController;

// ── Public routes (no auth required) ────────────────────────

// Auth
Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('register',       [AuthController::class, 'register'])->name('register');
    Route::post('login',          [AuthController::class, 'login'])->name('login');
});

// Products (public — anyone can browse)
Route::prefix('products')->name('products.')->group(function () {
    Route::get('/',               [ProductController::class, 'index'])->name('index');
    Route::get('featured',        [ProductController::class, 'featured'])->name('featured');
    Route::get('bestsellers',     [ProductController::class, 'bestsellers'])->name('bestsellers');
    Route::get('{slug}',          [ProductController::class, 'show'])->name('show');
});

// Categories (public)
Route::prefix('categories')->name('categories.')->group(function () {
    Route::get('/',               [CategoryController::class, 'index'])->name('index');
    Route::get('{slug}',          [CategoryController::class, 'show'])->name('show');
    Route::get('{slug}/products', [CategoryController::class, 'products'])->name('products');
});

// Brands (public)
Route::get('brands', [\App\Http\Controllers\Api\BrandController::class, 'index'])->name('brands.index');

// Reviews (public read)
Route::get('products/{product}/reviews', [ReviewController::class, 'index']);

// Newsletter (public)
Route::prefix('newsletter')->name('newsletter.')->group(function () {
    Route::post('subscribe',   [NewsletterController::class, 'subscribe'])->name('subscribe');
    Route::post('unsubscribe', [NewsletterController::class, 'unsubscribe'])->name('unsubscribe');
});

// Serve uploaded product images from local storage
// Needed for Railway/containerized deploys where public/storage symlink doesn't persist
Route::get('/images/{path}', function ($path) {
    $fullPath = storage_path("app/public/{$path}");
    if (!file_exists($fullPath)) abort(404);
    return response()->file($fullPath, [
        'Cache-Control' => 'public, max-age=86400',
    ]);
})->where('path', '.*');

// ── Protected routes (valid JWT required) ───────────────────
// The 'auth:api' middleware validates the Bearer token.
// Any invalid token → 401 Unauthorized automatically.

Route::middleware('auth:api')->group(function () {

    // Auth actions that require being logged in
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('logout',     [AuthController::class, 'logout'])->name('logout');
        Route::post('refresh',    [AuthController::class, 'refresh'])->name('refresh');
        Route::get('me',          [AuthController::class, 'me'])->name('me');
    });

    // Cart
    Route::prefix('cart')->name('cart.')->group(function () {
        Route::get('/',           [CartController::class, 'index'])->name('index');
        Route::post('add',        [CartController::class, 'add'])->name('add');
        Route::put('{item}',      [CartController::class, 'update'])->name('update');
        Route::delete('{item}',   [CartController::class, 'remove'])->name('remove');
        Route::delete('/',        [CartController::class, 'clear'])->name('clear');
        Route::post('coupon',     [CartController::class, 'applyCoupon'])->name('coupon');
    });

    // Coupon validation (used by cart's "Apply" button)
    Route::post('coupons/validate', [CouponController::class, 'validate'])->name('coupons.validate');

    // Wishlist
    Route::prefix('wishlist')->name('wishlist.')->group(function () {
        Route::get('/',           [WishlistController::class, 'index'])->name('index');
        Route::post('{product}',  [WishlistController::class, 'toggle'])->name('toggle');
    });

    // Orders
    Route::prefix('orders')->name('orders.')->group(function () {
        Route::get('/',           [OrderController::class, 'index'])->name('index');
        Route::post('/',          [OrderController::class, 'store'])->name('store');
        Route::get('{number}',    [OrderController::class, 'show'])->name('show');
        Route::post('{number}/cancel', [OrderController::class, 'cancel'])->name('cancel');
    });

    // Reviews (write — must be logged in)
    Route::post('products/{product}/reviews', [ReviewController::class, 'store']);

});

// ── Admin routes (admin/super_admin role required) ───────────
// The 'role:admin' middleware checks the user's role after JWT.
// 'log.admin' middleware (Phase 5) records every write action
// to the activity_logs table automatically.

Route::middleware(['auth:api', 'role:admin,super_admin', 'log.admin'])->prefix('admin')->name('admin.')->group(function () {

    // Products CRUD + bulk operations
    Route::apiResource('products', \App\Http\Controllers\Api\Admin\ProductController::class);
    Route::post('products/bulk-delete',         [\App\Http\Controllers\Api\Admin\ProductController::class, 'bulkDelete']);
    Route::put('products/bulk-update-status',   [\App\Http\Controllers\Api\Admin\ProductController::class, 'bulkUpdateStatus']);
    Route::post('products/{product}/adjust-stock', [\App\Http\Controllers\Api\Admin\ProductController::class, 'adjustStock']);
    Route::put('products/{id}/restore',         [\App\Http\Controllers\Api\Admin\ProductController::class, 'restore']);
    Route::delete('products/{id}/force-delete', [\App\Http\Controllers\Api\Admin\ProductController::class, 'forceDelete']);

    // Categories CRUD
    Route::apiResource('categories', \App\Http\Controllers\Api\Admin\CategoryController::class);

    // Brands CRUD
    Route::apiResource('brands', \App\Http\Controllers\Api\Admin\BrandController::class);

    // Coupons CRUD
    Route::apiResource('coupons', \App\Http\Controllers\Api\Admin\CouponController::class)->except(['show']);

    // Banners CRUD
    Route::apiResource('banners', \App\Http\Controllers\Api\Admin\BannerController::class);

    // Orders management
    Route::get('orders',                  [\App\Http\Controllers\Api\Admin\OrderController::class, 'index']);
    Route::get('orders/{id}',             [\App\Http\Controllers\Api\Admin\OrderController::class, 'show']);
    Route::put('orders/{id}/status',      [\App\Http\Controllers\Api\Admin\OrderController::class, 'updateStatus']);
    Route::get('orders/{id}/invoice-pdf', [\App\Http\Controllers\Api\Admin\ReportController::class, 'orderInvoicePdf']);

    // Payments — manual GCash/Maya/bank verification (Phase 4)
    Route::prefix('payments')->name('payments.')->group(function () {
        Route::get('/',                      [\App\Http\Controllers\Api\Admin\PaymentController::class, 'index'])->name('index');
        Route::post('{payment}/verify',      [\App\Http\Controllers\Api\Admin\PaymentController::class, 'verify'])->name('verify');
        Route::post('{payment}/reject',      [\App\Http\Controllers\Api\Admin\PaymentController::class, 'reject'])->name('reject');
        Route::post('{payment}/refund',      [\App\Http\Controllers\Api\Admin\PaymentController::class, 'refund'])->name('refund');
    });

    // Customers
    Route::get('customers',               [\App\Http\Controllers\Api\Admin\CustomerController::class, 'index']);
    Route::get('customers/{id}',          [\App\Http\Controllers\Api\Admin\CustomerController::class, 'show']);
    Route::put('customers/{id}/toggle-active', [\App\Http\Controllers\Api\Admin\CustomerController::class, 'toggleActive']);

    // Activity log (audit trail)
    Route::get('activity-log',            [\App\Http\Controllers\Api\Admin\ActivityLogController::class, 'index']);

    // Reports & exports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('orders/csv',             [\App\Http\Controllers\Api\Admin\ReportController::class, 'ordersCsv'])->name('orders-csv');
        Route::get('products/csv',           [\App\Http\Controllers\Api\Admin\ReportController::class, 'productsCsv'])->name('products-csv');
        Route::get('sales/excel',            [\App\Http\Controllers\Api\Admin\ReportController::class, 'salesExcel'])->name('sales-excel');
        Route::get('sales/pdf',              [\App\Http\Controllers\Api\Admin\ReportController::class, 'salesPdf'])->name('sales-pdf');
    });

    // Product image uploads (Cloudinary signed uploads)
    // POST   /api/admin/products/{product}/images          → upload one or more images
    // DELETE /api/admin/products/{product}/images/{image}  → delete one image
    // PUT    /api/admin/products/{product}/images/{image}/set-primary → change primary
    Route::post('products/{product}/images',                    [\App\Http\Controllers\Api\ImageController::class, 'store']);
    Route::delete('products/{product}/images/{image}',         [\App\Http\Controllers\Api\ImageController::class, 'destroy']);
    Route::put('products/{product}/images/{image}/set-primary', [\App\Http\Controllers\Api\ImageController::class, 'setPrimary']);

    // Reviews management
    Route::get('reviews',                     [\App\Http\Controllers\Api\Admin\ReviewController::class, 'index']);
    Route::post('reviews/{review}/approve',   [\App\Http\Controllers\Api\Admin\ReviewController::class, 'approve']);
    Route::post('reviews/{review}/reject',    [\App\Http\Controllers\Api\Admin\ReviewController::class, 'reject']);
    Route::post('reviews/{review}/reply',     [\App\Http\Controllers\Api\Admin\ReviewController::class, 'reply']);
    Route::delete('reviews/{review}',         [\App\Http\Controllers\Api\Admin\ReviewController::class, 'destroy']);

    // Dashboard stats
    Route::get('dashboard',               [\App\Http\Controllers\Api\Admin\DashboardController::class, 'index']);
});
