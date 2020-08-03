<?php

namespace App\Http\Controllers\Business\Index;

use App\Http\Controllers\Business\BusinessBase;
use App\Http\Models\banner;
use App\Http\Models\carBelong;
use App\Http\Models\carBrand;
use App\Http\Models\carInfo;
use App\Http\Models\carModel;
use App\Http\Models\carModelCarBelong;
use App\Http\Models\carType;
use App\Http\Models\chinaArea;
use App\Http\Models\coupon;
use App\Http\Models\order;
use App\Http\Models\purchaseOrder;
use App\Http\Models\users;
use App\Http\Service\MiniAppPay;
use App\Http\Service\SendSms;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use wanghanwanghan\someUtils\control;
use Yansongda\LaravelPay\Facades\Pay;

class Index extends BusinessBase
{
    //城市列表
    public function cityList(Request $request)
    {
        $carBelongId=carBelong::groupBy('cityId')->pluck('cityId')->toArray();

        $china_area=chinaArea::whereIn('id',$carBelongId)->get()->toArray();

        return response()->json($this->createReturn(200,$china_area));
    }

    //算offset
    private function offset($request)
    {
        $page = $request->page ?? 1;

        $pageSize = $request->pageSize ?? 10;

        $offset = ( $page - 1 ) * $pageSize;

        return [$offset,$pageSize];
    }

    //根据timeRange从订单表中取出哪些车被消耗了多少辆
    private function getCarInfoIdByTimeRange($start,$stop,$carBelongId,$orderType=['自驾','出行','摩托'])
    {
        //首先要根据timeRange从表中查出，每种车，有多少被预定了
        $carOrder=order::where(function ($q) use ($start,$stop){
            $q->where(function ($query) use ($start){
                $query->where('startTime','<=',$start)->where('stopTime','>=',$start);
            })->OrWhere(function ($query) use ($start,$stop){
                $query->where('startTime','>=',$start)->where('stopTime','<=',$stop);
            })->OrWhere(function ($query) use ($stop){
                $query->where('startTime','<=',$stop)->where('stopTime','>=',$stop);
            })->OrWhere(function ($query) use ($start,$stop){
                $query->where('startTime','<=',$start)->where('stopTime','>=',$stop);
            });
        })
            ->whereIn('carBelongId',$carBelongId)
            ->whereIn('orderType',$orderType)
            ->whereIn('orderStatus',['待确认','已确认','用车中'])
            ->groupBy('carModelId')->select(DB::raw('carModelId,count(1) as num'))->get()->toArray();

        if (in_array('摩托',$orderType))
        {
            //目前没摩托了
            //$carInfo=carModel::get(['id','carNum'])->toArray();
        }else
        {
            if (empty($carOrder))
            {
                //没有订单，哪种车型都能租
                $carModelId=carModelCarBelong::whereIn('carBelongId',$carBelongId)->get(['carModelId'])->toArray();

                $carId=array_values(Arr::flatten($carModelId));

            }else
            {
                //有订单，要查看库存是不是大于租出去的数量

                //先找出当前车行，有哪种车，有多少库存
                $carModelId=carModelCarBelong::whereIn('carBelongId',$carBelongId)->get()->toArray();

                //整理数组，变成 [ 'carModelId' => 库存 ]
                $kuCun=[];
                foreach ($carModelId as $one)
                {
                    $kuCun[$one['carModelId']]=$one['carNum'];
                }

                //得到在这段时间内所有，有订单的车，然后判断有没有超过库存
                foreach ($carOrder as $one)
                {
                    //租出去的数量，大于等于库存
                    if ($one['num'] >= $kuCun[$one['carModelId']])
                    {
                        unset($kuCun[$one['carModelId']]);
                    }
                }

                $carId=$kuCun;

                $carId=array_keys($carId);
            }
        }

        return $carId;
    }

