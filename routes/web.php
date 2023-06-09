<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AgoraVideoController;

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



Auth::routes();

Route::group(['middleware' => ['auth']], function () {
    Route::get('/', [AgoraVideoController::class, 'index']);
    Route::post('/agora/token', [AgoraVideoController::class, 'token']);
    Route::post('/agora/call-user', [AgoraVideoController::class, 'callUser']);
    Route::post('/agora/call-end', [AgoraVideoController::class, 'call_end']);
    Route::post('/agora/call-decline', [AgoraVideoController::class, 'call_decline']);
    Route::post('/auth/video_chat', [AgoraVideoController::class, 'auth']);
});
