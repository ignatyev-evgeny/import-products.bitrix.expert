<?php

namespace App\Imports;

use App\Http\Services\Bitrix24Service;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\BeforeImport;

class BatchImportToBitrix24 implements ToCollection, WithHeadingRow, WithChunkReading, ShouldQueue, WithEvents
{
    private Bitrix24Service $bitrixService;

    public int $timeout = 3600;

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

            $indexedRow = $row->values();

            if(!empty($indexedRow[3])) {
                $products[] = [
                    'ownerId' => $this->bitrixService->getObjectId(),
                    'ownerType' => $smartProcessDetail['SYMBOL_CODE_SHORT'],
                    'productId' => null,
                    'article' => trim($indexedRow[1] ?? null),
                    'brand' => trim($indexedRow[2] ?? null),
                    'name' => trim($indexedRow[3]),
                    'price' => $indexedRow[4] ?? 0,
                    'quantity' => $indexedRow[5] ?? 1
                ];
            }
        }

        if(!empty($products)) {

            $uniqueProducts = [];

            foreach ($products as $product) {
                $uniqueKey = ($product['article'] ?? 'no_article') . '|' . ($product['brand'] ?? 'no_brand') . '|' . $product['name'];
                if (isset($uniqueProducts[$uniqueKey])) {
                    $uniqueProducts[$uniqueKey]['quantity'] += $product['quantity'];
                } else {
                    $uniqueProducts[$uniqueKey] = $product;
                }
            }

            $uniqueProducts = array_values($uniqueProducts);

            if(empty($uniqueProducts)) {
                $this->bitrixService->sendNotify(
                    $this->bitrixService->getAssigned(),
                    'Был отправлен пустой файл либо при обработке файла произошла ошибка, дальнейшая обработка файла невозможна. Свяжитесь с технической поддержкой.',
                    $this->bitrixService->getDomain(),
                    $this->bitrixService->getAuthID()
                );
                return false;
            }

            $bitrixProducts = $this->bitrixService->getOrCreateProducts($uniqueProducts);
            $this->bitrixService->addProductRows(
                $uniqueProducts,
                $bitrixProducts
            );

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
                $this->bitrixService->clearProductRow(
                    $smartProcessDetail['SYMBOL_CODE_SHORT']
                );
            },
            AfterImport::class => function(AfterImport $event) {
                $this->bitrixService->sendNotify(
                    $this->bitrixService->getAssigned(),
                    'Импорт завершен!',
                    $this->bitrixService->getDomain(),
                    $this->bitrixService->getAuthID()
                );
            },
        ];
    }
}
