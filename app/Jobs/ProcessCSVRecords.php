<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Facades\ {
    App\Data\Services\ModuleService
};

class ProcessCSVRecords implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $filename;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(String $fileName) {
        $this->filename = $fileName;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $headerKeys = array('module_code', 'module_name', 'module_term');
        $isHeader = false;
        $recordCount = 1;
        $errors = $header = $moduleData = $dataGroup = [];
        if (($handle = fopen(storage_path('temp/') . $this->filename, 'r')) !== false) {
            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                if (!$isHeader) {
                    $isHeader = true;
                    $row = array_map('strtolower', $row);
                    $row = array_map('trim', $row);
                    foreach ($row as $key => $cell) {
                        if(ModuleService::validateHeaders($cell, $key)) {
                            $errors['Header name errors'][] = ModuleService::validateHeaders($cell, $key);
                            $row[$key] = preg_replace('/[^A-Za-z0-9\_]/', '', $cell); //proceed anyways
                        }
                    }
                    foreach ($headerKeys as $cell) {
                        if (!in_array($cell, $row)) {
                            $errors['Missing fields'][] = $cell;
                        } 
                    }
                    if (count($errors) > 0) {
                        ModuleService::sendMail($errors);
                    }
                    $header = $row;
                } else {
                    $recordCount++;
                    $dataGroup[] = array_combine($header, $row);
                }
            }
            fclose($handle);
        }
        if($recordCount < 1000) {
            $errors []= "File donot contain 1000 records.";
        }
        unlink(storage_path('/temp/' . $this->filename));
        // handle records
        foreach ($dataGroup as $key => &$value) {
            $noError = true;            //empty value rows are not inserted
            foreach($header as $ind => $name) {
                if(empty($name)) {
                    unset($value[$name]);
                } else {
                    if (empty($value[$name])) {
                        $noError = false;
                        $errors['Empty values'][$name][] = $key + 2; // +2 to exclude 0 and header
                    } else if (preg_match('/[^A-Za-z0-9 ,.;\n]/', $value[$name])) { //check string with special char except .,;
                        $errors['Invalid values'][$name][] = $key + 2;
                    } 
                }
            }
            if ($noError) {
                $moduleData[] = $value;
            }
        }
        if (count($moduleData) > 0) {
            $totalInserted = count($moduleData);
            $result = ModuleService::saveModule($moduleData);
            if ($result) {
                $removed = $recordCount - $totalInserted;
                $removed = $removed == -1 ? 0 : $removed;
                Log::info($totalInserted . " records inserted. ". $removed . " failed!");
                if(!empty($errors)) {
                    ModuleService::sendMail($errors);
                }
            }
        }
    }
}
