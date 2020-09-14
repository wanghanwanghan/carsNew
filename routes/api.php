<?php

use Illuminate\Support\Facades\Route;

//admin
Route::group(['prefix'=>'admin'],function ()
{
    Route::match(['get','post'],'reg','Admin\AdminController@reg');//注册
    Route::match(['get','post'],'get/adminUser','Admin\AdminController@getAdminUserList');//后台用户列表
    Route::match(['get','post'],'edit/user','Admin\AdminController@editUser');//修改后台用户
    Route::match(['get','post'],'delete/user','Admin\AdminController@deleteUser');//修改后台用户
    Route::match(['get','post'],'login','Admin\AdminController@login');//登录
    Route::match(['get','post'],'uploadImg','Admin\AdminController@uploadImg');//上传图片
    Route::match(['get','post'],'paginate','Admin\AdminController@paginate');//公共分页
    Route::match(['get','post'],'create/car','Admin\AdminController@createCar');//创建车辆
    Route::match(['get','post'],'edit/car','Admin\AdminController@editCar');//编辑车辆
    //Route::match(['get','post'],'delete/car','Admin\AdminController@deleteCar');//删除车辆，改成isShow了
    Route::match(['get','post'],'isShow/car','Admin\AdminController@isShowCar');//上架/下架
    Route::match(['get','post'],'create/carBelong','Admin\AdminController@createCarBelong');//创建车行
    Route::match(['get','post'],'edit/carBelong','Admin\AdminController@editCarBelong');//编辑车行
    Route::match(['get','post'],'delete/carBelong','Admin\AdminController@deleteCarBelong');//删除车行，判断有没有关联的车
    Route::match(['get','post'],'edit/carRelationWithBelong','Admin\AdminController@carRelationWithBelong');//修改车和车行的关系
    Route::match(['get','post'],'create/carBrand','Admin\AdminController@createCarBrand');//创建品牌
    Route::match(['get','post'],'edit/carBrand','Admin\AdminController@editCarBrand');//修改品牌
    Route::match(['get','post'],'delete/carBrand','Admin\AdminController@deleteCarBrand');//删除品牌，判断有没有关联的车
    Route::match(['get','post'],'edit/carRelationWithBrand','Admin\AdminController@carRelationWithBrand');//修改车和品牌的关系
    Route::match(['get','post'],'create/coupon','Admin\AdminController@createCoupon');//创建优惠券
    Route::match(['get','post'],'delete/coupon','Admin\AdminController@deleteCoupon');//删除优惠券，删除未使用的，所以不影响订单
    Route::match(['get','post'],'create/banner','Admin\AdminController@createBanner');//创建banner
    Route::match(['get','post'],'edit/banner','Admin\AdminController@editBanner');//修改banner
    Route::match(['get','post'],'delete/banner','Admin\AdminController@deleteBanner');//删除banner
    Route::match(['get','post'],'get/order','Admin\AdminController@getOrder');//获取订单
    Route::match(['get','post'],'refund/order','Admin\AdminController@refundOrder');//订单退款
    Route::match(['get','post'],'setStatus/order','Admin\AdminController@setOrderStatus');//修改订单状态
    Route::match(['get','post'],'setForfeitPrice/order','Admin\AdminController@setForfeitPriceOrder');
    Route::match(['get','post'],'get/license','Admin\AdminController@getLicense');//获取证件
    Route::match(['get','post'],'setStatus/license','Admin\AdminController@setLicenseStatus');//修改证件审核状态
    Route::match(['get','post'],'get/user','Admin\AdminController@getUserList');//获取用户列表
    Route::match(['get','post'],'setRemark/user','Admin\AdminController@setRemarkUser');//修改用户的备注
    Route::match(['get','post'],'setRemark/order','Admin\AdminController@setRemarkOrder');//修改订单的备注
    Route::match(['get','post'],'get/purchase','Admin\AdminController@getPurchaseList');//充值页面
    Route::match(['get','post'],'index','Admin\AdminController@index');//首页
    Route::match(['get','post'],'edit/notifyPhone','Admin\AdminController@editNotifyPhone');
    Route::match(['get','post'],'getOrderNumByStatus','Admin\AdminController@getOrderNumByStatus');
    Route::match(['get','post'],'setRentPersonInfo/order','Admin\AdminController@setRentPersonInfo');
    Route::match(['get','post'],'create/label','Admin\AdminController@createLabel');
    Route::match(['get','post'],'edit/label','Admin\AdminController@editLabel');
    Route::match(['get','post'],'delete/label','Admin\AdminController@deleteLabel');

    Route::match(['get','post'],'create/AXTG','Admin\AdminController@createAXTG');
    Route::match(['get','post'],'edit/AXTG','Admin\AdminController@editAXTG');
    Route::match(['get','post'],'delete/AXTG','Admin\AdminController@deleteAXTG');

    Route::match(['get','post'],'create/JZCY','Admin\AdminController@createJZCY');
    Route::match(['get','post'],'edit/JZCY','Admin\AdminController@editJZCY');
    Route::match(['get','post'],'delete/JZCY','Admin\AdminController@deleteJZCY');

    Route::match(['get','post'],'create/CZCZ','Admin\AdminController@createCZCZ');
    Route::match(['get','post'],'edit/CZCZ','Admin\AdminController@editCZCZ');
    Route::match(['get','post'],'delete/CZCZ','Admin\AdminController@deleteCZCZ');

    Route::match(['get','post'],'export/order','Admin\AdminController@exportOrder');
    Route::match(['get','post'],'modify/service/phone','Admin\AdminController@servicePhone');
});

