<div @if($privilege['read']) wire:poll.5000ms @else wire:poll.30000ms @endif>
    <div wire:ignore.self class="card card-warning card-outline direct-chat direct-chat-warning mx-2">
        <div class="card-header">
            <h3 class="card-title">@lang('group.messages.title') <span class="badge badge-secondary">Beta</span></h3>
            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                <i class="fas fa-minus"></i>
                </button>
            </div>
        </div>
        @if($privilege['read'])
            <div class="card-body">        
                <div class="direct-chat-messages">

                    @forelse  ($messages as $message)
                        <div class="direct-chat-msg @if(auth()->id() == $message->user_id) right @endif">
                            <div class="direct-chat-infos clearfix">
                                <span class="direct-chat-name @if(auth()->id() == $message->user_id) float-right @else float-left @endif">
                                    {{ $message->user->name }}
                                    @if(!is_null($message->message) && ($privilege['delete'] || auth()->id() == $message->user_id))
                                        <span class="badge badge-danger @if(auth()->id() == $message->user_id) float-left @else float-right @endif mx-1 mt-1">
                                            <a href="javascript:void(0);" wire:click="$emitSelf('deleteMessage', {{ $message->id }})" class="text-white" >
                                                @lang('Delete')
                                            </a>
                                        </span>
                                    @endif
                                </span>
                                <span class="direct-chat-timestamp @if(auth()->id() == $message->user_id) float-left @else float-right @endif">
                                    {{ $message->created_at->format(__('app.format.datetime')) }}
                                </span>
                            </div>
                            <img class="direct-chat-img" src="{{ asset('public/avatars/avatar-'.$message->user_id.'.png') }}" alt="{{ $message->user->name }}">

                            <div class="direct-chat-text">
                                @if(!is_null($message->message))
                                    @if($message->priority == 1)
                                        <i class="fas fa-exclamation-triangle mr-1 text-red"></i>
                                    @endif
                                    {{ $message->message }}
                                @else
                                    <i>@lang('group.messages.deleted')</i>
                                @endif
                            </div>
                        
                        </div>
                    @empty
                        <div class="my-2 text-center">@lang('group.messages.no_messages')</div>
                    @endforelse
                    @if($messages_count > 0 && $message_number < $total)
                        <div class="direct-chat-msg">
                            <div class="text-sm text-center">
                                <a href="javascript:void(0);" wire:click="increaseMessages()"  wire:loading.attr="disabled" wire:target="increaseMessages">
                                    @lang('group.history')
                                </a></div>
                        </div>
                    @endif
                </div>
            </div>
            <div class="card-footer">
                @if($privilege['write'])
                    <form autocomplete="off" wire:submit.prevent="sendMessage">
                        <div class="input-group">
                            <input wire:model.defer="message" type="text" name="message" placeholder="@lang('group.messages.type') ..." class="form-control @error('message') is-invalid @enderror">
                            <span class="input-group-append">
                                @if($group_priority ?? 0)
                                <button class="btn @if($message_priority == 1) btn-danger @else btn-secondary @endif" type="button" wire:click="changePriority">
                                    @if($message_priority == 1)
                                    <i class="far fa-check-square mr-1"></i>
                                    @else
                                        <i class="far fa-square mr-1"></i>
                                    @endif
                                    @lang('group.messages.urgent')
                                </button>
                                @endif
                                <button type="submit" class="btn btn-warning" wire:loading.attr="disabled" wire:target="sendMessage">
                                    <i class="far fa-paper-plane mr-1"></i>
                                    @lang('app.send')
                                </button>
                            </span>
                            @error('message')<div class="invalid-feedback" role="alert">{{$message}}</div>@enderror
                        </div>
                        <small id="passwordHelpBlock" class="form-text text-muted">
                            @if($message_priority == 1)
                                <b>@lang('group.messages.urgent_info')</b><br/>
                            @endif
                            @lang('group.messages.info')
                            @lang('group.messages.be_short')
                        </small>
                    </form>
                @else
                    <small id="passwordHelpBlock" class="form-text text-muted text-center">@lang('group.messages.cant_write')</small>
                @endif
            </div>
        @else
            <div class="card-footer">
                <small id="passwordHelpBlock" class="form-text text-muted">
                    @lang('group.messages.info')
                </small>
            </div>
        @endif     
    </div>
</div>
