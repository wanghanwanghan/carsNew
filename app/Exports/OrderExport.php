<?php

namespace App\Exports;

use App\Http\Models\order;
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

    public function exec()
    {
        return $this;
    }

    public function query()
    {
        return order::orderBy('created_at', 'desc');
    }

    public function map($order): array
    {
        return [
            $order->orderId,
            $order->rentPersonName,
            $order->rentPersonPhone,
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
        return [
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
