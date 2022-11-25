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
                <div class="col-12">
                    <canvas id="myChart" width="100" height="30"></canvas>

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
            var ctx = document.getElementById('myChart').getContext('2d');
            var myChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: [
                        @foreach ($periods as $period)
                            '{{ $period->format(__('app.format.time')) }}',
                        @endforeach
                    ],
                    datasets: [{
                        label: '@lang('user.online')',
                        data: [
                            @foreach ($stats as $stat)
                                {{ $stat['number'] }},
                            @endforeach
                        ],
                        backgroundColor: //[
                            'rgba(255, 99, 132, 0.2)',
                        //     'rgba(54, 162, 235, 0.2)',
                        //     'rgba(255, 206, 86, 0.2)',
                        //     'rgba(75, 192, 192, 0.2)',
                        //     'rgba(153, 102, 255, 0.2)',
                        //     'rgba(255, 159, 64, 0.2)'
                        // ],
                        borderColor: //[
                            'rgba(255, 99, 132, 1)',
                        //     'rgba(54, 162, 235, 1)',
                        //     'rgba(255, 206, 86, 1)',
                        //     'rgba(75, 192, 192, 1)',
                        //     'rgba(153, 102, 255, 1)',
                        //     'rgba(255, 159, 64, 1)'
                        // ],
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
