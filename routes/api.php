<?php

use Illuminate\Support\Facades\Route;

Route::group(['middleware'=>['testMiddleware'],'prefix'=>'v1'],function ()
{
    //admin
    Route::match(['get','post'],'login','Admin\AdminController@login');






    //business



});


