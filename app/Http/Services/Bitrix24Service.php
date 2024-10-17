<?php

namespace App\Http\Services;

use App\Http\Controllers\Controller;
use App\Models\Integration;
use Illuminate\Support\Facades\Cache;

class Bitrix24Service extends Controller
{

    private mixed $objectId;
    private mixed $type;
    private mixed $domain;
    private mixed $authId;
    private array $assigned;
    private int $batchSize;
    private Integration $integration;

    public function __construct(string $domain)
    {
        $integration = Integration::where('domain', $domain)->first();

        if(empty($integration)) {
            abort(
                404,
                "Интеграция не найдена. Переустановите приложение."
            );
        }

        $this->authId = Cache::get($integration->id.'_AUTH_ID');
        $this->domain = Cache::get($integration->id.'_DOMAIN');
        $this->type = Cache::get($integration->id.'_TYPE');
        $this->objectId = Cache::get($integration->id.'_OBJECT_ID');
        $this->batchSize = 50;
        $this->integration = $integration;

        if(empty($this->authId) || empty($this->domain) || empty($this->type)){
           abort(
               403,
               "AUTH_ID и/или DOMAIN и/или TYPE не были переданы.<br>Свяжитесь с технической поддержкой"
           );
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
    public function getAuthID(): string
    {
        return $this->authId;
    }
    public function getAssigned(): array
    {
        return $this->assigned ?? [1];
    }


    public function getProductRows(string $ownerType): array
    {
        $allProductRows = [];
        $start = 0;

        do {
            $response = $this->executeQuery(
                $this->domain,
                $this->authId,
                'crm.item.productrow.list',
                'POST',
                [
                    'auth' => $this->authId,
                    'filter' => [
                        "=ownerId" => $this->objectId,
                        "=ownerType" => $ownerType,
                    ],
                    'start' => $start,
                ],
                false,
                null,
                null,
                true
            );

            if (isset($response['result']['productRows']) && is_array($response['result']['productRows'])) {
                $allProductRows = array_merge($allProductRows, $response['result']['productRows']);
            }

            $start = $response['next'] ?? null;

        } while ($start !== null);

        return $allProductRows;
    }

    public function getSmartProcessDetail(): array {
        $ownerTypes = $this->getOwnerType();

        if(empty($ownerTypes)) {

            $message = 'Ошибка при получении ownerTypes. Свяжитесь с технической поддержкой.';

            $this->sendNotify(
                $this->getAssigned(),
                $message,
                $this->domain,
                $this->authId
            );
            abort(
                400,
                $message
            );
        }

        $type = $this->type;

        $ownerType = array_filter($ownerTypes, function ($item) use ($type) {
            return $item['SYMBOL_CODE'] === $type;
        });

        if(empty($ownerType)){
            $this->sendNotify(
                $this->getAssigned(),
                'Ошибка при получении SYMBOL_CODE. Свяжитесь с технической поддержкой.',
                $this->domain,
                $this->authId
            );

            abort(
                404,
                'Ошибка при получении SYMBOL_CODE. Свяжитесь с технической поддержкой.'
            );
        }

        $firstItem = reset($ownerType);

        if(empty($firstItem)) {
            $this->sendNotify(
                $this->getAssigned(),
                'Ошибка при определении smartProcessDetail. Свяжитесь с технической поддержкой.',
                $this->domain,
                $this->authId
            );

            abort(
                404,
                'Ошибка при определении smartProcessDetail. Свяжитесь с технической поддержкой.'
            );
        }

        $response = $this->executeQuery(
            $this->domain,
            $this->authId,
            'crm.item.get',
            'POST',
            [
                'auth' => $this->authId,
                'entityTypeId' => $firstItem['ID'],
                'id' => $this->objectId,
            ]
        );

        if(empty($response['item'])) {
            $this->sendNotify(
                $this->getAssigned(),
                'Ошибка при получении детальной информации по смарт процессу. Свяжитесь с технической поддержкой.',
                $this->domain,
                $this->authId
            );

            abort(
                404,
                'Ошибка при получении детальной информации по смарт процессу. Свяжитесь с технической поддержкой.'
            );
        }

        $this->assigned = [
            $response['item']['assignedById']
        ];
        
        return [
            'ID' => $firstItem['ID'],
            'SYMBOL_CODE_SHORT' => $firstItem['SYMBOL_CODE_SHORT'],
        ];
    }

    public function clearProductRow(string $ownerType): void
    {
        do {

            $response = $this->executeQuery(
                $this->domain,
                $this->authId,
                'crm.item.productrow.list',
                'POST',
                [
                    'auth' => $this->authId,
                    'filter' => [
                        "=ownerId" => $this->objectId,
                        "=ownerType" => $ownerType,
                    ]
                ],
                true,
                "Ошибка соединения с порталом. $this->domain",
                $this->assigned
            );

            if(!isset($response['productRows'])) {
                $this->sendNotify(
                    $this->assigned,
                    'Ошибка при получении списка товарных позиций. Свяжитесь с технической поддержкой.',
                    $this->domain,
                    $this->authId
                );
                break;
            }

            if(!empty($response['productRows'])) {
                $this->batchDeleteProductRows($response['productRows'], $this->authId);
            }

        } while (!empty($response['productRows']));
    }

    public function productDetail(int $productId) {
        $response = $this->executeQuery(
            $this->domain,
            $this->authId,
            'crm.product.get',
            'POST',
            [
                'auth' => $this->authId,
                'id' => $productId,
            ]
        );
        return $response ?? [];
    }

    public function getOrCreateProducts(array $data): array
    {

        $productData = $batchRequests = $needToCreateProducts = [];

        foreach ($data as $key => $row) {
            $filterArray = [
                "filter[NAME]" => $row['name']
            ];

            if (!empty($row['article'])) {
                $filterArray["filter[PROPERTY_".$this->integration->product_field_article."]"] = $row['article'];
            }

            if (!empty($row['brand'])) {
                $filterArray["filter[PROPERTY_".$this->integration->product_field_brand."]"] = $row['brand'];
            }

            $filterArray["select"] = ["ID", "NAME", "PROPERTY_*"];

            $queryString = http_build_query($filterArray);

            $batchRequests["product_$key"] = "crm.product.list?auth={$this->integration->access_key}&$queryString";
            $productData["product_$key"] = $row;
        }

        $searchProductResult = $this->executeBatch($batchRequests, 'searchProducts');

        foreach ($searchProductResult as $key => $product) {
            if(empty($product)) {
                $needToCreateProducts[$key] = $productData[$key];
            }
        }

        $this->batchAddProduct($needToCreateProducts);

        return $this->executeBatch($batchRequests, 'getProducts');
    }

    public function addProductRows(array $products, array $bitrixProducts): void
    {

        $properties = [
            'article' => $this->integration->product_field_article,
            'brand' => $this->integration->product_field_brand
        ];

        $matchProducts = $this->matchProducts($products, $bitrixProducts, $properties);

        if(is_array($matchProducts)) {
            foreach ($matchProducts as $key => $product) {
                $addArray = [
                    "fields[ownerId]" => $product['ownerId'],
                    "fields[ownerType]" => $product['ownerType'],
                    "fields[productId]" => $product['productId'],
                    "fields[price]" => $product['price'],
                    "fields[quantity]" => $product['quantity'],
                ];
                $queryString = http_build_query($addArray);
                $batchRequests["productrow_add_$key"] = "crm.item.productrow.add?auth={$this->integration->access_key}&$queryString";
            }
            if(!empty($batchRequests)) {
                $this->executeBatch($batchRequests, 'addProductRows');
            }
        }

    }

    private function executeBatch(array $batch, string $type): array
    {
        $chunks = array_chunk($batch, $this->batchSize, true);

        $errorReason = match ($type) {
            'getProducts' => 'При получении товаров',
            'searchProducts' => 'При поиске товаров',
            'addProducts' => 'При создании товара',
            'deleteProductRows' => 'При удалении товарных позиций',
            'addProductRows' => 'При добавлении товарных позиций',
            default => null
        };

        $allResults = [];

        foreach ($chunks as $chunk) {

            $response = $this->executeQuery(
                $this->domain,
                $this->authId,
                'batch',
                'POST',
                [
                    'auth' => $this->authId,
                    'halt' => 0,
                    'cmd' => $chunk,
                ],
                true,
                "Ошибка соединения с порталом - $errorReason - $this->domain",
                $this->assigned
            );

            if (isset($response) && is_array($response)) {
                $allResults = array_merge_recursive($allResults, $response);
            }

        }

        return flattenArray($allResults);
    }

    private function batchAddProduct(array $data): void
    {
        foreach ($data as $key => $row) {

            $addArray = [
                "fields[NAME]" => $row['name'],
                "fields[CURRENCY_ID]" => "RUB",
                "fields[PRICE]" => $row['price'],
            ];

            if(!empty($row['article'])) {
                $addArray['fields[PROPERTY_'.$this->integration->product_field_article.']'] = $row['article'];
            }

            if(!empty($row['brand'])) {
                $addArray['fields[PROPERTY_'.$this->integration->product_field_brand.']'] = $row['brand'];
            }

            $queryString = http_build_query($addArray);

            $batchRequests["product_add_$key"] = "crm.product.add?auth={$this->integration->access_key}&$queryString";

        }

        if(!empty($batchRequests)) {
            $this->executeBatch($batchRequests, 'addProducts');
        }
    }

    private function batchDeleteProductRows(array $productRows, string $accessKey): void
    {
        foreach ($productRows as $key => $row) {
            $batchRequests["productrow_delete_$key"] = "crm.item.productrow.delete?auth=$accessKey&id=".$row['id'];
        }

        if(!empty($batchRequests)) {
            $this->executeBatch($batchRequests, 'deleteProductRows');
        }
    }

    private function matchProducts($firstArray, $secondArray, array $properties): false|array {
        foreach ($firstArray as &$firstProduct) {

            $matchedProducts = array_filter($secondArray, function ($secondProduct) use ($firstProduct, $properties) {
                $matchByName = $firstProduct['name'] === $secondProduct['NAME'];
                $matchByArticle = empty($firstProduct['article']) || $firstProduct['article'] === ($secondProduct['PROPERTY_'.$properties['article']]['value'] ?? null);
                $matchByBrand = empty($firstProduct['brand']) || $firstProduct['brand'] === ($secondProduct['PROPERTY_'.$properties['brand']]['value'] ?? null);
                return $matchByName && $matchByArticle && $matchByBrand;
            });

            if (count($matchedProducts) > 1) {
                $this->sendNotify(
                    $this->assigned,
                    "Найдено несколько совпадений для товара: {$firstProduct['name']}",
                    $this->domain,
                    $this->authId
                );
                return false;
            }

            if (count($matchedProducts) === 1) {
                $matchedProduct = reset($matchedProducts);
                $firstProduct['productId'] = $matchedProduct['ID'];
            }
        }

        return $firstArray;
    }

    private function getOwnerType() {
        return $this->executeQuery(
            $this->domain,
            $this->authId,
            'crm.enum.ownertype',
            'POST',
            [
                'auth' => $this->authId
            ]
        );
    }

}