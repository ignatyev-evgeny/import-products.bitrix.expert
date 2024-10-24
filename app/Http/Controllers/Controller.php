<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class Controller
{
    public function executeQuery(
        string $domain,
        string $authID,
        string $endpoint,
        string $method,
        array $data,
        bool $notify = false,
        string $message = null,
        $assigned = null,
        $pagination = false,
        $type = null,
        $recursive = false,
        $uuid = null,
    ) {
        $request = match ($method) {
            'GET' => Http::timeout(120)->get("https://$domain/rest/$endpoint", $data),
            'POST' => Http::timeout(120)->post("https://$domain/rest/$endpoint", $data),
        };

        if ($request->failed()) {

            $logData = [
                'request' => [
                    'domain' => $domain,
                    'authID' => $authID,
                    'endpoint' => $endpoint,
                    'method' => $method,
                    'data' => $data,
                ],
                'response' => $request->json(),
            ];
            Log::channel('critical')->critical(json_encode($logData));

            $message = empty($message) ? "Ошибка соединения с порталом $domain" : $message;

            if($notify && !empty($assigned)) {
                $this->sendNotify(
                    $assigned,
                    $message,
                    $domain,
                    $authID
                );
            }

            if(!empty($uuid)) {
                logImport($uuid, [
                    'status' => 'Ошибка',
                    'events_history' => $message
                ]);
            }

            return [];
        }

        if($pagination) {
            return $request->json();
        }

        if($recursive) {
            return $request->json()['result']['result'];
        }

        return $request->json()['result'];
    }

    public function sendNotify(
        array $userIds,
        string $message,
        string $domain,
        string $authID
    ): void {
        foreach ($userIds as $userId) {
            $this->executeQuery(
                $domain,
                $authID,
                "im.notify.system.add",
                "POST",
                [
                    'auth' => $authID,
                    'USER_ID' => $userId,
                    'MESSAGE' => $message,
                ]
            );
        }
    }
}
