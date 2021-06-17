<div>
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-md-8">
            <h1 class="m-0">
                @if (isset($state['id']))
                    @lang('news.edit')
                @else
                    @lang('news.create')
                @endif
                 ({{ $group->name }})</h1>
            </div><!-- /.col -->
            <div class="col-md-4">
            <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="{{route('home.home')}}">{{ __('app.menu-home') }}</a></li>
                <li class="breadcrumb-item"><a href="{{route('groups')}}">{{ __('app.menu-groups') }}</a></li>
                <li class="breadcrumb-item active">{{ __('group.news') }}</li>
            </ol>
            </div><!-- /.col -->
        </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->

    <!-- Main content -->
    <div class="content">
        <div class="container-fluid">
            <form wire:submit.prevent="editNews">
                @csrf
                <div class="row">
                    <div class="col-md-4">
                        <div class="card card-primary card-outline">
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="form_date">@lang('news.date')</label>
                                    <input wire:model.defer="state.date" type="date" class="form-control @error('date') is-invalid @enderror" id="form_date" placeholder="" aria-describedby="date_helper">
                                    <small id="date_helper" class="form-text text-muted">
                                        @lang('news.date_helper')
                                    </small>
                                    @error('date')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                </div>
                                <div class="form-group">
                                    <label for="form_status">@lang('news.status')</label>
                                    <select wire:model.defer="state.status" class="form-control @error('status') is-invalid @enderror" id="form_status">
                                        @foreach (trans('news.statuses') as $id => $translate) 
                                            <option value="{{ $id }}">{{ $translate }}</option>      
                                        @endforeach
                                    </select>
                                    @error('status')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="card card-primary card-outline">
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="form_title">@lang('news.title')</label>
                                    <input wire:model.defer="state.title" name="title" type="text" class="form-control @error('title') is-invalid @enderror" id="form_title" placeholder="">
                                    @error('title')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                </div>
                                <div class="form-group" wire:ignore>
                                    <label for="form_content">@lang('news.content')</label>
                                    <textarea wire:model.defer="state.content" class="form-control @error('content') is-invalid @enderror" rows="10" id="summernote" name="content"></textarea>
                                </div>
                                @error('content')<code>{{$message}}</code>@enderror
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-lg-4">
                        <a href="{{ route('groups.news', ['group' => $group->id]) }}">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="fa fa-times mr-1"></i>@lang('app.cancel')</button>
                        </a>        
                        <button type="submit" class="btn btn-primary"><i class="fa fa-save mr-1"></i>
                            @lang('app.save')</button>
                    </div>
                    <div class="col-lg-8">
                        @if ($errors->any())
                            <p class="text-danger mt-2">
                                {{__('app.pleaseFixErrors')}}
                            </p>
                        @endif
                        @if (isset($state['id']))
                            <a wire:loading.attr="disabled" wire:click.prevent="confirmNewDelete()" class="btn btn-danger float-right">
                                <i class="fa fa-trash mr-1"></i>
                                @lang('news.delete')
                            </a>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </div>
    @section('header_style') 
    <link rel="stylesheet" href="{{ asset('plugins/summernote/summernote-bs4.min.css') }}">
    @endsection
    @section('footer_scripts')
        <script src="{{ asset('plugins/summernote/summernote-bs4.min.js') }}"></script>
        @if (trans('news.editor_lang') !== null)
            <script src="{{ asset('plugins/summernote/lang/summernote-' . __('news.editor_lang') . '.min.js') }}"></script>            
        @endif
        <script>
            $(document).ready(function() {
                window.addEventListener('show-newsDelete-confirmation', event => {
                    Swal.fire({
                        title: '@lang('news.confirmDelete.question')',
                        text: '@lang('news.confirmDelete.message')',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: '@lang('Yes')',
                        cancelButtonText: '@lang('Cancel')'
                    }).then((result) => {
                    if (result.isConfirmed) {
                        Livewire.emit('deleteConfirmed');
                    }
                    })
                });

                $('#summernote').summernote({
                    height: 200,
                    @if (trans('news.editor_lang') !== null)
                        lang: '@lang('news.editor_lang')', // default: 'en-US'
                    @endif
                    toolbar: [
                        ['style', ['style']],
                        ['font', ['bold', 'italic', 'underline', 'clear']],
                        ['fontsize', ['fontsize']],
                        ['color', ['color']],
                        ['para', ['ul', 'ol', 'paragraph']],
                        ['table', ['table']],
                        ['insert', ['link']],
                        ['view', ['codeview']]
                        ],
                        callbacks: {
                            //save into livewire model when leave texteditor
                            onBlur: function(e) {
                                code = $(this).summernote('code');
                                @this.set('state.content', code);
                            }
                        }
                });
            });
          </script>
    @endsection
</div>
