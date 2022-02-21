<?php

use Illuminate\Support\Facades\Route;

Route::post('download', [
    'as' => 'gdpr-download',
    'uses' => 'GdprController@download',
]);

Route::get('/terms', [
    'as' => 'gdpr-terms',
    'uses' => 'GdprController@showTerms',
]);
Route::post('terms/accepted', [
    'as' => 'gdpr-terms-accepted',
    'uses' => 'GdprController@termsAccepted',
]);
Route::post('terms/denied', [
    'as' => 'gdpr-terms-denied',
    'uses' => 'GdprController@termsDenied',
]);
