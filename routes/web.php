<?php

use App\Http\Controllers\DatabaseBackup;
use App\Models\LoanAccount;
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
Route::get('/database/download',[DatabaseBackup::class,'download']);

Route::get('/', function () {
    return view('welcome');
});
//Route::get('/del_trans', function (){
//running query
//});

