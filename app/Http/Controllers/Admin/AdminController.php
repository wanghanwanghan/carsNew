<?php

namespace App\Http\Controllers\Admin;

use App\Http\Models\banner;
use App\Http\Models\bannerAction;
use App\Http\Models\carBelong;
use App\Http\Models\carBrand;
use App\Http\Models\carInfo;
use App\Http\Models\carLicenseType;
use App\Http\Models\carType;
use App\Http\Models\chinaArea;
use App\Http\Models\coupon;
use App\Http\Service\UploadImg;
use Geohash\GeoHash;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
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

    //算offset
    private function offset($request)
    {
        $page = $request->page ?? 1;

        $pageSize = $request->pageSize ?? 10;

        $offset = ( $page - 1 ) * $pageSize;

        return [$offset,$pageSize];
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

            $pageInfo=$this->offset($request);

            $carInfoList=DB::table('carInfo');

            if (!empty($request->carBrand)) $carInfoList->where('carBrand',$request->carBrand);

            $tmp=[];
            $tmp['list']=$carInfoList->offset(head($pageInfo))->limit(last($pageInfo))->get()->toArray();
            $tmp['total']=$carInfoList->offset(head($pageInfo))->limit(last($pageInfo))->count();

            $res=[
                'carType'=>$carType,
                'carBrand'=>$carBrand,
                'carLicenseType'=>$carLicenseType,
                'china_area'=>$china_area,
                'carBelong'=>$carBelong,
                'carInfoList'=>$tmp
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

    //创建优惠券
    public function createCoupon(Request $request)
    {
        if ($request->getMethod() === 'GET')
        {
            //刚打开页面

            $couponType=['自驾','出行','摩托'];
            $discount=['折扣减免','金额减免'];

            $pageInfo=$this->offset($request);

            $couponList=DB::table('coupon');

            $tmp=[];
            $tmp['list']=$couponList->offset(head($pageInfo))->limit(last($pageInfo))->get()->toArray();
            $tmp['total']=$couponList->offset(head($pageInfo))->limit(last($pageInfo))->count();

            $res=[
                'couponType'=>$couponType,
                'discount'=>$discount,
                'couponList'=>$tmp
            ];

            return response()->json($this->createReturn(200,$res));

        }else
        {
            //要插入数据了
            $data=[
                'name'=>$request->name ?? '我是优惠券',//名称
                'couponType'=>$request->couponType ?? '自驾',//哪种租可以用
                'needMoney'=>$request->needMoney ?? 500,//多少钱可以用
                'discountWay'=>$request->discountWay ?? 10,//折扣减免是按%，金额减免是直接减钱
                'discount'=>$request->discount ?? '折扣减免',//减免方式
                'expireStart'=>$request->expireStart ?? time(),//有效期开始
                'expireStop'=>$request->expireStop ?? time(),//有效期结束
                'phone'=>$request->phone ?? '13800138000',//手机
                'createdAt'=>$request->createdAt ?? time(),//创建时间
            ];

            try
            {
                $code=200;

                coupon::create($data);

            }catch (\Exception $e)
            {
                $code=210;
            }

            return response()->json($this->createReturn($code));
        }
    }

    //创建车行
    public function createCarBelong(Request $request)
    {
        if ($request->getMethod() === 'GET')
        {
            //刚打开页面

            $pageInfo=$this->offset($request);

            $carBelongList=DB::table('carBelong');

            $tmp=[];
            $tmp['list']=$carBelongList->offset(head($pageInfo))->limit(last($pageInfo))->get()->toArray();
            $tmp['total']=$carBelongList->offset(head($pageInfo))->limit(last($pageInfo))->count();

            $res=[
                'carBelongList'=>$tmp
            ];

            return response()->json($this->createReturn(200,$res));

        }else
        {
            $lng=$request->lng ?? '116.3623500000';
            $lat=$request->lat ?? '39.9733390000';

            $geo=(new GeoHash())->encode($lat,$lng,12);

            //要插入数据了
            $data=[
                'name'=>$request->name ?? '我是车行',//名称
                'lng'=>$lng,//纬度
                'lat'=>$lat,//经度
                'geo'=>$geo,//geo
                'address'=>$request->address ?? '北京市海淀区花园路13号汉太华',//地址
                'tel'=>$request->tel ?? '12345678',//座机
                'phone'=>$request->phone ?? '13800138000',//手机
                'open'=>$request->open ?? '9:00',//开门时间
                'close'=>$request->close ?? '22:00',//关门时间
            ];

            try
            {
                $code=200;

                carBelong::create($data);

            }catch (\Exception $e)
            {
                $code=210;
            }

            return response()->json($this->createReturn($code));
        }
    }

    //创建车辆品牌
    public function createCarBrand(Request $request)
    {
        if ($request->getMethod() === 'GET')
        {
            //刚打开页面

            $pageInfo=$this->offset($request);

            $carBrandList=DB::table('carBrand');

            $tmp=[];
            $tmp['list']=$carBrandList->offset(head($pageInfo))->limit(last($pageInfo))->get()->toArray();
            $tmp['total']=$carBrandList->offset(head($pageInfo))->limit(last($pageInfo))->count();

            $res=[
                'carBrandList'=>$tmp
            ];

            return response()->json($this->createReturn(200,$res));

        }else
        {
            //要插入数据了
            $data=[
                'carBrand'=>$request->carBrand ?? Str::random(8),//品牌名称
            ];

            try
            {
                $code=200;

                carBrand::create($data);

            }catch (\Exception $e)
            {
                $code=210;
            }

            return response()->json($this->createReturn($code));
        }
    }

    //创建banner
    public function createBanner(Request $request)
    {
        if ($request->getMethod() === 'GET')
        {
            //刚打开页面

            $pageInfo=$this->offset($request);

            $bannerList=DB::table('banner');

            $tmp=[];
            $tmp['list']=$bannerList->offset(head($pageInfo))->limit(last($pageInfo))->get()->toArray();
            $tmp['total']=$bannerList->offset(head($pageInfo))->limit(last($pageInfo))->count();

            $res=[
                'bannerList'=>$tmp
            ];

            return response()->json($this->createReturn(200,$res));

        }else
        {
            //要插入数据了
            $data=[
                'image'=>$request->image ?? Str::random(),//图片地址
                'isShow'=>$request->isShow ?? 1,//是否显示
                'level'=>$request->level ?? mt_rand(1,100),//权重
                'type'=>$request->type ?? 1,//是跳转页面还是公众号文章
                'href'=>$request->href ?? Str::random(),//跳转地址
            ];

            try
            {
                $code=200;

                banner::create($data);

            }catch (\Exception $e)
            {
                $code=210;
            }

            return response()->json($this->createReturn($code));
        }
    }

    //把文章content变成一行一行的，用<p>标签分割
    private function handleContent($content)
    {
        $tmp=[];

        preg_match_all("/\<p(.*)\<\/p\>|\<table(.*)\<\/table\>/Us",$content,$res);

        foreach (head($res) as $one)
        {
            array_push($tmp,$one);
        }

        return $tmp;
    }

    //创建banner的活动页
    public function createBannerAction(Request $request)
    {
        if ($request->getMethod() === 'GET')
        {
            //刚打开页面

            $pageInfo=$this->offset($request);

            $bannerActionList=DB::table('bannerAction');

            $tmp=[];
            $tmp['list']=$bannerActionList->offset(head($pageInfo))->limit(last($pageInfo))->get()->toArray();
            $tmp['total']=$bannerActionList->offset(head($pageInfo))->limit(last($pageInfo))->count();

            $res=[
                'bannerActionList'=>$tmp
            ];

            return response()->json($this->createReturn(200,$res));

        }else
        {
            //要插入数据了
            $data=[
                'long'=>$request->long ?? Str::random(),//长标题
                'short'=>$request->short ?? Str::random(),//端标题
                'content'=>$request->contents ?? Str::random(),//内容富文本
                'click'=>$request->click ?? 1,//点击量默认是1
                'createAt'=>$request->createAt ?? time(),//创建时间
            ];

            try
            {
                $code=200;

                bannerAction::create($data);

            }catch (\Exception $e)
            {
                $code=210;
            }

            return response()->json($this->createReturn($code));
        }
    }









    private function createTable()
    {
        if (!Schema::hasTable('bannerAction'))
        {
            Schema::create('bannerAction',function (Blueprint $table)
            {
                $table->increments('id')->unsigned()->comment('主键');
                $table->string('long')->comment('长标题');
                $table->string('short')->comment('短标题');
                $table->text('contents')->comment('内容');
                $table->integer('click')->unsigned()->comment('点击量');
                $table->integer('createAt')->unsigned()->comment('创建时间');
            });
        }
    }
}
