<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;

class GroupUserLogoutNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private $data;

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
                    ->subject(Lang::get('email.GroupUserLogout.subject', ['groupName' => $this->data['groupName']]))
                    ->line(Lang::get('email.GroupUserLogout.line_1', [
                                        'groupName' => $this->data['groupName'],
                                    ]))
                    ->line(Lang::get('email.GroupUserLogout.line_2', [
                        'userName' => $this->data['userName'],
                    ]))
                    ->action(Lang::get('Log in'), url('/'))
                    ->line(__('email.footer'));
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
