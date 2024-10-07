<?php

namespace App\Http\Controllers;

use App\Exports\ExportProductRows;
use App\Http\Services\Bitrix24Service;
use App\Imports\ImportToBitrix24;
use App\Models\Integration;
use Exception;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class SyncController extends Controller {

    protected Bitrix24Service $bitrixService;

    public function __construct(Bitrix24Service $bitrixService)
    {
        $this->bitrixService = $bitrixService;
    }

    public function importProcess(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls'
        ]);

        try {
            $this->bitrixService->getSmartProcessDetail();
            Excel::queueImport(new ImportToBitrix24($this->bitrixService), storage_path('app/' . $request->file('file')->store('temp')));
            $this->bitrixService->sendNotify($this->bitrixService->getAssigned(), 'Файл добавлен в очередь на обработку.');
            return response()->json(['message' => 'Файл добавлен в очередь на обработку.']);
        } catch (Exception $exception) {
            return response()->json(['message' => $exception->getMessage()], 500);
        }
    }

    public function exportProcess()
    {
        try {

            $smartProcessDetail = $this->bitrixService->getSmartProcessDetail();
            $getProductRows = $this->bitrixService->getProductRows($smartProcessDetail['SYMBOL_CODE_SHORT']);

            $exportData = [];
            $count = 1;
            $bitrixService = new Bitrix24Service();
            $domain = $bitrixService->getDomain();
            $integration = Integration::where('domain', $domain)->first();

            foreach ($getProductRows as $getProductRow) {
                $getProductRow['detail'] = $bitrixService->productDetail($getProductRow['productId']);

                if(empty($getProductRow['detail'])) {
                    abort(500, 'Ошибка при получении детальной информации по товарной позиции. Попробуйте еще раз или свяжитесь с технической поддержкой.');
                }

                $exportData[] = [
                    $count++,
                    $getProductRow['detail']['PROPERTY_'.$integration->product_field_article]['value'] ?? '',
                    $getProductRow['detail']['PROPERTY_'.$integration->product_field_brand]['value'] ?? '',
                    $getProductRow['productName'],
                    $getProductRow['price'],
                    $getProductRow['quantity'],
                ];
            }

            return Excel::download(new ExportProductRows($exportData), 'exported_data.xlsx');
        } catch (Exception $exception) {
            report($exception);
            return response()->json(['message' => $exception->getMessage()], 500);
        }
    }







}
