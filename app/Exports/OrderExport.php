<?php

namespace App\Exports;

use App\Http\Models\carBelong;
use App\Http\Models\carModel;
use App\Http\Models\order;
use App\Http\Models\users;
use Maatwebsite\Excel\Concerns\Exportable;
use Illuminate\Contracts\Support\Responsable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

class OrderExport extends DefaultValueBinder implements Responsable, FromQuery, WithMapping, WithHeadings, WithTitle, ShouldAutoSize, WithCustomValueBinder
{
    use Exportable;

    private $fileName = 'order.xlsx';

    protected $days;

    protected $carBelongId;//车行id
    protected $orderType;//自驾
    protected $orderStatus;//已完成
    protected $created_at;//时间范围

    public function __construct($args)
    {
        $this->carBelongId=$args['carBelongId'] ?? '';
        $this->orderType=$args['orderType'] ?? '';
        $this->orderStatus=$args['orderStatus'] ?? '';
        $this->created_at=$args['created_at'] ?? '';
    }

    public function exec()
    {
        return $this;
    }

    public function query()
    {
        $model=order::orderBy('created_at', 'desc');

        if ($this->carBelongId !== '') $model->where('carBelongId',$this->carBelongId);

        if ($this->orderType !== '') $model->where('orderType',$this->orderType);

        if ($this->orderStatus !== '') $model->where('orderStatus',$this->orderStatus);

        if ($this->created_at !== '') $model->whereBetween('created_at',$this->created_at);

        return $model;
    }

    public function map($order): array
    {
        $carInfo=carModel::where('id',$order->carModelId)->first();

        if (empty($carInfo))
        {
            $order->carModelName='';
            $order->dayPrice='';
            $order->goPrice='';
        }else
        {
            $order->carModelName=$carInfo->carModel;
            $order->dayPrice=$carInfo->dayPrice;
            $order->goPrice=$carInfo->goPrice;
        }

        $carBelongInfo=carBelong::where('id',$order->carBelongId)->first();

        if (empty($carBelongInfo))
        {
            $order->carBelongName='';
            $order->carBelongAddress='';
            $order->carBelongTel='';
            $order->carBelongPhone='';
        }else
        {
            $order->carBelongName=$carBelongInfo->name;
            $order->carBelongAddress=$carBelongInfo->address;
            $order->carBelongTel=$carBelongInfo->tel;
            $order->carBelongPhone=$carBelongInfo->phone;
        }

        //这里补充每行的数据
        return [
            $order->orderId,
            $order->orderPrice,
            $order->orderType,
            $order->orderStatus,
            $order->carModelName,
            $order->dayPrice,
            $order->goPrice,
            $order->carBelongName,
            $order->carBelongAddress,
            $order->carBelongTel,
            $order->carBelongPhone,
            $order->account,
            $order->rentPersonName,
            $order->rentPersonPhone,
            $order->payWay,
            $order->payment,
        ];
    }

    public function bindValue(Cell $cell, $value)
    {
        if (is_numeric($value))
        {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function headings(): array
    {
        //这里添加表头
        return [
            '订单号',
            '订单价格',
            '订单类型',
            '订单状态',
            '车型名称',
            '日租价格',
            '出行价格',
            '车行名称',
            '车行地址',
            '车行电话',
            '车行手机',
            '下单账号',
            '租车人姓名',
            '租车人电话',
            '支付方式',
            '支付金额',
        ];
    }

    public function title(): string
    {
        return 'order信息';
    }
}
