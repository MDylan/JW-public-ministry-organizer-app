<?php

namespace App\Notifications;

use App\Traits\OptOutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;

class UserProfileChangedNotification extends Notification implements ShouldQueue
{
    use OptOutable, Queueable;

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
        return isset($notifiable->opted_out_of_notifications['UserProfileChangedNotification']);
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
                    .Lang::get('user.phone').": ".$this->data['old']['phone_number']."; "
                    .Lang::get('user.congregation').": ".$this->data['old']['congregation'])
            ->line(Lang::get('email.profileChanged.line_3'))
                ->line(Lang::get('user.name').": ".$this->data['new']['name']."; "
                    .Lang::get('user.phone').": ".$this->data['new']['phone_number']."; "
                    .Lang::get('user.congregation').": ".$this->data['new']['congregation'])
            ->line(Lang::get('email.profileChanged.line_4'))
            ->action(Lang::get('Log in'), url('/login'))
            ->line(__('email.footer')." ".__('email.unsubscribe'));
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
