<x-admin-layout>
    <div>
        <!-- Content Header (Page header) -->
        <div class="content-header">
            <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                <h1 class="m-0">{{__('Verify Email Address')}}</h1>
                </div><!-- /.col -->
                <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="{{ route('home.home') }}">{{__('app.menu-home')}}</a></li>
                    <li class="breadcrumb-item active">{{__('Verify Email Address')}}</li>
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
                    <div class="col-lg-6">

                        <div class="card card-default">
                            <!-- /.card-header -->
                            <div class="card-body">
                                @if (session('status') == 'verification-link-sent')
                                    <div class="alert alert-success alert-dismissible">
                                        <h5><i class="icon fas fa-check"></i></h5>
                                        {{__('A new verification link has been sent to the email address you provided during registration.')}}
                                    </div>
                                @else
                                    <form method="POST" action="/email/verification-notification">
                                        @csrf
                                        <input type="submit" class="btn btn-primary" value="{{__('app.sendVerifyEmail')}}" />
                                    </form>
                                @endif
                            </div>
                            <!-- /.card-body -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>    