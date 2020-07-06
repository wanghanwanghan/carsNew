<?php

namespace App\Http\Controllers\Business\Index;

use App\Http\Controllers\Business\BusinessBase;
use App\Http\Models\banner;
use App\Http\Models\carBrand;
use App\Http\Models\carInfo;
use App\Http\Models\carModel;
use App\Http\Models\chinaArea;
use App\Http\Models\order;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use wanghanwanghan\someUtils\control;

class Index extends BusinessBase
{
    //算offset
    private function offset($request)
    {
        $page = $request->page ?? 1;

        $pageSize = $request->pageSize ?? 10;

        $offset = ( $page - 1 ) * $pageSize;

        return [$offset,$pageSize];
    }

    //根据timeRange从订单表中取出哪些车被消耗了多少辆
    private function getCarInfoIdByTimeRange($start,$stop,$orderType=['自驾','出行','摩托'])
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
            ->whereIn('orderType',$orderType)
            ->whereIn('orderStatus',['待确认','已确认'])
            ->groupBy('carInfoId')->select(DB::raw('carInfoId,count(1) as num'))->get()->toArray();

        if (in_array('摩托',$orderType))
        {
            $carInfo=carInfo::get(['id','carNum'])->toArray();
        }else
        {
            $carInfo=carInfo::whereIn('carType',[1,2])->get(['id','carNum'])->toArray();
        }

        //整理数组
        $carId=[];

        foreach ($carInfo as $one)
        {
            $carId[$one['id']]=$one['carNum'];
        }

        //得到在这段时间内所有，有订单的车，然后判断有没有超过库存

        foreach ($carOrder as $one)
        {
            if (!isset($carId[$one['carInfoId']])) continue;

            //租出去的数量，大于等于库存
            if ($one['num'] >= $carId[$one['carInfoId']])
            {
                unset($carId[$one['carInfoId']]);
            }
        }

        return array_keys($carId);
    }

    //返回全局变量
    private function globalConf()
    {
        $appName=Redis::hget('globalConf','appName');
        $appName=$appName == null ? '超酷的名字' : $appName;

        $logo=Redis::hget('globalConf','logo');
        $logo=$logo == null ? '/static/logo/miniLogo.png' : $logo;

        $tel=Redis::hget('globalConf','tel');
        $tel=$tel == null ? '4008-517-517' : $tel;

        return [
            'appName'=>$appName,
            'tel'=>$tel,
            'logo'=>$logo,
        ];
    }

    //小程序进入首页
    public function index(Request $request)
    {
        $module=[
            [
                'name'=>'酷享自驾',
                'subtext'=>['你想要的','都在这里'],
                'img'=>Redis::hget('globalConf','module1') ?? '',
                'href'=>'/v1/module1',
                'isNew'=>true,
            ],
            [
                'name'=>'尊享出行',
                'subtext'=>['专人专车','一应俱全'],
                'img'=>Redis::hget('globalConf','module2') ?? '',
                'href'=>'/v1/module2',
                'isNew'=>false,
            ],
            [
                'name'=>'急速摩托',
                'subtext'=>['追求极致','畅快淋漓'],
                'img'=>Redis::hget('globalConf','module3') ?? '',
                'href'=>'/v1/module3',
                'isNew'=>false,
            ],
            [
                'name'=>'安心托管',
                'subtext'=>['追求极致','畅快淋漓'],
                'img'=>Redis::hget('globalConf','module4') ?? '',
                'href'=>'/v1/module4',
                'isNew'=>false,
            ],
            [
                'name'=>'精致车源',
                'subtext'=>['炫酷超跑','触手可及'],
                'img'=>Redis::hget('globalConf','module5') ?? '',
                'href'=>'/v1/module5',
                'isNew'=>false,
            ],
            [
                'name'=>'超值长租',
                'subtext'=>['长期租赁','更多优惠'],
                'img'=>Redis::hget('globalConf','module6') ?? '',
                'href'=>'/v1/module6',
                'isNew'=>false,
            ],
        ];

        return response()->json($this->createReturn(200,[
            'globalConf'=>$this->globalConf(),
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
        $cond=$request->cond ?? '';//搜索条件
        $start=$request->start ?? '';
        $stop=$request->stop ?? '';
        $orderBy=$request->orderBy ?? 1;
        $page=$request->page ?? 1;
        $pageSize=$request->pageSize ?? 10;
        $orderType=['自驾'];

        if (empty($lng) || empty($lat))
        {
            //展示所有车型






        }else
        {

        }








        switch ($request->orderBy)
        {
            case 1:
                //根据权重排序
                break;
            case 2:
                //价格desc
                break;
            case 3:
                //价格asc
                break;
            case 4:
                //根据订单量
                break;

            default:
        }

        //$carInfo=$carInfo->paginate($request->pageSize??10,['*'],'',$request->page??1)->toArray();

        //$res['list']=$carInfo['data'];
        //$res['total']=$carInfo['total'];

        return $res;
    }

    //尊享出行
    private function module2(Request $request)
    {
        //车辆没有库存限制
        //取出所有出行属性的车
        $carInfo=carInfo::whereIn('carType',[1,2])->get()->toArray();


        dd(123,$carInfo);

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





}
