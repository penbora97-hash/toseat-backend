<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Route::get('/', function () {
//     return view('welcome');
// });
Route::get('/', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'TosEat API is running',
        'version' => '1.0.0',
    ]);
});

// ✅ Serve storage files when symlink is not available
Route::get('/storage/{path}', function ($path) {
    $fullPath = storage_path('app/public/' . $path);
    
    if (!file_exists($fullPath)) {
        // Return default avatar instead of 404
        $defaultAvatar = storage_path('app/public/avatars/default.png');
        if (file_exists($defaultAvatar)) {
            return response()->file($defaultAvatar);
        }
        abort(404);
    }
    
    return response()->file($fullPath);
})->where('path', '.*');