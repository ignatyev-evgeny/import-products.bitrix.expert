<?php

namespace App\Imports;

use App\Http\Services\Bitrix24Service;
use Cache;
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
    private string $uuid;

    public function __construct(Bitrix24Service $bitrixService, $uuid)
    {
        $this->bitrixService = $bitrixService;
        $this->uuid = $uuid;
    }

    public function collection(Collection $collection)
    {

        $smartProcessDetail = $this->bitrixService->getSmartProcessDetail();

        $filteredCollection = $collection->filter(function ($row) {
            return $row->filter()->isNotEmpty();
        });

        logImport($this->uuid, [
            'status' => 'Обработка файла',
            'file_count_rows' => $filteredCollection->count(),
            'events_history' => 'Файл запущен в обработку для формирования массива товарных позиций'
        ]);

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

        logImport($this->uuid, [
            'events_history' => 'Массив товарных позиций успешно подготовлен в кол-ве - '.count($products)
        ]);

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

            logImport($this->uuid, [
                'product_count_rows' => count($uniqueProducts),
                'events_history' => 'Массив уникальных товарных позиций успешно подготовлен  в кол-ве - '.count($uniqueProducts)
            ]);

            if(empty($uniqueProducts)) {
                $this->bitrixService->sendNotify(
                    $this->bitrixService->getAssigned(),
                    'Был отправлен пустой файл либо при обработке файла произошла ошибка, дальнейшая обработка файла невозможна. Свяжитесь с технической поддержкой.',
                    $this->bitrixService->getDomain(),
                    $this->bitrixService->getAuthID()
                );

                logImport($this->uuid, [
                    'status' => 'Ошибка',
                    'events_history' => 'Массив уникальных товарных позиций пустой'
                ]);

                return false;
            }

            logImport($this->uuid, [
                'status' => 'Создание товаров',
                'events_history' => 'Запускается алгоритм массового поиска / создания товаров'
            ]);

            $bitrixProducts = $this->bitrixService->getOrCreateProducts($uniqueProducts, $this->uuid);

            logImport($this->uuid, [
                'status' => 'Создание товарных позиций',
                'events_history' => 'Запускается алгоритм массового создания товарных позиций'
            ]);

            $this->bitrixService->addProductRows(
                $uniqueProducts,
                $bitrixProducts,
                $this->uuid
            );

        } else {

            logImport($this->uuid, [
                'status' => 'Ошибка',
                'events_history' => 'Массив товарных позиций пустой'
            ]);

        }

        Cache::forget($this->bitrixService->getObjectId().'_' . $this->bitrixService->getDomain() . '_import_in_progress');

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

                logImport($this->uuid, [
                    'status' => 'Очистка',
                    'events_history' => 'Запуск очистки товарных позиций'
                ]);

                $this->bitrixService->clearProductRow(
                    $smartProcessDetail['SYMBOL_CODE_SHORT'],
                    $this->uuid
                );

                logImport($this->uuid, [
                    'status' => 'Очищено',
                    'events_history' => 'Товарные позиции успешно очищены'
                ]);

            },
            AfterImport::class => function(AfterImport $event) {

                logImport($this->uuid, [
                    'status' => 'Успех',
                    'events_history' => 'Импорт завершен'
                ]);

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
