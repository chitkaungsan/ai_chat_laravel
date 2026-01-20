<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ChatBKController;

Route::post('/chat', ChatController::class);
Route::post('/chat/bk', ChatController::class);
