<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;

class GroupParentGroupDetachedNotification extends Notification implements ShouldQueue
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
                    ->subject(Lang::get('email.ParentGroupDetached.subject'))
                    ->line(Lang::get('email.ParentGroupDetached.line_1', [
                                        'childGroupName' => $this->data['childGroupName'],
                                    ]))
                    ->line(Lang::get('email.ParentGroupDetached.line_2'))
                    ->line(Lang::get('email.ParentGroupDetached.line_3', [
                        'groupName' => $this->data['groupName'],
                    ]))
                    ->line(Lang::get('email.ParentGroupDetached.line_4', [
                        'userName' => $this->data['userName'],
                    ]))
                    ->line(Lang::get('email.ParentGroupDetached.line_5'))
                    ->action(Lang::get('Log in'), url('/'));
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
