<?php

namespace ILabAfrica\EMRInterface;

use Illuminate\Database\Eloquent\Model;

class DiagnosticOrderStatus extends Model
{
	const result_pending = 1;
	const result_sent = 2;
}
