<div>
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
            <h1 class="m-0">{{ __('group.users') }} ({{$group_name}})</h1>
            </div><!-- /.col -->
            <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="{{route('home.home')}}">{{ __('app.menu-home') }}</a></li>
                <li class="breadcrumb-item"><a href="{{route('groups')}}">{{ __('app.menu-groups') }}</a></li>
                <li class="breadcrumb-item active">{{ __('group.users') }}</li>
            </ol>
            </div><!-- /.col -->
        </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->

    <!-- Main content -->
    <div class="content">
        <div class="container-fluid">
            <div class="card card-primary card-outline">
                <div class="card-body">
                    <div class="table-responsive-md">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>@lang('user.name')</th>
                                    <th>@lang('user.email')</th>
                                    <th>@lang('user.phone')</th>
                                    <th>@lang('user.last_login')</th>
                                    <th>@lang('app.role')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($users as $user)
                                <tr>
                                    <td>{{ $user->id }}</td>
                                    <td>{{ $user->full_name }}</td>
                                    <td>
                                        <a href="mailto:{{ $user->email }}">{{ $user->email }}</a>                                    
                                    </td>
                                    <td>
                                        <a href="tel:+{{ $user->phone }}">{{ $user->phone }}</a>
                                    </td>
                                    <td>{{ $user->last_login_time }}</td>
                                    <td>
                                        {{ __('group.roles.'.$user->pivot->group_role) }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <a href="{{ route('groups') }}" class="btn btn-primary">@lang('app.back')</a>
                </div>
            </div>
        </div>
    </div>
</div>
