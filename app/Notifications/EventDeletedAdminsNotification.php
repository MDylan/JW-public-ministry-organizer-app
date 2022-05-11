<?php

namespace App\Notifications;

use DateTime;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;

class EventDeletedAdminsNotification extends Notification implements ShouldQueue
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
        $d = new DateTime($this->data['date']);
        $date = $d->format(__('app.format.date'));

        $s = new DateTime($this->data['oldService']['start']);
        $start = $s->format(__('app.format.time'));

        $e = new DateTime($this->data['oldService']['end']);
        $end = $e->format(__('app.format.time'));

        $message = (new MailMessage)
            ->subject(__('email.event.deleted_to_admin.subject', [
                'date' => $date,
                'groupName' => $this->data['groupName']
            ]))
            ->line(__('email.event.deleted_to_admin.line_1', [
                                'groupName' => $this->data['groupName'],
                            ]))
            ->line(__('email.event.deleted_to_admin.line_2', [
                'oldServiceDate' => $date.": ".$start." - ".$end,
            ]))
            ->line(__('event.publisher').": ".($this->data['event_user'] ?? '-' ))
            ->line(__('email.event.deleted_to_admin.line_3', [
                'reason' => (($this->data['reason']) !== false
                                ? __('email.event.deletion_reasons.'.$this->data['reason'])
                                : __('email.event.deletion_reasons.unknown')
                            ),
            ]));

        
        if($this->data['userName'] !== false) {
            $message->line(__('email.event.deleted_to_admin.line_4', [
                'userName' => $this->data['userName'],
            ]));
        }
       
        $message->action(__('Log in'), url('/'));

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
