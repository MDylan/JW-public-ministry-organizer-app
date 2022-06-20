<div>
    @section('title')
    @lang('app.menu-staticpages')
    @endsection
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-8">
            <h1 class="m-0">@lang('app.menu-staticpages')</h1>
            </div><!-- /.col -->
            <div class="col-sm-4">
            <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="{{route('home.home')}}">{{ __('app.menu-home') }}</a></li>
                <li class="breadcrumb-item">{{ __('app.menu-admin') }}</li>
                <li class="breadcrumb-item active">{{ __('app.menu-staticpages') }}</li>
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
                        <div class="col-12 d-flex justify-content-end">
                            <a href="{{route('admin.staticpages_create')}}">
                            <button class="btn btn-primary">
                                <i class="fa fa-plus-circle mr-1"></i>
                                @lang('staticpage.create_new')</button>
                            </a>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12">
                            <div class="card card-primary card-outline">
                                <div class="card-body">
                                    <div class="table-responsive-md">
                                        <table class="table table-striped table-hover">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>@lang('Edit')</th>
                                                    <th>@lang('staticpage.title')</th>
                                                    <th>@lang('staticpage.slug')</th>
                                                    <th>@lang('staticpage.status')</th>
                                                    <th>@lang('staticpage.position')</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($pages as $page)
                                                <tr>
                                                    <td>{{ $page->id }}</td>
                                                    <td><a href="{{ route('admin.staticpages_edit', ['staticPage' => $page->id]) }}" class="btn btn-sm btn-primary">
                                                        @lang('Edit')
                                                        </a>
                                                    </td>
                                                    <td>
                                                        {{ $page->title }}
                                                    </td>
                                                    <td><a target="_blank" href="/page/{{ $page->slug }}">{{ $page->slug }}</a></td>
                                                    <td>{{ __('staticpage.statuses_sort.'.$page->status) }}</td>
                                                    <td>{{ __('staticpage.positions.'.$page->position ) }}</td>
                                                </tr>                                                
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
