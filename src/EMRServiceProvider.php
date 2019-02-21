<?php

namespace ILabAfrica\EMRInterface;

use Illuminate\Support\ServiceProvider;

class EMRServiceProvider extends ServiceProvider {

    public function boot() {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
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