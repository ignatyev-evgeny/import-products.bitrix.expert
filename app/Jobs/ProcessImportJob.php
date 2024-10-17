<?php

namespace App\Jobs;

use App\Http\Services\Bitrix24Service;
use App\Imports\BatchImportToBitrix24;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;

class ProcessImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $filePath;
    public $bitrixService;

    public $timeout = 3600;

    public function __construct($filePath, Bitrix24Service $bitrixService)
    {
        $this->filePath = $filePath;
        $this->bitrixService = $bitrixService;
    }

    public function handle()
    {
        Excel::import(new BatchImportToBitrix24($this->bitrixService), $this->filePath);
    }
}