<?php

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

Route::middleware('prevent-back-history')->group(function () {

    Route::get('/clear-cache', function () {
        Artisan::call('cache:clear');
        Artisan::call('route:clear');
        Artisan::call('config:clear');
        Artisan::call('view:clear');

        return Redirect::back()->with('success', 'All cache cleared successfully.');
    });

    Auth::routes();

    Route::middleware('auth')->group(function () {

        Route::any('addUserCard','StripeController@addUserCard')->name('users.addUserCard');
        Route::get('/redriect-url', 'HomeController@getUrl')->name('getUrl');
        Route::get('/get-token', 'HomeController@getToken')->name('getToken');
        Route::post('make-payment', 'StripeController@makePayment')->name('make_payment');
        Route::post('process-payment', 'StripeController@processPayment')->name('process_payment');
        Route::post('capture-payment', 'StripeController@capturePayment')->name('process_payment');
         
       
    });
});