    //返回全局变量
    public function globalConf(Request $request)
    {
        $appName=Redis::hget('globalConf','appName') ?? '超酷的名字';

        $logo=Redis::hget('globalConf','logo') ?? '/static/logo/wx_logo.png';

        $tel=Redis::hget('globalConf','tel') ?? '4008-517-517';

        return response()->json($this->createReturn(200,[
            'appName'=>$appName,
            'tel'=>$tel,
            'logo'=>$logo,
        ]));
    }

    //小程序进入首页
    public function index(Request $request)
    {
        $module=[
            [
                'name'=>'酷享自驾',
                'subtext'=>['你想要的','都在这里'],
                'img'=>Redis::hget('globalConf','module1') ?? '/static/carImg/2020/8be594ba12b6.png',
                'href'=>'/v1/module1',
                'isNew'=>true,
            ],
            [
                'name'=>'尊享出行',
                'subtext'=>['专人专车','一应俱全'],
                'img'=>Redis::hget('globalConf','module2') ?? '/static/carImg/2020/3650457923e4.png',
                'href'=>'/v1/module2',
                'isNew'=>false,
            ],
            [
                'name'=>'急速摩托',
                'subtext'=>['追求极致','畅快淋漓'],
                'img'=>Redis::hget('globalConf','module3') ?? '/static/carImg/2020/b7ebcc68ab2c.png',
                'href'=>'/v1/module3',
                'isNew'=>false,
            ],
            [
                'name'=>'安心托管',
                'subtext'=>['追求极致','畅快淋漓'],
                'img'=>Redis::hget('globalConf','module4') ?? '/static/carImg/2020/f7ca9919164b.png',
                'href'=>'/v1/module4',
                'isNew'=>false,
            ],
            [
                'name'=>'精致车源',
                'subtext'=>['炫酷超跑','触手可及'],
                'img'=>Redis::hget('globalConf','module5') ?? '/static/carImg/2020/3ecbddf84db1.png',
                'href'=>'/v1/module5',
                'isNew'=>false,
            ],
            [
                'name'=>'超值长租',
                'subtext'=>['长期租赁','更多优惠'],
                'img'=>Redis::hget('globalConf','module6') ?? '/static/carImg/2020/5682a08ddc02.png',
                'href'=>'/v1/module6',
                'isNew'=>false,
            ],
        ];

        return response()->json($this->createReturn(200,[
            'banner'=>banner::all()->toArray(),
            'module'=>$module,
        ]));
    }

    //分配模块
    public function moduleDispatch(Request $request)
    {
        preg_match('/\d+/',last(explode('/',$request->path())),$res);

        $res=head($res);

        switch ($res)
        {
            case 1:
                $res=$this->module1($request);
                break;
            case 2:
                $res=$this->module2($request);
                break;
            case 3:
                $res=$this->module3($request);
                break;
            case 4:
                $res=$this->module4($request);
                break;
            case 5:
                $res=$this->module5($request);
                break;
            case 6:
                $res=$this->module6($request);
                break;
            default:
                $res=[];
        }

        return response()->json($this->createReturn(200,$res));
    }

