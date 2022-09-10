<?php

Route::group(['middleware' => ['web', 'auth', 'tenant', 'service.accounting']], function() {

	Route::prefix('expenses')->group(function () {

        //Route::get('summary', 'Rutatiina\PettyCash\Http\Controllers\PettyCashController@summary');
        Route::post('export-to-excel', 'Rutatiina\PettyCash\Http\Controllers\PettyCashController@exportToExcel');
        Route::post('{id}/approve', 'Rutatiina\PettyCash\Http\Controllers\PettyCashController@approve');
        //Route::post('contact-estimates', 'Rutatiina\PettyCash\Http\Controllers\Sales\ReceiptController@estimates');
        Route::get('{id}/copy', 'Rutatiina\PettyCash\Http\Controllers\PettyCashController@copy');

    });

    Route::resource('expenses/settings', 'Rutatiina\PettyCash\Http\Controllers\PettyCashSettingsController');
    Route::resource('expenses', 'Rutatiina\PettyCash\Http\Controllers\PettyCashController');

});