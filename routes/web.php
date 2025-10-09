<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BroadcastController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\GoogleReviewController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Semua route utama aplikasi. Route login tetap di luar middleware agar
| bisa diakses tanpa login, sedangkan dashboard & fitur lain hanya bisa
| diakses jika user sudah login (middleware: auth.session)
|
*/

// =======================
// 🔐 AUTHENTICATION
// =======================
Route::get('/', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/', [AuthController::class, 'login']);
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);

// =======================
// 🚪 LOGOUT
// =======================
Route::post('/logout', function () {
    session()->flush();
    return redirect()->route('login');
})->name('logout');


Route::middleware('auth.session')->group(function () {

    // === EA Dashboard ===
    Route::get('/dashboard/ea', [AuthController::class, 'dashboardEA'])->name('dashboard.ea');

    Route::prefix('/dashboards/ea/broadcast')->group(function () {

        // Daftar broadcast
        Route::get('/list', [BroadcastController::class, 'list'])->name('dashboards.ea.broadcast.list');

        // Ambil data broadcast
        Route::post('/get', [BroadcastController::class, 'getBroadcast'])->name('broadcast.get');

        // Tampilkan halaman insert
        Route::get('/insert', [BroadcastController::class, 'showInsert'])->name('dashboards.ea.broadcast.insert');

        // Proses insert batch ke API
        Route::post('/insert', [BroadcastController::class, 'postInsert'])->name('broadcast.insert.post');
    });

    // =========================
    // 👥 COMMUNITY MANAGEMENT
    // =========================
    Route::prefix('/dashboards/ea/community')->group(function () {
        Route::get('/dashboard', [CommunityController::class, 'dashboard'])->name('dashboards.ea.community.dashboard');
    });

    // =========================
    // 🌟 GOOGLE REVIEW
    // =========================
    Route::prefix('/dashboards/ea/google-review')->group(function () {
        Route::get('/dashboard', [GoogleReviewController::class, 'dashboard'])->name('dashboards.ea.google-review.dashboard');
    });
});
