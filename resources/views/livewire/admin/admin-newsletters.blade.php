<div>
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-8">
            <h1 class="m-0">@lang('app.menu-newsletters')</h1>
            </div><!-- /.col -->
            <div class="col-sm-4">
            <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="{{route('home.home')}}">{{ __('app.menu-home') }}</a></li>
                <li class="breadcrumb-item active">@lang('app.menu-newsletters')</li>
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
                        @can('is-admin')
                            <div class="col-6 d-flex justify-content-end">
                                <a href="{{route('admin.newsletter_edit')}}">
                                <button class="btn btn-primary">
                                    <i class="fa fa-plus-circle mr-1"></i>
                                    @lang('Add')</button>
                                </a>
                            </div>
                        @endcan
                    </div>

                    <div class="row">
                        <div class="col-12" id="accordion">
                            @forelse ($newsletters as $newsletter)
                                @if ( $editor == 0 
                                        && (
                                            Carbon\Carbon::parse($newsletter->date)->isPast() != 1
                                            || $newsletter->status == 0
                                        ) )
                                    @continue
                                @endif
                                <div class="card @if ($newsletter->status == 0) 
                                                    card-warning 
                                                @else 
                                                    @if( Carbon\Carbon::parse($newsletter->date)->isPast() != 1) 
                                                        card-warning 
                                                    @else 
                                                        card-primary 
                                                    @endif 
                                                @endif card-outline">
                                    <a class="d-block w-100 collapsed" data-toggle="collapse" href="#collapse-{{ $newsletter->id }}" aria-expanded="false">
                                        <div class="card-header">
                                            <h4 class="card-title w-100">
                                                {{ $newsletter->date->format( __('app.format.date') ) }} - {{ $newsletter->subject }}
                                                @if ($newsletter->send_newsletter == 1)
                                                    <i class="far fa-envelope mx-1 @if($newsletter->sent_time !== null) text-success @endif"></i>
                                                @endif
                                                @if ($newsletter->send_to == 'groupCreators')
                                                    <i class="fas fa-users-cog mx-1"></i>
                                                @else
                                                    <i class="fas fa-users mx-1"></i>
                                                @endif
                                                @if ($newsletter->status == 0)
                                                    <span class="ml-2 badge badge-warning"> @lang('news.statuses.0') </span>
                                                @endif
                                                @if( Carbon\Carbon::parse($newsletter->date)->isPast() != 1) 
                                                <span class="ml-2 badge badge-secondary"> @lang('news.future') </span>
                                                @endif
                                            </h4>
                                        </div>
                                    </a>
                                    <div id="collapse-{{ $newsletter->id }}" class="collapse" data-parent="#accordion" style="">
                                        <div class="card-body">
                                            {!! $newsletter->content !!}
                                            <div class="row">
                                                <div class="col-md-12 justify-content-end text-right">
                                                    <span class="text-left mb-0">{{  $newsletter->user->name }}</span>
                                                    @if ($editor && $newsletter->sent_time === null)
                                                    <a class="ml-2 btn btn-primary btn-sm" href="{{ route('admin.newsletter_edit', ['id' => $newsletter->id]) }}">
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
                                        @lang('app.newsletter.not_found').
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
