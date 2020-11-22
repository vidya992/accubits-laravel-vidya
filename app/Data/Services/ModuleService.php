<?php

namespace App\Data\Services;

use Mail;
use App\Mail\SendRecordErrorsMail;
use App\Module;
use Illuminate\Support\Facades\Validator;

class ModuleService {
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
    public function saveModule($bulkdata) {
        $chunks = array_chunk($bulkdata, 100);
        // foreach ($chunks as $chunk) {
        //     Module::insert($chunk);
        // }
        return true;
    }
}