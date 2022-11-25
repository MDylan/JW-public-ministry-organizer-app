<?php

namespace App\Http\Livewire\Admin;

use App\Models\Statistics as ModelsStatistics;
use Carbon\CarbonInterval;
use Livewire\Component;

class Statistics extends Component
{
    public function render()
    {

        $start = now()->subHours(24)->floorHour();
        $end = now()->floorHour();

        $periods = CarbonInterval::minutes(60)->toPeriod($start, $end);

        $stats = ModelsStatistics::whereBetween('date', [$start, $end])
                                ->where('type', 'active_users')
                                ->orderBy('date')
                                ->get()
                                ->toArray();

        return view('livewire.admin.statistics', [
            'periods' => $periods,
            'stats' => $stats
        ]);
    }
}