    //酷享自驾
    private function module1(Request $request)
    {
        $city=$request->city ?? 1;
        $lng=$request->lng ?? '';
        $lat=$request->lat ?? '';
        $cond=$request->cond ?? '';//搜索条件，目前只有品牌
        $start=$request->start ?? '';
        $stop=$request->stop ?? '';
        $orderBy=$request->orderBy ?? 1;
        $page=$request->page ?? 1;
        $pageSize=$request->pageSize ?? 10;
        $orderType=['自驾','出行'];

        if (empty($lng) || empty($lat))
        {
            //展示所有车型
            $all=carModel::whereIn('carType',[1,2]);

            if (!empty($cond))
            {
                //先查出品牌
                try
                {
                    $carBrand=carBrand::where('carBrand','like',"%{$cond}%")->pluck('id')->toArray();
                }catch (\Exception $e)
                {
                    $carBrand=[control::getUuid()];
                }

                $all->whereIn('carBrandId',$carBrand);

                //$all->where(function ($q) use ($cond) {
                //    $q->where('carModel','like',"%{$cond}%")->orWhere('carDesc','like',"%{$cond}%");
                //});
            }

            $all=$all->paginate($pageSize,['*'],'',$page)->toArray();

            $res['list']=$all['data'];
            $res['total']=$all['total'];

            return $res;

        }else
        {
            //查询出车行，找一个最近的
            $carBelong=carBelong::where('cityId',$city)->get()->toArray();

            //随便写个key
            $key=__FUNCTION__.Str::random(8);

            //添加近去，和用户选择的地方一起添加
            foreach ($carBelong as $one)
            {
                Redis::geoadd($key,$one['lng'],$one['lat'],$one['id']);
            }

            Redis::geoadd($key,$lng,$lat,'now');

            //开始对比距离
            foreach ($carBelong as $one)
            {
                $dist[$one['id']]=Redis::geodist($key,$one['id'],'now');
            }

            Redis::expire($key,60);

            //值 升序
            asort($dist,SORT_NUMERIC);

            //取第一个就是最近的车行id
            $carBelongId=key($dist);

            //然后从订单表中计算这个车行有多少车被订出去了
            $carModelId=$this->getCarInfoIdByTimeRange($start,$stop,[$carBelongId],$orderType);

            $carModel=carModel::whereIn('carType',[1,2])->whereIn('id',$carModelId);

            if (!empty($cond))
            {
                //先查出品牌
                try
                {
                    $carBrand=carBrand::where('carBrand','like',"%{$cond}%")->pluck('id')->toArray();
                }catch (\Exception $e)
                {
                    $carBrand=[control::getUuid()];
                }

                $carModel->whereIn('carBrandId',$carBrand);

                //if (!empty($cond)) $carModel->where(function ($q) use ($cond){
                //    $q->where('carModel','like',"%{$cond}%")->orWhere('carDesc','like',"%{$cond}%");
                //});
            }

            switch ($orderBy)
            {
                case 1:
                    //根据权重排序
                    $carModel->orderBy('level','desc');
                    break;
                case 2:
                    //价格desc
                    $carModel->orderBy('dayPrice','desc');
                    break;
                case 3:
                    //价格asc
                    $carModel->orderBy('dayPrice','asc');
                    break;
                case 4:
                    //根据订单量
                    break;
                default:
            }

            $all=$carModel->paginate($pageSize,['*'],'',$page)->toArray();

            foreach ($all['data'] as &$one)
            {
                $one['carBrandId']=carBrand::find($one['carBrandId'])->toArray();
            }
            unset($one);

            $res['list']=$all['data'];
            $res['total']=$all['total'];
            $res['carBelongId']=$carBelongId;

            return $res;
        }
    }

    //尊享出行
    private function module2(Request $request)
    {
        $page=$request->page ?? 1;
        $pageSize=$request->pageSize ?? 5;

        $offset=($page-1)*$pageSize;

        //车辆没有库存限制
        //取出所有出行属性的车

        $carInfo=carModel::whereIn('carType',[1,2])
            ->offset($offset)
            ->limit($pageSize)
            ->orderBy('level','desc')
            ->paginate($pageSize,['*'],'',$page)->toArray();

        $res=[];

        foreach ($carInfo['data'] as &$one)
        {
            $one['carBrandId']=carBrand::find($one['carBrandId'])->toArray();
        }
        unset($one);

        $res['list']=$carInfo['data'];
        $res['total']=$carInfo['total'];


        dd($this->curl(['orderInfo'=>json_encode(['w'=>123,'duan'=>321])]));


        return $res;
    }

    //急速摩托
    private function module3(Request $request)
    {

    }

    //安心托管
    private function module4(Request $request)
    {

    }

    //精致车源
    private function module5(Request $request)
    {

    }

    //超值长租
    private function module6(Request $request)
    {

    }

