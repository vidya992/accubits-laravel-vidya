<?php

namespace App\Http\Controllers;

use App\Module;
use Illuminate\Http\Request as Requests;
use Illuminate\Http\Response;
use App\Data\Services\ModuleService;
use App\Jobs\ProcessCSVRecords;

class ModuleController extends Controller {
    
    protected $moduleService;

    public function __construct(ModuleService $moduleService) {
        $this->moduleService = $moduleService;
    }
    public function importCSV(Requests $request) {
        $errors = [];
        $file = $request->file('file');
        $filExtension = $file->getClientOriginalExtension();
        if (empty($file)) {
            return response(['success' => false, 'data' => 'No file uploaded']);
        } else if ($filExtension != 'csv') {
            $errors[] = "Invalid File Type.";
            $this->moduleService->sendMail($errors);
            return response(['success' => false, 'data' => $errors]);
        }
        $totalLength = count(file($file));
        if($totalLength < 1001) {
            $errors []= "File donot contain 1000 records.";
            $this->moduleService->sendMail($errors);
        }
        $fileName = $file->getClientOriginalName();
        if (!storage_path('/temp')) {
            File::makeDirectory('/temp', $mode = 0777, true, true);
        }
        $file->move(storage_path('/temp'), $fileName);
        // handle headers
        try {
            $jobResult = new ProcessCSVRecords($fileName);
            dispatch($jobResult);
            return response(['success' => true]);
        } catch (Throwable $err) {
            return response(['success' => false, 'error' => $err]);
        }
        // return response(['success' => false, 'data' => 'No record(s) inserted, ' . $totalLength . ' failed']);
    }
}
