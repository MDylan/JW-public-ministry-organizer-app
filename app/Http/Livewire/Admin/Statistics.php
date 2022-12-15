<?php

namespace App\Http\Livewire\Admin;

use App\Models\Statistics as ModelsStatistics;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Livewire\Component;

class Statistics extends Component
{
    public function render()
    {
        //Acitve users
        $active_users_start = now()->subHours(25)->floorHour();
        $active_users_end = now()->subHour()->floorHour();

        $active_users_periods = CarbonInterval::minutes(60)->toPeriod($active_users_start, $active_users_end);

        $active_users_stats = ModelsStatistics::whereBetween('date', [$active_users_start, $active_users_end])
                                ->where('type', 'active_users')
                                ->orderBy('date')
                                ->get();
        //merge datas
        $active_users_array = [];
        foreach($active_users_periods as $active_users_period) {
            $active_users_array[$active_users_period->format("Y-m-d H:i:s")] = 0;
        }
        foreach($active_users_stats as $active_users_stat) {
            $active_users_array[Carbon::parse($active_users_stat->date)->format("Y-m-d H:i:s")] = $active_users_stat->number;
        }

        //dialy users statistics
        $dialy_users_start = now()->subDays(7)->format("Y-m-d");
        $dialy_users_end = now()->subDay()->format("Y-m-d");

        $dialy_users_periods = CarbonInterval::days(1)->toPeriod($dialy_users_start, $dialy_users_end);

        $dialy_users_stats = ModelsStatistics::whereBetween('date', [$dialy_users_start, $dialy_users_end])
                                ->where('type', 'dialy_users')
                                ->orderBy('date')
                                ->get();
        //merge datas
        $dialy_users_array = [];
        foreach($dialy_users_periods as $dialy_users_period) {
            $dialy_users_array[$dialy_users_period->format(__("app.format.date"))] = 0;
        }
        foreach($dialy_users_stats as $dialy_users_stat) {
            $dialy_users_array[Carbon::parse($dialy_users_stat->date)->format(__("app.format.date"))] = $dialy_users_stat->number;
        }

        return view('livewire.admin.statistics', [
            'active_users_array' => $active_users_array,
            'dialy_users_array' => $dialy_users_array,
        ]);
    }
}
