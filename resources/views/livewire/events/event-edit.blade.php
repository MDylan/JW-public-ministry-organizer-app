<div>
    <h4>{{$formText['title']}}</h4>
    <form wire:submit.prevent="saveEvent">
        @csrf
        <div class="form-group row">
            <label for="start" class="col-md-3 col-form-label">
                @lang('event.service_start')
            </label>
            <div class="col-md-9">
            <select name="start" id="" wire:model.defer="state.start" wire:change="change_end" class="form-control">
                <option value="0">@lang('event.choose_time')</option>
                @if (!empty($day_data['selects']))
                    @foreach ($day_data['selects']['start'] as $time => $option)
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
                <select name="end" wire:model.defer="state.end"  wire:change="change_start" id="" class="form-control">
                    <option value="0">@lang('event.choose_time')</option>
                    @if (!empty($day_data['selects']))
                    @foreach ($day_data['selects']['end'] as $time => $option)
                        <option value="{{$time}}">{{ $option }}</option>
                    @endforeach
                    @endif
                </select>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">
            <i class="fa fa-save mr-1"></i>
            @lang('event.save')
        </button>
    </form>
</div>
