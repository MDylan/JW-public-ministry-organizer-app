<div>
    <div>
    <h4>{{$formText['title']}}</h4>
    <form wire:submit.prevent="saveEvent">
        @csrf
        @if (in_array($role, ['admin', 'roler', 'helper']))
            <div class="form-group row">
                <label for="start" class="col-md-3 col-form-label">
                    @lang('event.publisher')
                </label>
                <div class="col-md-9">
                    @if ($eventId === null)
                    <select wire:model.defer="state.user_id" wire:change="change_user" class="form-control @error('user_id') is-invalid @enderror"">
                        <option value="0">@lang('event.choose_publisher')</option>
                        @if (!empty($users))
                            @foreach ($users as $user)
                                <option value="{{$user['id']}}">{{ $user['name'] }}</option>
                            @endforeach
                        @endif
                    </select>
                    @error('user_id')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror    
                    @else
                        {{ $editEvent['user']['name'] }}
                    @endif
                
                </div>
            </div>
        @endif
        <div class="form-group row">
            <label for="start" class="col-md-3 col-form-label">
                @lang('event.service_start')
            </label>
            <div class="col-md-9">
            <select wire:model.defer="state.start" wire:change="change_end" class="form-control @error('start') is-invalid @enderror"">
                <option value="0">@lang('event.choose_time')</option>
                @if (!empty($day_data['selects']))
                    @foreach ($day_data['selects']['start'] as $time => $option)
                        <option value="{{$time}}">{{ $option }}</option>
                    @endforeach
                @endif
            </select>
            @error('start')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
            </div>
        </div>
        <div class="form-group row">
            <label for="end" class="col-md-3 col-form-label">
                @lang('event.service_end')
            </label>
            <div class="col-md-9">
                <select wire:model.defer="state.end" wire:change="change_start" id="" class="form-control @error('end') is-invalid @enderror"">
                    <option value="0">@lang('event.choose_time')</option>
                    @if (!empty($day_data['selects']))
                    @foreach ($day_data['selects']['end'] as $time => $option)
                        <option value="{{$time}}">{{ $option }}</option>
                    @endforeach
                    @endif
                </select>
                @error('end')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
            </div>
        </div>
        <div class="form-group row">
            <label for="end" class="col-md-3 col-form-label">
                @lang('event.comment.label')
            </label>
            <div class="col-md-9">
                <input wire:model.defer="state.comment" aria-describedby="commentHelpBlock" type="text" class="form-control @error('comment') is-invalid @enderror" />
                @error('comment')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                <small id="commentHelpBlock" class="form-text text-muted">
                    @lang('event.comment.helper')
                </small>
            </div>
        </div>
        @if (count($other_events) > 0) 
            <div class="row">
                <div class="col-md-3"></div>
                <div class="col-md-9">
                    <div class="alert alert-light">
                        <i class="fas fa-info mr-1"></i> @lang('event.paralel_events')<br/>
                        @foreach ($other_events as $other)
                            <b>{{ Crypt::decryptString($other['name']) }}</b>
                                {{ \Carbon\Carbon::parse($other['start'])->format(__('app.format.time')) }} - 
                                {{ \Carbon\Carbon::parse($other['end'])->format(__('app.format.time')) }}<br/>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
        @if ( $group_data['need_approval'] === 1)
            
            @if (in_array($role, ['admin', 'roler']))
                <div class="form-group row">
                    <label for="approval_check" class="col-md-3 col-form-label">
                        @lang('event.status')
                    </label>
                    <div class="col-md-9">
                        <select wire:model.defer="state.status" id="" class="form-control @error('status') is-invalid @enderror">
                            <option value="0">@lang('event.status_0')</option>
                            <option value="1">@lang('event.status_1')</option>
                            @if($eventId !== null)
                                <option value="2">@lang('event.status_2')</option>
                            @endif
                           
                        </select>
                        @error('status')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror

                        @if(count($user_statistics))
                            <div class="alert alert-light mt-2">
                                <b><i class="fas fa-hourglass-half mr-1"></i> @lang('loghistory.history_button'):</b><br/>
                                @foreach ($user_statistics as $stat)
                                    <div class="row">
                                        <div class="col-12 col-md-6 text-left text-md-right">
                                            <b>{{ Crypt::decryptString($stat['name']) }}</b>
                                        </div>
                                        <div class="col-12 col-md-6">
                                            @if ($stat['status'] == 0)
                                                <span class="badge badge-warning mr-1" style="width: 24px;">
                                                    <i class="fas fa-balance-scale-right" title="@lang('event.status_0')"></i>
                                                </span>
                                            @elseif($stat['status'] == 1)
                                                <span class="badge badge-success mr-1" style="width: 24px;">
                                                    <i class="far fa-check-circle" title="@lang('event.status_1')"></i>
                                                </span>
                                            @else
                                                <span class="badge badge-danger mr-1" style="width: 24px;">
                                                    <i class="fas fa-times" title="@lang('event.status_2')"></i>
                                                </span>
                                            @endif
                                            {{ \Carbon\Carbon::parse($stat['start'])->format(__('app.format.datetime')) }} 
                                            - {{ \Carbon\Carbon::parse($stat['end'])->format(__('app.format.time')) }}
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>                
            @endif
            @if ($state['status'] == 0)            
                <div class="form-group row">
                    <div class="col-md-3">
                    </div>
                    <div class="col-md-9">
                        @lang('event.need_approval')
                    </div>
                </div>
            @endif
        @endif

        @error('busy')
            <div class="alert alert-danger">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @enderror

        @if ($eventId)
            <div class="row">
                <div class="col-6 col-md-3">
                    <a wire:click="$emitUp('cancelEdit')" class="btn btn-secondary mr-1 w-100">
                        <i class="fa fa-times mr-1"></i>
                        @lang('event.cancel_edit')
                    </a>
                </div>
                <div class="col-6 col-md-3 text-right text-md-center">
                    <button wire:loading.attr="disabled" type="submit" class="btn btn-primary w-100">
                        <i class="fa fa-save mr-1"></i>
                        @lang('event.save_changes')
                    </button>
                </div>
                <div class="col-6 col-md-3 text-left text-md-center mt-md-0 mt-4">
                    <button class="btn btn-info w-100" type="button" data-toggle="collapse" data-target="#histories" aria-expanded="false" aria-controls="histories">
                        <i class="fa fa-history mr-1"></i> @lang('loghistory.history_button')
                    </button>
                </div>
                <div class="col-6 col-md-3 mt-md-0 mt-4 text-right text-md-right">
                    <a wire:loading.attr="disabled" wire:click.prevent="confirmEventDelete()" class="btn btn-danger w-100">
                        <i class="fa fa-trash mr-1"></i>
                        @lang('event.delete_event')
                    </a>
                </div>
            </div>
            @if(count($editEvent['histories']))
                <div class="collapse" id="histories">
                    <div class="row m-2 mt-4">
                        <div class="col-12 text-bold">
                            <i class="fa fa-history mr-1"></i> @lang('loghistory.history_title')
                        </div>
                    </div>
                    @foreach ($editEvent['histories'] as $history)
                        <div class="row mx-2 mb-sm-2">
                            <div class="col-md-4">
                                {{ __('loghistory.events.'.$history['event'], [ 
                                    'userName' => $history['user']['name'] ?? __('app.unknown'),
                                    'date' => $history['created_at_format']
                                ]) }}
                            </div>
                            <div class="col-md-8 mb-3 mb-md-0">
                                @if(count($history['changes_array']))
                                    <div class="row">
                                        @foreach ($history['changes_array'] as $type => $changes)
                                        <div class="col-6"><strong>{{ __('loghistory.change_type.'.$type) }}:</strong><br/>
                                            @foreach ($changes as $field => $change)
                                                {{ __('loghistory.models.'.$history['model_name'].'.'.$field) }} : {{ $change }}<br/>
                                            @endforeach
                                        </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>                        
                        </div>
                    @endforeach
                </div>
            @endif
        @else
            <div class="row">
                <div class="col-6 col-md-4">
                    <a wire:click="$emitUp('cancelEdit')" class="btn btn-secondary mr-1">
                        <i class="fa fa-times mr-1"></i>
                        @lang('Cancel')
                    </a>
                </div>
                <div class="col-6 col-md-4 text-right text-md-center">
                    <button wire:loading.attr="disabled" type="submit" class="btn btn-primary">
                        <i class="fa fa-save mr-1"></i>
                        @lang('event.save')
                    </button>
                </div>
            </div>
        @endif
    </form>
    </div>
</div>
