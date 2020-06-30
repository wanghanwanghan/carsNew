<?php

namespace App\Http\Service;

use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use wanghanwanghan\someUtils\control;

class UploadImg
{
    public function store(UploadedFile $one)
    {
        //获取后缀
        $ext=$one->getClientMimeType();

        $ext=explode('/',$ext);

        $ext=".{$ext[1]}";

        $year=Carbon::now()->year;

        //这里需要返回的
        $pathAndName="/static/carImg/{$year}/".control::getUuid(12).$ext;

        try
        {
            $one->move(public_path("/static/carImg/{$year}/"),control::getUuid(12).$ext);

        }catch (\Exception $e)
        {
            $pathAndName=null;
        }

        return $pathAndName;
    }






}
