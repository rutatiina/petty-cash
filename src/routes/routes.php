<?php

Route::group(['middleware' => ['web', 'auth', 'tenant', 'service.accounting']], function() {

	Route::prefix('petty-cash')->group(function () {

        Route::get('credit-accounts', 'Rutatiina\PettyCash\Http\Controllers\PettyCashController@creditAccounts');
        Route::post('export-to-excel', 'Rutatiina\PettyCash\Http\Controllers\PettyCashController@exportToExcel');
        Route::post('{id}/approve', 'Rutatiina\PettyCash\Http\Controllers\PettyCashController@approve');
        //Route::post('contact-estimates', 'Rutatiina\PettyCash\Http\Controllers\Sales\ReceiptController@estimates');
        Route::get('{id}/copy', 'Rutatiina\PettyCash\Http\Controllers\PettyCashController@copy');

    });

    Route::resource('petty-cash/settings', 'Rutatiina\PettyCash\Http\Controllers\PettyCashSettingsController');
    Route::resource('petty-cash', 'Rutatiina\PettyCash\Http\Controllers\PettyCashController');

});