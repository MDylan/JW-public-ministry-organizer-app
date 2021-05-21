<td @if ($day['colspan'] !== null) colspan = "{{$day['colspan']}}" 
    @else
        class="pr-1 pt-1
        @if ($day['current']) table-secondary
        @elseif (isset($service_days[$day['weekDay']]) == null || !$day['available']) table-active
        @else table-light @endif
        @if ($day['service_day']) available @endif
        "
    @endif data-day="{{ $day['fullDate'] }}"
    @if ($day['service_day']) wire:click.prevent="openModal('{{$day['fullDate']}}')" @endif>
    <div class="row">
        <div class="col-8">
        </div>
        <div class="col-4 d-flex justify-content-end">{{ $day['day'] }}</div>
    </div>
</td>

