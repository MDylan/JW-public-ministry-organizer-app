<div>
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
            <h1 class="m-0">{{ __('app.menu-calendar') }}</h1>
            </div><!-- /.col -->
            <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="{{route('home.home')}}">{{ __('app.menu-home') }}</a></li>
                <li class="breadcrumb-item active">{{ __('app.menu-calendar') }}</li>
            </ol>
            </div><!-- /.col -->
        </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->

    <!-- Main content -->
    <div class="content">
        <div class="container-fluid">
            <div class="row">                
                <div class="col-lg-12">
                    <div class="card card-primary card-outline">
                        <div class="card-body">
                            <div id="calendar"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @livewire('events.modal')
</div>
@section('header_style')
<link rel="stylesheet" href="{{ asset('plugins/fullcalendar/main.min.css') }}">
@endsection
@section('footer_scripts')
<script src="{{ asset('plugins/moment/moment-with-locales.min.js') }}"></script>
<script src="{{ asset('plugins/fullcalendar/main.min.js') }}"></script>
<script src="{{ asset('plugins/fullcalendar/locales-all.js') }}"></script>
<script>
$(document).ready(function() {
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
        eventSources: [
            {
            url: '{{route('getevents')}}',
            method: 'POST',
            extraParams: {
                _token: '{{ csrf_token() }}', 
                groupId: {{$group_data['id']}}
            },
            failure: function() {
                alert('there was an error while fetching events!');
            },
            color: 'yellow',   // a non-ajax option
            textColor: 'black' // a non-ajax option
            }

            // any other sources...

            ],
        // events: '{{route('getevents')}}',
        initialView: 'dayGridMonth',
        selectable: true,
        allDaySlot: false,
        locale: 'hu', // the initial locale
        themeSystem: 'bootstrap',
        headerToolbar: {
            left  : 'prev,next today',
            center: 'title',
            right : 'dayGridMonth,timeGridWeek,timeGridDay'
      },
      businessHours: [ // specify hours
            @foreach ($service_days as $d => $day)
                {
                    daysOfWeek: [{{$d}}],
                    startTime: '{{$day['start_time']}}',
                    endTime: '{{$day['end_time']}}',
                },
            @endforeach
        ],
        dateClick: function(info) {
            // console.log(moment().format('YYYY-MM-DD'), moment(info.dateStr));
            console.log(info.dateStr);
            diff = moment().diff(info.dateStr, 'days');
            if (diff < 0 && diff > (-1 * {{$group_data['max_extend_days']}})) {
                // livewire.emitTo('events.modal', 'openModal', {{$group_data['id']}}, info.dateStr);
            }
            // if (moment().format('YYYY-MM-DD') === info.dateStr 
            //     || info.dateStr.isAfter(moment())) {
            //     console.log(info);
            // }
            // livewire.emitTo('events.modal', 'openModal', {{$group_data['id']}}, info.dateStr);
            // alert('Date: ' + info.dateStr);
            // alert('Resource ID: ' + info.resource.id);
        },
        selectConstraint: {
            start: moment().format('YYYY-MM-DD'),
            end: '{{$group_data['max_day']}}'
        },
        // selectOverlap: function(event) {
        //     return event.rendering === 'background';
        // }

    });
    calendar.render();
    // $('#calendar').fullCalendar()
});
</script>
@endsection