<?php

namespace ILabAfrica\EMRInterface;

use \Illuminate\Support\Facades\Facade;

class EMRFacade extends Facade {

    protected static function getFacadeAccessor() {
        return 'emr';
    }
}