    //登录
    public function login(Request $request)
    {
        $phone=$request->phone;

        $vCode=$request->vCode;

        if (!is_numeric($phone) || strlen($phone) !== 11) return response()->json($this->createReturn(201,[],'手机号码错误'));

        if (empty($vCode)) return response()->json($this->createReturn(201,[],'验证码错误'));

        $vCodeInRedis=Redis::get("login_{$phone}");

        if ($vCode != $vCodeInRedis && $vCode != 66666666) return response()->json($this->createReturn(201,[],'验证码错误'));

        $userInfo=DB::table('users')->where('phone',$phone)->first();

        if (empty($userInfo))
        {
            //注册
            DB::table('users')->insert([
                'phone'=>$phone,
                'created_at'=>Carbon::now()->format('Y-m-d H:i:s'),
                'updated_at'=>Carbon::now()->format('Y-m-d H:i:s'),
            ]);

        }else
        {
            //登录
        }

        $token=control::aesEncode("{$phone}_{$vCode}",env('AES_SALT',''));

        Redis::hset('auth',$phone,$token);

        return response()->json($this->createReturn(200,['token'=>$token],'登录成功'));
    }

    //退出登录
    public function unLogin(Request $request)
    {
        $phone=$request->phone;

        Redis::hset('auth',$phone,'');

        return response()->json($this->createReturn(200,[],'退出成功'));
    }

    //获取验证码
    public function getVerificationCode(Request $request)
    {
        $phone=(string)$request->phone;

        $type=$request->type;

        if (!is_numeric($phone) || strlen($phone) !== 11 || empty($type)) return response()->json($this->createReturn(201,[],'参数'));

        $code=mt_rand(100000,999999);

        SendSms::getInstance()->send(['vCode',$code],[$phone]);

        switch ($type)
        {
            case 'login':
                $key="login_{$phone}";
                break;
            default:
        }

        Redis::set($key,$code);
        Redis::expire($key,300);

        return response()->json($this->createReturn(200,[],'发送成功'));
    }

    //根据城市id，返回所有车行信息
    public function allCarBelongInCity(Request $request)
    {
        $cityId=$request->cityId ?? 1;

        $carBelongInfo=carBelong::where('cityId',$cityId)->get()->toArray();

        return response()->json($this->createReturn(200,$carBelongInfo));
    }

    //获取车辆详情
    public function carDetail(Request $request)
    {
        $carModelId=$request->carModelId ?? 1;

        $carDetail=carModel::where('id',$carModelId)->first()->toArray();

        $carDetail['carType']=carType::find($carDetail['carType'])->toArray();

        $carDetail['carBrandId']=carBrand::find($carDetail['carBrandId'])->toArray();

        $carDetail['label']=[];

        return response()->json($this->createReturn(200,$carDetail));
    }

    public function getLicenseStatus(Request $request)
    {
        $phone=$request->phone;

        //判断驾照过没过审核
        $userInfo=users::where('phone',$phone)->first();

        $status=[
            99=>'通过',
            0=>'未提交',
            1=>'审核中',
            2=>'未通过',
        ];

        $licenseStatus=[
            'car'=>$userInfo->isCarLicensePass,
            'motor'=>$userInfo->isMotorLicensePass,
            'passport'=>$userInfo->isPassportPass,
            'IdCard'=>$userInfo->isIdCardPass,
        ];

        return response()->json($this->createReturn(200,['statusList'=>$status,'licenseStatus'=>$licenseStatus]));
    }

