<?php

namespace App\Http\Controllers\Admin;

use App\Exports\OrderExport;
use App\Http\Models\banner;
use App\Http\Models\bannerAction;
use App\Http\Models\carBelong;
use App\Http\Models\carBrand;
use App\Http\Models\carInfo;
use App\Http\Models\carLabel;
use App\Http\Models\carLicenseType;
use App\Http\Models\carModel;
use App\Http\Models\carModelCarBelong;
use App\Http\Models\carModelLabel;
use App\Http\Models\carType;
use App\Http\Models\chinaArea;
use App\Http\Models\coupon;
use App\Http\Models\order;
use App\Http\Models\purchaseOrder;
use App\Http\Models\refundInfo;
use App\Http\Models\topics;
use App\Http\Models\users;
use App\Http\Service\UploadImg;
use Carbon\Carbon;
use Geohash\GeoHash;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
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

    //注册
    public function reg(Request $request)
    {
        $name=$request->name ?? control::getUuid(8);
        $username=$request->username ?? control::getUuid(8);
        $password=$request->password ?? control::getUuid(8);
        $phone=$request->phone ?? control::getUuid(11);
        $type=$request->type ?? 1;

        DB::table('admin_users')->insert([
            'name'=>$name,
            'username'=>$username,
            'password'=>$password,
            'phone'=>$phone,
            'type'=>$type,
        ]);

        return response()->json($this->createReturn(200,[],'注册成功'));
    }

    //后台用户列表
    public function getAdminUserList(Request $request)
    {
        $pageInfo=$this->offset($request);

        $res=DB::table('admin_users')
            ->limit(last($pageInfo))->offset(head($pageInfo))
            ->get()->toArray();

        $tmp['list']=$res;
        $tmp['total']=DB::table('admin_users')->count();

        return response()->json($this->createReturn(200,$tmp,''));
    }

    //修改后台用户
    public function editUser(Request $request)
    {
        DB::table('admin_users')->where('id',$request->id)->update([
            'name'=>$request->name,
            'username'=>$request->username,
            'password'=>$request->password,
            'phone'=>$request->phone,
            'type'=>$request->type,
        ]);

        return response()->json($this->createReturn(200,[],''));
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

            if (!empty($request->isShow)) $carModelList->where('isShow',$request->isShow);
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

                //添加标签
                $carLabelIdArr=Arr::flatten(carModelLabel::where('carModelId',$oneCar->id)->get(['carLabelId'])->toArray());

                $carLabelInfo=carLabel::whereIn('id',$carLabelIdArr)->get()->toArray();

                $oneCar->carLabelInfo=$carLabelInfo;
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
                'dayDiscount'=>$request->dayDiscount ?? 0,//日租折扣
                'goPrice'=>$request->goPrice ?? 3000,//出行价格
                'goDiscount'=>$request->goDiscount ?? 0,//出行折扣
                'kilPrice'=>$request->kilPrice ?? 0,//每公里价格
                'isShow'=>$request->isShow ?? 1,//
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

    //编辑车
    public function editCar(Request $request)
    {
        $carModelId=$request->carModelId;
        $carImg=$request->carImg;
        $carType=$request->carType;
        $carBrandId=$request->carBrandId;
        $carModel=$request->carModel;
        $dayPrice=$request->dayPrice;
        $dayDiscount=$request->dayDiscount;
        $goPrice=$request->goPrice;
        $goDiscount=$request->goDiscount;
        $damagePrice=$request->damagePrice;
        $forfeitPrice=$request->forfeitPrice;
        $level=$request->level;
        $kilPrice=$request->kilPrice;
        $carBelongArr=json_decode($request->carBelongArr,true);
        $carLabelArr=json_decode($request->carLabelArr,true);
        $carDesc=$request->carDesc;
        $isShow=$request->isShow ?? 1;

        //搜索车型
        $carInfo=carModel::find($carModelId);

        $carInfo->carType=$carType;
        $carInfo->carImg=$carImg;
        $carInfo->carBrandId=$carBrandId;
        $carInfo->carModel=$carModel;
        $carInfo->dayPrice=$dayPrice;
        $carInfo->dayDiscount=$dayDiscount;
        $carInfo->goPrice=$goPrice;
        $carInfo->goDiscount=$goDiscount;
        $carInfo->damagePrice=$damagePrice;
        $carInfo->forfeitPrice=$forfeitPrice;
        $carInfo->level=$level;
        $carInfo->kilPrice=$kilPrice;
        $carInfo->carDesc=$carDesc;
        $carInfo->isShow=$isShow;

        $carInfo->save();

        foreach ($carBelongArr as &$one)
        {
            $one['carModelId']=$carModelId;
        }
        unset($one);

        DB::table('carModelCarBelong')->where('carModelId',$carModelId)->delete();
        DB::table('carModelCarBelong')->insert($carBelongArr);

        foreach ($carLabelArr as &$one)
        {
            $one['carModelId']=$carModelId;
        }
        unset($one);

        DB::table('carModelLabel')->where('carModelId',$carModelId)->delete();
        DB::table('carModelLabel')->insert($carLabelArr);

        return response()->json($this->createReturn(200,[]));
    }

    //删除车 不删了
    public function deleteCar(Request $request)
    {
        $carModelId=$request->carModelId;

        //删除车型
        carModel::where('id',$carModelId)->delete();

        //删除和车行的关联表数据
        carModelCarBelong::where('carModelId',$carModelId)->delete();

        //删除标签表关联数据
        carModelLabel::where('carModelId',$carModelId)->delete();

        //删除订单表
        order::where('carModelId',$carModelId)->delete();

        return response()->json($this->createReturn(200,[]));
    }

    //上架/下架车辆
    public function isShowCar(Request $request)
    {
        $carModelId=$request->carModelId;
        $isShow=$request->isShow;

        $info=carModel::find($carModelId);

        $info->isShow=$isShow;
        $info->save();

        return response()->json($this->createReturn(200,[]));
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

            $tmp=[];
            $tmp['list']=DB::table('coupon')->offset(head($pageInfo))->limit(last($pageInfo))->get()->toArray();
            $tmp['total']=DB::table('coupon')->count();

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

    //删除优惠券
    public function deleteCoupon(Request $request)
    {
        $couponId=$request->couponId;

        $couponInfo=coupon::where(['id'=>$couponId,'isUse'=>0])->first();

        if (empty($couponInfo)) return response()->json($this->createReturn(201,[],'未找到优惠券'));

        $couponInfo->delete();

        return response()->json($this->createReturn(200));
    }

    //创建车行
    public function createCarBelong(Request $request)
    {
        if ($request->getMethod() === 'GET')
        {
            //刚打开页面

            $pageInfo=$this->offset($request);

            $china_area=chinaArea::all()->toArray();
            $tmp=[];
            control::traverseMenu($china_area,$tmp);
            $china_area=$tmp;

            $tmp=[];
            $tmp['list']=DB::table('carBelong')->offset(head($pageInfo))->limit(last($pageInfo))->get()->toArray();
            $tmp['total']=DB::table('carBelong')->count();

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
                'lng'=>$lng,//经度
                'lat'=>$lat,//纬度
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

    //编辑车行
    public function editCarBelong(Request $request)
    {
        $carBelongId=$request->carBelongId;
        $name=$request->name;
        $lng=$request->lng;
        $lat=$request->lat;
        $geo=(new GeoHash())->encode($lat,$lng,12);
        $cityId=$request->cityId;
        $address=$request->address;
        $tel=$request->tel;
        $phone=$request->phone;
        $open=$request->open;
        $close=$request->close;

        $carBelongInfo=carBelong::find($carBelongId);

        $carBelongInfo->name=$name;
        $carBelongInfo->lng=$lng;
        $carBelongInfo->lat=$lat;
        $carBelongInfo->geo=$geo;
        $carBelongInfo->cityId=$cityId;
        $carBelongInfo->address=$address;
        $carBelongInfo->tel=$tel;
        $carBelongInfo->phone=$phone;
        $carBelongInfo->open=$open;
        $carBelongInfo->close=$close;

        $carBelongInfo->save();

        return response()->json($this->createReturn(200,[]));
    }

    //删除车行
    public function deleteCarBelong(Request $request)
    {
        $carBelongId=$request->carBelongId;

        //有关联的车就不能删
        $hasCarArr=carModelCarBelong::where('carBelongId',$carBelongId)->get()->toArray();

        if (!empty($hasCarArr)) return response()->json($this->createReturn(201,$hasCarArr,'车行中还有关联的车'));

        //删除车行
        carBelong::where('id',$carBelongId)->delete();

        return response()->json($this->createReturn(200,[]),'删除车行成功');
    }

    //创建车辆品牌
    public function createCarBrand(Request $request)
    {
        if ($request->getMethod() === 'GET')
        {
            //刚打开页面

            $pageInfo=$this->offset($request);

            $tmp=[];
            $tmp['list']=DB::table('carBrand')->offset(head($pageInfo))->limit(last($pageInfo))->get()->toArray();
            $tmp['total']=DB::table('carBrand')->count();

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

    //编辑车辆品牌
    public function editCarBrand(Request $request)
    {
        $carBrandId=$request->carBrandId;
        $carBrand=$request->carBrand;

        $model=carBrand::find($carBrandId);

        $model->carBrand=$carBrand;

        $model->save();

        return response()->json($this->createReturn(200,[]));
    }

    //删除车辆品牌
    public function deleteCarBrand(Request $request)
    {
        $carBrandId=$request->carBrandId;

        //有关联的车就不能删
        $hasCarArr=carModel::where('carBrandId',$carBrandId)->get()->toArray();

        if (!empty($hasCarArr)) return response()->json($this->createReturn(201,$hasCarArr,'品牌中还有关联的车'));

        //删除品牌
        carBrand::where('id',$carBrandId)->delete();

        return response()->json($this->createReturn(200,[],'删除品牌成功'));
    }

    //创建banner
    public function createBanner(Request $request)
    {
        if ($request->getMethod() === 'GET')
        {
            //刚打开页面

            $pageInfo=$this->offset($request);

            $tmp=[];
            $tmp['list']=DB::table('banner')->offset(head($pageInfo))->limit(last($pageInfo))->get()->toArray();
            $tmp['total']=DB::table('banner')->count();

            $res=[
                'bannerList'=>$tmp
            ];

            return response()->json($this->createReturn(200,$res));

        }else
        {
            //要插入数据了
            $data=[
                'name'=>$request->name ?? Str::random(),//活动名称
                'image'=>$request->image ?? '',//图片地址
                'isShow'=>$request->isShow ?? 1,//是否显示
                'level'=>$request->level ?? mt_rand(1,100),//权重
                'type'=>$request->type ?? 1,//是跳转页面还是公众号文章
                'href'=>$request->href ?? '',//跳转地址
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

    //编辑banner
    public function editBanner(Request $request)
    {
        $bannerId=$request->bannerId;
        $name=$request->name;
        $image=$request->image;
        $isShow=$request->isShow;
        $level=$request->level;
        $type=$request->type;
        $href=$request->href;
        $contents=$request->contents;

        $bannerInfo=banner::find($bannerId);

        $bannerInfo->name=$name;
        $bannerInfo->image=$image;
        $bannerInfo->isShow=$isShow;
        $bannerInfo->level=$level;
        $bannerInfo->type=$type;
        $bannerInfo->href=$href;
        $bannerInfo->contents=$contents;

        $bannerInfo->save();

        return response()->json($this->createReturn(200,[]));
    }

    //删除banner
    public function deleteBanner(Request $request)
    {
        $bannerId=$request->bannerId;

        banner::where('id',$bannerId)->delete();

        return response()->json($this->createReturn(200,[]));
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

    //获取订单
    public function getOrder(Request $request)
    {
        $orderType=$request->orderType;
        if (empty($orderType))
        {
            $orderType=['自驾','出行','摩托'];
        }else
        {
            $orderType=explode(',',$orderType);
        }
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
        $res=order::whereIn('orderType',$orderType);

        if (!empty($cond))
        {
            if (is_numeric($cond))
            {
                //手机号
                $res=$res->where('account',$cond)->whereIn('orderStatus',$orderStatus)
                    ->orderBy(head($orderBy),last($orderBy))
                    ->paginate($pageSize,['*'],'',$page)->toArray();

                //总的数据条数
                $total1=order::where('orderType','自驾')->whereIn('orderStatus',$orderStatus)->where('account',$cond)->count();
                $total2=order::where('orderType','出行')->whereIn('orderStatus',$orderStatus)->where('account',$cond)->count();
                $total3=order::where('orderType','摩托')->whereIn('orderStatus',$orderStatus)->where('account',$cond)->count();

            }else
            {
                //订单号
                $res=$res->where('orderId','like',"%{$cond}%")->whereIn('orderStatus',$orderStatus)
                    ->orderBy(head($orderBy),last($orderBy))
                    ->paginate($pageSize,['*'],'',$page)->toArray();

                //总的数据条数
                $total1=order::where('orderType','自驾')->whereIn('orderStatus',$orderStatus)->where('orderId','like',"%{$cond}%")->count();
                $total2=order::where('orderType','出行')->whereIn('orderStatus',$orderStatus)->where('orderId','like',"%{$cond}%")->count();
                $total3=order::where('orderType','摩托')->whereIn('orderStatus',$orderStatus)->where('orderId','like',"%{$cond}%")->count();
            }

        }else
        {
            $res=$res->whereIn('orderStatus',$orderStatus)
                ->orderBy(head($orderBy),last($orderBy))
                ->paginate($pageSize,['*'],'',$page)->toArray();

            //总的数据条数
            $total1=order::where('orderType','自驾')->whereIn('orderStatus',$orderStatus)->count();
            $total2=order::where('orderType','出行')->whereIn('orderStatus',$orderStatus)->count();
            $total3=order::where('orderType','摩托')->whereIn('orderStatus',$orderStatus)->count();
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

                    //如果退款任务里面有这个orderId，那么还是不可退的，每个订单只能退款一次
                    $check=refundInfo::where(['orderId'=>$one['orderId']])->first();

                    if ($check) $one['refundInfo']['canRefund']=0;

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

            //取车时间
            //$test=$one['takeTime'];
            //$h=$test/60/60;
            //$m=$test - ((int)$h * 60 * 60);
            //$m=$m/60;
            //$one['takeTime']=str_pad((int)$h,2,'0',STR_PAD_LEFT).':'.str_pad((int)$m,2,'0',STR_PAD_LEFT);

            $one['takeTime']=date('H:i',$one['startTime']);
        }
        unset($one);

        $tmp['list']=$res;
        $tmp['total']['自驾']=$total1;
        $tmp['total']['出行']=$total2;
        $tmp['total']['摩托']=$total3;
        $tmp['notifyPhone']=Redis::hget('adminConfig','notifyPhone') ?? 13800138000;

        return response()->json($this->createReturn(200,$tmp,''));
    }

    //创建退款任务
    public function refundOrder(Request $request)
    {
        $orderId=$request->orderId ?? '';
        $refundType=$request->refundType ?? 1;
        $refundPrice=$request->refundPrice ?? 0.01;
        $refundPercent=$request->refundPercent ?? 100;
        $day=$request->day ?? 0;
        $password=$request->password ?? '*#06#';
        $remark=$request->remark;

        $orderInfo=order::where('orderId',$orderId)->first();

        if (!empty($remark))
        {
            $orderInfo->remark=$remark;
        }

        refundInfo::create([
            'phone'=>$orderInfo->account,
            'orderId'=>$orderInfo->orderId,
            'refundId'=>control::getUuid(16),
            'refundType'=>$refundType,
            'refundPercent'=>$refundPercent,
            'refundPrice'=>$refundPrice,
            'day'=>$day,
            'refundTime'=>time() + 86400 * $day,
            'isFinish'=>0,
        ]);

        $orderInfo->save();

        return response()->json($this->createReturn(200,[],'success'));
    }

    //setOrderStatus
    public function setOrderStatus(Request $request)
    {
        $orderId=$request->orderId ?? 1;
        $orderStatus=$request->orderStatus ?? '已完成';
        $takeTime=$request->takeTime ?? '00,00';
        $remark=$request->remark ?? '';

        $time=explode(',',$takeTime);
        $h=(int)head($time);
        $m=(int)last($time);

        $h=$h * 60 * 60;
        $m=$m * 60;

        $orderInfo=order::where('orderId',$orderId)->first();

        $orderInfo->orderStatus=$orderStatus;

        switch ($orderStatus)
        {
            case '已确认':
                $orderInfo->takeTime=$h + $m;
                break;
        }

        if ($remark) $orderInfo->remark=$remark;

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

        $cond=$request->cond ?? '';

        $offset=$this->offset($request);

        if (empty($cond))
        {
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

        }else
        {
            $userInfo=users::where('phone',$cond)->first();

            if (empty($userInfo))
            {
                $userInfo=[];
                $count=0;
            }else
            {
                $userInfo=$userInfo->toArray();

                if (is_numeric($userInfo['oftenCity']))
                {
                    $userInfo['oftenCity']=chinaArea::find($userInfo['oftenCity']);
                }

                $userInfo=[$userInfo];

                $count=1;
            }
        }

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

    //获取用户列表
    public function getUserList(Request $request)
    {
        $cond=$request->cond ?? '';

        $offset=$this->offset($request);

        if (empty($cond))
        {
            $userInfo=users::where('phone','like',"1%");
            $total=users::where('phone','like',"1%")->count();
        }else
        {
            $userInfo=users::where('phone','like',"%{$cond}%");
            $total=users::where('phone','like',"%{$cond}%")->count();
        }

        $userInfo=$userInfo->orderBy('created_at','desc')
            ->offset(head($offset))->limit(last($offset))
            ->get()->toArray();

        foreach ($userInfo as &$one)
        {
            //这个用户订了几次车
            $one['orderNum']=order::where('account',$one['phone'])->count();

            //真实密码
            $one['realPassword']=control::aesDecode($one['password'],$one['phone']);

            //累计充值
            $res=purchaseOrder::where('orderStatus','支付成功')->where('phone',$one['phone'])
                ->select(DB::raw('sum(purchaseMoney) as money'))
                ->get()->toArray();

            $one['purchaseMoney']=(int)current(Arr::flatten($res));
        }
        unset($one);

        $tmp['list']=$userInfo;
        $tmp['total']=$total;

        return response()->json($this->createReturn(200,$tmp));
    }

    //修改用户备注
    public function setRemarkUser(Request $request)
    {
        $phone=$request->phone ?? 13800138000;

        $remark=$request->remark ?? '无';

        $userInfo=users::where('phone',$phone)->first();

        $userInfo->remark=$remark;

        $userInfo->save();

        return response()->json($this->createReturn(200,[]));
    }

    //修改用户备注
    public function setRemarkOrder(Request $request)
    {
        $orderId=$request->orderId ?? 123123;

        $remark=$request->remark ?? '无';

        $orderInfo=order::where('orderId',$orderId)->first();

        $orderInfo->remark=$remark;

        $orderInfo->save();

        return response()->json($this->createReturn(200,[]));
    }

    //充值页面
    public function getPurchaseList(Request $request)
    {
        //当日充值次数=============================
        $dayCount=purchaseOrder::where([
            'year'=>date('Y'),
            'month'=>date('m'),
            'day'=>date('d'),
            'orderStatus'=>'支付成功'
        ])->count();

        //累计充值次数=======================================================================================
        //看看是年的还是月的还是日的
        $tmp=purchaseOrder::where('orderStatus','支付成功');

        $tmp=$tmp->select(DB::raw("orderStatus,count(1) as num"))->get()->toArray();

        $totalCount=current($tmp)['num'];

        //当日充值金额=============================
        $dayMoney=purchaseOrder::where([
            'year'=>date('Y'),
            'month'=>date('m'),
            'day'=>date('d'),
            'orderStatus'=>'支付成功'
        ])->select(DB::raw('sum(purchaseMoney) as money'))->get()->toArray();

        $dayMoney=(int)current(Arr::flatten($dayMoney));

        //累计充值金额=======================================================================================
        //看看是年的还是月的还是日的
        $tmp=purchaseOrder::where('orderStatus','支付成功');

        $tmp=$tmp->select(DB::raw("sum(purchaseMoney) as money"))->get()->toArray();

        $totalMoney=(int)current($tmp)['money'];

        //充值金额折线图===========================
        $yearChartsLine=[];//最近几年
        $monthChartsLine=[];//最近几月
        $dayChartsLine=[];//最近几日

        $lineYear=$request->lineYear ?? 20;
        $lineMonth=$request->lineMonth ?? 20;
        $lineDay=$request->lineDay ?? 20;

        //最近几年=======================================================================================
        for ($i=$lineYear;$i--;)
        {
            $yearChartsLine[date('Y') - $i]=0;
        }

        $tmp=purchaseOrder::whereIn('year',array_keys($yearChartsLine))->where('orderStatus','支付成功')
            ->groupBy('year')->select(DB::raw('year,sum(purchaseMoney) as money'))->get()->toArray();

        foreach ($tmp as $one)
        {
            $yearChartsLine[$one['year']]=$one['money'];
        }

        //最近几月=======================================================================================
        for ($i=$lineMonth;$i--;)
        {
            $monthChartsLine[Carbon::now()->subMonths($i)->format('Y-m')]=0;
        }

        foreach (array_keys($monthChartsLine) as $one)
        {
            $res=purchaseOrder::where('orderStatus','支付成功')
                ->where('year',head(explode('-',$one)))
                ->where('month',last(explode('-',$one)))
                ->select(DB::raw('sum(purchaseMoney) as money'))
                ->get()->toArray();

            $monthChartsLine[$one]=(int)current(Arr::flatten($res));
        }

        //最近几天=======================================================================================
        for ($i=$lineDay;$i--;)
        {
            $dayChartsLine[Carbon::now()->subDays($i)->format('Y-m-d')]=0;
        }

        foreach (array_keys($dayChartsLine) as $one)
        {
            $res=purchaseOrder::where('orderStatus','支付成功')
                ->where('year',head(explode('-',$one)))
                ->where('month',explode('-',$one)[1])
                ->where('day',last(explode('-',$one)))
                ->select(DB::raw('sum(purchaseMoney) as money'))
                ->get()->toArray();

            $dayChartsLine[$one]=(int)current(Arr::flatten($res));
        }

        $offset=$this->offset($request);

        $orderBy=$request->orderBy ?? 'created_at,desc';
        $orderBy=explode(',',$orderBy);

        if (empty($request->cond))
        {
            $list=purchaseOrder::orderBy(head($orderBy),last($orderBy))->offset(head($offset))->limit(last($offset))
                ->get([
                    'id','phone','orderId','orderStatus','purchaseMoney','created_at','NotifyInfo'
                ])->toArray();

            $total=purchaseOrder::count();

        }else
        {
            $cond=$request->cond;

            $list=purchaseOrder::where(function ($query) use ($cond) {
                $query->where('phone',$cond)->orWhere('orderId','like',"%{$cond}%");
            })->orderBy(head($orderBy),last($orderBy))->offset(head($offset))->limit(last($offset))
                ->get([
                    'id','phone','orderId','orderStatus','purchaseMoney','created_at','NotifyInfo'
                ])->toArray();

            $total=purchaseOrder::where(function ($query) use ($cond) {
                $query->where('phone',$cond)->orWhere('orderId','like',"%{$cond}%");
            })->count();
        }

        //to一个交易单号
        foreach ($list as &$one)
        {
            $notify=json_decode($one['NotifyInfo'],true);

            $one['toOrderId']=$notify['transaction_id'] ?? null;
        }
        unset($one);

        return response()->json($this->createReturn(200,[
            'list'=>$list,
            'total'=>$total,
            'dayCount'=>$dayCount,
            'totalCount'=>$totalCount,
            'dayMoney'=>$dayMoney,
            'totalMoney'=>$totalMoney,
            'yearChartsLine'=>$yearChartsLine,
            'monthChartsLine'=>$monthChartsLine,
            'daysChartsLine'=>$dayChartsLine,
        ]));
    }

    //首页
    public function index(Request $request)
    {
        $lineYear=$request->lineYear ?? 3;
        $lineMonth=$request->lineMonth ?? 10;
        $lineDay=$request->lineDay ?? 100;

        //待确认等待======================================================
        $readyToConfirm=order::where('orderStatus','待确认')->count();

        //等审核认证======================================================
        $readyToCheck=users::where(function ($query) {
            $query->orWhere('isCarLicensePass','<>',99)->orWhere('isMotorLicensePass','<>',99)
                ->orWhere('isIdCardPass','<>',99)->orWhere('idCardImg','<>',99)->orWhere('isPassportPass','<>',99);
        })->count();

        //今日订单========================================================
        $start=Carbon::now()->startOfDay()->format('Y-m-d H:i:s');
        $stop=Carbon::now()->endOfDay()->format('Y-m-d H:i:s');
        $todayOrder=order::whereBetween('created_at',[$start,$stop])->count();

        //今日充值========================================================
        $start=Carbon::now()->startOfDay()->format('Y-m-d H:i:s');
        $stop=Carbon::now()->endOfDay()->format('Y-m-d H:i:s');
        $todayPurchase=purchaseOrder::whereBetween('created_at',[$start,$stop])->count();

        //最近几年=======================================================================================
        for ($i=$lineYear;$i--;)
        {
            $yearChartsLine[date('Y') - $i]=0;
        }

        $tmp=order::whereIn('year',array_keys($yearChartsLine))->whereNotIn('orderStatus',['待支付','已退单'])
            ->groupBy('year')->select(DB::raw('year,count(1) as num'))->get()->toArray();

        foreach ($tmp as $one)
        {
            $yearChartsLine[$one['year']]=$one['num'];
        }

        //最近几月=======================================================================================
        for ($i=$lineMonth;$i--;)
        {
            $monthChartsLine[Carbon::now()->subMonths($i)->format('Y-m')]=0;
        }

        foreach (array_keys($monthChartsLine) as $one)
        {
            $res=order::whereNotIn('orderStatus',['待支付','已退单'])
                ->where('year',head(explode('-',$one)))
                ->where('month',last(explode('-',$one)))
                ->count();

            $monthChartsLine[$one]=(int)$res;
        }

        //最近几天=======================================================================================
        for ($i=$lineDay;$i--;)
        {
            $dayChartsLine[Carbon::now()->subDays($i)->format('Y-m-d')]=0;
        }

        foreach (array_keys($dayChartsLine) as $one)
        {
            $res=purchaseOrder::whereNotIn('orderStatus',['待支付','已退单'])
                ->where('year',head(explode('-',$one)))
                ->where('month',explode('-',$one)[1])
                ->where('day',last(explode('-',$one)))
                ->count();

            $dayChartsLine[$one]=(int)$res;
        }

        //pv uv只支持以天取
        for ($i=$lineDay;$i--;)
        {
            $key=Carbon::now()->subDays($i)->format('Ymd');
            $pv[control::insertSomething($key,[4,6])]=(int)Redis::hget('pv',$key);
            $uv[control::insertSomething($key,[4,6])]=(int)Redis::hget('uv',$key);
        }

        return response()->json($this->createReturn(200,[
            'readyToConfirm'=>$readyToConfirm,
            'readyToCheck'=>$readyToCheck,
            'todayOrder'=>$todayOrder,
            'todayPurchase'=>$todayPurchase,
            'yearChartsLine'=>$yearChartsLine,
            'monthChartsLine'=>$monthChartsLine,
            'dayChartsLine'=>$dayChartsLine,
            'pv'=>$pv,
            'uv'=>$uv
        ]));
    }

    //修改通知手机号
    public function editNotifyPhone(Request $request)
    {
        $phone=$request->phone ?? 13800138000;

        Redis::hset('adminConfig','notifyPhone',$phone);

        return response()->json($this->createReturn(200,[]));
    }

    //getOrderNumByStatus
    public function getOrderNumByStatus(Request $request)
    {
        $orderStatus=$request->orderStatus ?? '待支付';

        $count=order::where('orderStatus',$orderStatus)->count();

        return response()->json($this->createReturn(200,['num'=>$count]));
    }

    //setRentPersonInfo
    public function setRentPersonInfo(Request $request)
    {
        $orderId=$request->orderId;
        $name=$request->rentPersonName ?? '飞';
        $phone=$request->rentPersonPhone ?? '13800138000';

        $info=order::where('orderId',$orderId)->first();

        $info->rentPersonName=$name;
        $info->rentPersonPhone=$phone;

        $info->save();

        return response()->json($this->createReturn(200,[]));
    }

    //获取标签label
    public function createLabel(Request $request)
    {
        if ($request->getMethod() === 'GET')
        {
            //刚打开页面

            $pageInfo=$this->offset($request);

            $tmp=[];
            $tmp['list']=DB::table('carLabel')->offset(head($pageInfo))->limit(last($pageInfo))->get()->toArray();
            $tmp['total']=DB::table('carLabel')->count();

            $res=[
                'carLabelList'=>$tmp
            ];

            return response()->json($this->createReturn(200,$res));

        }else
        {
            //要插入数据了
            $data=[
                'label'=>$request->label ?? Str::random(),
            ];

            try
            {
                $code=200;

                carLabel::create($data);

            }catch (\Exception $e)
            {
                $code=210;
            }

            return response()->json($this->createReturn($code));
        }
    }

    //编辑label
    public function editLabel(Request $request)
    {
        $labelId=$request->labelId ?? 1;

        $label=$request->label ?? '无';

        $labelInfo=carLabel::find($labelId);

        $labelInfo->label=$label;

        $labelInfo->save();

        return response()->json($this->createReturn(200,[]));
    }

    //删除label
    public function deleteLabel(Request $request)
    {
        $labelId=$request->labelId;

        //删除label
        carLabel::where('id',$labelId)->delete();

        //删除关联表
        carModelLabel::where('carLabelId',$labelId)->delete();

        return response()->json($this->createReturn(200,[]));
    }

    //创建 安心托管
    public function createAXTG(Request $request)
    {
        if ($request->getMethod() === 'GET')
        {
            //刚打开页面

            $pageInfo=$this->offset($request);

            $tmp=[];
            $tmp['list']=DB::table('topics')->where('topicBelong',1)
                ->offset(head($pageInfo))
                ->limit(last($pageInfo))
                ->get()->toArray();
            $tmp['total']=DB::table('topics')->where('topicBelong',1)->count();

            $res=[
                'AXTGList'=>$tmp,
                'coverName'=>Redis::hget('globalConf','module4CoverName'),
                'coverWord'=>json_decode(Redis::hget('globalConf','module4CoverWord'),true),
                'coverImage'=>Redis::hget('globalConf','module4'),
            ];

            return response()->json($this->createReturn(200,$res));

        }else
        {
            //要插入数据了
            $data=[
                'topicBelong'=>1,
                'name'=>$request->name ?? Str::random(),//活动名称
                'image'=>$request->image ?? '',//图片地址
                'isShow'=>$request->isShow ?? 1,//是否显示
                'level'=>$request->level ?? mt_rand(1,100),//权重
                'type'=>$request->type ?? 1,//是跳转页面还是公众号文章
                'href'=>$request->href ?? '',//跳转地址
                'contents'=>$request->contents ?? '空',//富文本
            ];

            Redis::hset('globalConf','module4CoverName',$request->coverName);
            Redis::hset('globalConf','module4CoverWord',json_encode(explode(',',$request->coverWord)));
            Redis::hset('globalConf','module4',$request->coverImage);

            try
            {
                $code=200;

                topics::create($data);

            }catch (\Exception $e)
            {
                $code=210;
            }

            return response()->json($this->createReturn($code));
        }
    }

    //编辑 安心托管
    public function editAXTG(Request $request)
    {
        $topicId=$request->topicId;
        $name=$request->name;
        $image=$request->image;
        $isShow=$request->isShow;
        $level=$request->level;
        $type=$request->type;
        $href=$request->href;
        $contents=$request->contents;

        $topicInfo=topics::find($topicId);

        $topicInfo->name=$name;
        $topicInfo->image=$image;
        $topicInfo->isShow=$isShow;
        $topicInfo->level=$level;
        $topicInfo->type=$type;
        $topicInfo->href=$href;
        $topicInfo->contents=$contents;

        $topicInfo->save();

        Redis::hset('globalConf','module4CoverName',$request->coverName);
        Redis::hset('globalConf','module4CoverWord',json_encode(explode(',',$request->coverWord)));
        Redis::hset('globalConf','module4',$request->coverImage);

        return response()->json($this->createReturn(200,[]));
    }

    //删除 安心托管
    public function deleteAXTG(Request $request)
    {
        $topicId=$request->topicId;

        topics::where('id',$topicId)->delete();

        return response()->json($this->createReturn(200,[]));
    }

    //创建 精致车源
    public function createJZCY(Request $request)
    {
        if ($request->getMethod() === 'GET')
        {
            //刚打开页面

            $pageInfo=$this->offset($request);

            $tmp=[];
            $tmp['list']=DB::table('topics')->where('topicBelong',2)
                ->offset(head($pageInfo))
                ->limit(last($pageInfo))
                ->get()->toArray();
            $tmp['total']=DB::table('topics')->where('topicBelong',2)->count();

            $res=[
                'JZCYList'=>$tmp,
                'coverName'=>Redis::hget('globalConf','module5CoverName'),
                'coverWord'=>json_decode(Redis::hget('globalConf','module5CoverWord'),true),
                'coverImage'=>Redis::hget('globalConf','module5'),
            ];

            return response()->json($this->createReturn(200,$res));

        }else
        {
            //要插入数据了
            $data=[
                'topicBelong'=>2,
                'name'=>$request->name ?? Str::random(),//活动名称
                'image'=>$request->image ?? '',//图片地址
                'isShow'=>$request->isShow ?? 1,//是否显示
                'level'=>$request->level ?? mt_rand(1,100),//权重
                'type'=>$request->type ?? 1,//是跳转页面还是公众号文章
                'href'=>$request->href ?? '',//跳转地址
                'contents'=>$request->contents ?? '空',//富文本
            ];

            Redis::hset('globalConf','module5CoverName',$request->coverName);
            Redis::hset('globalConf','module5CoverWord',json_encode(explode(',',$request->coverWord)));
            Redis::hset('globalConf','module5',$request->coverImage);

            try
            {
                $code=200;

                topics::create($data);

            }catch (\Exception $e)
            {
                $code=210;
            }

            return response()->json($this->createReturn($code));
        }
    }

    //编辑 精致车源
    public function editJZCY(Request $request)
    {
        $topicId=$request->topicId;
        $name=$request->name;
        $image=$request->image;
        $isShow=$request->isShow;
        $level=$request->level;
        $type=$request->type;
        $href=$request->href;
        $contents=$request->contents;

        $topicInfo=topics::find($topicId);

        $topicInfo->name=$name;
        $topicInfo->image=$image;
        $topicInfo->isShow=$isShow;
        $topicInfo->level=$level;
        $topicInfo->type=$type;
        $topicInfo->href=$href;
        $topicInfo->contents=$contents;

        $topicInfo->save();

        Redis::hset('globalConf','module5CoverName',$request->coverName);
        Redis::hset('globalConf','module5CoverWord',json_encode(explode(',',$request->coverWord)));
        Redis::hset('globalConf','module5',$request->coverImage);

        return response()->json($this->createReturn(200,[]));
    }

    //删除 精致车源
    public function deleteJZCY(Request $request)
    {
        $topicId=$request->topicId;

        topics::where('id',$topicId)->delete();

        return response()->json($this->createReturn(200,[]));
    }

    //创建 超值长租
    public function createCZCZ(Request $request)
    {
        if ($request->getMethod() === 'GET')
        {
            //刚打开页面

            $pageInfo=$this->offset($request);

            $tmp=[];
            $tmp['list']=DB::table('topics')->where('topicBelong',3)
                ->offset(head($pageInfo))
                ->limit(last($pageInfo))
                ->get()->toArray();
            $tmp['total']=DB::table('topics')->where('topicBelong',3)->count();

            $res=[
                'CZCZList'=>$tmp,
                'coverName'=>Redis::hget('globalConf','module6CoverName'),
                'coverWord'=>json_decode(Redis::hget('globalConf','module6CoverWord'),true),
                'coverImage'=>Redis::hget('globalConf','module6'),
            ];

            return response()->json($this->createReturn(200,$res));

        }else
        {
            //要插入数据了
            $data=[
                'topicBelong'=>3,
                'name'=>$request->name ?? Str::random(),//活动名称
                'image'=>$request->image ?? '',//图片地址
                'isShow'=>$request->isShow ?? 1,//是否显示
                'level'=>$request->level ?? mt_rand(1,100),//权重
                'type'=>$request->type ?? 1,//是跳转页面还是公众号文章
                'href'=>$request->href ?? '',//跳转地址
                'contents'=>$request->contents ?? '空',//富文本
            ];

            Redis::hset('globalConf','module6CoverName',$request->coverName);
            Redis::hset('globalConf','module6CoverWord',json_encode(explode(',',$request->coverWord)));
            Redis::hset('globalConf','module6',$request->coverImage);

            try
            {
                $code=200;

                topics::create($data);

            }catch (\Exception $e)
            {
                $code=210;
            }

            return response()->json($this->createReturn($code));
        }
    }

    //编辑 超值长租
    public function editCZCZ(Request $request)
    {
        $topicId=$request->topicId;
        $name=$request->name;
        $image=$request->image;
        $isShow=$request->isShow;
        $level=$request->level;
        $type=$request->type;
        $href=$request->href;
        $contents=$request->contents;

        $topicInfo=topics::find($topicId);

        $topicInfo->name=$name;
        $topicInfo->image=$image;
        $topicInfo->isShow=$isShow;
        $topicInfo->level=$level;
        $topicInfo->type=$type;
        $topicInfo->href=$href;
        $topicInfo->contents=$contents;

        $topicInfo->save();

        Redis::hset('globalConf','module6CoverName',$request->coverName);
        Redis::hset('globalConf','module6CoverWord',json_encode(explode(',',$request->coverWord)));
        Redis::hset('globalConf','module6',$request->coverImage);

        return response()->json($this->createReturn(200,[]));
    }

    //删除 超值长租
    public function deleteCZCZ(Request $request)
    {
        $topicId=$request->topicId;

        topics::where('id',$topicId)->delete();

        return response()->json($this->createReturn(200,[]));
    }

    //导出订单
    public function exportOrder(Request $request)
    {
        $carBelongId=$request->carBelongId ?? '';
        $orderType=$request->orderType ?? '';
        $orderStatus=$request->orderStatus ?? '';

        Redis::set('exportOrder',json_encode([
            'carBelongId'=>$carBelongId,
            'orderType'=>$orderType,
            'orderStatus'=>$orderStatus,
        ]));

        $orderExport=new OrderExport([
            'carBelongId'=>$carBelongId,
            'orderType'=>$orderType,
            'orderStatus'=>$orderStatus,
        ]);

        return $orderExport->exec();
    }

    //修改手机号
    public function servicePhone(Request $request)
    {
        $phone=$request->phone ?? '4008-517-517';

        Redis::hset('globalConf','tel',$phone);

        return response()->json($this->createReturn(200,[]));
    }

    //修改车辆关联的品牌
    public function carRelationWithBrand(Request $request)
    {
        $carModelId=$request->carModelId;
        $carBrandId=$request->carBrandId;

        $info=carModel::where('id',$carModelId)->first();
        $info->carBrandId=$carBrandId;
        $info->save();

        return response()->json($this->createReturn(200,[]));
    }

    //修改车辆关联的车行
    public function carRelationWithBelong(Request $request)
    {
        $carModelId=$request->carModelId;
        $carBelongIdOld=$request->carBelongIdOld;
        $carBelongIdNew=$request->carBelongIdNew;
        $carNum=$request->carNum;

        carModelCarBelong::updateOrCreate(['carModelId'=>$carModelId,'carBelongId'=>$carBelongIdNew],['carNum'=>$carNum]);

        carModelCarBelong::where(['carModelId'=>$carModelId,'carBelongId'=>$carBelongIdOld])->delete();

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
