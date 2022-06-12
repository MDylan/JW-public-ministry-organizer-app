<div>
    <form autocomplete="off" wire:submit.prevent="saveDate">
        <x-modal modalId="SpecialDateModal" modalSize="modal-xl">
            <x-slot name="title">
                @lang('group.special_dates.title') @if(isset($group->name)) - {{ $group->name }} @endif
            </x-slot>
        
            <x-slot name="content">
                    <div class="alert alert-info">
                    @lang('group.special_dates.info')
                </div>
                <div class="row">
                    <div class="col-md-8">                    
                        <div class="row mb-2">
                            <div class="col-md-6">
                                <label for="state_date">@lang('group.special_dates.date')</label>
                                <input @if(isset($state['id'])) disabled @endif wire:model="state.date" type="date" id="state_date" class="form-control @error('date') is-invalid @enderror" />
                                @error('date')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label for="state_status">@lang('group.special_dates.date_status')</label>
                                <select id="state_status" wire:model="state.date_status" class="form-control @error('date_status') is-invalid @enderror">
                                    <option value="2">@lang('group.special_dates.statuses.2')</option>
                                    <option value="0">@lang('group.special_dates.statuses.0')</option>
                                </select>
                                @error('date_status')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                            </div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-12">
                                <label for="state_note">@lang('group.special_dates.note')</label>
                                <input wire:model.defer="state.note" type="text" id="state_note" class="form-control @error('note') is-invalid @enderror" placeholder="@lang('group.special_dates.note_placeholder')" />
                                @error('note')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                            </div>
                        </div>
                        <div class="row">
                            <div class="col">
                                <div class="form-group">
                                    <label for="state_min_publishers">{{__('group.min_publishers')}}</label>
                                    <input @if(($state['date_status'] ?? 2) != 2) disabled @endif wire:model.defer="state.date_min_publishers" value="" placeholder="{{__('group.min_publishers_placeholder')}}" type="number" class="form-control @error('date_min_publishers') is-invalid @enderror" id="state_min_publishers" />
                                    @error('date_min_publishers')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                </div>
                            </div>
                            <div class="col">
                                <div class="form-group">
                                    <label for="state_max_publishers">{{__('group.max_publishers')}}</label>
                                    <input @if(($state['date_status'] ?? 2) != 2) disabled @endif wire:model.defer="state.date_max_publishers" type="number" class="form-control @error('date_max_publishers') is-invalid @enderror" id="state_max_publishers" value="" placeholder="{{__('group.max_publishers_placeholder')}}" />
                                    @error('date_max_publishers')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                </div>  
                            </div>
                        </div>
                        <div class="row">
                            <div class="col">
                                <div class="form-group">
                                    <label for="state_min_time">{{__('group.min_time')}}</label>
                                    <select wire:model="state.date_min_time" @if(($state['date_status'] ?? 2) != 2) disabled @endif class="form-control @error('date_min_time') is-invalid @enderror" id="state_min_time">
                                        @foreach ($min_time_options as $field => $translate) 
                                            <option value="{{$translate}}">{{__('group.min_time_options.'.$translate)}}</option>
                                        @endforeach
                                    </select>
                                    @error('date_min_time')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                </div>
                            </div>
                            <div class="col">
                                <div class="form-group">
                                    <label for="state_max_time">{{__('group.max_time')}}</label>
                                    <select wire:model="state.date_max_time" @if(($state['date_status'] ?? 2) != 2) disabled @endif id="state_max_time" class="form-control @error('date_max_time') is-invalid @enderror">
                                        @foreach ($max_time_options as $field => $translate) 
                                            <option value="{{$translate}}">{{__('group.max_time_options.'.$translate)}}</option>
                                        @endforeach
                                    </select>
                                    @error('date_max_time')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                </div>  
                            </div>
                        </div>
                        <div class="row align-items-end">
                            <div class="col-md-6">
                                <label for="state_start">@lang('group.start_time')</label>
                                <select @if(($state['date_status'] ?? 2) != 2) disabled @endif  id="state_start" wire:model="state.date_start" class="form-control @error('date_start') is-invalid @enderror">
                                    @foreach ($starts as $field => $translate) 
                                        <option value="{{$translate}}">{{ $translate }}</option>
                                    @endforeach
                                </select>
                                @error('date_start')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label for="state_end">@lang('group.end_time')</label>
                                <select @if(($state['date_status'] ?? 2) != 2) disabled @endif id="state_end" wire:model="state.date_end" class="form-control @error('date_end') is-invalid @enderror">
                                    @foreach ($ends as $field => $translate) 
                                        <option value="{{$translate}}">{{ $translate }}</option>
                                    @endforeach
                                </select>
                                @error('date_end')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                            </div>
                        </div> 
                    </div>
                    <div class="col-md-4">                        
                        <label for="disabled_slots">
                            <i class="fas fa-ban mr-1"></i>
                            @lang('group.disabled_time_slots'):</label>
                        @if  (count($disabled_slots ?? []) > 0)
                            <span class="ml-2 badge badge-warning">@lang('app.total'): {{ count(array_filter($disabled_slots ?? [])) }}</span>
                        @endif
                        <br/>
                        @lang('group.disabled_time_slots_info')
                        
                        <div class="ml-2 w-100 border  @if  (count(array_filter($disabled_slots ?? [])) > 0) border-warning @else border-secondary @endif rounded" style="height:300px;overflow-y:auto;">
                            @foreach ($disabled_selects as $key => $time)
                            <div class="ml-2 form-check">
                                <input @if(($state['date_status'] ?? 2) != 2) disabled @endif wire:model="state.disabled_slots.{{ $time }}" class="form-check-input" type="checkbox" id="disabled_{{ $time }}">
                                <label class="form-check-label" for="disabled_{{ $time }}" role="button">
                                    {{ $time }}
                                </label>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                @if(isset($state['error'])) 
                    <div class="alert alert-danger" role="alert">
                    {{ $state['error'] }}
                  </div>
                @endif
            </x-slot>
        
            <x-slot name="buttons">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="fa fa-times mr-1"></i>@lang('app.cancel')</button>
                @if($date !== null)
                    <a wire:loading.attr="disabled" wire:click.prevent="deleteDateConfirmation()" class="btn btn-danger">
                        <i class="fa fa-trash mr-1"></i>
                        @lang('Delete')
                    </a>
                @endif
                <button wire:loading.attr="disabled" type="submit" class="btn btn-primary">
                        @if($date == null)
                            <i class="fa fa-plus mr-1"></i>
                            @lang('app.add')
                        @else
                            <i class="fa fa-save mr-1"></i>
                            @lang('app.change')
                        @endif
                </button>
            </x-slot>
        </x-modal>
    </form>
</div>

