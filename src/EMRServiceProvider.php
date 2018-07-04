<?php

namespace ILabAfrica\EMRInterface;

use Illuminate\Support\ServiceProvider;

class EMRServiceProvider extends ServiceProvider {

	public function boot() {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations/2018_06_27_074705_create_emr_interface_tables.php');
        $this->loadRoutesFrom(__DIR__.'/routes/api.php');
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
