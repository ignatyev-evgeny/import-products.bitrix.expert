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

class ImportToBitrix24 implements ToCollection, WithHeadingRow, WithChunkReading, ShouldQueue, WithEvents
{

    private Bitrix24Service $bitrixService;

    public function __construct(Bitrix24Service $bitrixService)
    {
        $this->bitrixService = $bitrixService;
    }

    public function collection(Collection $collection)
    {
        $smartProcessDetail = $this->bitrixService->getSmartProcessDetail();

        $this->bitrixService->clearProductRow($smartProcessDetail['SYMBOL_CODE_SHORT']);

        foreach ($collection as $row) {

            if(!empty($row['naimenovanie'])) {

                $data['addProduct'] = [
                    'article' => $row['artikul'],
                    'brand' => $row['brend'],
                    'name' => $row['naimenovanie'],
                    'price' => $row['cena'],
                ];

                Log::channel('importProduct')->debug('PRODUCT DATA: '.json_encode($data['addProduct']));

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
        return 100;
    }

    public function registerEvents(): array
    {
        return [
            AfterImport::class => function(AfterImport $event) {
                $this->bitrixService->sendNotify($this->bitrixService->getAssigned(), 'Импорт завершен!');
            },
        ];
    }

}
