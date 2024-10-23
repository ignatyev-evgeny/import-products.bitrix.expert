<?php

namespace App\Http\Controllers;

use App\Exports\ExportProductRows;
use App\Http\Services\Bitrix24Service;
use App\Jobs\ProcessImportJob;
use App\Models\Integration;
use Cache;
use Exception;
use Illuminate\Http\Request;
use Log;
use Maatwebsite\Excel\Facades\Excel;

class SyncController extends Controller {

    protected Bitrix24Service $bitrixService;
    private mixed $domain;

    public function __construct(Request $request)
    {
        parse_str(parse_url($request->header('referer'), PHP_URL_QUERY), $queryParams);
        if(!empty($queryParams['DOMAIN'])) {
            $domain = $queryParams['DOMAIN'];
            $this->domain = $domain;
            $this->bitrixService = new Bitrix24Service($domain);
        }
    }

    public function importProcess(Request $request)
    {

        if(empty($this->domain)) {
            Log::channel('critical')->critical('[importProcess] $domain не определен. Headers: '.json_encode($request->headers).' | Request: '.json_encode($request->all()));
            return response()->json([
                'message' => 'Портал не определен. Пожалуйста, свяжитесь с технической поддержкой.'
            ], 429);
        }

        $request->validate([
            'file' => 'required|mimes:xlsx,xls',
            'objectID' => 'required|integer'
        ]);

        try {
            $this->bitrixService->getSmartProcessDetail();
            $filePath = storage_path('app/' . $request->file('file')->store('temp'));

            if (Cache::has($request->objectID.'_import_in_progress')) {
                $this->bitrixService->sendNotify(
                    $this->bitrixService->getAssigned(),
                    'Импорт уже выполняется. Пожалуйста, дождитесь завершения.',
                    $this->bitrixService->getDomain(),
                    $this->bitrixService->getAuthID()
                );

                return response()->json([
                    'message' => 'Импорт уже выполняется. Пожалуйста, дождитесь завершения.'
                ], 429);
            }

            Cache::put($request->objectID.'_' . $this->bitrixService->getDomain() . '_import_in_progress', true, 3600);

            try {
                ProcessImportJob::dispatch(
                    $filePath,
                    $this->bitrixService,
                    $request->objectID,
                    $this->bitrixService->getDomain()
                );
                $this->bitrixService->sendNotify(
                    $this->bitrixService->getAssigned(),
                    'Файл добавлен в очередь на обработку.',
                    $this->bitrixService->getDomain(),
                    $this->bitrixService->getAuthID()
                );
                return response()->json([
                    'message' => 'Файл добавлен в очередь на обработку.'
                ]);
            } catch (Exception $e) {
                Cache::forget($request->objectID.'_' . $this->bitrixService->getDomain() . '_import_in_progress');
                return response()->json([
                    'message' => 'Ошибка запуска импорта: ' . $e->getMessage()
                ], 500);
            }

        } catch (Exception $exception) {
            return response()->json([
                'message' => $exception->getMessage()
            ], 500);
        }
    }

    public function exportProcess(Request $request)
    {
        if(empty($this->domain)) {
            Log::channel('critical')->critical('[exportProcess] $domain не определен. Headers: '.json_encode($request->headers).' | Request: '.json_encode($request->all()));
            return response()->json([
                'message' => 'Портал не определен. Пожалуйста, свяжитесь с технической поддержкой.'
            ], 429);
        }

        try {
            $smartProcessDetail = $this->bitrixService->getSmartProcessDetail();
            $getProductRows = $this->bitrixService->getProductRows($smartProcessDetail['SYMBOL_CODE_SHORT']);
            $exportData = [];
            $count = 1;
            $integration = Integration::where('domain', $this->domain)->first();
            foreach ($getProductRows as $getProductRow) {
                $getProductRow['detail'] = $this->bitrixService->productDetail($getProductRow['productId']);
                if (empty($getProductRow['detail'])) {
                    throw new Exception('Ошибка при получении детальной информации по товарной позиции. <br> Возможно товар <b>' . $getProductRow['productName'] . '</b> ранее был удален. <br> Попробуйте еще раз или свяжитесь с технической поддержкой.');
                }
                $exportData[] = [
                    $count++,
                    $getProductRow['detail']['PROPERTY_' . $integration->product_field_article]['value'] ?? '',
                    $getProductRow['detail']['PROPERTY_' . $integration->product_field_brand]['value'] ?? '',
                    $getProductRow['productName'],
                    $getProductRow['price'],
                    $getProductRow['quantity'],
                ];
            }
            $fileName = 'exported_data_' . time() . '.xlsx';
            Excel::store(new ExportProductRows($exportData), 'public/' . $fileName);
            $downloadUrl = asset('storage/' . $fileName);
            return response()->json([
                'download_url' => $downloadUrl
            ]);
        } catch (Exception $exception) {
            report($exception);
            return response()->json([
                'message' => $exception->getMessage()
            ], 500);
        }
    }
}
