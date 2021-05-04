<x-admin-layout>
    <div>
        <!-- Content Header (Page header) -->
        <div class="content-header">
            <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                <h1 class="m-0">{{__('user.updateProfile')}}</h1>
                </div><!-- /.col -->
                <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="{{route('home.home')}}">{{__('app.menu-home')}}</a></li>
                    <li class="breadcrumb-item active"><a href="{{route('user.profile')}}">{{__('app.profile')}}</a></li>
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

                <div class="card card-primary card-outline">
                    <div class="card-header">
                    <h5 class="m-0">{{__('user.userData')}}</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('user-profile-information.update')}}">
                            @csrf
                            @method("PUT")
                            <div class="row">
                                
                            @foreach (trans('user.nameFields') as $field => $translate) 
                            <div class="col">
                                <div class="input-group mb-3">
                                    {!! Form::text($field, auth()->user()->$field, ['class' => 'form-control'. ( $errors->has($field) ? ' is-invalid' : '' ), 'placeholder' => $translate]) !!}
                                    <div class="input-group-append">
                                        <div class="input-group-text">
                                        <span class="fas fa-user"></span>
                                        </div>
                                    </div>
                                    @error($field)<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                </div>
                            </div>
                            @endforeach
                            </div>
                            <div class="row">
                                <div class="col">
                                <div class="input-group mb-3">
                                    {!! Form::email('email', auth()->user()->email, ['class' => 'form-control'. ( $errors->has('email') ? ' is-invalid' : '' ), 'placeholder' => __('user.email')]) !!}
                                    <div class="input-group-append">
                                        <div class="input-group-text">
                                        <span class="fas fa-envelope"></span>
                                        </div>
                                    </div>
                                    @error('email')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                                </div>
                                </div>
                                <div class="col">
                                    <div class="input-group mb-3">
                                        {!! Form::text('phone', auth()->user()->phone, ['class' => 'form-control'. ( $errors->has('phone') ? ' is-invalid' : '' ), 'placeholder' => __('user.phone')]) !!}
                                        <div class="input-group-append">
                                            <div class="input-group-text">
                                            <span class="fas fa-phone"></span>
                                            </div>
                                        </div>
                                        @error('phone')<div class="invalid-feedback" role="alert">{{__($message, ['attribute' => __('user.phone')])}}</div>@enderror
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">{{__('Save')}}</button>
                        </form>
                    </div>
                </div>

                </div>
                <!-- /.col-md-6 -->
                <div class="col-lg-6">

                <div class="card card-primary card-outline">
                    <div class="card-header">
                    <h5 class="m-0">{{__('Update Password')}}</h5>
                    </div>
                    <div class="card-body">
                    <h6 class="card-title">Special title treatment</h6>

                    <p class="card-text">With supporting text below as a natural lead-in to additional content.</p>
                    <a href="#" class="btn btn-primary">Go somewhere</a>
                    </div>
                </div>
                </div>
                <!-- /.col-md-6 -->
            </div>
            <!-- /.row -->
            </div><!-- /.container-fluid -->
        </div>
        <!-- /.content -->
    </div>
</x-admin-layout>