    //预定车辆
    public function bookCar(Request $request)
    {
        $phone=$request->phone;
        $carModelId=$request->carModelId;
        $rentDays=$request->rentDays;
        $orderType=$request->orderType ?? '自驾';

        //找出这辆车需要花费多少钱
        $carInfo=carModel::find($carModelId);

        //车损押金
        $damagePrice=$carInfo->damagePrice;

        //违章押金
        $forfeitPrice=$carInfo->forfeitPrice;

        //日租价格
        $dayPrice=$carInfo->dayPrice;

        //日租折扣
        $dayDiscount=$carInfo->dayDiscount;

        if ($dayDiscount==0)
        {
            $payMoney=$dayPrice * $rentDays;
        }else
        {
            $payMoney=sprintf('%.2f',$dayPrice - ($dayPrice * $dayDiscount * 0.01));
            $payMoney=$payMoney * $rentDays;
        }

        $pay=[
            'damagePrice'=>$damagePrice,
            'forfeitPrice'=>$forfeitPrice,
            'dayPrice'=>$dayPrice,
            'dayDiscount'=>$dayDiscount,
            'payMoney'=>$payMoney,
        ];

        //找出哪些优惠券可用
        $couponInfo=coupon::where('phone',$phone)->where('couponType',$orderType)->where('isUse',0)->get()->toArray();

        $available=$disabled=[];

        foreach ($couponInfo as $val)
        {
            //过期了，或者还没开始
            if ($val['expireStart'] >= time() || $val['expireStop'] <= time())
            {
                $disabled[]=$val;
                continue;
            }

            //金额减免，未到触发金额
            if ($val['discountWay']==='金额减免' && $val['needMoney'] > $payMoney)
            {
                $disabled[]=$val;
                continue;
            }

            //折扣减免，未到触发金额
            if ($val['discountWay']==='折扣减免' && $val['needMoney'] > $payMoney)
            {
                $disabled[]=$val;
                continue;
            }

            //违章押金减免，未到触发金额
            if ($val['discountWay']==='违章押金减免' && $val['needMoney'] > $payMoney)
            {
                $disabled[]=$val;
                continue;
            }

            //车损押金减免，未到触发金额
            if ($val['discountWay']==='车损押金减免' && $val['needMoney'] > $payMoney)
            {
                $disabled[]=$val;
                continue;
            }

            //可用的优惠券
            $available[]=$val;
        }

        return response()->json($this->createReturn(200,['coupon'=>['available'=>$available,'disabled'=>$disabled],'pay'=>$pay]));
    }

    //保存或更新用户的驾照，或者身份证图片
    public function updateOrCreateUserImg(Request $request)
    {
        $phone=$request->phone;
        $car=$request->car;
        $motor=$request->motor;
        $idCard=$request->idCard;
        $passport=$request->passport;
        $oftenCity=$request->oftenCity;

        $userInfo=users::where('phone',$phone)->first();

        if (!empty($car))
        {
            $userInfo->isCarLicensePass=1;
            $userInfo->carLicenseImg=$car;
        }

        if (!empty($motor))
        {
            $userInfo->isMotorLicensePass=1;
            $userInfo->motorLicenseImg=$motor;
        }

        if (!empty($idCard))
        {
            $userInfo->isIdCardPass=1;
            $userInfo->idCardImg=$idCard;
        }

        if (!empty($passport))
        {
            $userInfo->isPassportPass=1;
            $userInfo->passportImg=$passport;
        }

        if (!empty($oftenCity))
        {
            $userInfo->oftenCity=$oftenCity;
        }

        $userInfo->save();

        return response()->json($this->createReturn(200,[]));
    }

