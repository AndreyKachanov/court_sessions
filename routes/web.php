<?php

use App\Http\Controllers\HomeController;
use App\Http\Middleware\SetCourtSessionsToRedis;
use Illuminate\Support\Facades\Route;

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

Route::get('/', [HomeController::class, 'index'])
    ->name('home')
    ->middleware(SetCourtSessionsToRedis::class);

Route::post('change_room_number', [HomeController::class, 'setRoomNumber'])->name('change_room_number');
