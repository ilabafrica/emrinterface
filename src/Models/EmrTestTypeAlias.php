<?php

namespace ILabAfrica\EMRInterface\Models;

use Illuminate\Database\Eloquent\Model;

class EmrTestTypeAlias extends Model{
    protected $table = 'emr_test_type_aliases';

    public $fillable = [
        'client_id',
        'test_type_id',
        'emr_alias',
        'system',
        'request_code',
        'result_code',
        'display'
    ];

    public $timestamps = false;

    public function testType()
    {
        return $this->belongsTo('App\Models\TestType');
    }

    public function diagnosticOrders()
    {
        return $this->belongsTo('ILabAfrica\EMRInterface\DiagnosticOrder');
    }

}
