{{-- The user's passkeys (roadmap TODO 69). The page already sits behind
     password.confirm, which Fortify's registration and delete routes ask for
     too. Adding needs browser support, so that form appears only where the
     client finds it; listing and deleting work everywhere. --}}
@php($passkeys = auth()->user()->passkeys()->orderBy('created_at')->get())
<div class="row">
    <div class="col-md-6">
        <div class="pt-2 card card-primary card-outline" data-passkey-scope>
            <div class="card-header">
                <h5 class="m-0"><i class="fas fa-fingerprint mr-1"></i> @lang('user.passkeys.title')</h5>
            </div>
            <div class="card-body">
                <p><i class="fas fa-info-circle mr-1"></i> @lang('user.passkeys.help')</p>

                @if (session('status') == 'passkey-deleted')
                    <div class="alert alert-success">@lang('user.passkeys.deleted')</div>
                @endif

                @forelse ($passkeys as $passkey)
                    <div class="d-flex align-items-center border-bottom py-2">
                        <div class="flex-grow-1">
                            <b>{{ $passkey->name }}</b>
                            @if ($passkey->authenticator)
                                <small class="text-muted">({{ $passkey->authenticator }})</small>
                            @endif
                            <br/>
                            <small class="text-muted">
                                @lang('user.passkeys.created'): {{ $passkey->created_at?->isoFormat('LLL') }} &middot;
                                @lang('user.passkeys.last_used'):
                                {{ $passkey->last_used_at ? $passkey->last_used_at->isoFormat('LLL') : __('user.passkeys.never_used') }}
                            </small>
                        </div>
                        <form action="{{ route('passkey.destroy', $passkey) }}" method="POST">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="fas fa-trash-alt mr-1"></i> @lang('user.passkeys.delete')
                            </button>
                        </form>
                    </div>
                @empty
                    <p class="text-muted">@lang('user.passkeys.none')</p>
                @endforelse

                <div class="mt-3 d-none" data-passkey-block>
                    <div class="input-group">
                        <input type="text" class="form-control" id="passkey-name" maxlength="255"
                               placeholder="@lang('user.passkeys.name_placeholder')">
                        <div class="input-group-append">
                            <button type="button" class="btn btn-primary d-none"
                                    data-passkey="register"
                                    data-passkey-name="#passkey-name"
                                    data-options="{{ route('passkey.registration-options') }}"
                                    data-submit="{{ route('passkey.store') }}">
                                <i class="fas fa-plus mr-1"></i> @lang('user.passkeys.add')
                            </button>
                        </div>
                    </div>
                    <div class="text-danger mt-2 d-none" role="alert" data-passkey-error></div>
                </div>
            </div>
        </div>
    </div>
</div>