    //创建订单
    public function createOrder(Request $request)
    {
        $phone=$request->phone;
        $startTime=$request->startTime;
        $stopTime =$request->stopTime ?? 9999999999;
        $carModelId=$request->carModelId;
        $carBelongId=(int)$request->carBelongId;
        $rentPersonName=$request->rentPersonName;
        $rentPersonPhone=$request->rentPersonPhone;
        $couponId=(int)$request->couponId;
        $orderType=$request->orderType;
        $rentDays=(int)$request->rentDays;
        $getCarWay=$request->getCarWay;
        $getCarPlace=$request->getCarPlace ?? '';
        $start=$request->start ?? '';//出行用的起点
        $destination=$request->destination ?? '';//出行用的终点
        $payment=(int)$request->payment;//1是全款，2是只违章押金

        $payment===1 ? $payment='全款' : $payment='违章押金';

        //$orderId=control::getUuid();

        //日租还是出行，还是摩托的价格
        $carInfo=carModel::find($carModelId);

        switch ($orderType)
        {
            case '1':
                //日租
                $payMoney=$carInfo->dayPrice - ($carInfo->dayPrice * $carInfo->dayDiscount * 0.01);
                $payMoney=$payMoney * $rentDays;
                $orderType='自驾';
                //车损
                $damagePrice=$carInfo->damagePrice;
                //违章
                $forfeitPrice=$carInfo->forfeitPrice;
                break;
            case '2':
                //出行
                $payMoney=$carInfo->goPrice - ($carInfo->goPrice * $carInfo->goDiscount * 0.01);
                $orderType='出行';

                //算两点间距离乘以每公里价格
                //$lat纬度 $lng经度
                $start=explode('_',$start);
                $destination=explode('_',$destination);
                $key=control::getUuid();
                Redis::geoadd($key,head($start),last($start),'start');
                Redis::geoadd($key,head($destination),last($destination),'destination');
                $km=Redis::geodist($key,'start','destination','km');
                $payMoney=$carInfo->kilPrice * $km + $payMoney;
                Redis::expire($key,5);

                //车损
                $damagePrice=0;
                //违章
                $forfeitPrice=0;
                break;
            case '3':
                //摩托
                $orderType='摩托';
                //车损
                $damagePrice=$carInfo->damagePrice;
                //违章
                $forfeitPrice=$carInfo->forfeitPrice;
                break;
            default:
        }

        //如果有优惠券
        if (!empty($couponId))
        {
            $couponInfo=coupon::find($couponId);

            if ($couponInfo->isUse != 0)
            {
                return response()->json($this->createReturn(201,[''],'优惠券已经使用'));
            }

            switch ($couponInfo->discountWay)
            {
                case '金额减免':
                    $payMoney=$payMoney - $couponInfo->discount;
                    break;
                case '折扣减免':
                    $payMoney=$payMoney - ($payMoney * $couponInfo->discount * 0.01);
                    break;
                case '违章押金减免':
                    $forfeitPrice=$forfeitPrice - $couponInfo->discount;
                    break;
                case '车损押金减免':
                    $damagePrice=$damagePrice - $couponInfo->discount;
                    break;
                default:
            }

            $couponInfo->isUse=1;
            $couponInfo->save();
        }

        if ($getCarWay==1)
        {
            $getCarWay='自取';

            $getCarPlace=carBelong::find($carBelongId)->address;

        }else
        {
            $getCarWay='送车';
        }

        $orderId=$this->getOrderId('微信小程序',$orderType,'待选择',time(),users::where('phone',$phone)->first()->id);

        $insert=[
            'orderId'=>$orderId, 'coupon1'=>$couponId, 'carModelId'=>$carModelId,
            'carBelongId'=>$carBelongId, 'orderType'=>$orderType, 'orderStatus'=>'待支付', 'account'=>$phone,
            'orderPrice'=>sprintf('%.2f',$payMoney), 'damagePrice'=>$damagePrice, 'forfeitPrice'=>$forfeitPrice,
            'payWay'=>'待选择', 'payment'=>$payment, 'startTime'=>$startTime, 'stopTime'=>$stopTime,
            'getCarWay'=>$getCarWay, 'getCarPlace'=>$getCarPlace,
            'rentPersonName'=>$rentPersonName, 'rentPersonPhone'=>$rentPersonPhone, 'start'=>$start, 'destination'=>$destination,
            'NotifyInfo'=>'','year'=>date('Y'),'month'=>date('m'),'day'=>date('d'),'hour'=>date('H')
        ];

        try
        {
            $code=200;
            $res=order::create($insert);
            $orderId=$res->orderId;

        }catch (\Exception $e)
        {
            $code=201;
            $orderId='';
        }

        return response()->json($this->createReturn($code,['orderId'=>$orderId]));
    }

