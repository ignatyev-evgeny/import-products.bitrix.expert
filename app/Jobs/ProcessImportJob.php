<?php

namespace App\Jobs;

use App\Http\Services\Bitrix24Service;
use App\Imports\BatchImportToBitrix24;
use Cache;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;

class ProcessImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $filePath;
    public $bitrixService;

    public $timeout = 3600;
    private int $objectID;
    private string $domain;
    private mixed $uuid;

    public function __construct($filePath, Bitrix24Service $bitrixService, $objectID, $domain, $uuid)
    {
        $this->filePath = $filePath;
        $this->bitrixService = $bitrixService;
        $this->objectID = $objectID;
        $this->domain = $domain;
        $this->uuid = $uuid;
    }

    public function middleware()
    {
        return [
            new WithoutOverlapping($this->objectID.'_' . $this->domain . '_process-import-job-lock')
        ];
    }

    public function tags()
    {
        return ['import', 'domain:' . $this->domain];
    }

    public function handle()
    {

        logImport($this->uuid, [
            'status' => 'Запуск импорта',
            'events_history' => 'Очередь ProcessImportJob - Запуск импорта'
        ]);

        Excel::import(new BatchImportToBitrix24($this->bitrixService, $this->uuid), $this->filePath);

        logImport($this->uuid, [
            'status' => 'Импорт запущен',
            'events_history' => 'Очередь ProcessImportJob - Импорт запущен'
        ]);

    }

    public function failed()
    {

        logImport($this->uuid, [
            'status' => 'Ошибка',
            'events_history' => 'Очередь ProcessImportJob - Ошибка при запуске импорта'
        ]);

        Cache::forget($this->objectID.'_' . $this->domain . '_import_in_progress');
    }
}