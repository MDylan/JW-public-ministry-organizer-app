<div>
    @section('title')
    @if (isset($state['id']))
        @lang('news.edit')
    @else
        @lang('news.create')
    @endif
    ({{ $group->name }})
    @endsection
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
                    <div class="col-md-8">
                        <div class="card card-primary card-outline">
                            <div class="card-header p-0 pt-1 border-bottom-0">
                                <ul wire:ignore class="nav nav-tabs" id="pageTab" role="tablist">
                                    @foreach ($languages as $code => $lang)
                                        <li class="nav-item">
                                            <a class="nav-link @if ($lang == reset($languages )) active @endif" id="custom-tabs-{{$code}}-tab" data-toggle="pill" href="#custom-tabs-{{$code}}" role="tab" aria-controls="custom-tabs-{{$code}}" aria-selected="false">
                                                @if (!$lang['visible'])
                                                    <i class="fas fa-eye-slash mr-1"></i>
                                                @endif{{ $lang['name'] }}
                                            </a>
                                        </li>      
                                    @endforeach
                                </ul>
                              </div>
                            <div class="card-body">
                                <div class="tab-content" id="pageTabContent">
                                    @foreach ($languages as $code => $lang)
                                        <div wire:ignore.self class="tab-pane fade @if ($lang == reset($languages )) show active @endif " id="custom-tabs-{{ $code }}" role="tabpanel" aria-labelledby="custom-tabs-{{ $code }}">
                                            <div class="form-group">
                                                <label for="form_title">@lang('news.title')</label>
                                                <input wire:model.defer="state.lang.{{$code}}.title" name="title" type="text" class="form-control @error('title') is-invalid @enderror" id="form_title" placeholder="">
                                                @error('title')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                            </div>
                                            <div class="form-group" wire:ignore>
                                                <label for="form_content">@lang('news.content')</label>
                                                <textarea data-lang="{{$code}}" wire:model.defer="state.lang.{{$code}}.content" class="summernote form-control @error('content') is-invalid @enderror" rows="10" name="content"></textarea>
                                            </div>
                                            @error('content')<code>{{$message}}</code>@enderror
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
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

                        <div class="card card-primary card-outline">
                            <div class="card-body">
                                <div class="form-group">
                                    <label>@lang('news.attachments')</label>
                                    @if ($attached_files)                                    
                                        @foreach ($attached_files as $key => $file)
                                        <div class="row highlight mx-2">
                                            <div class="col-10 mb-1 pt-1">
                                                <a target="_blank" href="{{ $file['url'] }}">
                                                    {{ $file['name'] }}</a> 
                                                    ({{ number_format($file['size'] / (1024 * 1024), 2) }} MB)
                                            </div>
                                            <div class="col-2 mb-1">
                                                <a title="@lang('Delete')" href="javascript:void(0);" class="btn btn-danger btn-sm float-right" wire:click="confirmFileDelete({{$key}}, '{{ $file['name'] }}')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </div>
                                        @endforeach                                    
                                    @endif
                                    
                                    <div class="custom-file mt-3" lang="hu">
                                        <div x-data="{ isUploading: false, progress: 3 }"
                                            x-on:livewire-upload-start="isUploading = true"
                                            x-on:livewire-upload-finish="isUploading = false; progress: 3"
                                            x-on:livewire-upload-error="isUploading = false"
                                            x-on:livewire-upload-progress="progress = $event.detail.progress">
                                            <input wire:model="files" type="file" class="custom-file-input" id="customFile" aria-describedby="filesHelpText" multiple>
                                            <label data-browse="@lang('news.file.browse')" class="custom-file-label" for="customFile">@lang('news.file.choose')</label>
                                            <div x-show="isUploading">
                                                <progress class="w-100" max="100" x-bind:value="progress"></progress>
                                            </div>
                                            @error('files.*') <span class="text-danger error">{{ $message }}</span>@enderror
                                        </div>
                                        
                                        <small id="filesHelpText" class="text-muted">
                                            @lang('news.file.available_types'):<br/>.{{ implode(', .', $file_types) }}
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row pb-2 mb-3">
                    <div class="col-lg-4">
                        <a wire:loading.attr="disabled" href="{{ route('groups.news', ['group' => $group->id]) }}">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="fa fa-times mr-1"></i>@lang('app.cancel')</button>
                        </a>        
                        <button wire:loading.attr="disabled" type="submit" class="btn btn-primary">
                            <i class="fa fa-save mr-1"></i>
                            @if(isset($state['id']))
                                @lang('app.saveChanges')
                            @else
                                @lang('app.save')
                            @endif
                        </button>
                    </div>
                    <div class="col-lg-8">
                        @if ($errors->any())
                            <p class="text-danger mt-2">
                                {{__('app.pleaseFixErrors')}}
                            </p>
                        @endif
                        @if (isset($state['id']))
                            <button type="button" wire:loading.attr="disabled" wire:click.prevent="confirmNewDelete()" class="btn btn-danger float-right">
                                <i class="fa fa-trash mr-1"></i>
                                @lang('news.delete')
                            </button>
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
        <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
        <script src="{{ asset('plugins/summernote/summernote-bs4.min.js') }}"></script>
        @if (trans('news.editor_lang') !== null)
            <script src="{{ asset('plugins/summernote/lang/summernote-' . __('news.editor_lang') . '.min.js') }}"></script>            
        @endif
        <script>
            $(document).ready(function() {
                @if(isset($state['id']))    
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
                                window.location.replace('{{ route('groups.news_delete', ['group' => $group->id, 'new' => $state['id']]) }}');
                        }
                        })
                    });
                @endif

                window.addEventListener('show-fileDelete-confirmation', event => {
                    Swal.fire({
                        title: '@lang('news.confirmFileDelete.question')',
                        text: event.detail.fileName,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: '@lang('Yes')',
                        cancelButtonText: '@lang('Cancel')'
                    }).then((result) => {
                    if (result.isConfirmed) {
                        Livewire.emit('deleteFileConfirmed');
                    }
                    })
                });

                $('.summernote').summernote({
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
                        ],
                        callbacks: {
                            //save into livewire model when leave texteditor
                            onBlur: function(e) {
                                code = $(this).summernote('code');
                                lang = $(this).data('lang');
                                @this.set('state.lang.'+ lang +'.content', code);
                            }
                        }
                });
            });
          </script>
    @endsection
</div>
