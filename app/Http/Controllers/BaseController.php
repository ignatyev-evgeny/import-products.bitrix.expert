<?php

namespace App\Http\Controllers;

use App\Http\Services\Bitrix24Service;
use App\Models\Integration;
use App\Models\IntegrationField;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BaseController extends Controller {

    public const PROPERTY_NAME_AVAILABLE = ['Артикул', 'Бренд'];

    public function index(Request $request)
    {

        if (preg_match('/(DYNAMIC_\d+|CRM_DEAL_DETAIL_TAB)/', $request->PLACEMENT, $matches)) {
            $type = match ($matches[0]) {
                'CRM_DEAL_DETAIL_TAB' => 'DEAL',
                default => $matches[0]
            };
        }

        if(empty($type) || empty($request->PLACEMENT_OPTIONS)) {
            abort(403, "Вызов запрещен вне рамок смарт процесса");
        }

        $objectId = json_decode($request->PLACEMENT_OPTIONS, true)['ID'];

        if(empty($objectId)) {
            abort(404, "objectId не определен");
        }

        $integration = Integration::where('domain', $request->DOMAIN)->first();

        if(empty($integration)) {
            abort(404, "Интеграция не найдена. Переустановите приложение.");
        }

        Cache::set('AUTH_ID', $integration->access_key, $request->AUTH_EXPIRES);
        Cache::set('DOMAIN', $request->DOMAIN, $request->AUTH_EXPIRES);
        Cache::set('TYPE', $type, $request->AUTH_EXPIRES);
        Cache::set('OBJECT_ID', $objectId, $request->AUTH_EXPIRES);

        $availablePlacements = $this->listProcess($integration->access_key, $request->DOMAIN);

        if($request->DOMAIN != 'eugenekulakov.bitrix24.ru111') {
            return view('index', [
                'availablePlacements' => $availablePlacements,
            ]);
        }

        return view('newIndex', [
            'availablePlacements' => $availablePlacements,
        ]);

    }

    public function eventHandler(Request $request) {
        Log::channel('eventHandler')->debug(json_encode($request->all()));

        if(!empty($request['event']) && !empty($request['auth']['domain']) && $request['event'] == "ONAPPUNINSTALL") {
            Integration::where('domain', $request['auth']['domain'])->delete();
        }

    }

    public function install(Request $request) {

        Log::channel('installApplication')->debug(json_encode($request->all()));

        $auth = $request->AUTH_ID ?? $request->auth['access_token'];
        $refresh = $request->auth['refresh_token'] ?? null;
        $domain = $request->DOMAIN ?? $request->auth['domain'];

        if(empty($auth) || empty($domain)) {
            abort(403, 'AUTH_ID и/или DOMAIN и/или TYPE не были переданы. Свяжитесь с технической поддержкой.');
        }

        $property['article'] = $this->getOrCreateProductProperty("Артикул", $auth, $domain, 'article');
        $property['brand'] = $this->getOrCreateProductProperty("Бренд", $auth, $domain, 'brand');

        if(empty($property['article']) || empty($property['brand'])) {
            abort(404, "Ошибка при создании свойств товара");
        }

        $availablePlacements = $this->getAvailablePlacements($auth, $domain);

        $availablePlacements[] = 'CRM_DEAL_DETAIL_TAB';

        Log::channel('placementList')->debug(json_encode($availablePlacements));


        foreach ($availablePlacements as $placement) {
            $placementBindResponse = $this->bindPlacement($placement, $auth, $domain);
            if (!empty($placementBindResponse->error) && $placementBindResponse->error_description == 'Unable to set placement handler: Handler already binded') {
                $this->unbindPlacement($placement, $auth, $domain);
                $this->bindPlacement($placement, $auth, $domain);
            }
        }

        $this->setEventHandler($auth, "onCrmTypeAdd", $domain);
        $this->setEventHandler($auth, "OnAppUninstall", $domain);

        Integration::updateOrCreate([
            'domain' => $domain
        ], [
            'access_key' => $auth,
            'refresh_key' => $refresh,
            'product_field_article' => $property['article'],
            'product_field_brand' => $property['brand'],
        ]);

        return view('install');
    }

    private function bindPlacement(string $placement, string $auth, string $domain)
    {
        return Http::get("https://{$domain}/rest/placement.bind", [
            'auth' => $auth,
            'PLACEMENT' => $placement,
            'HANDLER' => 'https://import-products.bitrix.expert',
            'LANG_ALL' => [
                'ru' => [
                    'TITLE' => 'Импорт товаров',
                ],
            ],
        ])->object();
    }

    private function getOrCreateProductProperty(string $name, string $auth, string $domain, string $type)
    {

        $integrationFields = IntegrationField::where('domain', $domain)->first();

        if(!empty($integrationFields->article) && !empty($integrationFields->brand)) {
            return match ($type) {
                'article' => $this->checkIssetProductProperty($integrationFields->article, $auth, $domain, $name, $type),
                'brand' => $this->checkIssetProductProperty($integrationFields->brand, $auth, $domain, $name, $type)
            };
        }

        $response = Http::get("https://{$domain}/rest/crm.product.property.add", [
            'auth' => $auth,
            'FIELDS' => [
                'ACTIVE' => 'Y',
                'NAME' => $name,
                'PROPERTY_TYPE' => 'S',
            ]
        ]);

        if ($response->failed()) {
            throw new Exception("Ошибка соединения с порталом {$domain}");
        }

        $result = $response->json()['result'];

        IntegrationField::updateOrCreate([
            'domain' => $domain
        ], [
            $type => $result,
        ]);

        return $result;
    }

    private function checkIssetProductProperty(int $property, string $auth, string $domain, string $name, string $type)
    {

        $response = Http::get("https://{$domain}/rest/crm.product.property.get", [
            'auth' => $auth,
            'id' => $property,
        ]);


        if ($response->failed()) {
            throw new Exception("Ошибка соединения с порталом {$domain}");
        }

        $propertyData = $response->json()['result'];


        if(!empty($propertyData) && in_array($propertyData['NAME'], self::PROPERTY_NAME_AVAILABLE) && $propertyData['ACTIVE'] = 'Y' && $propertyData['PROPERTY_TYPE'] == 'S') {
            return $propertyData['ID'];
        }

        $response = Http::get("https://{$domain}/rest/crm.product.property.add", [
            'auth' => $auth,
            'FIELDS' => [
                'ACTIVE' => 'Y',
                'NAME' => $name,
                'PROPERTY_TYPE' => 'S',
            ]
        ]);

        if ($response->failed()) {
            throw new Exception("Ошибка соединения с порталом {$domain}");
        }

        $result = $response->json()['result'];

        IntegrationField::updateOrCreate([
            'domain' => $domain
        ], [
            $type => $result,
        ]);

        return $result;

    }

    private function unbindPlacement(string $placement, string $auth, string $domain)
    {

        $requestData = [
            'name' => $placement,
            'auth' => $auth,
            'domain' => $domain
        ];

        Log::channel('unbindPlacement')->debug("REQUEST: ".json_encode($requestData));

        $result = Http::get("https://{$domain}/rest/placement.unbind", [
            'auth' => $auth,
            'PLACEMENT' => $placement,
        ]);

        $result = $result->object();

        Log::channel('unbindPlacement')->debug("RESPONSE: ".json_encode($result));
        return $result;
    }

    private function listPlacement(string $auth, string $domain)
    {
        return Http::get("https://{$domain}/rest/placement.list", [
            'auth' => $auth,
        ])->object();
    }

    private function listProcess(string $auth, string $domain)
    {
        $response = Http::get("https://{$domain}/rest/crm.type.list", [
            'auth' => $auth,
        ]);

        if ($response->failed()) {
            throw new Exception("Ошибка соединения с порталом {$domain}");
        }

        $availablePlacements = [];

        foreach ($this->getAvailablePlacements($auth, $domain) as $key => $value) {
            preg_match('/(\d+)/', $value, $matches);
            $entityTypeId = $matches[1];
            foreach ($response->json()['result']['types'] as $item) {
                if ($item['entityTypeId'] == $entityTypeId) {
                    $availablePlacements[$value] = [
                        'id' => $item['id'],
                        'title' => $item['title'],
                        'entityTypeId' => $item['entityTypeId']
                    ];
                    break;
                }
            }
        }

        return $availablePlacements;

    }



    private function getAvailablePlacements(string $auth, string $domain) {
        $placementList = $this->listPlacement($auth, $domain);

        $pattern = '/^CRM_DYNAMIC_\d+_DETAIL_TAB$/';
        $availablePlacements = array_filter($placementList->result, function($item) use ($pattern) {
            return preg_match($pattern, $item);
        });

        return $availablePlacements;
    }

    private function setEventHandler(string $auth, string $event, string $domain)
    {
        return Http::get("https://{$domain}/rest/event.bind.json", [
            'auth' => $auth,
            'event' => $event,
            'handler' => 'https://import-products.bitrix.expert/event/handler',
        ])->object();
    }

}
