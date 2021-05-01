<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Livewire\Admin\Users\ListUsers;
use Illuminate\Support\Facades\Auth;
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



if(Auth::guest()) {

    Route::get('/', function () {
        return view('main');
    });

} else {
    //ezeket csak belépett Felhasználók láthatják
    Route::get('/', function () {
        redirect("/home");
    });

    Route::get('/home', DashboardController::class)->name('home.home')->middleware('auth');

    Route::get('/admin/users', ListUsers::class)->name('admin.users')->middleware('auth');

}