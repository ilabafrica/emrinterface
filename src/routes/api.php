<?php
Route::group(['prefix' => 'api','middleware' => 'auth:tpa_api'], function () {
	Route::get('/testmenu', 'ILabAfrica\EMRInterface\EMR@testmenu');
	Route::post('/testrequest', 'ILabAfrica\EMRInterface\EMR@receiveTestRequest');
	Route::post('/maptesttypeget', 'ILabAfrica\EMRInterface\EMR@mapTestTypeGet');
	Route::post('/maptesttypestore', 'ILabAfrica\EMRInterface\EMR@mapTestTypeStore');
	Route::post('/maptesttypeupdate/{id}', 'ILabAfrica\EMRInterface\EMR@mapTestTypeUpdate');
	Route::post('/maptesttypedestroy/{id}', 'ILabAfrica\EMRInterface\EMR@mapTestTypeDestroy');
});