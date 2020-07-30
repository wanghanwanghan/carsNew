<?php

namespace App\Http\Controllers\Admin;

use App\Exports\OrderExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class AdminBase extends Controller
{
    public function uploadFile(Request $request)
    {
        if (strtoupper($request->getMethod()) === 'GET') return view('welcome');

        $obj=$request->file('upfile');

        $obj->move(public_path(),'xuqiu.'.$obj->getClientOriginalExtension());

        $res=[
            '飞飞正在磨刀',
            '飞飞脸色不好看了',
            '飞飞偷偷给你买了保险',
            '飞飞还有5秒到达现场',
            '起飞',
        ];

        return Arr::random($res);
    }

    public function excelTest(Request $request, OrderExport $orderExport)
    {
        return $orderExport->withinDays(20);
    }
}
