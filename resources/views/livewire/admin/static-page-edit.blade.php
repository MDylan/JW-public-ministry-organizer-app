<div>
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-md-6">
            <h1 class="m-0">
                @if (isset($state['id']))
                    @lang('staticpage.edit')
                @else
                    @lang('staticpage.create_new')
                @endif
                </h1>
            </div><!-- /.col -->
            <div class="col-md-6">
            <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="{{route('home.home')}}">{{ __('app.menu-home') }}</a></li>
                <li class="breadcrumb-item">{{ __('app.menu-admin') }}</li>
                <li class="breadcrumb-item"><a href="{{ route('admin.staticpages') }}">{{ __('app.menu-staticpages') }}</a></li>
                <li class="breadcrumb-item active">
                    @if (isset($state['id']))
                        @lang('staticpage.edit')
                    @else
                        @lang('staticpage.create_new')
                    @endif    
                </li>
            </ol>
            </div><!-- /.col -->
        </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->

    <!-- Main content -->
    <div class="content">
        <div class="container-fluid">
            <form wire:submit.prevent="editPage">
                @csrf
                <div class="row">                    
                    <div class="col-md-8">
                        <div class="card card-primary card-outline">
                            <div class="card-header p-0 pt-1 border-bottom-0">
                                <ul wire:ignore class="nav nav-tabs" id="pageTab" role="tablist">
                                    @foreach ($languages as $code => $lang)
                                        <li class="nav-item">
                                            <a class="nav-link @if ($lang == reset($languages )) active @endif" id="custom-tabs-{{$code}}-tab" data-toggle="pill" href="#custom-tabs-{{$code}}" role="tab" aria-controls="custom-tabs-{{$code}}" aria-selected="false">
                                                {{ $lang }}
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
                                                <label for="form_title">@lang('staticpage.title')</label>
                                                <input wire:model.defer="state.lang.{{$code}}.title" name="title" type="text" class="form-control @error('title') is-invalid @enderror" id="form_title" placeholder="">
                                                @error('title')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                            </div>
                                            <div class="form-group" wire:ignore>
                                                <label for="form_content">@lang('staticpage.content')</label>
                                                <textarea data-lang="{{$code}}" wire:model.defer="state.lang.{{ $code }}.content" class="form-control summernote @error('content') is-invalid @enderror" rows="10" id="summernote-{{ $code }}" name="{{$code}}[content]"></textarea>
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
                                    <label for="slug">@lang('staticpage.slug')</label>
                                    <input wire:model.defer="state.slug" wire:keydown.debounce.1000ms="checkSlug" type="text" 
                                    name="slug" class="form-control @error('slug') is-invalid @enderror" id="slug"  aria-describedby="slugHelp" />
                                    @error('slug')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                    <small id="slugHelp" class="form-text text-muted">
                                        @lang('staticpage.slugHelp')
                                    </small>                                    
                                </div>
                                <div class="form-group">
                                    <label for="form_status">@lang('staticpage.status')</label>
                                    <select wire:model="state.status" class="form-control @error('status') is-invalid @enderror" id="form_status">
                                        @foreach ($statuses as $id) 
                                            <option value="{{ $id }}">{{ __('staticpage.statuses.'.$id) }}</option>      
                                        @endforeach
                                    </select>
                                    @error('status')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                </div>
                                <div class="form-group">
                                    <label for="formposition">@lang('staticpage.position')</label>
                                    <select wire:model="state.position" class="form-control @error('position') is-invalid @enderror" id="form_status" aria-describedby="positions_helper">
                                        @foreach ($positions as $translate) 
                                            <option value="{{ $translate }}">{{ __('staticpage.positions.'.$translate) }}</option>      
                                        @endforeach
                                    </select>
                                    <small id="positions_helper" class="form-text text-muted">
                                        @lang('staticpage.positions_helper')
                                    </small>                                    
                                    @error('position')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                </div>
                                @if ($state['position'] == 'left')
                                    <div class="form-group">
                                        <label for="icon">@lang('staticpage.icon')</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">
                                                @if (isset($state['icon']))
                                                    <i class="{{ $state['icon'] }}"></i>
                                                @endif
                                                </span>
                                            </div>
                                            <input class="form-control" wire:model="state.icon" type="text" name="icon" class="form-group" id="icon" placeholder="fa fa-file" aria-describedby="iconHelp" />
                                        </div>
                                        @error('icon')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                        <small id="iconHelp" class="form-text text-muted">
                                            @lang('staticpage.iconHelp')
                                        </small>
                                        
                                    </div>                                    
                                @endif

                                
                            </div>
                        </div>

                        
                    </div>
                </div>
                <div class="row pb-2 mb-3">
                    <div class="col-lg-4">
                        <a wire:loading.attr="disabled" href="{{ route('admin.staticpages') }}">
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
                                @lang('staticpage.delete')
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
                window.addEventListener('show-pageDelete-confirmation', event => {
                    Swal.fire({
                        title: '@lang('staticpage.confirmDelete.question')',
                        text: '@lang('staticpage.confirmDelete.message')',
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
                        ['view', ['codeview']]
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