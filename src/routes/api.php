<?php
Route::group(['prefix' => 'api','middleware' => 'auth:tpa_api'], function () {
	Route::get('/testmenu', 'ILabAfrica\EMRInterface\EMR@testmenu');
	Route::post('/testrequest', 'ILabAfrica\EMRInterface\EMR@receiveTestRequest');
	Route::post('/maptesttypebynames', 'ILabAfrica\EMRInterface\EMR@mapTestTypeByNames');
});

Route::group(['prefix' => 'api','middleware' => 'auth:api'], function () {
	Route::get('/maptesttypeget', 'ILabAfrica\EMRInterface\EMR@mapTestTypeGet');
	Route::post('/maptesttypestore', 'ILabAfrica\EMRInterface\EMR@mapTestTypeStore');
	Route::post('/maptesttypedestroy/{id}', 'ILabAfrica\EMRInterface\EMR@mapTestTypeDestroy');
	Route::get('/mapresultget/{emr_test_type_alias_id}', 'ILabAfrica\EMRInterface\EMR@mapResultGet');
	Route::post('/mapresultstore', 'ILabAfrica\EMRInterface\EMR@mapResultStore');
	Route::get('/mapresultdestroy/{id}', 'ILabAfrica\EMRInterface\EMR@mapResultDestroy');
	Route::get('/emrclients', 'ILabAfrica\EMRInterface\EMR@getEMRClients');
	Route::post('/emrclientregistration', 'ILabAfrica\EMRInterface\EMR@registerEMRClient');
	Route::post('/emrclientregistration/{id}', 'ILabAfrica\EMRInterface\EMR@updateEMRClient');
});
