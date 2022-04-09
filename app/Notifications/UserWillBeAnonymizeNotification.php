<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;

class UserWillBeAnonymizeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private $data = [];

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 60;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
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
                    ->subject(Lang::get('email.anonymize.subject'))
                    ->line(Lang::get('email.anonymize.line_1', [
                        'appName' => env('APP_NAME')
                    ]))
                    ->line(Lang::get('email.anonymize.line_2'))
                    ->line(Lang::get('email.anonymize.line_3', ['lastDate' => $this->data['lastDate']]))
                    ->action(Lang::get('Log in'), url('/login'))
                    ->line(Lang::get('email.footer'));
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
