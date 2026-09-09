<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

class TimeCheck implements ValidationRule, DataAwareRule
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
     * Still reached under the new contract: InvokableValidationRule::passes()
     * calls setData($this->validator->getData()) whenever the invokable it
     * wraps is a DataAwareRule, so nothing about this method changes.
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
     * Run the validation rule.
     *
     * The decision below is the untouched body of the `passes()` this
     * replaced, and the message is the untouched body of its `message()`.
     * Keeping them verbatim is the point of this commit: the contract moves,
     * the behaviour does not.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->passes($attribute, $value)) {
            return;
        }

        $fail('validation.'.$this->error_str)->translate(['date' => date("H:i", $this->other_time)]);
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    private function passes($attribute, $value)
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
}
