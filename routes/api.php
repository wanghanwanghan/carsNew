<?php

use Illuminate\Support\Facades\Route;

Route::group(['middleware'=>['testMiddleware'],'prefix'=>'admin'],function ()
{
    //admin
    Route::match(['get','post'],'login','Admin\AdminController@login');






    //business



});


