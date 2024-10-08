<?php

namespace App\Http\Controllers;

use App\Http\Services\Bitrix24Service;
use App\Models\Integration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BaseController extends Controller {
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

        return view('index');
    }

    public function eventHandler(Request $request) {
        Log::channel('eventHandler')->debug(json_encode($request->all()));

        if(!empty($request['event']) && !empty($request['auth']['domain']) && $request['event'] == "ONAPPUNINSTALL") {
            Integration::where('domain', $request['auth']['domain'])->delete();
        }

    }

    public function install(Request $request) {

//        dd($request->all());
//abort(200);

        Log::channel('installApplication')->debug(json_encode($request->all()));

        $auth = $request->AUTH_ID ?? $request->auth['access_token'];
        $refresh = $request->auth['refresh_token'] ?? null;
        $domain = $request->DOMAIN ?? $request->auth['domain'];

        if(empty($auth) || empty($domain)) {
            abort(403, 'AUTH_ID и/или DOMAIN и/или TYPE не были переданы. Свяжитесь с технической поддержкой.');
        }

        $property['article'] = $this->createProductProperty("Артикул", $auth, $domain);
        $property['brand'] = $this->createProductProperty("Бренд", $auth, $domain);

        if(empty($property['article']['result']) || empty($property['brand']['result'])) {
            abort(404, "Ошибка при создании свойств товара");
        }

        $placementList = $this->listPlacement($auth, $domain);

        $pattern = '/^CRM_DYNAMIC_\d+_DETAIL_TAB$/';
        $availablePlacements = array_filter($placementList->result, function($item) use ($pattern) {
            return preg_match($pattern, $item);
        });

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
            'product_field_article' => $property['article']['result'],
            'product_field_brand' => $property['brand']['result'],
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

    private function createProductProperty(string $name, string $auth, string $domain)
    {

        $requestData = [
            'name' => $name,
            'auth' => $auth,
            'domain' => $domain
        ];

        Log::channel('createProductProperty')->debug("REQUEST: ".json_encode($requestData));

        $result = Http::get("https://{$domain}/rest/crm.product.property.add", [
            'auth' => $auth,
            'FIELDS' => [
                'ACTIVE' => 'Y',
                'NAME' => $name,
                'PROPERTY_TYPE' => 'S',
            ]
        ]);

        $result = $result->json();

        Log::channel('createProductProperty')->debug("RESPONSE: ".json_encode($result));
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

    private function getPlacement(string $auth, string $domain)
    {
        return Http::get("https://{$domain}/rest/placement.get", [
            'auth' => $auth,
        ])->object();
    }

    private function listPlacement(string $auth, string $domain)
    {
        return Http::get("https://{$domain}/rest/placement.list", [
            'auth' => $auth,
        ])->object();
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
