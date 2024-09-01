<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BaseController extends Controller {
    public function index(Request $request)
    {

        if (preg_match('/DYNAMIC_\d+/', $request->PLACEMENT, $matches)) {
            $type = $matches[0];
        }

        if(empty($type) || empty($request->PLACEMENT_OPTIONS)) {
            abort(403, "Вызов запрещен вне рамок смарт процесса");
        }

        $objectId = json_decode($request->PLACEMENT_OPTIONS, true)['ID'];

        if(empty($objectId)) {
            abort(404, "objectId не определен");
        }

        Cache::set('AUTH_ID', $request->AUTH_ID, $request->AUTH_EXPIRES);
        Cache::set('DOMAIN', $request->DOMAIN, $request->AUTH_EXPIRES);
        Cache::set('TYPE', $type, $request->AUTH_EXPIRES);
        Cache::set('OBJECT_ID', $objectId, $request->AUTH_EXPIRES);
        return view('index');
    }

    public function eventHandler(Request $request) {
        Log::debug(json_encode($request->all()));
    }

    public function install(Request  $request) {

        $auth = $request->AUTH_ID;
        $domain = $request->DOMAIN;

        $placementList = $this->listPlacement($auth, $domain);

        $pattern = '/^CRM_DYNAMIC_\d+_DETAIL_TAB$/';
        $availablePlacements = array_filter($placementList->result, function($item) use ($pattern) {
            return preg_match($pattern, $item);
        });

        foreach ($availablePlacements as $placement) {
            $placementBindResponse = $this->bindPlacement($placement, $auth, $domain);
            if (!empty($placementBindResponse->error) && $placementBindResponse->error_description == 'Unable to set placement handler: Handler already binded') {
                $this->unbindPlacement($placement, $auth, $domain);
                $this->bindPlacement($placement, $auth, $domain);
            }
        }

        $this->setEventHandler($auth, "onCrmTypeAdd", $domain);

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
                    'TITLE' => 'Импорт24',
                ],
            ],
        ])->object();
    }

    private function unbindPlacement(string $placement, string $auth, string $domain)
    {
        return Http::get("https://{$domain}/rest/placement.unbind", [
            'auth' => $auth,
            'PLACEMENT' => $placement,
        ])->object();
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
