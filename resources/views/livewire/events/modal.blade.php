<div @if($polling) wire:poll.30000ms wire:key="modal-polling" @endif>
    @if($show_content)
        <div wire:ignore.self class="modal fade" id="form" tabindex="-1" aria-labelledby="ModalLabel" aria-hidden="true">
            @php
                $date = \Carbon\Carbon::parse($day_data['date']);
            @endphp
            <div class="modal-dialog modal-xl eventModal"> 
                <div class="modal-content">
                    <div class="modal-header pb-0 pt-2">
                        <div class="w-100">
                            <div class="row justify-content-center">
                                <div class="col-12 col-lg-5 mt-2 pb-2 text-center">
                                    <h5>                                    
                                    @if($editor && ($date->isFuture() || $date->isToday()) ) 
                                        <a wire:ignore.self href="javascript:void(0);" 
                                        wire:click="openPosterModal()"
                                        class="btn btn-sm btn-info mb-2">
                                            <i class="fa fa-plus mr-1"></i>
                                            @lang('group.poster.button')</a>
                                    @endif
                                    
                                        {{ $group_data['name'] }}
                                        @if(isset($group_data['current_date']['note']))
                                            <span class="badge badge-info">{{ $group_data['current_date']['note'] }}</span>
                                        @endif
                                    </h5>
                                </div>
                                <div class="col-12 col-lg-4 text-center">
                                    <nav>
                                        <ul class="pagination justify-content-center">
                                            @if (($day_data['prev_date'] ?? false) !== false)
                                                <li class="page-item" id="prev_{{$date}}">
                                                    <a class="page-link" href="javascript:void(0);" wire:click="setDate('{{$day_data['prev_date']}}')">@lang('Previous')</a>
                                                </li>
                                            @endif
                                            <li class="page-item disabled">
                                                <a class="page-link text-nowrap" href="#" tabindex="-1" aria-disabled="true">
                                                    <b>{{$day_data['dateFormat']}}</b>
                                                </a>
                                            </li>
                                            @if (($day_data['next_date'] ?? false) !== false)
                                                <li class="page-item" id="next_{{$date}}">
                                                    <a class="page-link" href="javascript:void(0);" wire:click="setDate('{{$day_data['next_date']}}')">@lang('Next')</a>
                                                </li>
                                            @endif
                                        </ul>
                                    </nav>
                                </div>
                            </div>                        
                        </div>                    
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body p-0">
                        <div class="tab-content">
                            <div class="tab-pane fade @if ($active_tab == '') show active @endif relative events-list" id="custom-tabs-home" role="tabpanel" aria-labelledby="custom-tabs-home-tab">

                                @if (count($group_data['posters']) > 0)
                                    <div class="callout callout-success mx-2 mt-2">
                                        <h5 class="border-bottom mb-1 pb-1"> 
                                            <i class="far fa-file-image mr-1"></i> @lang('group.poster.title')
                                        </h5>
                                        @foreach ($group_data['posters'] as $poster)                            
                                            <div class="row">
                                                <div class="col">
                                                    @if($editor) 
                                                        <button wire:ignore.self
                                                            wire:click="openPosterModal({{ $poster['id'] }})"
                                                            class="btn btn-sm btn-info my-1 mr-1">
                                                            <i class="far fa-edit"></i>
                                                        </button>
                                                    @endif
                                                    <button wire:click="togglePosterRead({{ $poster['id'] }})" wire:loading.attr="disabled" class="btn btn-sm btn-info" type="button" data-toggle="collapse" data-target="#collapse_poster{{ $poster['id'] }}" aria-expanded="false" aria-controls="collapse_poster{{ $poster['id'] }}">
                                                        @if(count($poster['user_read'] ?? []) == 0) 
                                                            <i class="far fa-check-circle mr-1"></i>
                                                            @lang('group.poster.i_have_read')
                                                        @else
                                                            <i class="fas fa-eye mr-1"></i>
                                                            @lang('app.show')
                                                        @endif
                                                    </button>
                                                    <strong>
                                                        {{  \Carbon\Carbon::parse($poster["show_date"])->format(__('app.format.date')) }}
                                                        - 
                                                        @if ($poster["hide_date"] !== null)
                                                            {{  \Carbon\Carbon::parse($poster["hide_date"])->format(__('app.format.date')) }}
                                                        @else
                                                            @lang('group.poster.until_revoked')
                                                        @endif
                                                    </strong>
                                                    <div class="collapse @if(count($poster['user_read'] ?? []) == 0) show @endif" id="collapse_poster{{ $poster['id'] }}">
                                                        {!! $poster['info'] !!}
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach                                
                                    </div>
                                @endif

                                @if ($editor && $group_data['need_approval'] == 1)

                                    <div class="mx-2 @if($bulk_function) p-2 border border-warning rounded @endif">                                        
                                        @if($bulk_function)
                                            <div class="btn-group" role="group" aria-label="@lang('event.bulk.button')">
                                                <button wire:click="cancelBulk" class="btn btn-secondary">
                                                    <i class="fas fa-times mr-1"></i>
                                                        @lang('event.bulk.cancel')
                                                </button>
                                                <button wire:click="acceptBulk" type="button" class="btn btn-success">
                                                    <i class="far fa-check-circle mr-1"></i>
                                                        @lang('event.bulk.accept')
                                                </button>
                                                <button wire:click="rejectBulk" type="button" class="btn btn-danger">
                                                    <i class="far fa-times-circle mr-1"></i>
                                                        @lang('event.bulk.reject')
                                                </button>
                                            </div>
                                            <small class="form-text text-muted">
                                                @lang('event.bulk.help')
                                            </small>
                                        @else
                                            <button wire:click="setBulk" class="btn btn-primary">
                                                <i class="fas fa-tasks mr-1"></i>
                                                    @lang('event.bulk.button')
                                            </button>
                                        @endif
                                    </div>
                                @endif

                                {{-- {{ var_dump($day_events) }} --}}
                                <div class="m-2 schedule_new" style="grid-template-columns: [times] 4em repeat({{$date_data['peak']}}, 1fr);" aria-labelledby="schedule-heading">
                                    @if (!empty($day_data['table']))
                                        @foreach ($day_data['table'] as $time => $r)
                                            <div class="time-slot" style="grid-row: {{$r['row']}};">
                                                @if ($r['status'] == 'full')
                                                    <button id="ts_{{$r['ts']}}" disabled class="btn btn-outline-danger">{{$r['hour']}}</button>
                                                @elseif ($r['status'] == 'ready')
                                                    <button wire:loading.attr="disabled" id="ts_{{$r['ts']}}" wire:click="setStart({{$r['ts']}})" class="btn btn-warning pb-1">{{$r['hour']}}</button>
                                                @else
                                                    <button wire:loading.attr="disabled" id="ts_{{$r['ts']}}" wire:click="setStart({{$r['ts']}})" class="btn btn-success pb-1">{{$r['hour']}}</button>
                                                @endif
                                            </div>
                                            @if (isset($day_events[$time]))
                                                {{-- @dd($day_events) --}}
                                                @foreach ($day_events[$time] as $event)
                                                    @php
                                                        $event_cell = $event['cell'];
                                                        //if more then 8 column exists than we reset the css
                                                        if($event_cell%8 == 0) {
                                                            $event_cell = $event['cell'] - 8;
                                                        }
                                                    @endphp
                                                    <div id="e_{{ $event['id'] }}" class="session session-2 track-{{$event_cell}}@if($event['status'] == 0)-plan @endif 
                                                    @if ($event['user_id'] == auth()->id())
                                                        userEvent
                                                    @endif
                                                    @if ($bulk_function && ($bulk_ids[$event['id']] ?? false) == true)
                                                        bulk_selected
                                                    @endif" style="grid-column: {{$event['cell'] + 1}}; grid-row: {{$event['row']}}; grid-row-end: {{( $event['row'] + $event['height'])}};">
                                                        <h3 class="session-title">
                                                            @if($event['status'] == 0)
                                                                <span class="badge badge-warning">
                                                                    <i class="fas fa-balance-scale-right" title="@lang('event.status_0')"></i>
                                                                </span>
                                                            @endif
                                                            
                                                            @if ($event['editable'] != 'disabled' && !$bulk_function) 
                                                                <a href="" wire:click.prevent="editEvent_modal({{$event['id']}})" id="edit_event_{{ $event['id'] }}">
                                                            @elseif($editor && ($bulk_function && $event['status'] != 1))
                                                                @if ($r['status'] != 'full' || ($bulk_ids[$event['id']] ?? false) == true)
                                                                    <a href="" wire:click.prevent="bulk({{$event['id']}})" id="bulk_event_{{ $event['id'] }}">
                                                                @else
                                                                    <a href="" id="bulk_event_{{ $event['id'] }}">
                                                                @endif
                                                                
                                                            @elseif ($event['editable'] != 'disabled' && $bulk_function) 
                                                                <a id="edit_event_{{ $event['id'] }}">
                                                            @endif
                                                                @if (is_array($group_data['signs']))
                                                                    @foreach ($group_data['signs'] as $icon => $sign) 
                                                                        @if(isset($group_data['users_signs'][$event['user_id']][$icon]))
                                                                            @if ($group_data['users_signs'][$event['user_id']][$icon])
                                                                                <i class="fa {{ $icon }} mr-1" title="@if(isset($sign['name'])) {{ $sign['name'] }} @endif"></i>
                                                                            @endif
                                                                        @endif                                                                
                                                                    @endforeach
                                                                @else 
                                                                    <i class="fa fa-user mr-1"></i>
                                                                @endif 
                                                                {{ $event['user']['name'] }}
                                                            @if ($event['editable'] != 'disabled' && !$bulk_function) 
                                                                    <i class="fa fa-edit ml-1"></i>
                                                                </a>
                                                            @elseif ($editor && ($bulk_function && $event['status'] != 1))
                                                                    @if (($bulk_ids[$event['id']] ?? false) == true)
                                                                        <i class="far fa-check-circle ml-1 text-success"></i>
                                                                    @elseif($r['status'] == 'full')
                                                                        <i class="fas fa-hourglass ml-1 text-warning"></i>
                                                                    @else
                                                                        <i class="far fa-circle ml-1 text-warning"></i>
                                                                    @endif
                                                                </a>
                                                            @elseif ($event['editable'] != 'disabled' && $bulk_function) 
                                                                </a>
                                                            @endif
                                                            @if(!$bulk_function && $group_data['showPhone'] == 1 && $group_data['users_active'][$event['user_id']])
                                                            <div wire:ignore id="phone_event_{{ $event['id'] }}" class="badge badge-info p-2 font-weight-normal" onclick="showChild(this, 'hidden_child');">
                                                                <i class="fas fa-phone"></i>
                                                                <span class="ml-1 hidden_child">
                                                                    @if(($event['user']['show_fields']['phone'] ?? false) == true || $editor) 
                                                                        @if(($event['user']['show_fields']['phone'] ?? false) == false)
                                                                            <i class="fas fa-eye-slash mr-1 text-warning"></i>
                                                                        @endif
                                                                    {{ $event['user']['phone_number'] }}
                                                                    @endif
                                                                </span>
                                                            </div>
                                                            @endif
                                                            @if (isset($event['comment']))
                                                                <button wire:ignore id="comment_event_{{ $event['id'] }}" class="btn btn-sm btn-info px-1 py-0" onclick="showFullChild(this, 'cr');">
                                                                    <div class="cr crop_child overflow-hidden">
                                                                        <i class="far fa-comment-dots mr-1"></i>{{ $event['comment'] }}
                                                                    </div>
                                                                </button>
                                                            @endif  
                                                        </h3>
                                                        <span class="session-time">{{$event['start_time']}} - {{$event['end_time']}}</span>
                                                        <span class="session-presenter"></span>
                                                    </div>  
                                                @endforeach
                                            @endif
                                            <div class="grid-row" style="grid-row: {{$r['row']}};grid-column: 1 / {{$date_data['peak'] + 2}};"></div>
                                        @endforeach
                                    @endif
                                </div>
                            </div>
                            <div class="tab-pane fade @if ($active_tab == 'event') show active @endif" id="custom-tabs-event" role="tabpanel" aria-labelledby="custom-tabs-event-tab">
                                <div class="m-4">
                                    @if ($active_tab == 'event')
                                        {{-- @livewire('events.event-edit', 
                                            [
                                                'groupId' => $form_groupId, 
                                                'date' => $date
                                            ], key('eventEdit-'.$form_groupId.'-'.$date)) --}}
                                            <livewire:events.event-edit :groupId="$form_groupId" :date="$date" :wire:key="'eventEdit-'.$form_groupId.'-'.$date">
                                        @endif
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
    @endif
</div>