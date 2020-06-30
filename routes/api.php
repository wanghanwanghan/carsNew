<?php

use Illuminate\Support\Facades\Route;

Route::group(['middleware'=>['testMiddleware'],'prefix'=>'admin'],function ()
{
    //admin
    Route::match(['get','post'],'login','Admin\AdminController@login');//登录
    Route::match(['get','post'],'uploadImg','Admin\AdminController@uploadImg');//上传图片
    Route::match(['get','post'],'create/sportsCar','Admin\AdminController@createSportsCar');//创建跑车
    Route::match(['get','post'],'create/coupon','Admin\AdminController@createCoupon');//创建优惠券
    Route::match(['get','post'],'create/carBelong','Admin\AdminController@createCarBelong');//创建车行
    Route::match(['get','post'],'create/carBrand','Admin\AdminController@createCarBrand');//创建车辆品牌






    //business



});


