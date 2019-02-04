<?php

namespace ILabAfrica\EMRInterface\Models;

use Illuminate\Database\Eloquent\Model;

class EmrResultAlias extends Model{
    protected $table = 'emr_result_aliases';

    public $timestamps = false;

    public function emrTestTypeAlias()
    {
        return $this->belongsTo('ILabAfrica\EMRInterface\EmrTestTypeAlias');
    }

}
