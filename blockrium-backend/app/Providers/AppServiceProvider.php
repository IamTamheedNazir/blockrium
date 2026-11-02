<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Notifications\Channels\DatabaseChannel as IlluminateDatabaseChannel;
use Illuminate\Notifications\DatabaseNotification as RefferalNotification;
use App\Channels\DatabaseChannel;
use App\DatabaseNotification;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    
    {
        $this->app->instance(IlluminateDatabaseChannel::class, new DatabaseChannel());
        $this->app->instance(RefferalNotification::class, new DatabaseNotification());
        
        //
    }
}
