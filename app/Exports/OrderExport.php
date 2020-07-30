<?php

namespace App\Exports;

use App\Http\Models\order;
use Maatwebsite\Excel\Concerns\Exportable;
use Illuminate\Contracts\Support\Responsable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class OrderExport implements Responsable, FromQuery, WithMapping, WithHeadings, WithTitle, ShouldAutoSize
{
    use Exportable;

    private $fileName = 'order.xlsx';

    protected $days;

    public function withinDays(int $days)
    {
        $this->days = $days;
        return $this;
    }

    public function query()
    {
        return order::whereDate('created_at', '>=', now()->subDays($this->days));
    }

    public function map($order): array
    {
        return [
            $order->id,
            $order->orderId,
            $order->rentPersonName,
            $order->rentPersonPhone,
        ];
    }

    public function headings(): array
    {
        return [
            'id',
            '订单号',
            '租车人姓名',
            '租车人电话',
        ];
    }

    public function title(): string
    {
        return 'order信息';
    }
}
