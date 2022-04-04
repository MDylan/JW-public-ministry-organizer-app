<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;

class LoginData extends Notification implements ShouldQueue
{
    use Queueable;
    private $data;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 5;

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
            ->subject(Lang::get('email.loginData.subject'))
            ->line(Lang::get('email.loginData.line_1', [
                'groupAdmin' => $this->data['groupAdmin']
            ]))
            ->line(Lang::get('email.loginData.line_2'))
            ->line(Lang::get('email.loginData.line_3', [
                'userMail' => $this->data['userMail']
            ]))
            ->line(Lang::get('email.loginData.line_4', [
                'userPassword' => $this->data['userPassword']
            ]))
            ->line(Lang::get('email.loginData.line_5'))
            ->action(Lang::get('Log in'), url('/login'));
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
