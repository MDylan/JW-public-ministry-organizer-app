<?php

/**
 * Based on: 
 * https://tighten.com/blog/opting-out-a-simple-solution-for-conditionally-cancelling-laravel-notifications/
 */

namespace App\Traits;
 
trait OptOutable
{
    public function via($notifiable)
    {
        if ($this->optOut($notifiable)) {
            return [];
        }
 
        return ['mail'];
    }
 
    public function optOut($notifiable)
    {
        return false;
    }
}