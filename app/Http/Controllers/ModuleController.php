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
        if (empty($file)) {
            return response(['success' => false, 'data' => 'No file uploaded']);
        }
        $filExtension = $file->getClientOriginalExtension();
        if ($filExtension != 'csv') {
            $errors[] = "Invalid File Type.";
            $this->moduleService->sendMail($errors);
            return response(['success' => false, 'data' => $errors]);
        }
        // $totalLength = count(file($file));
        $fileName = $file->getClientOriginalName();
        if (!storage_path('/temp')) {
            File::makeDirectory('/temp', $mode = 0777, true, true);
        }
        $file->move(storage_path('/temp'), $fileName);
        // handle headers
        try {
            $jobResult = new ProcessCSVRecords($fileName);
            dispatch($jobResult);
            return response(['success' => true, 'data' => 'Processing Complete']);
        } catch (Throwable $err) {
            return response(['success' => false, 'error' => $err]);
        }
        // return response(['success' => false, 'data' => 'No record(s) inserted, ' . $totalLength . ' failed']);
    }
}