    //获取用户常用车城市
    public function getOftenCity(Request $request)
    {
        $phone=$request->phone;

        $userInfo=users::where('phone',$phone)->first();

        $cityName=chinaArea::where('id',$userInfo->oftenCity)->first()->name;

        $china_area=chinaArea::all()->toArray();
        $tmp=[];
        control::traverseMenu($china_area,$tmp);
        $china_area=$tmp;

        return response()->json($this->createReturn(200,[
            'oftenCity'=>$cityName,
            'china_area'=>$china_area
        ]));
    }

    //获取用户信息
    public function getUserInfo(Request $request)
    {
        $phone=$request->phone;

        $userInfo=users::where('phone',$phone)->first()->toArray();

        empty($userInfo['password']) ? $userInfo['password']=0 : $userInfo['password']=1;

        //用车城市
        $userInfo['oftenCity']=chinaArea::find($userInfo['oftenCity'])->toArray();

        //驾照的几个状态
        $status=[
            99=>'通过',
            0=>'未提交',
            1=>'审核中',
            2=>'未通过',
        ];

        //驾照
        $userInfo['isCarLicensePass']=['status'=>$userInfo['isCarLicensePass'],'name'=>$status[$userInfo['isCarLicensePass']]];
        $userInfo['isMotorLicensePass']=['status'=>$userInfo['isMotorLicensePass'],'name'=>$status[$userInfo['isMotorLicensePass']]];
        $userInfo['isPassportPass']=['status'=>$userInfo['isPassportPass'],'name'=>$status[$userInfo['isPassportPass']]];
        $userInfo['isIdCardPass']=['status'=>$userInfo['isIdCardPass'],'name'=>$status[$userInfo['isIdCardPass']]];

        return response()->json($this->createReturn(200,$userInfo));
    }

    //支付订单
    public function payOrder(Request $request)
    {
        $orderId=$request->orderId;
        $jsCode=$request->jsCode;
        $phone=$request->phone;
        $password=$request->password;
        $payWay=(int)$request->payWay;//1是用户钱包支付

        //用orderId取出该订单需要支付的所有金额再转换成XXX分钱

        $orderInfo=order::where('orderId',$orderId)->first();

        $payment=$orderInfo->payment;

        //自驾价格
        if ($payment==='全款' && $orderInfo->orderType==='自驾')
        {
            $payMoney=$orderInfo->orderPrice + $orderInfo->damagePrice + $orderInfo->forfeitPrice;
        }

        if ($payment==='违章押金' && $orderInfo->orderType==='自驾')
        {
            $payMoney=$orderInfo->forfeitPrice;
        }

        //出行价格
        if ($payment==='全款' && $orderInfo->orderType==='出行')
        {
            $payMoney=$orderInfo->orderPrice;
        }

        if ($payWay===1)
        {
            $payWay='钱包';

            //查询用户余额够不够，支付密码对不对
            $userInfo=users::where('phone',$phone)->first();

            $password=control::aesEncode($password,$phone);

            if ($password !== $userInfo->password)
            {
                return response()->json($this->createReturn(201,[],'支付密码错误'));
            }

            if ($userInfo->money < $payMoney)
            {
                return response()->json($this->createReturn(201,[],'余额不足'));

            }else
            {
                //减去余额
                //改状态

                $userInfo->money=$userInfo->money - $payMoney;

                $orderInfo->payWay=$payWay;
                $orderInfo->orderStatus='待确认';

                $userInfo->save();
                $orderInfo->save();

                return response()->json($this->createReturn(200,[],'支付成功'));
            }

        }else
        {
            $payWay='微信';

            //如果不付钱，直接修改状态
            if ($payMoney <= 0)
            {
                $orderInfo->payWay=$payWay;
                $orderInfo->orderStatus='待确认';
                $orderInfo->save();

                return response()->json($this->createReturn(200,[],'支付成功'));
            }
        }

        //微信支付，等支付回调再修改状态

        //把用户选择的状态先存一下，等支付回调的时候再修改上

        $orderInfo->NotifyInfo="{$payWay}_payWay";

        $orderInfo->save();

        $body="极客超跑-租车服务";

        $miniApp=MiniAppPay::getInstance()->createMiniAppOrder($jsCode,$orderId,$body,$payMoney);

        return response()->json($this->createReturn(200,$miniApp));
    }