//business
Route::group(['prefix'=>'v1','middleware'=>['statistics']],function ()
{
    //需要登录的
    Route::group(['middleware'=>['testMiddleware']],function ()
    {
        Route::match(['get','post'],'bookCar','Business\Index\Index@bookCar');//预定车辆
        Route::match(['get','post'],'bookCar/checkCarAvailable','Business\Index\Index@checkCarAvailable');
        Route::match(['get','post'],'updateOrCreateUserImg','Business\Index\Index@updateOrCreateUserImg');//更新或保存用户图片
        Route::match(['get','post'],'getLicenseStatus','Business\Index\Index@getLicenseStatus');//获取用户驾照和身份证审核状态
        Route::match(['get','post'],'createOrder','Business\Index\Index@createOrder');//创建订单
        Route::match(['get','post'],'payOrder','Business\Index\Index@payOrder');//支付订单
        Route::match(['get','post'],'getOftenCity','Business\Index\Index@getOftenCity');//获取用户常用车城市
        Route::match(['get','post'],'getUserInfo','Business\Index\Index@getUserInfo');//获取用户信息
        Route::match(['get','post'],'payPassword','Business\Index\Index@payPassword');//支付密码
        Route::match(['get','post'],'getUserCoupon','Business\Index\Index@getUserCoupon');//用户所有优惠券
        Route::match(['get','post'],'orderInfo','Business\Index\Index@orderInfo');//订单
        Route::match(['get','post'],'purchaseList','Business\Index\Index@purchaseList');//充值列表
        Route::match(['get','post'],'purchase','Business\Index\Index@purchase');//充值




    });

    Route::match(['get','post'],'carDetail','Business\Index\Index@carDetail');//车辆详情
    Route::match(['get','post'],'index','Business\Index\Index@Index');//首页
    Route::match(['get','post'],'globalConf','Business\Index\Index@globalConf');//全局配置
    Route::match(['get','post'],'cityList','Business\Index\Index@cityList');//城市列表
    Route::match(['get','post'],'getVerificationCode','Business\Index\Index@getVerificationCode');//获取验证码
    Route::match(['get','post'],'unLogin','Business\Index\Index@unLogin');//退出登录
    Route::match(['get','post'],'login','Business\Index\Index@login');//登录
    Route::match(['get','post'],'allCarBelongInCity','Business\Index\Index@allCarBelongInCity');//这个城市所有的门店
    Route::match(['get','post'],'module{id}','Business\Index\Index@moduleDispatch')->where('id','[1-6]{1}');//6个模块登录



});

//notify
Route::group(['prefix'=>'notify'],function ()
{
    Route::match(['get','post'],'wxNotify','Notify\Notify@wxNotify');//微信小程序支付通知




});
