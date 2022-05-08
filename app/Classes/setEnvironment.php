<?php

namespace App\Classes;

use Illuminate\Support\Facades\Artisan;

class setEnvironment {
    static function setEnvironmentValue(array $values)
    {
        if(
            (auth()->user()->role ?? null) !== "mainAdmin" && request()->routeIs('admin.settings')
            && (
                !request()->routeIs('setup.*')
            )
        ) {
            abort('403');
        }

        $envFile = app()->environmentFilePath();
        $str = file_get_contents($envFile);
    
        if (count($values) > 0) {
            foreach ($values as $envKey => $envValue) {
    
                $str .= "\n"; // In case the searched variable is in the last line without \n
                $keyPosition = strpos($str, "{$envKey}=");
                $endOfLinePosition = strpos($str, "\n", $keyPosition);
                $oldLine = substr($str, $keyPosition, $endOfLinePosition - $keyPosition);
                // If key does not exist, add it
                if ($keyPosition === false || !$endOfLinePosition || !$oldLine) {
                    $str .= "{$envKey}={$envValue}\n";
                } else {
                    $str = str_replace($oldLine, "{$envKey}={$envValue}", $str);
                }
    
            }
        }
    
        $str = substr($str, 0, -1);
        if (!file_put_contents($envFile, trim($str))) {
            return false;
        }         
        Artisan::call('config:clear');
        return true;
    
    }
}