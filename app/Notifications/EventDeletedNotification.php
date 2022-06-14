<?php

namespace App\Notifications;

use App\Traits\OptOutable;
use DateTime;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;


class EventDeletedNotification extends Notification implements ShouldQueue
{
    use OptOutable, Queueable;

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

    public function optOut($notifiable)
    {
        // Insert opt-out logic that returns a boolean
        return isset($notifiable->opted_out_of_notifications['EventDeletedNotification']);
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $d = new DateTime($this->data['date']);
        $date = $d->format(__('app.format.date'));

        $s = new DateTime($this->data['oldService']['start']);
        $start = $s->format(__('app.format.time'));

        $e = new DateTime($this->data['oldService']['end']);
        $end = $e->format(__('app.format.time'));

        $message = (new MailMessage)
            ->replyTo($this->data['replyTo'] ?? env('MAIL_FROM_ADDRESS'))
            ->subject(__('email.event.deleted.subject', [
                'date' => $date,
                'groupName' => $this->data['groupName']
            ]))
            ->line(__('email.event.deleted.line_1', [
                                'groupName' => $this->data['groupName'],
                            ]))
            ->line(__('email.event.deleted.line_2', [
                'oldServiceDate' => $date.": ".$start." - ".$end,
            ]))
            ->line(__('email.event.deleted.line_3', [
                'reason' => (($this->data['reason']) !== false
                                ? __('email.event.deletion_reasons.'.$this->data['reason'])
                                : __('email.event.deletion_reasons.unknown')
                            ),
            ]));

        
        if($this->data['userName'] !== false) {
            $message->line(__('email.event.deleted.line_4', [
                'userName' => $this->data['userName'],
            ]));
        }
       
        $message->action(__('Log in'), url('/'));
        $message->line(__('email.footer')." ".__('email.unsubscribe'));

        return $message;
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
