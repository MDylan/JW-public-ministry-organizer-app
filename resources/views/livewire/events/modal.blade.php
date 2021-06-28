<div>
    <div wire:ignore.self class="modal fade" id="form" tabindex="-1" aria-labelledby="ModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl eventModal"> 
            <div class="modal-content">
                <div class="modal-header pb-0 pl-0 pt-2">
                    <div class="container">
                        <div class="row justify-content-center">
                            <div class="col-12 col-lg-4 mt-2 pb-2 text-center">
                                <h5>
                                    {{ $group_data['name'] }}
                                </h5>
                            </div>
                            <div class="col-12 col-lg-4 text-center">
                                <nav>
                                    <ul class="pagination justify-content-center">
                                        <li class="page-item">
                                            <a class="page-link" href="#" wire:click="setDate('{{$day_data['prev_date']}}')">@lang('Previous')</a></li>
                                        <li class="page-item disabled">
                                            <a class="page-link text-nowrap" href="#" tabindex="-1" aria-disabled="true">
                                                <strong>
                                                {{$day_data['dateFormat']}}    
                                                </strong>
                                            </a>
                                        </li>
                                        @if ($day_data['next_date'] !== false)
                                            <li class="page-item">
                                                <a class="page-link" href="#" wire:click="setDate('{{$day_data['next_date']}}')">@lang('Next')</a>
                                            </li>
                                        @endif
                                    </ul>
                                </nav>
                            </div>
                            {{-- <div class="col-12 col-lg-4 text-center">
                                <ul class="nav nav-tabs border-0 justify-content-center" id="custom-tabs-four-tab" role="tablist">
                                    <li class="nav-item">
                                    <a class="nav-link @if ($active_tab == '') active @endif " id="custom-tabs-home-tab" data-toggle="pill" href="#custom-tabs-home" role="tab" aria-controls="custom-tabs-four-home" aria-selected="true">
                                        @lang('event.modal.tab_events')
                                    </a>
                                    </li>
                                    @if ($current_available === true)
                                        <li class="nav-item">
                                        <a wire:click="$emitTo('events.event-edit', 'createForm')" class="nav-link @if ($active_tab == 'event') active @endif " id="custom-tabs-event-tab" data-toggle="pill" href="#custom-tabs-event" role="tab" aria-controls="custom-tabs-four-profile" aria-selected="false">
                                            @lang('event.modal.tab_set_event')
                                        </a>
                                        </li>
                                    @endif
                                </ul>
                            </div> --}}
                        </div>
                    </div>
                    
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-0">
                    <div class="tab-content">
                        <div class="tab-pane fade @if ($active_tab == '') show active @endif" id="custom-tabs-home" role="tabpanel" aria-labelledby="custom-tabs-home-tab">
                            <div class="m-2 schedule_new" style="grid-template-columns: [times] 4em repeat({{$group_data['max_publishers']}}, 1fr);" aria-labelledby="schedule-heading">
                                @if (!empty($day_data['table']))
                                    @foreach ($day_data['table'] as $time => $r)
                                        <div class="time-slot" style="grid-row: {{$r['row']}};">
                                            @if ($r['status'] == 'full') 
                                                <button id="ts_{{$r['ts']}}" disabled class="btn btn-outline-danger">{{$r['hour']}}</button>
                                            @elseif ($r['status'] == 'ready')
                                                <button id="ts_{{$r['ts']}}" wire:click="setStart({{$r['ts']}})" class="btn btn-warning pb-1">{{$r['hour']}}</button>
                                            @else
                                                <button id="ts_{{$r['ts']}}" wire:click="setStart({{$r['ts']}})" class="btn btn-success pb-1">{{$r['hour']}}</button>
                                            @endif                                    
                                        </div>
                                        @if (isset($day_events[$time]))
                                            @foreach ($day_events[$time] as $event)
                                                <div class="session session-2 track-{{($event['cell'])}} @if ($event['user_id'] == auth()->id())
                                                    userEvent
                                                @endif" style="grid-column: {{$event['cell'] + 1}}; grid-row: {{$event['row']}}; grid-row-end: {{( $event['row'] + $event['height'])}};">
                                                    <h3 class="session-title">
                                                        @if ($event['status'] == 'disabled')
                                                        <i class="fa fa-user mr-1"></i> {{ $event['user']['full_name'] }}
                                                        @else
                                                        <a href="#" wire:click.prevent="editEvent_modal({{$event['id']}})"><i class="fa fa-user mr-1"></i> {{ $event['user']['full_name'] }}</a>    
                                                        @endif
                                                        
                                                    </h3>
                                                    <span class="session-time">{{$event['start_time']}} - {{$event['end_time']}}</span>
                                                    <span class="session-presenter"></span>
                                                </div>  
                                            @endforeach
                                        @endif
                                        <div class="grid-row" style="grid-row: {{$r['row']}};grid-column: 1 / {{$group_data['max_publishers'] + 2}};"></div>
                                    @endforeach
                                @endif
                            </div>
                        </div>
                        <div class="tab-pane fade @if ($active_tab == 'event') show active @endif" id="custom-tabs-event" role="tabpanel" aria-labelledby="custom-tabs-event-tab">
                            <div class="m-4">
                                @livewire('events.event-edit', 
                                    [
                                        'groupId' => $form_groupId, 
                                        'date' => $date
                                    ], key('eventEdit-'.$form_groupId.'-'.$date))
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