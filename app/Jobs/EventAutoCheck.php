<?php

namespace App\Jobs;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EventAutoCheck implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $start = null;
    private $end = null;
    private $event = null;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Event $event, int $start, int $end)
    {
        $this->event = $event;
        $this->start = $start;
        $this->end = $end;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //if not need to approve event, skip the whole process
        if(!$this->event->groups->need_approval) return true;

        if($this->event->groups->auto_approval) {
            $events = Event::where('group_id', $this->event->group_id)
                            ->where('status', '<>', 2)
                            ->where('start', '<', date("Y-m-d H:i:s", $this->end))
                            ->where('end', '>', date("Y-m-d H:i:s", $this->start))
                            ->get();
            foreach($events as $event) {

            }
        }

        if ($this->event->groups->auto_back) {

        }

    }
}
