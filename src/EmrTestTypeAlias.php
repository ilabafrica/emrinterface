<?php

namespace ILabAfrica\EMRInterface;

use Illuminate\Database\Eloquent\Model;

class EmrTestTypeAlias extends Model{
    protected $table = 'emr_test_type_aliases';

    public function diagnosticOrders()
    {
        return $this->belongsTo('ILabAfrica\EMRInterface\DiagnosticOrder');
    }


}
