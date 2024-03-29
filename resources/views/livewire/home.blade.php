<div @if($polling) wire:poll.30000ms wire:key="home-polling" @endif>
@section('title')
{{__('app.menu-home')}}
@endsection
    <x-loading-indicator target="openEventsModal,openModal" />
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
            <h1 class="m-0">{{__('app.menu-home')}}</h1>
            </div><!-- /.col -->
            <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="#">{{__('app.menu-home')}}</a></li>
            </ol>
            </div><!-- /.col -->
        </div><!-- /.row -->
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->

    <!-- Main content -->
    <div class="content">
        <div class="container-fluid">
        @if (config('show_homepage_alert') == 1)
            <div class="callout callout-info">
                <h5><i class="far fa-bell mr-1"></i> <b>@lang('app.system_message')</b></h5>
                {!! config('homepage_message') !!}
            </div>
        @endif
        <div class="row">
            <div class="col-12">
            @if (session()->has('verified'))
                <div class="alert alert-success">
                    <i class="far fa-thumbs-up mr-1"></i>
                    @lang('user.newEmail.success') ({{ auth()->user()->email }})<br/>
                    <b>@lang('user.newEmail.please_use_it')</b>
                </div>                
            @endif
        </div>
        {{-- <div class="card-columns"> --}}
            @forelse ($groups as $group)
                <div class="col-md-6">
                    <div class="card card-primary card-outline" id="card_{{ $group['id'] }}" wire:ignore.self>
                        <div class="card-header">
                            <div class="card-title">
                                {{ $group['name'] }}                            
                            </div>
                            <div class="card-tools">
                                @if (!$loop->first)
                                <button wire:click="setOrder({{ $group['id'] }}, 'up')" type="button" class="btn btn-tool" title="@lang('app.up')">
                                    <i class="fas fa-chevron-up"></i>
                                </a>
                                @endif
                                @if (!$loop->last)
                                <button wire:click="setOrder({{ $group['id'] }}, 'down')" type="button" class="btn btn-tool" title="@lang('app.down')">
                                    <i class="fas fa-chevron-down"></i>
                                </a> 
                                @endif
                                <button type="button" class="btn btn-tool" data-card-widget="collapse" title="@lang('app.collapse')" wire:ignore>
                                <i class="fas fa-minus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-1">
                            @if (count($group['posters']) > 0)
                                <div class="callout callout-success mx-2 mt-2">
                                    <h5 class="border-bottom mb-1 pb-1"> <i class="far fa-file-image mr-1"></i> @lang('group.poster.title')</h5>
                                        @foreach ($group['posters'] as $poster)                            
                                            <div class="row">
                                                <div class="col">
                                                    @if(in_array($group_roles[$group['id']], ['admin', 'roler'])) 
                                                        <button wire:ignore.self
                                                            wire:click="$emitTo('groups.poster-edit-modal', 'openModal', {{ $group['id'] }}, {{ $poster['id'] }})"
                                                            class="btn btn-sm btn-info my-1 mr-1">
                                                            <i class="far fa-edit"></i>
                                                        </button>
                                                    @endif
                                                    <button wire:click="togglePosterRead({{ $poster['id'] }})" wire:loading.attr="disabled" class="btn btn-sm btn-info" type="button" data-toggle="collapse" data-target="#collapse_poster{{ $poster['id'] }}" aria-expanded="false" aria-controls="collapse_poster{{ $poster['id'] }}">
                                                        @if(count($poster['user_read'] ?? []) == 0) 
                                                            <i class="far fa-check-circle mr-1"></i>
                                                            @lang('group.poster.i_have_read')
                                                        @else
                                                            <i class="fas fa-eye mr-1"></i>
                                                            @lang('app.show')
                                                        @endif
                                                    </button>
                                                    <strong>
                                                        {{  \Carbon\Carbon::parse($poster["show_date"])->format(__('app.format.date')) }}
                                                        - 
                                                        @if ($poster["hide_date"] !== null)
                                                            {{  \Carbon\Carbon::parse($poster["hide_date"])->format(__('app.format.date')) }}
                                                        @else
                                                            @lang('group.poster.until_revoked')
                                                        @endif
                                                    </strong>
                                                    <div class="collapse @if(count($poster['user_read'] ?? []) == 0) show @endif" id="collapse_poster{{ $poster['id'] }}">
                                                        {!! $poster['info'] !!}
                                                    </div>
                                                    
                                                </div>
                                            </div>
                                        @endforeach
                                </div>
                            @endif

                            @if ($group['messages_on'])
                                <livewire:groups.messages :group="$group['id']" :wire:key="'messages_'.$group['id']">
                            @endif                            



                        <table class="table table-sm m-0">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width: 130px;">@lang('event.date')</th>
                                    <th class="text-center" style="width: 130px;">@lang('event.status')</th>
                                    <th class="text-center">@lang('event.eventsBar.title')</th>
                                </tr>
                            </thead>
                            <tbody>
                                    @foreach ($days as $day)
                                    <tr @if (date("w", $day) == 0) style="border-bottom:2px solid #000;" @endif>
                                        <td class="text-center position-relative">
                                            @if($available_days[$group['id']][$day]) 
                                                {{-- <a href="javascript:void(0);" onclick="modal({{$group['id']}}, '{{ date("Y-m-d", $day) }}')"> --}}
                                                <a wire:loading.class="text-secondary" wire:loading.attr="disabled" href="javascript:void(0);" wire:click="openEventsModal({{$group['id']}}, '{{ date("Y-m-d", $day) }}')">
                                                    @if(in_array($group_roles[$group['id']], ['admin', 'roler'])) 
                                                        @if(isset($notAccepts[$group['id']][date("Y-m-d", $day)]))
                                                            <i class="fas fa-balance-scale-right mr-1 mt-1 position-absolute" style="margin-left: -26px"></i>
                                                        @endif
                                                    @endif
                                                    {{ date("m.d", $day) }}, {{ __('event.weekdays_short.'.date("w", $day)) }}
                                                </a>
                                            @else 
                                                {{ date("m.d", $day) }}, {{ __('event.weekdays_short.'.date("w", $day)) }}
                                            @endif                                    
                                        </td>
                                        <td class="p-0" @if($available_days[$group['id']][$day]) onclick="modal({{ $group['id'] }}, '{{ date("Y-m-d", $day) }}')" @endif> 
                                            @if (isset($day_stat[$group['id']][$day]))
                                                <div class="dayStat @if (isset($day_stat[$group['id']][$day]['event'])) userEvent @endif "
                                                    style="height:35px;background: {{$day_stat[$group['id']][$day]['style'] }}"></div>
                                            @endif
                                        </td>
                                        <td class="p-0">                                        
                                            @if ( isset($events[$group['id']][$day]) )
                                                <table class="table table-striped table-hover m-0">
                                                    @foreach ($events[$group['id']][$day] as $event)
                                                        <tr>
                                                            <td class="text-center">
                                                                @if($event['status'] == 0)
                                                                    <span class="badge badge-warning">
                                                                        <i class="fas fa-balance-scale-right" title="@lang('event.status_0')"></i>
                                                                    </span>
                                                                @endif
                                                                {{ $event['full_time'] }}
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </table>  
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                            </tbody>
                        </table>
                            <div class="row ml-2 mt-2 mr-2 mb-0">
                                <div class="col-12">
                                    <a wire:click="changeGroup({{$group['id']}})" href="javascript:void(0);" class="btn btn-primary mr-1 mb-2">
                                        <i class="fa fa-calendar mr-1"></i>
                                        @lang('app.jump_to_calendar')</a>
                                    
                                        <a href="{{ route('groups.users', ['group' => $group['id']]) }}" class="btn btn-outline-secondary mr-1 mb-2">
                                            <i class="fa fa-user-friends mr-1"></i>
                                            @lang('group.users')
                                        </a>

                                        <a href="{{ route('groups.news', ['group' => $group['id']]) }}" class="btn btn-info mb-2">
                                            <i class="fa fa-file mr-1"></i>
                                            @lang('group.news')</a>
                                        @if(in_array($group_roles[$group['id']], ['admin', 'roler'])) 
                                            <a wire:ignore.self href="javascript:void(0);" wire:click="$emitTo('groups.poster-edit-modal', 'openModal', {{ $group['id'] }})" class="btn btn-info mb-2 ml-1">
                                                <i class="fa fa-plus mr-1"></i>
                                                @lang('group.poster.button')</a>
                                        @endif

                                            
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                    <div class="card card-primary card-outline">
                        <div class="card-header">
                        <h5 class="m-0">@lang('app.information')</h5>
                        </div>
                        <div class="card-body">
                            <p>@lang('app.no_any_groups')</p>
                        </div>
                    </div>
            @endforelse
            <!-- /.col-md-6 -->
        </div>
        @if(count($groups) > 0)
            <script>
                function modal(groupId, date) {
                    @this.call('openEventsModal', groupId,  date);
                }
            </script>
        @endif
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content -->
    @if(count($groups) > 0)
        @livewire('groups.poster-edit-modal')
    @endif
</div>