<div>
    <div>
    <h4>{{$formText['title']}}</h4>
    <form wire:submit.prevent="saveEvent">
        @csrf
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
            <div class="col-md-6">
                <a wire:click="$emitUp('cancelEdit')" class="btn btn-secondary mr-1">
                    <i class="fa fa-times mr-1"></i>
                    @lang('event.cancel_edit')
                </a>
                <button wire:loading.attr="disabled" type="submit" class="btn btn-primary">
                    <i class="fa fa-save mr-1"></i>
                    @lang('event.save_changes')
                </button>
            </div>
            <div class="col-md-6">
                <a wire:loading.attr="disabled" wire:click.prevent="confirmEventDelete()" class="btn btn-danger float-right">
                    <i class="fa fa-trash mr-1"></i>
                    @lang('event.delete_event')
                </a>
            </div>
        </div>
        @else
            <a wire:click="$emitUp('cancelEdit')" class="btn btn-secondary mr-1">
                <i class="fa fa-times mr-1"></i>
                @lang('Cancel')
            </a>
            <button wire:loading.attr="disabled" type="submit" class="btn btn-primary">
                <i class="fa fa-save mr-1"></i>
                @lang('event.save')
            </button>
        @endif
    </form>
    </div>
</div>
