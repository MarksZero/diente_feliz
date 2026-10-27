<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{AuthController,ClinicController,AdminController};

Route::get('/survey/{token}',[\App\Http\Controllers\SurveyController::class,'show'])->where('token','[a-f0-9]{64}')->middleware('throttle:30,1');
Route::post('/survey/{token}',[\App\Http\Controllers\SurveyController::class,'submit'])->where('token','[a-f0-9]{64}')->middleware('throttle:10,1');
Route::view('/survey-complete','survey-public');
Route::middleware('guest')->group(function() {
    Route::get('/login',[AuthController::class,'form'])->name('login');
    Route::post('/login',[AuthController::class,'login'])->middleware('throttle:40,1');
});
Route::middleware(['auth','enabled'])->group(function() {
    Route::get('/surveys/{id}/invite',[\App\Http\Controllers\SurveyController::class,'inviteForm'])->whereNumber('id');
    Route::post('/surveys/{id}/invite',[\App\Http\Controllers\SurveyController::class,'invite'])->whereNumber('id')->middleware('throttle:writes');
    Route::get('/',[ClinicController::class,'dashboard'])->name('dashboard');
    Route::post('/logout',[AuthController::class,'logout']);
    Route::view('/account','account');
    Route::post('/account/password',[AuthController::class,'password'])->middleware('throttle:5,1');
    Route::view('/help','help');
    Route::get('/audit',[ClinicController::class,'audit']);
    Route::get('/reports',[ClinicController::class,'reports']);
    Route::get('/users',[AdminController::class,'users']);
    Route::get('/roles',[AdminController::class,'roles']);
    Route::get('/documents/{id}/download',[ClinicController::class,'download'])->whereNumber('id')->middleware('throttle:exports');
    Route::get('/modules/{module}/export',[ClinicController::class,'export'])->middleware('throttle:exports');
    Route::get('/modules/{module}/create',[ClinicController::class,'form']);
    Route::get('/modules/{module}/{id}/edit',[ClinicController::class,'form'])->whereNumber('id');
    Route::get('/modules/{module}/{id}',[ClinicController::class,'show'])->whereNumber('id');
    Route::get('/modules/{module}',[ClinicController::class,'index']);
    Route::middleware('throttle:writes')->group(function() {
        Route::post('/users/{id?}',[AdminController::class,'saveUser'])->whereNumber('id');
        Route::post('/roles/{id?}',[AdminController::class,'saveRole'])->whereNumber('id');
        Route::post('/modules/{module}',[ClinicController::class,'store']);
        Route::put('/modules/{module}/{id}',[ClinicController::class,'update'])->whereNumber('id');
    });
});