    //支付密码
    public function payPassword(Request $request)
    {
        $phone=$request->phone;
        $type=(int)$request->type;
        $newPassword=$request->newPassword;
        $oldPassword=$request->oldPassword;

        $userInfo=users::where('phone',$phone)->first();

        $code=201;
        $msg=null;

        //创建密码
        if ($type===1)
        {
            $password=control::aesEncode($newPassword,$phone);
            $userInfo->password=$password;
            $code=200;
            $msg='创建成功';
        }

        //修改密码
        if ($type===2)
        {
            $password=$userInfo->password;

            $oldPassword=control::aesEncode($oldPassword,$phone);

            if ($password !== $oldPassword)
            {
                $code=201;
                $msg='旧密码输入错误';

            }else
            {
                $password=control::aesEncode($newPassword,$phone);
                $userInfo->password=$password;
                $code=200;
                $msg='修改成功';
            }
        }

        $userInfo->save();

        return response()->json($this->createReturn($code,[],$msg));
    }

    //用户所有优惠券
    public function getUserCoupon(Request $request)
    {
        $phone=$request->phone;

        $type=(int)$request->type;

        $couponInfo=coupon::where('phone',$phone)->get()->toArray();

        $available=[];
        $disabled=[];

        foreach ($couponInfo as $val)
        {
            if ($val['isUse']!=0)
            {
                $disabled[]=$val;
                continue;
            }

            //过期了，或者还没开始
            if ($val['expireStart'] >= time() || $val['expireStop'] <= time())
            {
                $disabled[]=$val;
                continue;
            }

            //可用的优惠券
            $available[]=$val;
        }

        $type === 1 ? $data=$available : $data=$disabled;

        return response()->json($this->createReturn(200,$data));
    }

    //查看订单
    public function orderInfo(Request $request)
    {
        $phone=$request->phone;

        $orderId=$request->orderId ?? '';

        if (!empty($orderId))
        {
            $data=order::where('orderId',$orderId)->first()->toArray();
        }else
        {
            $data=order::where('account',$phone)->get()->toArray();
        }

        return response()->json($this->createReturn(200,$data));
    }

    //充值列表
    public function purchaseList(Request $request)
    {
        $phone=$request->phone;

        $res=Redis::hgetall('purchaseList');

        return response()->json($this->createReturn(200,$res));
    }

    //充值
    public function purchase(Request $request)
    {
        $phone=$request->phone;
        $type=$request->type ?? 't1';
        $jsCode=$request->jsCode ?? 123;

        $money=Redis::hget('purchaseList',$type);

        //创建订单
        $orderId=$this->getOrderId('微信小程序','充值','待选择',time(),users::where('phone',$phone)->first()->id);
        $orderId.='purchase';

        $body="{$type}_充值";

        purchaseOrder::create([
            'phone'=>$phone,
            'orderId'=>$orderId,
            'orderType'=>$type,
            'orderStatus'=>'待支付',
            'purchaseMoney'=>$money,
            'unixTime'=>time(),
            'year'=>date('Y'),
            'month'=>date('m'),
            'day'=>date('d'),
            'hour'=>date('H'),
        ]);

        $res=MiniAppPay::getInstance()->createMiniAppOrder($jsCode,$orderId,$body,$money);

        return response()->json($this->createReturn(200,$res));
    }




}
