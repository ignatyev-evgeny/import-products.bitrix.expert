<?php

namespace App\Http\Controllers;

use App\Imports\ImportToBitrix24;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ImportController extends Controller {
    public function process(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls'
        ]);

        try {
            $file = $request->file('file');
            Excel::import(new ImportToBitrix24(), $file);
            return response()->json(['message' => 'Файл успешно обработан']);
        } catch (\Exception $exception) {
            return response()->json(['message' => $exception->getMessage()], 500);
        }
    }

}
