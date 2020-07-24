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
use App\Http\Models\order;
use App\Http\Models\refundInfo;
use App\Http\Models\users;
use App\Http\Service\UploadImg;
use Geohash\GeoHash;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
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

            //计算一辆车，在哪个车行有多少库存
            foreach ($tmp['list'] as &$oneCar)
            {
                $rel=carModelCarBelong::where('carModelId',$oneCar->id)->get()->toArray();

                foreach ($rel as &$one)
                {
                    $one['carBelong']=carBelong::where('id',$one['carBelongId'])->first()->toArray();
                }
                unset($one);

                $oneCar->carBelongInfo=$rel;
            }
            unset($oneCar);

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
                'name'=>$request->name ?? Str::random(),//活动名称
                'image'=>$request->image ?? Str::random(),//图片地址
                'isShow'=>$request->isShow ?? 1,//是否显示
                'level'=>$request->level ?? mt_rand(1,100),//权重
                'type'=>$request->type ?? 1,//是跳转页面还是公众号文章
                'href'=>$request->href ?? Str::random(),//跳转地址
                'contents'=>$request->contents ?? '空',//富文本
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

    //获取订单
    public function getOrder(Request $request)
    {
        $orderType=$request->orderType ?? '自驾';
        $orderStatus=$request->orderStatus;
        if (empty($orderStatus))
        {
            $orderStatus=['待支付','待确认','已确认','用车中','已完成','已退单'];
        }else
        {
            $orderStatus=[$orderStatus];
        }
        $orderBy=$request->orderBy ?? 'created_at,desc';
        $cond=$request->cond ?? '';
        $orderBy=explode(',',$orderBy);
        $page=$request->page ?? 1;
        $pageSize=$request->pageSize ?? 10;

        //当前查看的orderType
        $res=order::where('orderType',$orderType);

        if (!empty($cond))
        {
            if (is_numeric($cond))
            {
                //手机号
                $res=$res->where('account',$cond)->whereIn('orderStatus',$orderStatus)
                    ->orderBy(head($orderBy),last($orderBy))
                    ->paginate($pageSize,['*'],'',$page)->toArray();

                //总的数据条数
                $total1=order::where('orderType','自驾')->where('account',$cond)->count();
                $total2=order::where('orderType','出行')->where('account',$cond)->count();
                $total3=order::where('orderType','摩托')->where('account',$cond)->count();

            }else
            {
                //订单号
                $res=$res->where('orderId','like',"%{$cond}%")->whereIn('orderStatus',$orderStatus)
                    ->orderBy(head($orderBy),last($orderBy))
                    ->paginate($pageSize,['*'],'',$page)->toArray();

                //总的数据条数
                $total1=order::where('orderType','自驾')->where('orderId','like',"%{$cond}%")->count();
                $total2=order::where('orderType','出行')->where('orderId','like',"%{$cond}%")->count();
                $total3=order::where('orderType','摩托')->where('orderId','like',"%{$cond}%")->count();
            }

        }else
        {
            $res=$res->whereIn('orderStatus',$orderStatus)
                ->orderBy(head($orderBy),last($orderBy))
                ->paginate($pageSize,['*'],'',$page)->toArray();

            //总的数据条数
            $total1=order::where('orderType','自驾')->count();
            $total2=order::where('orderType','出行')->count();
            $total3=order::where('orderType','摩托')->count();
        }

        $res=$res['data'];

        foreach ($res as &$one)
        {
            //补全优惠券信息
            $one['coupon1']=coupon::where('id',$one['coupon1'])->first();
            $one['coupon2']=coupon::where('id',$one['coupon2'])->first();
            $one['coupon3']=coupon::where('id',$one['coupon3'])->first();

            //补全车辆型号
            //补全车辆品牌
            $carModel=carModel::where('id',$one['carModelId'])->first();
            $carBrand=carBrand::where('id',$carModel->carBrandId)->first();

            $carModel ? $one['carModel']=$carModel : $one['carModel']=null;
            $carBrand ? $one['carBrand']=$carBrand : $one['carBrand']=null;

            //补全用户信息
            $userInfo=users::where('phone',$one['account'])->first();

            $userInfo ? $one['userInfo']=$userInfo : $one['userInfo']=null;

            //补全退款信息
            $one['refundInfo']=[];
            $damageRefund=null;
            $forfeitRefund=null;

            //先确定订单可不可退，待支付的订单不可退
            if (in_array($one['orderStatus'],['待支付','已退单']))
            {
                $one['refundInfo']['canRefund']=0;

            }else
            {
                //如果可退
                $one['refundInfo']['canRefund']=1;

                //$payment是 全款 说明有车损押金和违章押金 或者 违章押金 说明只有违章押金

                if ($one['payment']==='全款')
                {
                    //车损退了多少
                    $damageRefund=refundInfo::where(['orderId'=>$one['orderId'],'refundType'=>2])
                        ->select(DB::raw('sum(refundPrice) as refundPrice'))->get()->toArray();
                    $damageRefund=(int)head(Arr::flatten($damageRefund));

                    //违章退了多少
                    $forfeitRefund=refundInfo::where(['orderId'=>$one['orderId'],'refundType'=>3])
                        ->select(DB::raw('sum(refundPrice) as refundPrice'))->get()->toArray();
                    $forfeitRefund=(int)head(Arr::flatten($forfeitRefund));

                    $one['refundInfo']['damageRefund']=$damageRefund;
                    $one['refundInfo']['forfeitRefund']=$forfeitRefund;

                }elseif ($one['payment']==='违章押金')
                {
                    //违章退了多少
                    $forfeitRefund=refundInfo::where(['orderId'=>$one['orderId'],'refundType'=>3])
                        ->select(DB::raw('sum(refundPrice) as refundPrice'))->get()->toArray();
                    $forfeitRefund=(int)head(Arr::flatten($forfeitRefund));

                    $one['refundInfo']['forfeitRefund']=$forfeitRefund;

                }else
                {
                    continue;
                }
            }

            //押金状态
            if ($one['stopTime'] > time())
            {
                $status='锁定';
                $day=0;
            }else
            {
                $status='待退';
                $day=0;
                //看看退违章的时间
                $info=refundInfo::where(['orderId'=>$one['orderId'],'refundType'=>3])
                    ->orderBy('created_at','desc')->first();

                if (empty($info))
                {
                    //没有退单记录
                    $day=365;
                }else
                {
                    //有退单记录
                    //先看是不是已经退了
                    if ($info->isFinish==1)
                    {
                        $status='已退';
                    }else
                    {
                        $day=($info->refundTime - time()) / 86400;
                        $day=(int)$day;
                    }

                }
            }

            $one['forfeitStatus']['status']=$status;
            $one['forfeitStatus']['day']=$day;
        }
        unset($one);

        $tmp['list']=$res;
        $tmp['total']['自驾']=$total1;
        $tmp['total']['出行']=$total2;
        $tmp['total']['摩托']=$total3;

        return response()->json($this->createReturn(200,$tmp,''));
    }

    //创建退款任务
    public function refundOrder(Request $request)
    {
        $orderId=$request->orderId ?? '';
        $refundType=$request->refundType ?? 1;
        $refundPrice=$request->refundPrice ?? 0.01;
        $day=$request->day ?? 1;
        $password=$request->password ?? '*#06#';

        $orderInfo=order::where('orderId',$orderId)->first();

        refundInfo::create([
            'phone'=>$orderInfo->account,
            'orderId'=>$orderInfo->orderId,
            'refundId'=>control::getUuid(16),
            'refundType'=>$refundType,
            'refundPrice'=>$refundPrice,
            'day'=>$day,
            'refundTime'=>time() + 86400 * $day,
            'isFinish'=>0,
        ]);

        return response()->json($this->createReturn(200,[],'success'));
    }

    //设置订单为已完成状态
    public function setOrderStatus(Request $request)
    {
        $orderId=$request->orderId ?? 1;

        $orderInfo=order::where('orderId',$orderId)->first();

        $orderInfo->orderStatus='已完成';

        $orderInfo->save();

        return response()->json($this->createReturn(200,[]));
    }

    //修改订单中的违章押金金额
    public function setForfeitPriceOrder(Request $request)
    {
        $orderId=$request->orderId ?? 1;
        $forfeitPrice=$request->forfeitPrice ?? 2000;

        $orderInfo=order::where('orderId',$orderId)->first();

        $orderInfo->forfeitPrice=$forfeitPrice;

        $orderInfo->save();

        return response()->json($this->createReturn(200,[]));
    }

    //审核证件列表
    public function getLicense(Request $request)
    {
        $licenseType=$request->licenseType ?? 1;

        $offset=$this->offset($request);

        $userInfo=users::where('isCarLicensePass',$licenseType)
            ->orWhere('isMotorLicensePass',$licenseType)
            ->orWhere('isIdCardPass',$licenseType)
            ->orWhere('isPassportPass',$licenseType)
            ->orderBy('updated_at')->offset(head($offset))->limit(last($offset))->get()->toArray();

        $count=users::where('isCarLicensePass',$licenseType)
            ->orWhere('isMotorLicensePass',$licenseType)
            ->orWhere('isIdCardPass',$licenseType)
            ->orWhere('isPassportPass',$licenseType)->count();

        foreach ($userInfo as &$one)
        {
            if (is_numeric($one['oftenCity']))
            {
                $one['oftenCity']=chinaArea::find($one['oftenCity']);
            }
        }
        unset($one);

        $tmp['list']=$userInfo;
        $tmp['total']=$count;

        return response()->json($this->createReturn(200,$tmp));
    }

    //修改证件审核状态
    public function setLicenseStatus(Request $request)
    {
        $phone=$request->phone ?? 13800138000;

        $isCarLicensePass=$request->isCarLicensePass ?? 2;
        $isMotorLicensePass=$request->isMotorLicensePass ?? 2;
        $isIdCardPass=$request->isIdCardPass ?? 2;
        $isPassportPass=$request->isPassportPass ?? 2;

        $reason=$request->reason ?? '';

        $userInfo=users::where('phone',$phone)->first();

        $userInfo->isCarLicensePass=$isCarLicensePass;
        $userInfo->isMotorLicensePass=$isMotorLicensePass;
        $userInfo->isIdCardPass=$isIdCardPass;
        $userInfo->isPassportPass=$isPassportPass;

        $userInfo->reason=$reason;

        $userInfo->save();

        return response()->json($this->createReturn(200,[]));
    }







    private function createTable()
    {
        if (!Schema::hasTable('refundInfo'))
        {
            Schema::create('refundInfo',function (Blueprint $table)
            {
                $table->bigInteger('id')->autoIncrement()->unsigned()->comment('主键');
                $table->string('phone',50)->comment('手机号')->index();
                $table->string('orderId',50)->comment('订单号')->index();
                $table->string('refundId',50)->comment('退款单号');
                $table->tinyInteger('refundType')->unsigned()->comment('1是退全款，2是车损押金，3是违章押金');
                $table->decimal('refundPrice',10,2)->unsigned()->comment('退款金额');
                $table->tinyInteger('day')->unsigned()->comment('延迟几天退款');
                $table->integer('refundTime')->unsigned()->comment('退款时间')->index();
                $table->tinyInteger('isFinish')->unsigned()->comment('是否退完');
                $table->timestamps();
            });
        }
    }
}
