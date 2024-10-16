<?php

namespace App\Imports;

use App\Http\Services\Bitrix24Service;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\BeforeImport;

class ImportToBitrix24 implements ToCollection, WithHeadingRow, WithChunkReading, ShouldQueue, WithEvents
{

    private Bitrix24Service $bitrixService;

    public $timeout = 3600;

    public function __construct(Bitrix24Service $bitrixService)
    {
        $this->bitrixService = $bitrixService;
    }

    public function collection(Collection $collection)
    {
        $smartProcessDetail = $this->bitrixService->getSmartProcessDetail();

        $filteredCollection = $collection->filter(function ($row) {
            return $row->filter()->isNotEmpty();
        });

        foreach ($filteredCollection as $row) {
            if(!empty($row['naimenovanie'])) {

                $data['addProduct'] = [
                    'article' => $row['artikul'],
                    'brand' => $row['brend'],
                    'name' => $row['naimenovanie'],
                    'price' => $row['cena'],
                ];

                $product = $this->bitrixService->searchProduct($data['addProduct']);

                $data['addProductRow'] = [
                    'ownerId' => $this->bitrixService->getObjectId(),
                    'ownerType' => $smartProcessDetail['SYMBOL_CODE_SHORT'],
                    'productId' => $product,
                    'price' => $row['cena'],
                    'quantity' => $row['kol_vo']
                ];

                $this->bitrixService->addProductRow($data['addProductRow']);

            }
        }
    }

    public function chunkSize(): int
    {
        return 10000;
    }

    public function registerEvents(): array
    {
        return [
            BeforeImport::class => function(BeforeImport $event) {
                $smartProcessDetail = $this->bitrixService->getSmartProcessDetail();
                $this->bitrixService->clearProductRow($smartProcessDetail['SYMBOL_CODE_SHORT']);
            },
            AfterImport::class => function(AfterImport $event) {
                $this->bitrixService->sendNotify($this->bitrixService->getAssigned(), 'Импорт завершен!');
            },
        ];
    }
}
