<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\DataAwareRule;

class TimeCheck implements Rule, DataAwareRule
{

    private $type;
    private $other;
    private $error_str;
    private $other_time;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($other, $type)
    {
        $this->other = ($other);
        $this->type = $type;
    }
    
    /**
     * All of the data under validation.
     *
     * @var array
     */
    protected $data = [];
 
    // ...
 
    /**
     * Set the data under validation.
     *
     * @param  array  $data
     * @return $this
     */
    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {

        $parts = explode(".", $attribute);
        if(count($parts) !== 2) return false;
        $key = $parts[0];
        // $attr = $parts[1];
        $midnight = strtotime("00:00");
        if(!isset($this->data[$key][$this->other])) return false;
        $other = $this->other_time = strtotime($this->data[$key][$this->other]);
        $time = strtotime($value);

        

        if($time == $other && $time == $midnight) return true;
        else {
            if($this->type == 'after_or_midnight') {
                
                if($time > $other) return true;
                if($time == strtotime("00:00")) return true;
                $this->error_str = "after";
            } 
            if($this->type == 'before_or_midnight') {
                if($time < $other) return true;
                if($other == $midnight && $time > $midnight) return true;
                $this->error_str = "before";
            }
        }
        return false;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return trans('validation.'.$this->error_str, ['date' => date("H:i", $this->other_time)]);
    }
}
