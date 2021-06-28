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
                    <select name="user_id" id="" wire:model.defer="state.user_id" wire:change="change_user" class="form-control @error('user_id') is-invalid @enderror"">
                        <option value="0">@lang('event.choose_publisher')</option>
                        @if (!empty($users))
                            @foreach ($users as $user)
                                <option value="{{$user['id']}}">{{ $user['full_name'] }}</option>
                            @endforeach
                        @endif
                    </select>
                    @error('user_id')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror    
                    @else
                        {{ $editEvent['user']['full_name'] }}
                    @endif
                
                </div>
            </div>
        @endif
        <div class="form-group row">
            <label for="start" class="col-md-3 col-form-label">
                @lang('event.service_start')
            </label>
            <div class="col-md-9">
            <select name="start" id="" wire:model.defer="state.start" wire:change="change_end" class="form-control @error('start') is-invalid @enderror"">
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
                <select name="end" wire:model.defer="state.end"  wire:change="change_start" id="" class="form-control @error('end') is-invalid @enderror"">
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
        @if ($eventId)
        <div class="row">
            <div class="col-6 col-md-4">
                <a wire:click="$emitUp('cancelEdit')" class="btn btn-secondary mr-1">
                    <i class="fa fa-times mr-1"></i>
                    @lang('event.cancel_edit')
                </a>
            </div>
            <div class="col-6 col-md-4 text-right text-md-center">
                <button wire:loading.attr="disabled" type="submit" class="btn btn-primary">
                    <i class="fa fa-save mr-1"></i>
                    @lang('event.save_changes')
                </button>
            </div>
            <div class="col-12 col-md-4 mt-md-0 mt-4 text-center text-md-right">
                <a wire:loading.attr="disabled" wire:click.prevent="confirmEventDelete()" class="btn btn-danger">
                    <i class="fa fa-trash mr-1"></i>
                    @lang('event.delete_event')
                </a>
            </div>
        </div>
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
