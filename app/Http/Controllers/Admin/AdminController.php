<?php

namespace App\Http\Controllers\Admin;

use App\Http\Models\carBelong;
use App\Http\Models\carBrand;
use App\Http\Models\carInfo;
use App\Http\Models\carLicenseType;
use App\Http\Models\carType;
use App\Http\Models\chinaArea;
use App\Http\Service\UploadImg;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use wanghanwanghan\someUtils\control;

class AdminController extends AdminBase
{
    //后台用户登录
    public function login(Request $request)
    {
        $username=$request->username;
        $password=$request->password;

        $check=DB::table('admin_users')->where([
            'username'=>$username,
            'password'=>$password,
        ])->first();

        return $check === null ?
            response()->json($this->createReturn(201,[],'用户名密码错误')) :
            response()->json($this->createReturn(200,$check,'登录成功'));
    }

    //上传图片
    public function uploadImg(Request $request)
    {
        $path=[];

        foreach ($request->all() as $one)
        {
            if ($one instanceof UploadedFile)
            {
                $check=(new UploadImg())->store($one);

                if (empty($check)) continue;

                $path[]=$check;
            }
        }

        return response()->json($this->createReturn(200,$path));
    }

    //创建跑车
    public function createSportsCar(Request $request)
    {
        if ($request->getMethod() === 'GET')
        {
            //刚打开页面

            $carType=carType::all()->toArray();
            $carBrand=carBrand::all()->toArray();
            $carLicenseType=carLicenseType::all()->toArray();
            $china_area=chinaArea::all()->toArray();
            $tmp=[];
            control::traverseMenu($china_area,$tmp);
            $china_area=$tmp;
            $carBelong=carBelong::all()->toArray();

            $res=[
                'carType'=>$carType,
                'carBrand'=>$carBrand,
                'carLicenseType'=>$carLicenseType,
                'china_area'=>$china_area,
                'carBelong'=>$carBelong,
            ];

            return response()->json($this->createReturn(200,$res));

        }else
        {
            //要插入数据了
            $data=[
                'images'=>$request->images ?? '无',//车辆图片
                'carType'=>$request->carType ?? 1,//车辆类型
                'carBrand'=>$request->carBrand ?? 1,//品牌
                'carModel'=>$request->carModel ?? '无',//型号
                'engine'=>$request->engine ?? 1.0,//排量
                'year'=>$request->year ?? 2020,//年份
                'carLicenseType'=>$request->carLicenseType ?? 1,//牌照
                'carBelongCity'=>$request->carBelongCity ?? 1,//所属城市
                'operateType'=>$request->carBelongCity ?? '自动挡',//操作模式
                'seatNum'=>$request->seatNum ?? 2,//座位个数
                'driveType'=>$request->driveType ?? '四驱',//驱动方式
                'isRoadster'=>$request->isRoadster ?? '否',//是否敞
                'carColor'=>$request->carColor ?? '钻石白',//外观颜色
                'insideColor'=>$request->insideColor ?? '尊贵棕',//内饰颜色
                'dayPrice'=>$request->dayPrice ?? 5000,//日租价格
                'dayDiscount'=>$request->dayDiscount ?? 10,//日租折扣
                'goPrice'=>$request->goPrice ?? 3000,//出行价格
                'goDiscount'=>$request->goDiscount ?? 10,//出行折扣
                'kilPrice'=>$request->kilPrice ?? 20.0,//每公里价格
                'carNum'=>$request->carNum ?? 20,//库存剩余
                'carBelong'=>$request->carBelong ?? 1,//所属车行
                'damagePrice'=>$request->damagePrice ?? 20000,//车损押金
                'forfeitPrice'=>$request->forfeitPrice ?? 2000,//违章押金
                'isActivities'=>$request->isActivities ?? '否',//是否参加活动
                'rentMin'=>$request->rentMin ?? 1,//最小天数
                'rentMax'=>$request->rentMax ?? 9999,//最大天数
            ];

            try
            {
                $code=200;

                carInfo::create($data);

            }catch (\Exception $e)
            {
                $code=210;
            }

            return response()->json($this->createReturn($code));
        }
    }











}
