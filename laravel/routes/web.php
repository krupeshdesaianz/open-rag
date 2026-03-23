<?php

use App\Http\Controllers\UploadController;
use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;

// Upload & Train page
Route::get('/',                    [UploadController::class, 'index'])->name('upload.index');
Route::post('/upload',             [UploadController::class, 'upload'])->name('upload.file');
Route::delete('/files/{file}',     [UploadController::class, 'deleteFile'])->name('upload.delete');
Route::post('/train',              [UploadController::class, 'train'])->name('upload.train');
Route::get('/status',              [UploadController::class, 'status'])->name('upload.status');
Route::get('/system-file',         [UploadController::class, 'getSystemFile'])->name('upload.system-file.get');
Route::put('/system-file',         [UploadController::class, 'updateSystemFile'])->name('upload.system-file.update');

// Chat page
Route::get('/chat',                [ChatController::class, 'index'])->name('chat.index');
Route::post('/chat/query',         [ChatController::class, 'query'])->name('chat.query');
