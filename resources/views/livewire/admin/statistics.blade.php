<div>
    @section('title')
    @lang('statistics.statistics')
    @endsection
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-md-8">
                <h1 class="m-0">
                    @lang('statistics.statistics')
                </div><!-- /.col -->
                <div class="col-md-4">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="{{route('home.home')}}">{{ __('app.menu-home') }}</a></li>
                    <li class="breadcrumb-item"><a href="#">{{ __('app.menu-admin') }}</a></li>
                    <li class="breadcrumb-item active">{{ __('statistics.statistics') }}</li>
                </ol>
                </div><!-- /.col -->
            </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->
    {{-- 
        Documentation: 
        https://www.chartjs.org/docs/2.9.4/charts/line.html
    --}}
    <!-- Main content -->
    <div class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6">
                    <div class="card card-primary card-outline">
                        <div class="card-body">
                            <canvas id="active_users" width="100" height="50"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card card-primary card-outline">
                        <canvas id="dialy_users" width="100" height="50"></canvas>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
    @section('header_style') 
    <link rel="stylesheet" href="{{ asset('plugins/chart.js/Chart.min.css') }}">
    @endsection
    @section('footer_scripts')
        <script src="{{ asset('plugins/chart.js/Chart.bundle.min.js') }}"></script>
        <script>
            var ctx = document.getElementById('active_users').getContext('2d');
            var myChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: [
                        @foreach ($active_users_array as $period => $number)
                            '{{ \Carbon\Carbon::parse($period)->format(__('app.format.time')) }}',
                        @endforeach
                    ],
                    datasets: [{
                        label: '@lang('user.online')',
                        data: [
                            @foreach ($active_users_array as $number)
                                {{ $number }},
                            @endforeach
                        ],
                        backgroundColor: 
                            'rgba(255, 99, 132, 0.2)',
                        borderColor: 
                            'rgba(255, 99, 132, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    scales: {
                        yAxes: [{
                            ticks: {
                                beginAtZero: true
                            }
                        }]
                    }
                }
            });

            var ctx = document.getElementById('dialy_users').getContext('2d');
            var myChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: [
                        @foreach ($dialy_users_array as $period => $number)
                            '{{ $period }}',
                        @endforeach
                    ],
                    datasets: [{
                        label: '@lang('user.online')',
                        data: [
                            @foreach ($dialy_users_array as $number)
                                {{ $number }},
                            @endforeach
                        ],
                        backgroundColor: //[
                            'rgb(122, 148, 255, 0.5)',
                        borderColor: //[
                            'rgb(122, 148, 255, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    scales: {
                        yAxes: [{
                            ticks: {
                                beginAtZero: true
                            }
                        }]
                    }
                }
            });
            </script>
    @endsection
</div>
