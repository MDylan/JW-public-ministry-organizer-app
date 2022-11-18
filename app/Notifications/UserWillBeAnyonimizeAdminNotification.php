<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;

class UserWillBeAnyonimizeAdminNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private $data = [];
    public $tries = 2880;
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
        $mail = (new MailMessage)
                    ->subject(Lang::get('email.anonymizeAdmin.subject'))
                    ->line(Lang::get('email.anonymizeAdmin.line_1'))
                    ->line(Lang::get('email.anonymizeAdmin.line_2'))
                    ->line(Lang::get('email.anonymizeAdmin.line_3'))
                    ->line(Lang::get('email.anonymizeAdmin.line_4'))
                    ->line(Lang::get('email.anonymizeAdmin.line_5'))
                    ->line(Lang::get('email.anonymizeAdmin.line_6', ['group' => $this->data['name']]))
                    ->line(Lang::get('email.anonymizeAdmin.line_7'));
        foreach($this->data['users'] as $user) {
            $mail->line($user['name'].' ('.$user['lastDate'].')');
        }
        
        $mail->action(Lang::get('Log in'), url('/login'))
                    ->line(__('email.footer'));
        return $mail;
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
