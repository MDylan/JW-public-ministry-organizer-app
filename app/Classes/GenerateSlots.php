<?php

namespace App\Classes;

class GenerateSlots {

    /**
     * Generate time slots, need to escape summer/winter time shift
     */
    static function generate($date, $start, $end, $step) {
        //convert it for safe
        $date = date("Y-m-d", strtotime($date));
        $min_hour = date("G", $start);
        $max_hour = date("G", $end);

        if($max_hour == 0) $max_hour = 24; // - ($step / 60 / 60);
        $step_hour = ($step / 60 / 60);

        $start_half = false;

        if(date("Gi", $start) == date("G", $start)."30") {
            $min_hour += 0.5; //this is for half hours
            $start_half = true;
        }

        if(date("Gi", $end) == date("G", $end)."30") {
            if(!$start_half)
                $max_hour += $step_hour; //this is for half hours
        }
        
        $times = [];
        $current_unix = $start;
        $hour = $min_hour;
        while($current_unix < $end) {
            // $times[$current_unix] = date("Y-m-d H:i", $current_unix);
            $times[$current_unix] = $current_unix; 
            $hour += $step_hour;
            if(self::isInt($hour)) {
                $current = $hour.":00";
            } else {
                $current = floor($hour).":30";
            }
            $current_unix = strtotime($date." ".$current);
            // $times[$current_unix] = $current_unix; 
            // $times[$current_unix] = date("Y-m-d H:i", $current_unix);
        }
        // for ($hour = $min_hour; $hour < $max_hour; $hour += $step_hour) {
        //     if(self::isInt($hour)) {
        //         $current = $hour.":00";
        //     } else {
        //         $current = floor($hour).":30";
        //     }
        //     $current_unix = strtotime($date." ".$current);
        //     // $times[$current_unix] = $current_unix; 
        //     $times[$current_unix] = date("Y-m-d H:i", $current_unix);
        // }
        // dd($times, $min_hour, $max_hour);
        return $times;
    }

    static function isInt($int){
        return preg_match('@^[-]?[0-9]+$@',$int) === 1;
    }
}

?>