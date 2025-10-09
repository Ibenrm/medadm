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
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);

// Dashboard (hanya jika login)
// EA Dashboard
Route::middleware('auth.session')->group(function() {
    Route::get('/dashboard/ea', [AuthController::class, 'dashboardEA'])->name('dashboard.ea');

    // Broadcast
    Route::get('dashboards/ea/broadcast/list', [BroadcastController::class, 'list'])->name('dashboards.ea.broadcast.list');
    Route::post('dashboards/ea/broadcast/get', [BroadcastController::class, 'getBroadcast'])->name('broadcast.get');
    Route::get('dashboards/ea/broadcast/insert', [BroadcastController::class, 'showInsert'])->name('dashboards.ea.broadcast.insert');
    Route::post('dashboards/ea/broadcast/insert', [BroadcastController::class, 'postInsert'])->name('broadcast.insert.post');

    // Community
    Route::get('dashboards/ea/community/dashboard', [CommunityController::class, 'dashboard'])->name('dashboards.ea.community.dashboard');

    // Google Review
    Route::get('dashboards/ea/google-review/dashboard', [GoogleReviewController::class, 'dashboard'])->name('dashboards.ea.google-review.dashboard');
});


// Logout
Route::post('/logout', function () {
    session()->flush();
    return redirect()->route('login');
})->name('logout');

