<?php

/**
 * All route names are prefixed with 'admin.'.
 */
Route::get('dashboard', 'DashboardController@index')->name('dashboard');
Route::post('get-permission', 'DashboardController@getPermissionByRole')->name('get.permission');

// Edit Profile
/* Route::get('profile/edit', 'DashboardController@editProfile')->name('profile.edit');
Route::patch('profile/update', 'DashboardController@updateProfile')
    ->name('profile.update'); */

Route::get('req', 'DashboardController@request');
Route::get('payment/{price}', 'DashboardController@payment')->name('payment');
Route::get('payment-status', 'DashboardController@checkStatus')->name('payment.status');
