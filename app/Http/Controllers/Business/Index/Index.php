<?php

namespace App\Http\Controllers\Business\Index;

use App\Http\Controllers\Business\BusinessBase;
use App\Http\Models\banner;
use App\Http\Models\carBrand;
use App\Http\Models\carInfo;
use App\Http\Models\chinaArea;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redis;

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
        $carBelongCity=carInfo::groupBy('carBelongCity')->get(['carBelongCity'])->toArray();

        $carBelongCity=Arr::flatten($carBelongCity);

        $carBelongCity=chinaArea::whereIn('id',$carBelongCity)->get(['id','name'])->toArray();

        $res['carBelongCity']=$carBelongCity;

        //下面要取车辆信息了
        $carBelongCity=$request->carBelongCity ?? 1;

        $carInfo=carInfo::where('carBelongCity',$carBelongCity);

        //搜索品牌或型号
        $cond=$request->cond;

        $carBrand=carBrand::where('carBrand','like',"%{$cond}%")->get(['id'])->toArray();

        $carBrand=Arr::flatten($carBrand);

        $carInfo->where(function ($query) use ($carBrand,$cond) {
            $query->whereIn('carBrand',$carBrand)->orWhere('carModel','like',"%{$cond}%");
        });

        switch ($request->orderBy)
        {
            case 1:
                //根据权重排序
                $carInfo->orderBy('level','desc');
                break;
            case 2:
                //价格desc
                $carInfo->orderBy('dayPrice','desc');
                break;
            case 3:
                //价格asc
                $carInfo->orderBy('dayPrice');
                break;
            case 4:
                //根据订单量
                break;

            default:
        }

        $carInfo=$carInfo->paginate($request->pageSize??10,['*'],'',$request->page??1)->toArray();

        $res['list']=$carInfo['data'];
        $res['total']=$carInfo['total'];

        return $res;
    }

    //尊享出行
    private function module2(Request $request)
    {

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
