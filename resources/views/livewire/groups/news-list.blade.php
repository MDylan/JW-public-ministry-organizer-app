<div>
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-8">
            <h1 class="m-0">{{ __('group.news') }} ({{ $group->name }})</h1>
            </div><!-- /.col -->
            <div class="col-sm-4">
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
            <div class="row">
                <div class="col-lg-12">
                    <div class="row mb-2">
                        <div class="col-6">
                            <a href="{{ URL::previous() }}" class="btn btn-primary">
                                <i class="fas fa-arrow-alt-circle-left mr-1"></i>
                                @lang('app.back')
                            </a>
                        </div>
                        @if ($editor)
                            <div class="col-6 d-flex justify-content-end">
                                <a href="{{route('groups.news_create', ['group' => $group->id])}}">
                                <button class="btn btn-primary">
                                    <i class="fa fa-plus-circle mr-1"></i>
                                    {{ __('group.news_add') }}</button>
                                </a>
                            </div>
                        @endif
                    </div>

                    <div class="row">
                        <div class="col-12" id="accordion">
                            @forelse ($news as $new)
                                @if ( $editor == 0 
                                        && (
                                            Carbon\Carbon::parse($new->date)->isPast() != 1
                                            || $new->status == 0
                                        ) )
                                    @continue
                                @endif
                                <div class="card @if ($new->status == 0) 
                                                    card-warning 
                                                @else 
                                                    @if( Carbon\Carbon::parse($new->date)->isPast() != 1) 
                                                        card-warning 
                                                    @else 
                                                        card-primary 
                                                    @endif 
                                                @endif card-outline">
                                    <a class="d-block w-100 collapsed" data-toggle="collapse" href="#collapse-{{ $new->id }}" aria-expanded="false">
                                        <div class="card-header">
                                            <h4 class="card-title w-100">
                                                {{ $new->date->format( __('app.format.date') ) }} - {{ $new->title }}
                                                @if ($new->status == 0)
                                                    <span class="ml-2 badge badge-warning"> @lang('news.statuses.0') </span>
                                                @endif
                                                @if( Carbon\Carbon::parse($new->date)->isPast() != 1) 
                                                <span class="ml-2 badge badge-secondary"> @lang('news.future') </span>
                                                @endif
                                                @if (count($new->files))
                                                    <i class="fa fa-paperclip"></i>
                                                @endif
                                            </h4>
                                        </div>
                                    </a>
                                    <div id="collapse-{{ $new->id }}" class="collapse" data-parent="#accordion" style="">
                                        <div class="card-body">
                                            {!! $new->content !!}
                                            <div class="row">
                                                <div class="col-md-8">
                                                    @foreach ($new->files as $file)
                                                        <a class="btn btn-outline-primary btn-sm mr-1" href="{{ $file->url }}">
                                                            <i class="fa fa-download mr-1"></i>
                                                            {{ $file->name }}</a>
                                                    @endforeach
                                                </div>
                                                <div class="col-md-4 justify-content-end text-right">
                                                    <span class="text-left mb-0">{{  $new->user->name }}</span>
                                                    @if ($editor)
                                                    <a class="ml-2 btn btn-primary btn-sm" href="{{ route('groups.news_edit', ['group' => $group->id, 'new' => $new->id]) }}">
                                                        @lang('Edit')
                                                    </a>    
                                                    @endif 
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="card card-primary card-outline">
                                    <div class="card-body">
                                        @lang('news.no_news')
                                    </div>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
