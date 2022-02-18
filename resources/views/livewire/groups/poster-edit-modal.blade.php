<div>
    <form autocomplete="off" wire:submit.prevent="savePoster">
        <x-modal modalId="PosterEditModal">
            <x-slot name="title">
                @lang('group.poster.title')
            </x-slot>
        
            <x-slot name="content">
                <div class="alert alert-info">
                    @lang('group.poster.info')
                </div>
                <div class="form-group row">
                    <label for="field_show_date" class="col-sm-4 col-form-label">@lang('group.poster.field_show_date')</label>
                    <div class="col-sm-8">
                        <input wire:model.defer="state.show_date" type="date" class="form-control @error('show_date') is-invalid @enderror" id="field_show_date" aria-describedby="show_date_helpBlock">
                        @error('show_date')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                        <small id="show_date_helpBlock" class="form-text text-muted">
                            @lang('group.poster.show_date_helpBlock')
                        </small>
                    </div>
                </div>
                <div class="form-group row">
                    <label for="field_hide_date" class="col-sm-4 col-form-label">@lang('group.poster.field_hide_date')</label>
                    <div class="col-sm-8">
                        <input wire:model.defer="state.hide_date" type="date" class="form-control @error('hide_date') is-invalid @enderror" id="field_hide_date" aria-describedby="hide_date_helpBlock">
                        @error('hide_date')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                        <small id="hide_date_helpBlock" class="form-text text-muted">
                            @lang('group.poster.hide_date_helpBlock')
                        </small>
                    </div>
                </div>
                <div class="form-group">
                    <label for="field_info">@lang('group.poster.field_info')</label>
                    <textarea wire:model.defer="state.info" class="form-control @error('info') is-invalid @enderror" id="field_info" rows="3"></textarea>
                    @error('info')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                </div>
            </x-slot>
        
            <x-slot name="buttons">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="fa fa-times mr-1"></i>@lang('app.cancel')</button>
                @if($posterId !== 0)
                    <a wire:loading.attr="disabled" wire:click.prevent="deletePosterConfirmation()" class="btn btn-danger">
                        <i class="fa fa-trash mr-1"></i>
                        @lang('Delete')
                    </a>
                @endif
                <button wire:loading.attr="disabled" type="submit" class="btn btn-primary">
                        @if($posterId == 0)
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
