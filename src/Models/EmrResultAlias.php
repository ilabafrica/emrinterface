<?php

namespace ILabAfrica\EMRInterface\Models;

use Illuminate\Database\Eloquent\Model;

class EmrResultAlias extends Model{
    protected $table = 'emr_result_aliases';

    public $fillable = ['emr_test_type_alias_id', 'measure_range_id', 'emr_alias'];

    public $timestamps = false;

    public function emrTestTypeAlias()
    {
        return $this->belongsTo('ILabAfrica\EMRInterface\EmrTestTypeAlias');
    }

    public function measureRange()
    {
        return $this->belongsTo('App\Models\MeasureRange');
    }

}
