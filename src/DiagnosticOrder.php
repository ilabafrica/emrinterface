<?php

namespace ILabAfrica\EMRInterface;

use Illuminate\Database\Eloquent\Model;

class DiagnosticOrder extends Model
{
    public $fillable = ['test_id', 'diagnostic_order_status_id'];
}
