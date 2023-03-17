<div>
    <form autocomplete="off" wire:submit.prevent="savePoster">
        <x-modal modalId="PosterEditModal" modalSize="modal-xl">
            <x-slot name="title">
                @lang('group.poster.title')
            </x-slot>
        
            <x-slot name="content">
                <div class="row">
                    <div class="col-md-4">
                        @if(isset($group->name))
                            <h5>{{ $group->name }}</h5>
                        @endif
                    </div>
                    <div class="col-md-8">
                        <div class="alert alert-info">
                            @lang('group.poster.info')
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
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
                    </div>
                    <div class="col-md-6">
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
                    </div>
                </div>
                @if($openModal) 
                    <div class="form-group" id="poster_info_{{ $state['id'] }}" wire:ignore>
                        <label for="field_info">@lang('group.poster.field_info')</label>
                        <textarea wire:model.lazy="state.info" class="summernote form-control @error('content') is-invalid @enderror" rows="3" name="info">
                            {{-- {{ $state['info'] ?? '' }} --}}
                        </textarea>                        
                    </div>
                    @error('info')<div class="alert alert-danger">{{$message}}</div>@enderror
                    <script>
                        openSummernote('{{ $state['id'] }}');            
                    </script>
                @endif
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

    @section('header_style') 
    <link rel="stylesheet" href="{{ asset('plugins/summernote/summernote-bs4.min.css') }}">
@endsection
@section('footer_scripts')
    {!! Packer::js('/plugins/summernote/summernote-bs4.min.js', '/plugins/summernote/cache_summernote-bs4.min.js') !!} 
    {{-- <script src="{{ asset('plugins/summernote/summernote-bs4.min.js') }}"></script> --}}
    @if (trans('news.editor_lang') !== null)
        <script src="{{ asset('plugins/summernote/lang/summernote-' . __('news.editor_lang') . '.min.js') }}"></script>            
    @endif
    <script>
        function openSummernote(value) {
            $('.summernote').summernote({
                dialogsInBody: true,
                height: 150,
                @if (trans('news.editor_lang') !== null)
                    lang: '@lang('news.editor_lang')', // default: 'en-US'
                @endif
                toolbar: [
                    ['font', ['bold', 'italic', 'underline', 'clear']],
                    ['fontsize', ['fontsize']],
                    ['color', ['color']],
                    ['insert', ['link']],
                    ],
                    callbacks: {
                        //save into livewire model when leave texteditor
                        onBlur: function(e) {
                            code = $(this).summernote('code');
                            @this.set('state.info', code);
                        }
                    }
            });
        }
      </script>
@endsection

</div>
