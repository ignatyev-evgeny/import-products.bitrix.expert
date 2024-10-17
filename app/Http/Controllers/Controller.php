<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;

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
        $pagination = false
    ) {
        $request = match ($method) {
            'GET' => Http::timeout(120)->get("https://$domain/rest/$endpoint", $data),
            'POST' => Http::timeout(120)->post("https://$domain/rest/$endpoint", $data),
        };

        if ($request->failed()) {

            $message = empty($message) ? "Ошибка соединения с порталом $domain" : $message;

            if($notify && !empty($assigned)) {
                $this->sendNotify(
                    $assigned,
                    $message,
                    $domain,
                    $authID
                );
            }

            abort(
                503,
                $message
            );
        }

        return !$pagination ? $request->json()['result'] : $request->json();
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
