<?php

namespace App\Jobs;

use App\Classes\CalculateDatesEvents;
use App\Models\DayStat;
use App\Models\GroupDate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CalculateDateProcess implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $group_id;
    private $date;
    private $user_id;
    private $deleteAfterCalculate;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(int $group_id, array|string $date, $user_id = false, $deleteAfterCalculate = [])
    {
        $this->group_id = $group_id;
        $this->date = $date;
        $this->user_id = $user_id;
        $this->deleteAfterCalculate = $deleteAfterCalculate;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        CalculateDatesEvents::generate($this->group_id, $this->date, $this->user_id);
        if(count($this->deleteAfterCalculate)) {
            GroupDate::where('group_id', '=', $this->group_id)
                    ->whereIn('date', $this->deleteAfterCalculate)
                    ->where('date_status', '=', 0)
                    ->delete();

            DayStat::where('group_id', '=', $this->group_id)
                    ->whereIn('day', $this->deleteAfterCalculate)
                    ->delete();
        }
    }
}
