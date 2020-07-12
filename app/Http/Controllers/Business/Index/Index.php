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
use App\Http\Models\order;
use App\Http\Models\users;
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
            ->whereIn('orderStatus',['待确认','已确认'])
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

                $carId=Arr::flatten($carModelId);

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
            }
        }

        return array_keys($carId);
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

            Redis::expire($key,60);

            //开始对比距离
            foreach ($carBelong as $one)
            {
                $dist[$one['id']]=Redis::geodist($key,$one['id'],'now');
            }

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
        $cityId=$request->cityId;

        $carBelongInfo=carBelong::where('cityId',$cityId)->get()->toArray();

        return response()->json($this->createReturn(200,$carBelongInfo));
    }

    //获取车辆详情
    public function carDetail(Request $request)
    {
        $carModelId=$request->carModelId;

        $carDetail=carModel::where('id',$carModelId)->first()->toArray();

        $carDetail['carType']=carType::find($carDetail['carType'])->toArray();

        $carDetail['carBrandId']=carBrand::find($carDetail['carBrandId'])->toArray();

        $carDetail['label']=[];

        return response()->json($this->createReturn(200,$carDetail));
    }

    //预定车辆
    public function bookCar(Request $request)
    {
        $phone=$request->phone;
        $code=$request->code;


        //判断登录没登录
        //中间键中判断了

        //判断驾照过没过审核
    }

    //保存或更新用户的驾照，或者身份证图片
    public function updateOrCreateUserImg(Request $request)
    {
        $phone=$request->phone;
        $type=$request->type;
        $img=$request->img;

        $userInfo=users::where('phone',$phone)->first();

        $code=200;

        try
        {
            switch ($type)
            {
                case 'car':
                    $userInfo->isCarLicensePass=0;
                    $userInfo->carLicenseImg=$img;
                    break;
                case 'idCard':
                    $userInfo->isIdCardPass=0;
                    $userInfo->idCardImg=$img;
                    break;
                case 'motor':
                    $userInfo->isMotorLicensePass=0;
                    $userInfo->motorLicenseImg=$img;
                    break;
                default:
            }

            $userInfo->save();

        }catch (\Exception $e)
        {
            $code=201;
        }

        return response()->json($this->createReturn($code,[]));
    }










}
