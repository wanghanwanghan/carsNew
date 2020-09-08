<?php

namespace App\Providers;

use App\Http\Service\Test\TestService;
use Illuminate\Support\ServiceProvider;
use wanghanwanghan\someUtils\control;
use wanghanwanghan\someUtils\moudles\ioc\ioc;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        ioc::getInstance()->lazyCreate('testClosure',function () {
            return control::getUuid();
        });

        ioc::getInstance()->lazyCreate('testClass',TestService::class,$a=1,$b=2,$c=3);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {

    }
}
