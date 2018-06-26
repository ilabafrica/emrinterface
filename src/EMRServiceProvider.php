<?php

namespace ILabAfrica\EMRInterface;

use Illuminate\Support\ServiceProvider;

class EMRServiceProvider extends ServiceProvider {

	public function boot() {
        //
	}

    public function register() {

        $this->app->bind('emr', function($app) {
            return new EMR;
        });
    }

    public function provides() {
        return ['emr'];
    }
}
