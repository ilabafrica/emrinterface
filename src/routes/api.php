<?php
Route::group(['prefix' => 'api','middleware' => 'auth:tpa_api'], function () {
	Route::get('/testmenu', 'ILabAfrica\EMRInterface\EMR@testmenu');
	Route::post('/testrequest', 'ILabAfrica\EMRInterface\EMR@receiveTestRequest');
	Route::get('/emrclients', 'ILabAfrica\EMRInterface\EMR@getEMRClients');
	Route::post('/emrclientregistration', 'ILabAfrica\EMRInterface\EMR@registerEMRClient');
	Route::post('/maptesttypeget', 'ILabAfrica\EMRInterface\EMR@mapTestTypeGet');
	Route::post('/maptesttypestore', 'ILabAfrica\EMRInterface\EMR@mapTestTypeStore');
	Route::post('/maptesttypedestroy/{id}', 'ILabAfrica\EMRInterface\EMR@mapTestTypeDestroy');
    Route::post('/mapresultget/{emr_test_type_alias_id}', 'ILabAfrica\EMRInterface\EMR@mapResultGet');
    Route::post('/mapresultstore', 'ILabAfrica\EMRInterface\EMR@mapResultStore');
    Route::post('/mapresultdestroy/{id}', 'ILabAfrica\EMRInterface\EMR@mapResultDestroy');
});