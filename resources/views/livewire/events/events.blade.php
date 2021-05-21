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
                            <div class="container-fluid pl-0 pr-0">
                                <div class="row">
                                    <div class="col-md">
                                        @if (count($groups) > 1)
                                        <form wire:submit.prevent="changeGroup" class="form-inline">
                                            @csrf
                                            <div class="input-group">
                                                <div class="custom-file">
                                                    <select wire:model="form_groupId" class="form-control" id="inlineForm">
                                                        <option selected>@lang('event.choose_group')</option>
                                                        @foreach ($groups as $group)
                                                            {{-- @if ($group['id'] != $group_data['id']) --}}
                                                                <option value="{{$group['id']}}">{{ $group['name'] }}</option>
                                                            {{-- @endif --}}
                                                        @endforeach
                                                    </select> 
                                                </div>
                                                <div class="input-group-append">
                                                   <button type="submit" class="btn btn-primary" type="button">@lang('event.switch')</button>
                                                </div>
                                              </div>
                                        </form>
                                        @endif
                                    </div>
                                    <div class="col-md d-flex justify-content-center mt-1">
                                        <h4>{{ $group_data['name'] }}</h4>
                                    </div>
                                    <div class="col-md d-flex justify-content-end">
                                        <nav>
                                            <ul class="pagination justify-content-center">
                                                <li class="page-item"><a class="page-link" href="{{ route('calendar') }}/{{$pagination['prev']['year']}}/{{$pagination['prev']['month']}}">@lang('Previous')</a></li>
                                                <li class="page-item disabled">
                                                    <a class="page-link text-nowrap" href="#" tabindex="-1" aria-disabled="true"><strong>{{$year}}. {{__("$current_month")}}</strong></a>
                                                </li>                                  
                                                <li class="page-item">
                                                    <a class="page-link" href="{{ route('calendar') }}/{{$pagination['next']['year']}}/{{$pagination['next']['month']}}">@lang('Next')</a>
                                                </li>
                                            </ul>
                                        </nav>
                                    </div>                                
                                </div>
                            </div>
                            {{-- @livewire('events.calendar', ['month' => $month, 'year' => $year])  --}}
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
                                    livewire.emit('openModal', date);
                                }
                            </script>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div  wire:ignore.self class="modal fade" id="form" tabindex="-1" aria-labelledby="ModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header pb-0 pl-0 pt-2">
                    <ul class="nav nav-tabs border-0" id="custom-tabs-four-tab" role="tablist">
                        <li class="pt-2 px-3">
                            <h4 class="card-title">
                            {{ $group_data['name'] }} - {{$day_data['dateFormat']}}    
                            </h4>
                        </li>
                        <li class="nav-item">
                          <a class="nav-link @if ($active_tab == '') active @endif" id="custom-tabs-home-tab" data-toggle="pill" href="#custom-tabs-home" role="tab" aria-controls="custom-tabs-four-home" aria-selected="true">                              @lang('event.modal.tab_events')
                          </a>
                        </li>
                        <li class="nav-item">
                          <a class="nav-link @if ($active_tab == 'event') active @endif"" id="custom-tabs-event-tab" data-toggle="pill" href="#custom-tabs-event" role="tab" aria-controls="custom-tabs-four-profile" aria-selected="false">
                            @lang('event.modal.tab_set_event')
                          </a>
                        </li>
                      </ul>

                      <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                      </button>
                    
                </div>
                <div class="modal-body p-0">

                    <div class="tab-content">
                        <div class="tab-pane fade @if ($active_tab == '') show active @endif" id="custom-tabs-home" role="tabpanel" aria-labelledby="custom-tabs-home-tab">
                            
                            <table class="table table-hover table-sm mb-0">
                                @foreach ($day_data['table'] as $time => $timestamp)
                                <tr>
                                    <td class="ml-4">
                                        <button wire:click.prevent="setStart({{$timestamp}})" class="btn btn-primary">{{$time}}</button>
                                    </td>
                                    <td></td>
                                </tr>
                                @endforeach                        
                            </table>

                        </div>
                        <div class="tab-pane fade @if ($active_tab == 'event') show active @endif" id="custom-tabs-event" role="tabpanel" aria-labelledby="custom-tabs-event-tab">
                            <div class="m-4">
                                <h4>Válassz időpontot, amikor szolgálnál:</h4>
                                <form wire:submit.prevent="createEvent">
                                    @csrf
                                    <div class="form-group row">
                                      <label for="start" class="col-md-3 col-form-label">
                                          @lang('event.service_start')
                                      </label>
                                      <div class="col-md-9">
                                        <select name="start" id="" wire:model.defer="state.start" wire:change="change_end" class="form-control">
                                            <option value="0">@lang('event.choose_time')</option>
                                            @foreach ($day_data['selects']['start'] as $time => $option)
                                                <option value="{{$time}}">{{ $option }}</option>
                                            @endforeach
                                        </select>
                                      </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="end" class="col-md-3 col-form-label">
                                            @lang('event.service_end')
                                        </label>
                                        <div class="col-md-9">
                                          <select name="end" wire:model.defer="state.end"  wire:change="change_start" id="" class="form-control">
                                              <option value="0">@lang('event.choose_time')</option>
                                              @foreach ($day_data['selects']['end'] as $time => $option)
                                                  <option value="{{$time}}">{{ $option }}</option>
                                              @endforeach
                                          </select>
                                        </div>
                                      </div>
                                      <button type="submit" class="btn btn-primary">
                                        <i class="fa fa-save mr-1"></i>
                                        @lang('event.save')
                                        </button>
                                  </form>
                            </div>
                        </div>
                    </div>                    

                    
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fa fa-times mr-1"></i> @lang('Close')</button>
                </div>
            </div>
        </div>
    </div>

</div>

