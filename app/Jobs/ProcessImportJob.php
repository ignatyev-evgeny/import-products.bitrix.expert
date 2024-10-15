<?php

namespace App\Jobs;

use App\Http\Services\Bitrix24Service;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ImportToBitrix24;

class ProcessImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $filePath;
    public $bitrixService;

    public function __construct($filePath, Bitrix24Service $bitrixService)
    {
        $this->filePath = $filePath;
        $this->bitrixService = $bitrixService;
    }

    public function handle()
    {
        Excel::import(new ImportToBitrix24($this->bitrixService), $this->filePath);
    }
}