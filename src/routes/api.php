<?php
Route::post('/api/auth/accesstoken', 'App\Http\Controllers\Auth\APIController@login')->name('login');

Route::group(['prefix' => 'api','middleware' => 'auth:api'], function () {
	Route::get('/testmenu', 'ILabAfrica\EMRInterface\EMR@testmenu');
	Route::post('/testrequest', 'ILabAfrica\EMRInterface\EMR@receiveTestRequest');
});
