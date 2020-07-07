<?php

namespace App\Http\Controllers\Admin;

use App\Http\Models\banner;
use App\Http\Models\bannerAction;
use App\Http\Models\carBelong;
use App\Http\Models\carBrand;
use App\Http\Models\carInfo;
use App\Http\Models\carLicenseType;
use App\Http\Models\carModel;
use App\Http\Models\carModelCarBelong;
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
        $this->createTable();

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

    //创建车
    public function createCar(Request $request)
    {
        if ($request->getMethod() === 'GET')
        {
            //刚打开页面

            $carType=carType::all()->toArray();
            $carBrand=carBrand::all()->toArray();
            $china_area=chinaArea::all()->toArray();
            $tmp=[];
            control::traverseMenu($china_area,$tmp);
            $china_area=$tmp;
            $carBelong=carBelong::all()->toArray();

            $pageInfo=$this->offset($request);

            $carModelList=DB::table('carModel');

            if (!empty($request->carBrand)) $carModelList->where('carBrand',$request->carBrandId);

            $tmp=[];
            $tmp['total']=$carModelList->count();
            $tmp['list']=$carModelList->offset(head($pageInfo))->limit(last($pageInfo))->get()->toArray();

            $res=[
                'carType'=>$carType,
                'carBrand'=>$carBrand,
                'china_area'=>$china_area,
                'carBelong'=>$carBelong,
                'carModelList'=>$tmp
            ];

            return response()->json($this->createReturn(200,$res));

        }else
        {
            //要插入数据了
            $data=[
                'carModel'=>$request->carModel ?? '无',//型号
                'carImg'=>$request->carImg ?? '无',//车辆图片
                'carType'=>$request->carType ?? 1,//车辆类型
                'carBrandId'=>$request->carBrandId ?? 1,//品牌
                'carDesc'=>$request->carDesc ?? '无',//描述
                'level'=>$request->level ?? 0,//权重
                'damagePrice'=>$request->damagePrice ?? 20000,//车损押金
                'forfeitPrice'=>$request->forfeitPrice ?? 2000,//违章押金
                'dayPrice'=>$request->dayPrice ?? 5000,//日租价格
                'dayDiscount'=>$request->dayDiscount ?? 10,//日租折扣
                'goPrice'=>$request->goPrice ?? 3000,//出行价格
                'goDiscount'=>$request->goDiscount ?? 10,//出行折扣
                'kilPrice'=>$request->kilPrice ?? 20,//每公里价格
            ];

            try
            {
                $code=200;

                $id=(carModel::create($data))->id;

                $arr=json_decode($request->carBelongArr,true);

                foreach ($arr as &$one)
                {
                    $one['carModelId']=$id;
                }
                unset($one);

                DB::table('carModelCarBelong')->insert($arr);

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

            $china_area=chinaArea::all()->toArray();
            $tmp=[];
            control::traverseMenu($china_area,$tmp);
            $china_area=$tmp;

            $tmp=[];
            $tmp['list']=$carBelongList->offset(head($pageInfo))->limit(last($pageInfo))->get()->toArray();
            $tmp['total']=$carBelongList->offset(head($pageInfo))->limit(last($pageInfo))->count();

            $res=[
                'china_area'=>$china_area,
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
                'cityId'=>$request->cityId ?? 1,//所属城市
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
        //不用动
        //品牌表
        if (!Schema::hasTable('carBrand')) {}

        //牌照表
        //不用动
        if (!Schema::hasTable('carLicenseType')) {}

        //车辆型号
        if (!Schema::hasTable('carModel'))
        {
            Schema::create('carModel',function (Blueprint $table)
            {
                $table->increments('id')->unsigned()->comment('主键');
                $table->string('carModel',30)->comment('车辆型号');
                $table->text('carImg')->comment('车辆图片');
                $table->integer('carType')->unsigned()->comment('车辆类型表id');
                $table->integer('carBrandId')->unsigned()->comment('车辆品牌表id');
                $table->string('carDesc')->comment('描述');
                $table->integer('level')->unsigned()->comment('权重');
                $table->integer('damagePrice')->unsigned()->comment('车损押金');
                $table->integer('forfeitPrice')->unsigned()->comment('违章押金');
                $table->integer('dayPrice')->unsigned()->comment('日租价格');
                $table->integer('dayDiscount')->unsigned()->comment('日租折扣');
                $table->integer('goPrice')->unsigned()->comment('出行价格');
                $table->integer('goDiscount')->unsigned()->comment('出行折扣');
                $table->integer('kilPrice')->unsigned()->comment('每公里价格');
            });
        }

        //标签表
        if (!Schema::hasTable('carLabel'))
        {
            Schema::create('carLabel',function (Blueprint $table)
            {
                $table->increments('id')->unsigned()->comment('主键');
                $table->string('label',30)->comment('标签名称');
            });
        }

        //车型-标签关联表
        if (!Schema::hasTable('carModelLabel'))
        {
            Schema::create('carModelLabel',function (Blueprint $table)
            {
                $table->increments('id')->unsigned()->comment('主键');
                $table->integer('carModelId')->unsigned()->comment('车型表id');
                $table->integer('carLabelId')->unsigned()->comment('标签表id');
            });
        }

        //车型-车行关联表
        if (!Schema::hasTable('carModelCarBelong'))
        {
            Schema::create('carModelCarBelong',function (Blueprint $table)
            {
                $table->increments('id')->unsigned()->comment('主键');
                $table->integer('carModelId')->unsigned()->comment('车辆型号表id');
                $table->integer('carBelongId')->unsigned()->comment('车行表id');
                $table->integer('carNum')->unsigned()->comment('车辆库存');
            });
        }

        //车行表
        if (!Schema::hasTable('carBelong'))
        {
            Schema::create('carBelong',function (Blueprint $table)
            {
                $table->increments('id')->unsigned()->comment('主键');
                $table->string('name',30)->comment('车行名称');
                $table->string('lng',30)->comment('经度');
                $table->string('lat',30)->comment('纬度');
                $table->string('geo',30)->comment('geo');
                $table->integer('cityId')->unsigned()->comment('城市表id');
                $table->string('address')->comment('地址');
                $table->string('tel',30)->comment('座机');
                $table->string('phone',30)->comment('手机');
                $table->string('open',30)->comment('营业时间');
                $table->string('close',30)->comment('打烊时间');
            });
        }













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

        if (!Schema::hasTable('order'))
        {
            Schema::create('order',function (Blueprint $table)
            {
                $table->increments('id')->unsigned()->comment('主键');
                $table->string('orderId',50)->comment('订单号')->index();
                $table->integer('carModelId')->unsigned()->comment('车辆类型表id')->index();
                $table->integer('carBelongId')->unsigned()->comment('车行表id')->index();
                $table->string('orderType',50)->comment('自驾/出行/摩托');
                $table->string('orderStatus',50)->comment('待确认/已确认/用车中/已完成');
                $table->string('account',50)->comment('就是手机号')->index();
                $table->integer('orderPrice')->unsigned()->comment('订单金额');
                $table->string('payWay',50)->comment('钱包/微信');
                $table->string('payment',50)->comment('只交押金/交全款');
                $table->integer('startTime')->unsigned()->comment('开始时间')->index();
                $table->integer('stopTime')->unsigned()->comment('结束时间')->index();
                $table->string('getCarWay',50)->comment('自取/送车');
                $table->string('getCarPlace')->comment('取车地点');
                $table->string('rentPersonName',50)->comment('租车人');
                $table->string('rentPersonPhone',50)->comment('租车电话');
                $table->string('start')->comment('起点');
                $table->string('destination')->comment('终点');
                $table->string('hu')->nullable()->comment('保留字段');
                $table->string('kang')->nullable()->comment('保留字段');
                $table->string('fei')->nullable()->comment('保留字段');
                $table->timestamps();
            });
        }
    }
}
