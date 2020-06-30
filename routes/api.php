<?php

use Illuminate\Support\Facades\Route;

Route::group(['middleware'=>['testMiddleware'],'prefix'=>'admin'],function ()
{
    //admin
    Route::match(['get','post'],'login','Admin\AdminController@login');//登录
    Route::match(['get','post'],'uploadImg','Admin\AdminController@uploadImg');//上传图片






    //business



});


