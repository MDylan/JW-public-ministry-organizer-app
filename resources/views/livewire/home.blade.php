<div>
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
            <h1 class="m-0">{{__('app.menu-home')}}</h1>
            </div><!-- /.col -->
            <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="#">{{__('app.menu-home')}}</a></li>
            </ol>
            </div><!-- /.col -->
        </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->

    <!-- Main content -->
    <div class="content">
        <div class="container-fluid">
        <div class="row d-block">
            @forelse ($groups as $group)
            <div class="col-md-6 float-left">
                <div class="card card-primary card-outline">
                    <div class="card-header">
                    <h5 class="m-0">{{ $group->name }}</h5>
                    </div>
                    <div class="card-body px-1">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                @foreach ($days as $day)
                                    <th class="text-center">
                                        @if (isset($available_days[$group->id][date("w", $day)])) 
                                        <a href="javascript:void(0);" onclick="modal({{$group->id}}, '{{ date("Y-m-d", $day) }}')">
                                            {{ date("m.d", $day) }}
                                        </a>
                                        @else 
                                            {{ date("m.d", $day) }}
                                        @endif                                    
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                @foreach ($days as $day)
                                    <td class="p-0"> 
                                        @if (isset($day_stat[$group->id][date("Y-m-d", $day)]))
                                            <div class="dayStat @if (isset($day_stat[$group->id][date("Y-m-d", $day)]['event'])) userEvent @endif"
                                                style="background: {{$day_stat[$group->id][date("Y-m-d", $day)]['style'] }}"
                                            ></div>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        </tbody>
                    </table>

                    <p class="card-text px-2 pt-2">
                        @if ( isset($this->events[$group->id]) )
                            <strong>@lang('event.eventsBar.title')</strong>  
                            <table class="table table-striped table-hover">
                                @foreach ($events[$group->id] as $event)
                                    <tr>
                                        <td class="text-center">
                                            {{ $event['day_name'] }} {{ $event['full_time'] }}
                                        </td>
                                    </tr>
                                @endforeach
                            </table>  
                        @else
                            @lang('event.eventsBar.no_events')
                        @endif
                    </p>
                    <a wire:click="changeGroup({{$group->id}})" href="javascript:void(0);" class="btn btn-primary mx-3">
                        <i class="fa fa-calendar mr-1"></i>
                        @lang('app.jump_to_calendar')</a>
                    </div>
                </div>
            </div>
            @empty
                <div class="col-md-12">
                    <div class="card card-primary card-outline">
                        <div class="card-header">
                        <h5 class="m-0">@lang('app.information')</h5>
                        </div>
                        <div class="card-body">
                            <p>@lang('app.no_any_groups')</p>
                        </div>
                    </div>
                </div>
            @endforelse
            <!-- /.col-md-6 -->
        </div>
        @if(!$groups->isEmpty())
            <div style="clear:both;"></div>
            <!-- /.row -->
            <script>
                function modal(groupId, date) {
                    livewire.emitTo('events.modal', 'openModal', date, groupId);
                }
            </script>
        @endif
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content -->
    @if(!$groups->isEmpty())
        @livewire('events.modal', ['groupId' => 0], key('eventsmodal'))
    @endif
</div>