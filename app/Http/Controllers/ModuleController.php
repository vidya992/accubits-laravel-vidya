<?php

namespace App\Http\Controllers;

use Mail;
use App\Module;
use Illuminate\Http\Request as Requests;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use App\Mail\SendRecordErrorsMail;

class ModuleController extends Controller {
    
    public function importCSV(Requests $request) {
        $errors = $header = $moduleData = $metdata = [];
        $file = $request->file('file');
        $filExtension = $file->getClientOriginalExtension();
        if (empty($file)) {
            return response(['success' => false, 'data' => 'No file uploaded']);
        } else if ($filExtension != 'csv') {
            $errors[] = "Invalid File Type.";
            $this->sendMail($errors);
            return response(['success' => false, 'data' => $errors]);
        }
        $totalLength = count(file($file));
        if($totalLength < 1001) {
            $errors['Data count'] = "File donot contain 1000 records.";
        }
        $fileName = $file->getClientOriginalName();
        $headerKeys = array('module_code', 'module_name', 'module_term');
        $isHeader = false;
        $errorCount = 0;
        if (!storage_path('/temp')) {
            File::makeDirectory('/temp', $mode = 0777, true, true);
        }
        $file->move(storage_path('/temp'), $fileName);
        // handle headers
        if (($handle = fopen(storage_path('temp/') . $fileName, 'r')) !== false) {
            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                if (!$isHeader) {
                    $isHeader = true;
                    $row = array_map('strtolower', $row);
                    $row = array_map('trim', $row);
                    foreach ($row as $key => $cell) {
                        if($this->validateHeaders($cell, $key)) {
                            $errors['Header name errors'][] = $this->validateHeaders($cell, $key);
                            $row[$key] = preg_replace('/[^A-Za-z0-9\_]/', '', $cell); //proceed anyways
                        }
                    }
                    foreach ($headerKeys as $cell) {
                        if (!in_array($cell, $row)) {
                            $errors['Missing fields'][] = $cell;
                        } 
                    }
                    if (count($errors) > 0) {
                        $this->sendMail($errors);
                        return response(['success' => false, 'data' => $errors]);
                    }
                    $header = $row;
                } else {
                    $metdata[] = array_combine($header, $row);
                }
            }
            fclose($handle);
        }
        unlink(storage_path('/temp/' . $fileName));
        // handle records
        foreach ($metdata as $key => &$value) {
            $noError = true;
            foreach($header as $ind => $name) {
                if(empty($name)) {
                    unset($value[$name]);
                } else {
                    if (empty($value[$name])) {
                        $noError = false;            //empty value rows are not inserted
                        $errors['Empty values'][$name][] = $key + 2; // +2 to exclude 0 and header
                    } else if (preg_match('/[^A-Za-z0-9 ,.;\n]/', $value[$name])) { //check string with special char except .,;
                        $errors['Invalid values'][$name][] = $key + 2;
                    } 
                    // $value[$name] = (string) preg_replace('/[^ \w]+/', '', $value[$name]);
                }
            }
            if ($noError) {
                $moduleData[] = $value;
            }
        }
        if (count($moduleData) > 0) {
            $totalInserted = count($moduleData);
            $result = $this->saveModule($moduleData);
            if ($result) {
                $removed = $totalInserted - $totalInserted - 1; //-1 to remove header
                $removed = $removed == -1 ? 0 : $removed;
                if(!empty($errors)) {
                    $this->sendMail($errors);
                }
                return response(['success' => true, 'data' => $totalInserted . ' records inserted, ' . $removed . ' failed.']);
            } else {
                return response(['success' => false, 'data' => 'Record Insertion failed!']);
            }
        }
        return response(['success' => false, 'data' => 'No record(s) inserted, ' . $totalLength . ' failed']);
    }
    public function validateHeaders($name, $key) {
        $key = $key + 2;
        $data = ['name' => $name];
        $validator = Validator::make($data, [
            'name' => 'regex:/^[\s\w-]*$/',
        ]);
        if ($validator->fails()) {
            return  'Header column ('.$name.' at column '.$key.') is incorrect in csv file';
        }
        return  false;   
    }
    public function sendMail($errors) {
        foreach($errors as $key => $error) {
            $string = "";
            if (is_array($error)) {
                switch($key) {
                    case "Missing fields":
                        $string = implode(", ",$error) . " Not found!";
                        break;
                    case "Empty values":
                        foreach ($error as $k => $err) {
                            $name = str_replace("_"," ",$k);
                            $name = ucwords($name);
                            $str = $name . " is missing at row(s) : [" .implode(", ",$err). "] \n";
                            $string = $string.$str;
                        }
                        break;
                    case "Invalid values":
                        foreach ($error as $k => $err) {
                            $name = str_replace("_"," ",$k);
                            $name = ucwords($name);
                            $str = $name . " contains symbols at row(s) : [" .implode(", ",$err). "] \n";
                            $string = $string.$str;
                        }
                        break;
                    case "Header name errors":
                        $string = implode("\n",$error);
                        break;
                    default:
                        $string = $error;
                        break;
                }
                $errors[$key] = $string;
            }
        }
        return Mail::to(env('SEND_MAIL', 'charush@accubits.com'))
                ->queue(new SendRecordErrorsMail($errors));
    }
    public function saveModule($bulkdata) {
        $chunks = array_chunk($bulkdata, 100);
        foreach ($chunks as $chunk) {
            Module::insert($chunk);
        }
        return true;
    }
}
