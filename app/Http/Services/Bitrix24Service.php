<?php

namespace App\Http\Services;

use App\Http\Controllers\Controller;
use App\Models\Integration;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Bitrix24Service extends Controller
{

    private mixed $objectId;
    private mixed $type;
    private mixed $domain;
    private mixed $authId;
    private array $assigned;

    public function __construct()
    {
        $this->authId = Cache::get('AUTH_ID');
        $this->domain = Cache::get('DOMAIN');
        $this->type = Cache::get('TYPE');
        $this->objectId = Cache::get('OBJECT_ID');

        if(empty($this->authId) || empty($this->domain) || empty($this->type)){
            throw new Exception('AUTH_ID и/или DOMAIN и/или TYPE не были переданы. Свяжитесь с технической поддержкой.');
        }
    }

    public function getObjectId(): string
    {
        return $this->objectId;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getAssigned(): array
    {
        return $this->assigned ?? [1];
    }

    public function sendNotify(array $userIds, string $message): void {
        foreach ($userIds as $userId) {
            Http::post("https://{$this->domain}/rest/im.notify.system.add", [
                'auth' => $this->authId,
                'USER_ID' => $userId,
                'MESSAGE' => $message,
            ]);
        }
    }

    public function getOwnerType() {
        return Http::post("https://{$this->domain}/rest/crm.enum.ownertype", [
            'auth' => $this->authId,
        ])->json();
    }

    public function getProductRows(string $ownerType): array
    {
        $allProductRows = [];
        $start = 0;

        do {
            $response = Http::post("https://{$this->domain}/rest/crm.item.productrow.list", [
                'auth' => $this->authId,
                'filter' => [
                    "=ownerId" => $this->objectId,
                    "=ownerType" => $ownerType,
                ],
                'start' => $start,
            ]);

            $response = $response->json();

            if (isset($response['result']['productRows']) && is_array($response['result']['productRows'])) {
                $allProductRows = array_merge($allProductRows, $response['result']['productRows']);
            }

            $start = $response['next'] ?? null;

        } while ($start !== null);

        return $allProductRows;
    }

    public function getSmartProcessDetail(): array {
        $ownerTypes = $this->getOwnerType();

        if(empty($ownerTypes['result'])) {
            $this->sendNotify($this->getAssigned(),'Ошибка при получении ownerTypes. Свяжитесь с технической поддержкой.');
            throw new Exception($ownerTypes['error_description'] ?? $ownerTypes['error']);
        }

        $type = $this->type;
        $ownerTypes = $ownerTypes['result'];

        Log::channel('ownerTypes')->debug(json_encode($ownerTypes));

        $ownerType = array_filter($ownerTypes, function ($item) use ($type) {
            return $item['SYMBOL_CODE'] === $type;
        });

        if(empty($ownerType)){
            $this->sendNotify($this->getAssigned(),'Ошибка при получении SYMBOL_CODE. Свяжитесь с технической поддержкой.');
            throw new Exception('Ошибка при получении SYMBOL_CODE. Свяжитесь с технической поддержкой.');
        }

        $firstItem = reset($ownerType);

        if(empty($firstItem)) {
            $this->sendNotify($this->getAssigned(),'Ошибка при определении smartProcessDetail. Свяжитесь с технической поддержкой.');
            throw new Exception('Ошибка при определении smartProcessDetail. Свяжитесь с технической поддержкой.');
        }

        $response = Http::post("https://{$this->domain}/rest/crm.item.get", [
            'auth' => $this->authId,
            'entityTypeId' => $firstItem['ID'],
            'id' => $this->objectId,
        ]);

        $response = $response->json();

        if(empty($response['result']['item'])) {
            $this->sendNotify($this->getAssigned(),'Ошибка при получении детальной информации по смарт процессу. Свяжитесь с технической поддержкой.');
            throw new Exception('Ошибка при получении детальной информации по смарт процессу. Свяжитесь с технической поддержкой.');
        }

        $this->assigned = [
            $response['result']['item']['assignedById']
        ];
        
        return [
            'ID' => $firstItem['ID'],
            'SYMBOL_CODE_SHORT' => $firstItem['SYMBOL_CODE_SHORT'],
        ];
    }

    public function clearProductRow(string $ownerType): bool {

        do {

            $response = Http::post("https://{$this->domain}/rest/crm.item.productrow.list", [
                'auth' => $this->authId,
                'filter' => [
                    "=ownerId" => $this->objectId,
                    "=ownerType" => $ownerType,
                ]
            ])->json();

            if(!isset($response['result']['productRows']))
            {
                $this->sendNotify($this->assigned,'Ошибка при получении списка товарных позиций. Свяжитесь с технической поддержкой.');
                return false;
            }

            $productRows = $response['result']['productRows'];

            if(!empty($productRows)) {
                foreach ($productRows as $productRow) {
                    $this->deleteProductRow($productRow['id']);
                }
            }

        } while (!empty($productRows));

        $this->sendNotify($this->assigned,'Товарные позиции успешно очищены. Начинаю импортировать новые товарные позиции.');

        return true;
    }

    public function clearProductRowBatch(string $ownerType): bool
    {
        do {

            $response = Http::post("https://{$this->domain}/rest/crm.item.productrow.list", [
                'auth' => $this->authId,
                'filter' => [
                    "=ownerId" => $this->objectId,
                    "=ownerType" => $ownerType,
                ]
            ])->json();

            if (!isset($response['result']['productRows'])) {
                $this->sendNotify($this->assigned, 'Ошибка при получении списка товарных позиций. Свяжитесь с технической поддержкой.');
                return false;
            }

            $productRows = $response['result']['productRows'];

            if (!empty($productRows)) {
                $batchRequests = [];
                foreach ($productRows as $key => $productRow) {
                    $batchRequests["delete_{$key}"] = [
                        'method' => 'crm.item.productrow.delete',
                        'params' => [
                            'id' => $productRow['id'],
                        ]
                    ];
                }

                $batchResponse = Http::post("https://{$this->domain}/rest/batch", [
                    'auth' => $this->authId,
                    'cmd' => $batchRequests,
                ])->json();

                if (!isset($batchResponse['result']) || !empty($batchResponse['result']['error'])) {
                    $this->sendNotify($this->assigned, 'Ошибка при удалении товарных позиций. Свяжитесь с технической поддержкой.');
                    return false;
                }
            }

        } while (!empty($productRows));

        $this->sendNotify($this->assigned, 'Товарные позиции успешно очищены. Начинаю импортировать новые товарные позиции.');

        return true;
    }

    public function deleteProductRow(int $productRow): void
    {

        $response = Http::post("https://{$this->domain}/rest/crm.item.productrow.delete", [
            'auth' => $this->authId,
            'id' => $productRow,
        ]);

        //Log::channel('deleteProductRow')->debug('REQUEST: '.$productRow);
        //Log::channel('deleteProductRow')->debug('STATUS: '.$response->status());
        $response = $response->json();
        //Log::channel('deleteProductRow')->debug('RESPONSE: '.json_encode($response).PHP_EOL);

        if(!array_key_exists('result', $response)) {
            $this->sendNotify($this->assigned,'Ошибка при удалении товарной позиции. Попробуйте снова или свяжитесь с технической поддержкой.');
        }
    }

    public function productDetail(int $productId) {
        $response = Http::post("https://{$this->domain}/rest/crm.product.get", [
            'auth' => $this->authId,
            'id' => $productId,
        ])->json();

        if(empty($response['result'])) {
            Log::channel('importProduct')->debug('Ошибка при получении информации о продукте: '.json_encode($response));
            return null;
        }

        return $response['result'] ?? null;
    }

    public function productFields() {
        $response = Http::post("https://{$this->domain}/rest/crm.product.fields", [
            'auth' => $this->authId,
        ])->json();

        if(empty($response['result'])) {
            Log::channel('importProduct')->debug('Ошибка при получении списка продуктов : '.json_encode($response));
            return null;
        }

        return $response['result'] ?? null;
    }

    public function addProduct(array $data): ?int {
        $integration = Integration::where('domain', $this->domain)->first();

        $response = Http::post("https://{$this->domain}/rest/crm.product.add", [
            'auth' => $this->authId,
            'fields' => [
                "NAME" => $data['name'],
                "CURRENCY_ID" => "RUB",
                "PRICE" => $data['price'],
                "PROPERTY_".$integration->product_field_article => $data['article'],
                "PROPERTY_".$integration->product_field_brand => $data['brand'],
            ]
        ])->json();

        if(empty($response['result'])) {
            Log::channel('importProduct')->debug('Ошибка при добавлении продукта : '.json_encode($response));
            $this->sendNotify($this->assigned,'Ошибка при добавлении продукта. Свяжитесь с технической поддержкой.');
            return null;
        }

        return $response['result'] ?? null;
    }

    public function searchProduct(array $data): ?int {

        $integration = Integration::where('domain', $this->domain)->first();

        $searchArray = [
            "NAME" => $data['name']
        ];

        if (!empty($data['article'])) {
            $searchArray["PROPERTY_".$integration->product_field_article] = $data['article'];
        }

        if (!empty($data['brand'])) {
            $searchArray["PROPERTY_".$integration->product_field_brand] = $data['brand'];
        }

        $response = Http::post("https://{$this->domain}/rest/crm.product.list", [
            'auth' => $this->authId,
            'filter' => $searchArray
        ])->json();

        Log::channel('searchProduct')->debug('DOMAIN : '.$this->domain);
        Log::channel('searchProduct')->debug('REQUEST : '.json_encode($searchArray));
        Log::channel('searchProduct')->debug('RESPONSE : '.json_encode($response));

        if(!isset($response['result'])) {
            Log::channel('searchProduct')->debug('Ошибка при поиске продукта : '.json_encode($response));
            $this->sendNotify($this->assigned,'Ошибка при поиске продукта. Свяжитесь с технической поддержкой.');
            return null;
        }

        if(count($response['result']) > 1) {
            Log::channel('searchProduct')->debug('Продукт с именем "' . $data['name'] . '" уже существует, в кол-ве больше одной единицы. Дальнейший импорт невозможен.');
            $this->sendNotify($this->assigned,'Продукт с именем "' . $data['name'] . '" уже существует, в кол-ве больше одной единицы. Дальнейший импорт невозможен.');
            return null;
        }

        if(empty($response['result'])) {
            $product = $this->addProduct($data);
        }

        if(empty($product) && empty($response['result'][0]['ID'])) {
            Log::channel('searchProduct')->debug('Ошибка при поиске и/или создании продукта. Свяжитесь с технической поддержкой.');
            $this->sendNotify($this->assigned,'Ошибка при поиске и/или создании продукта. Свяжитесь с технической поддержкой.');
            return null;
        }

        $product = !empty($product) ? $product : (int) $response['result'][0]['ID'];

        return $product;
    }

    public function deleteProduct(int $productId): ?object {
        return Http::post("https://{$this->domain}/rest/crm.product.delete", [
            'auth' => $this->authId,
            'id' => $productId,
        ])->object();
    }

    public function addProductRow(array $data): ?array {
        $response = Http::post("https://{$this->domain}/rest/crm.item.productrow.add", [
            'auth' => $this->authId,
            'fields' => [
                "ownerId" => $data['ownerId'],
                "ownerType" => $data['ownerType'],
                "productId" => $data['productId'],
                "price" => $data['price'],
                "quantity" => $data['quantity'],
            ]
        ])->json();
        //Log::channel('importProductRow')->debug('NEW PRODUCT ROW: '.json_encode($response));
        return $response['result'];
    }

}