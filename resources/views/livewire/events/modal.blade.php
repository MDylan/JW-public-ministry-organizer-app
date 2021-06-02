<div>
    <div wire:ignore.self class="modal fade" id="form" tabindex="-1" aria-labelledby="ModalLabel" aria-hidden="true">
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
                          <a class="nav-link @if ($active_tab == '') active @endif " id="custom-tabs-home-tab" data-toggle="pill" href="#custom-tabs-home" role="tab" aria-controls="custom-tabs-four-home" aria-selected="true">
                            @lang('event.modal.tab_events')
                          </a>
                        </li>
                        <li class="nav-item">
                          <a class="nav-link @if ($active_tab == 'event') active @endif " id="custom-tabs-event-tab" data-toggle="pill" href="#custom-tabs-event" role="tab" aria-controls="custom-tabs-four-profile" aria-selected="false">
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
                            <div class="m-2 schedule_new" style="grid-template-columns: [times] 4em repeat({{$group_data['max_publishers']}}, 1fr);" aria-labelledby="schedule-heading">
                                @if (!empty($day_data['table']))
                                    @foreach ($day_data['table'] as $time => $r)
                                        <div class="time-slot" style="grid-row: {{$r['row']}};">
                                            @if ($r['status'] == 'full') 
                                                <button disabled class="btn btn-outline-danger">{{$r['hour']}}</button>
                                            @elseif ($r['status'] == 'ready')
                                            <button wire:click.prevent="setStart({{$r['ts']}})" class="btn btn-warning pb-1">{{$r['hour']}}</button>
                                            @else
                                                <button wire:click.prevent="setStart({{$r['ts']}})" class="btn btn-success pb-1">{{$r['hour']}}</button>
                                            @endif                                    
                                        </div>
                                        @if (isset($day_events[$time]))
                                            @foreach ($day_events[$time] as $event)
                                                <div class="session session-2 track-{{($event['cell'])}}" style="grid-column: {{$event['cell'] + 1}}; grid-row: {{$event['row']}}; grid-row-end: {{( $event['row'] + $event['height'])}};">
                                                    <h3 class="session-title"><a href="#" wire:click.prevent="editEvent_modal({{$event['id']}})"><i class="fa fa-user mr-1"></i> {{ $event['user']['full_name'] }}</a></h3>
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

                        <div class="tab-pane fade @if ($active_tab == 'event_edit') show active @endif" id="custom-tabs-event_edit" role="tabpanel" aria-labelledby="custom-tabs-event_edit-tab">
                            <div class="m-4">
                                <h4>@lang('event.edit_event')</h4>
                                <form wire:submit.prevent="editEvent">
                                    @csrf
                                    <div class="form-group row">
                                      <label for="start" class="col-md-3 col-form-label">
                                          @lang('event.service_start')
                                      </label>
                                      <div class="col-md-9">
                                        <select name="start" id="" wire:model.defer="event_edit.start" wire:change="change_end" class="form-control">
                                            <option value="0">@lang('event.choose_time')</option>
                                            @if (!empty($day_data['edit_selects']))
                                                @foreach ($day_data['edit_selects']['start'] as $time => $option)
                                                    <option value="{{$time}}">{{ $option }}</option>
                                                @endforeach
                                            @endif
                                        </select>
                                      </div>
                                    </div>
                                    <div class="form-group row">
                                        <label for="end" class="col-md-3 col-form-label">
                                            @lang('event.service_end')
                                        </label>
                                        <div class="col-md-9">
                                          <select name="end" wire:model.defer="event_edit.end"  wire:change="change_start" id="" class="form-control">
                                              <option value="0">@lang('event.choose_time')</option>
                                              @if (!empty($day_data['edit_selects']))
                                                @foreach ($day_data['edit_selects']['end'] as $time => $option)
                                                    <option value="{{$time}}">{{ $option }}</option>
                                                @endforeach
                                              @endif
                                          </select>
                                        </div>
                                    </div>
                                    <button type="button" wire:click.prevent="cancelEdit" class="btn btn-secondary">
                                        <i class="fa fa-times mr-1"></i>
                                        @lang('event.cancel_edit')
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa fa-save mr-1"></i>
                                        @lang('event.save_changes')
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