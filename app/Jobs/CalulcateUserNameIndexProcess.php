<?php

namespace App\Jobs;

use App\Helpers\CollectionHelper;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CalulcateUserNameIndexProcess implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds after which the job's unique lock will be released.
     *
     * @var int
     */
     /**
     * The unique ID of the job.
     *
     * @return string
     */
    public function uniqueId()
    {
        return "CalculateUserNameIndex";
    }

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // echo "handle";
        $users = User::all();
        $sorted_list = CollectionHelper::sortByCollator($users, 'name');
        $i = 1;
        foreach ($sorted_list as $user) {
            $user->name_index = $i;
            $user->save();
            $i++;
        }
    }
}
