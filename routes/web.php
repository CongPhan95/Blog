<?php

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

Route::get('/', function () {
    return view('welcome');
});

Route::get('locale/{locale}', function ($locale) {
    // lưu ngôn ngữ vào session
    session(['locale' => $locale]);
    return redirect(url()->previous());
    //
});

Auth::routes();

Route::get('/home', 'HomeController@index')->name('home');

Route::post('paypal', 'PaymentController@payWithpaypal')->name('payment');
Route::get('status', 'PaymentController@getPaymentStatus')->name('status');
