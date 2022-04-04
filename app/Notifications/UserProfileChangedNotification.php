<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;

class UserProfileChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private $data = [];

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
    public function __construct(array $data)
    {
        // dd($data);
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
            ->subject(Lang::get('email.profileChanged.subject'))
            ->line(Lang::get('email.profileChanged.line_1', [
                'userName' => $this->data['userName']
            ]))
            ->line(Lang::get('email.profileChanged.line_2'))
                ->line(Lang::get('user.name').": ".$this->data['old']['name']."; "
                    .Lang::get('user.phone').": ".$this->data['old']['phone_number'])
            ->line(Lang::get('email.profileChanged.line_3'))
                ->line(Lang::get('user.name').": ".$this->data['new']['name']."; "
                    .Lang::get('user.phone').": ".$this->data['new']['phone_number'])
            ->line(Lang::get('email.profileChanged.line_4'))
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
