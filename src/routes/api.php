<?php

Route::post('/api/auth/accesstoken', 'App\Http\Controllers\Auth\APIController@login')->name('login');

Route::group(['prefix' => 'api'], function () {

	Route::get('/testmenu', function ()
	{
		return EMR::testMenu();
	});

	Route::post('/testrequest', function ()
	{
		return EMR::receiveTestRequest();
	});
});
