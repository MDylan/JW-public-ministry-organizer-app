<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\HtmlString;

class Newsletter extends Notification implements ShouldQueue
{
    use Queueable;

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
        if($this->data['recipients'] == 'groupCreators') {
            $recipients = Lang::get('roles.groupCreator');
        } elseif($this->data['recipients'] == 'groupAdmins') {
            $recipients = Lang::get('group.roles.admin');
        } elseif($this->data['recipients'] == 'groupServants') {
            $recipients = Lang::get('group.roles.admin')." + ".Lang::get('group.roles.roler');
        }

        return (new MailMessage)
                    ->subject($this->data['subject'])
                    ->line(new HtmlString($this->data['content']))
                    ->line(Lang::get('app.newsletter.recipients', ['recipients' => $recipients]))
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
