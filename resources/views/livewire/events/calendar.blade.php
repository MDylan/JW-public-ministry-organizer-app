<div>
    <table class="table table-bordered">
        <thead>
            <tr>
                @foreach (trans('group.days') as $dn => $translate)
                <th class="calendar_day">
                    <div class="d-flex justify-content-center">
                        <div class="d-none d-sm-block">
                            {{ $translate }}
                        </div> 
                        <div class="d-block d-sm-none">
                            {{ __('event.weekdays_short.'.$dn) }}
                        </div>
                    </div>
                </th>    
                @endforeach
            </tr>
        </thead>
        <tbody>
        @foreach ($calendar as $row)
        <tr>
            @foreach ($row as $day)
            <td @if ($day['colspan'] !== null) colspan = "{{$day['colspan']}}" 
                @else
                    class="pr-1 pt-1
                    @if ($day['current']) table-secondary
                    @elseif (isset($service_days[$day['weekDay']]) == null || !$day['available']) table-active
                    @else table-light @endif
                    @if ($day['service_day']) available @endif
                    "
                @endif data-day="{{ $day['fullDate'] }}"
                @if ($day['service_day']) onclick="modal('{{ $day['fullDate'] }}')" @endif
                {{-- @if ($day['service_day']) wire:click="$emit('openModal', '{{$day['fullDate']}}')" @endif --}}
                >
                <div class="row">
                    <div class="col-8">
                    </div>
                    <div class="col-4 d-flex justify-content-end">{{ $day['day'] }}</div>
                </div>
            </td>
            @endforeach
        </tr>
        @endforeach
        </tbody>
    </table>
    <script>
        function modal(date) {
            // alert('date: '+ date);
            livewire.emit('openModal', '"'+date+'"');
        }
        // $(document).ready(function() {
        //     $('.available').on('click', function() {
        //         alert('click');
        //     });
        // });
    </script>
</div>