<?php

namespace App\Notifications;

use DateTime;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventUpdatedNotification extends Notification implements ShouldQueue
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
        $d = new DateTime($this->data['date']);
        $date = $d->format(__('app.format.date'));

        $s = new DateTime($this->data['oldService']['start']);
        $old_start = $s->format(__('app.format.time'));

        $e = new DateTime($this->data['oldService']['end']);
        $old_end = $e->format(__('app.format.time'));

        $s = new DateTime($this->data['newService']['start']);
        $new_start = $s->format(__('app.format.time'));

        $e = new DateTime($this->data['newService']['end']);
        $new_end = $e->format(__('app.format.time'));

        return (new MailMessage)
            ->replyTo($this->data['replyTo'] ?? env('MAIL_FROM_ADDRESS'))
            ->subject(__('email.event.modified.subject', [
                'date' => $date,
                'groupName' => $this->data['groupName']
            ]))
            ->line(__('email.event.modified.line_1', [
                                'groupName' => $this->data['groupName'],
                            ]))
            ->line(__('email.event.modified.line_2', [
                'oldServiceDate' => $date.": ".$old_start." - ".$old_end,
            ]))
            ->line(__('email.event.modified.line_3', [
                'newServiceDate' => $date.": ".$new_start." - ".$new_end,
            ]))
            ->line(__('email.event.modified.line_4'))
            ->line(__('email.event.modified.line_5', [
                'reason' => (($this->data['reason']) !== false
                                ? __('email.event.modify_reasons.'.$this->data['reason'])
                                : __('email.event.modify_reasons.unknown')
                            ),
            ]))
            ->line(__('email.event.modified.line_6', [
                'userName' => $this->data['userName'],
            ]))
            ->action(__('Log in'), url('/'));
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
