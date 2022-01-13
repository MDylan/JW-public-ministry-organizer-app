<?php

namespace App\Notifications;

use DateTime;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private $data;

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

        $s = new DateTime($this->data['newService']['start']);
        $start = $s->format(__('app.format.time'));

        $e = new DateTime($this->data['newService']['end']);
        $end = $e->format(__('app.format.time'));

        $status = $this->data['status'];

        return (new MailMessage)
            ->subject(__('email.event.status_changed.'.($status).'.subject', [
                'date' => $date,
                'groupName' => $this->data['groupName']
            ]))
            ->line(__('email.event.status_changed.'.($status).'.line_1', [
                'groupName' => $this->data['groupName'],
                'userName' => $this->data['userName'],
            ]))
            ->line(__('email.event.status_changed.'.($status).'.line_2', [
                'newServiceDate' => $date.": ".$start." - ".$end,
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