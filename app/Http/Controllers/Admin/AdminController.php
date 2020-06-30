<?php

namespace App\Http\Controllers\Admin;

use App\Http\Models\carBelong;
use App\Http\Models\carBrand;
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



            $res=[123];

            return response()->json($this->createReturn(200,$res));
        }
    }











}
