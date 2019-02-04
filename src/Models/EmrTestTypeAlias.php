<?php

namespace ILabAfrica\EMRInterface\Models;

use Illuminate\Database\Eloquent\Model;

class EmrTestTypeAlias extends Model{
    protected $table = 'emr_test_type_aliases';

    public $timestamps = false;

    public function diagnosticOrders()
    {
        return $this->belongsTo('ILabAfrica\EMRInterface\DiagnosticOrder');
    }

}
