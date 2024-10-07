<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ExportProductRows implements FromArray, WithHeadings
{

    protected $productRows;

    public function __construct(array $productRows)
    {
        $this->productRows = $productRows;
    }

    public function array(): array
    {
        return $this->productRows;
    }

    public function headings(): array
    {
        return [
            '№', 'Артикул', 'Бренд', 'Наименование', 'Цена', 'Кол-во'
        ];
    }


}
