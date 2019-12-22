<?php

namespace Deerdama\MongoHelper;

use Illuminate\Support\ServiceProvider;

class MongoHelperServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/mongo_helper.php' => config_path('mongo_helper.php'),
        ], 'config');
    }

    public function register()
    {
        $this->app->singleton('command.db:mongo-helper', function () {
            return new ShowOptions;
        });

        $this->mergeConfigFrom(
            __DIR__ . '/../config/mongo_helper.php', 'config'
        );

        $this->commands(['command.db:mongo-helper']);
    }
}