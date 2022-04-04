<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;
// use Illuminate\Support\Facades\URL;

class FinishRegistration extends Notification implements ShouldQueue
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
            ->subject(Lang::get('email.finishRegistration.subject', [
                'appName' => Lang::get('app.title')
            ]))
            ->line(Lang::get('email.finishRegistration.line_1', [
                'groupAdmin' => $this->data['groupAdmin'],
                'appName' => Lang::get('app.title')
            ]))
            ->line(Lang::get('email.finishRegistration.line_2', [
                'day' => 2
            ]))
            ->line(Lang::get('email.finishRegistration.line_3', [
                'userMail' => $this->data['userMail']
            ]))
            ->line(Lang::get('email.finishRegistration.line_4'))
            ->line(Lang::get('email.finishRegistration.line_5'))
            ->action(Lang::get('user.finish.registration'), $this->data['url']);
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
