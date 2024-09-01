<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ImportToBitrix24 implements ToCollection, WithHeadingRow
{
    /**
    * @param Collection $collection
    */
    public function collection(Collection $collection)
    {
        $authId = Cache::get('AUTH_ID');
        $domain = Cache::get('DOMAIN');
        $type = Cache::get('TYPE');
        $objectId = Cache::get('OBJECT_ID');

        if(empty($authId) || empty($domain) || empty($type)){
            throw new \Exception('AUTH_ID и/или DOMAIN и/или TYPE не были переданы. Свяжитесь с технической поддержкой.');
        }

        $smartProcessDetail = $this->getSmartProcessDetail($authId, $domain, $type);

        if(empty($smartProcessDetail)){
            throw new \Exception('Ошибка при определении smartProcessDetail. Свяжитесь с технической поддержкой.');
        }

        foreach ($collection as $row) {

            $data['addProduct'] = [
                'name' => $row['naimenovanie'],
                'price' => $row['cena']
            ];

            $product = $this->addProduct($data['addProduct'], $authId, $domain);

            if(empty($product->result)) {
                throw new \Exception('Ошибка при добавлении продукта. Свяжитесь с технической поддержкой.');
            }

            $data['addProductRow'] = [
                'ownerId' => $objectId,
                'ownerType' => $smartProcessDetail['SYMBOL_CODE_SHORT'],
                'productId' => $product->result,
                'price' => $row['cena'],
                'quantity' => $row['kol_vo']
            ];

            $this->addProductRow($data['addProductRow'], $authId, $domain);

            //$deleteProduct = $this->deleteProduct($product->result, $authId, $domain);
            //dd($data['addProductRow'], $productRow, $deleteProduct);
        }
    }

    private function addProduct(array $data, string $auth, string $domain): ?object {
        return Http::post("https://{$domain}/rest/crm.product.add", [
            'auth' => $auth,
            'fields' => [
                "NAME" => $data['name'],
			    "CURRENCY_ID" => "RUB",
			    "PRICE" => $data['price'],
            ]
        ])->object();
    }

    private function deleteProduct(int $productId, string $auth, string $domain): ?object {
        return Http::post("https://{$domain}/rest/crm.product.delete", [
            'auth' => $auth,
            'id' => $productId,
        ])->object();
    }

    private function addProductRow(array $data, string $auth, string $domain): ?object {
        return Http::post("https://{$domain}/rest/crm.item.productrow.add", [
            'auth' => $auth,
            'fields' => [
                "ownerId" => $data['ownerId'],
                "ownerType" => $data['ownerType'],
                "productId" => $data['productId'],
                "price" => $data['price'],
                "quantity" => $data['quantity'],
            ]
        ])->object();
    }

    private function getOwnerType(string $auth, string $domain) {
        return Http::post("https://{$domain}/rest/crm.enum.ownertype", [
            'auth' => $auth,
        ])->json();
    }

    private function getSmartProcessDetail(string $auth, string $domain, string $type): array {
        $ownerTypes = $this->getOwnerType($auth, $domain);
        if(empty($ownerTypes['result'])){
            throw new \Exception('Ошибка при получении ownerTypes. Свяжитесь с технической поддержкой.');
        }
        $ownerTypes = $ownerTypes['result'];
        $ownerType = array_filter($ownerTypes, function ($item) use ($type) {
            return $item['SYMBOL_CODE'] === $type;
        });
        if(empty($ownerType)){
            throw new \Exception('Ошибка при получении SYMBOL_CODE. Свяжитесь с технической поддержкой.');
        }
        $firstItem = reset($ownerType);
        return [
            'ID' => $firstItem['ID'],
            'SYMBOL_CODE_SHORT' => $firstItem['SYMBOL_CODE_SHORT'],
        ];
    }

}
