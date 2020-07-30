<?php

use Illuminate\Support\Facades\Route;

Route::group(['prefix'=>'admin'],function ()
{
    Route::match(['get','post'],'upload/file','Admin\AdminBase@uploadFile');//上传需求文件
});

