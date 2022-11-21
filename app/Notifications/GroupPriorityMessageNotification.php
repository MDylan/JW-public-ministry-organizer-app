<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Traits\OptOutable;

class GroupPriorityMessageNotification extends Notification implements ShouldQueue
{
    use Queueable, OptOutable;

    private $data = [];

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 2880;
    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 60;    

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(array $data)
    {
        // dd($data);
        $this->data = $data;
    }

    public function optOut($notifiable)
    {
        // Insert opt-out logic that returns a boolean
        return isset($notifiable->opted_out_of_notifications['GroupPriorityMessageNotification']);
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
                    ->subject(__('email.messages.subject', ['userName' => $this->data['userName']]))
                    ->line(__('email.messages.line_1', ['userName' => $this->data['userName'], 'groupName' => $this->data['groupName']]))
                    ->line(__('email.messages.line_2'))
                    ->line($this->data['message'])
                    ->line(__('email.messages.line_3'))
                    ->action(__('Log in'), url('/login'))
                    ->line(__('email.unsubscribe'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
