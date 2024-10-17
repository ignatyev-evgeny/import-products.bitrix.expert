<?php

namespace App\Http\Controllers;

use App\Models\Integration;
use App\Models\IntegrationField;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BaseController extends Controller {

    public const PROPERTY_NAME_AVAILABLE = ['Артикул', 'Бренд'];
    public const EVENT_HANDLER_URL = 'https://import-products.bitrix.expert/event/handler';
    public const PLACEMENT_HANDLER_URL = 'https://import-products.bitrix.expert';
    public const PLACEMENT_HANDLER_NAME = 'Импорт товаров';

    /** Обработчик главной страницы, на которую пользователь попадает каждый раз когда открывает приложение */
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
            abort(404, "Интеграция не найдена. <br> Переустановите приложение.");
        }

        Cache::set($integration->id.'_AUTH_ID', $integration->access_key, $request->AUTH_EXPIRES);
        Cache::set($integration->id.'_DOMAIN', $request->DOMAIN, $request->AUTH_EXPIRES);
        Cache::set($integration->id.'_TYPE', $type, $request->AUTH_EXPIRES);
        Cache::set($integration->id.'_OBJECT_ID', $objectId, $request->AUTH_EXPIRES);

        $availablePlacements = $this->listProcess($integration->access_key, $request->DOMAIN);

        if(config('app.maintenance_mode') != "ON") {
            return view('errors.maintenanceMode');
        }

        if($request->DOMAIN != 'b24-906dpp.bitrix24.ru') {
            return view('index', [
                'availablePlacements' => $availablePlacements,
                'domain' => $request->DOMAIN,
                'objectID' => $objectId,
            ]);
        }

        return view('newIndex', [
            'availablePlacements' => $availablePlacements,
            'domain' => $request->DOMAIN,
            'objectID' => $objectId,
        ]);

    }

    /** Обработчик входящих событий от Битрикс24 */
    public function eventHandler(Request $request) {
        Log::channel('eventHandler')->debug(json_encode($request->all()));
        if(!empty($request['event']) && !empty($request['auth']['domain']) && $request['event'] == "ONAPPUNINSTALL") {
            Integration::where('domain', $request['auth']['domain'])->delete();
        }
    }

    /** Обработчик установки приложения */
    public function install(Request $request) {

        Log::channel('installApplication')->debug(json_encode($request->all()));

        $auth = $request->auth['access_token'];
        $refresh = $request->auth['refresh_token'];
        $domain = $request->auth['domain'];

        if(empty($auth) || empty($refresh) || empty($domain)) {
            $message = '$auth и/или $refresh и/или $domain не были определены. Request: '.json_encode($request->all());
            Log::channel('critical')->critical($message);
            abort(403, $message);
        }

        $property['article'] = $this->getOrCreateProductProperty("Артикул", $auth, $domain, 'article');
        $property['brand'] = $this->getOrCreateProductProperty("Бренд", $auth, $domain, 'brand');

        if(empty($property['article']) || empty($property['brand'])) {
            $message = "$domain - Ошибка при создании свойств товара";
            Log::channel('critical')->critical($message);
            abort(403, $message);
        }

        $availablePlacements = $this->getAvailablePlacements($auth, $domain);
        $availablePlacements[] = 'CRM_DEAL_DETAIL_TAB';

        foreach ($availablePlacements as $placement) {
            $placementBindResponse = $this->bindPlacement($placement, $auth, $domain);

            if (!empty($placementBindResponse['error']) && $placementBindResponse['error_description'] == 'Unable to set placement handler: Handler already binded') {
                $this->unbindPlacement($placement, $auth, $domain);
                $this->bindPlacement($placement, $auth, $domain);
            }
        }

        /** Установка событий для отправки со стороны Битрикс24 и обработки на стороне приложения */
        $this->setEventHandler($auth, "onCrmTypeAdd", $domain);
        $this->setEventHandler($auth, "OnAppUninstall", $domain);

        /** Создание или обновление записи об интеграции на стороне приложения */
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

    /** Функция для установки приложения в смарт процессы */
    private function bindPlacement(string $placement, string $auth, string $domain)
    {
        return $this->executeQuery(
            $domain,
            $auth,
            "placement.bind",
            "GET",
            [
                'auth' => $auth,
                'PLACEMENT' => $placement,
                'HANDLER' => self::PLACEMENT_HANDLER_URL,
                'LANG_ALL' => [
                    'ru' => [
                        'TITLE' => self::PLACEMENT_HANDLER_NAME,
                    ],
                ],
            ]
        );
    }

    private function getOrCreateProductProperty(string $name, string $auth, string $domain, string $type)
    {
        $integrationFields = IntegrationField::where('domain', $domain)->first();

        if(!empty($integrationFields->article) && !empty($integrationFields->brand)) {
            return match ($type) {
                'article' => $this->checkOrCreateProductProperty($integrationFields->article, $auth, $domain, $name, $type),
                'brand' => $this->checkOrCreateProductProperty($integrationFields->brand, $auth, $domain, $name, $type)
            };
        }

        $result = $this->executeQuery(
            $domain,
            $auth,
            "crm.product.property.add",
            "GET",
            [
                'auth' => $auth,
                'FIELDS' => [
                    'ACTIVE' => 'Y',
                    'NAME' => $name,
                    'PROPERTY_TYPE' => 'S',
                ]
            ]
        );

        if(!empty($result)) {
            IntegrationField::updateOrCreate([
                'domain' => $domain
            ], [
                $type => $result,
            ]);
        }

        return $result;
    }

    private function checkOrCreateProductProperty(int $property, string $auth, string $domain, string $name, string $type)
    {

        $propertyData = $this->executeQuery(
            $domain,
            $auth,
            "crm.product.property.get",
            "GET",
            [
                'auth' => $auth,
                'id' => $property,
            ]
        );

        if(!empty($propertyData) && in_array($propertyData['NAME'], self::PROPERTY_NAME_AVAILABLE) && $propertyData['ACTIVE'] = 'Y' && $propertyData['PROPERTY_TYPE'] == 'S') {
            return $propertyData['ID'];
        }

        $result = $this->executeQuery(
            $domain,
            $auth,
            "crm.product.property.add",
            "GET",
            [
                'auth' => $auth,
                'FIELDS' => [
                    'ACTIVE' => 'Y',
                    'NAME' => $name,
                    'PROPERTY_TYPE' => 'S',
                ]
            ]
        );

        if(!empty($result)) {
            IntegrationField::updateOrCreate([
                'domain' => $domain
            ], [
                $type => $result,
            ]);
        }

        return $result;

    }

    private function unbindPlacement(string $placement, string $auth, string $domain): void {
        $this->executeQuery(
            $domain,
            $auth,
            "placement.unbind",
            "GET",
            [
                'auth' => $auth,
                'PLACEMENT' => $placement
            ]
        );
    }

    private function listPlacement(string $auth, string $domain)
    {
        return $this->executeQuery(
            $domain,
            $auth,
            "placement.list",
            "GET",
            [
                'auth' => $auth,
            ]
        );
    }

    private function listProcess(string $auth, string $domain)
    {
        $response = $this->executeQuery(
            $domain,
            $auth,
            "crm.type.list",
            "GET",
            [
                'auth' => $auth,
            ]
        );

        $availablePlacements = [];

        foreach ($this->getAvailablePlacements($auth, $domain) as $value) {
            preg_match('/(\d+)/', $value, $matches);
            $entityTypeId = $matches[1];
            foreach ($response['types'] as $item) {
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

    private function getAvailablePlacements(string $auth, string $domain)
    {
        $pattern = '/^CRM_DYNAMIC_\d+_DETAIL_TAB$/';
        $placements = $this->listPlacement($auth, $domain);

        if(!empty($placements)) {
            return array_filter($placements, function($item) use ($pattern) {
                return preg_match($pattern, $item);
            });
        }

        return [];
    }

    private function setEventHandler(string $auth, string $event, string $domain): void
    {
        $this->executeQuery(
            $domain,
            $auth,
            "event.bind.json",
            "GET",
            [
                'auth' => $auth,
                'event' => $event,
                'handler' => self::EVENT_HANDLER_URL,
            ]
        );
    }

}
