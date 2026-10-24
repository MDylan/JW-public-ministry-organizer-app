{{-- The setup key under the QR code, for an authenticator that cannot scan
     one. The same value Fortify's two-factor.secret-key route returns as JSON
     (roadmap TODO 69); grouped in fours only for reading - authenticators
     ignore the spaces. --}}
@if (auth()->user()->two_factor_secret)
    <p class="text-center mb-2">
        <small>@lang('user.two_factor.setup_key')</small><br/>
        <samp class="user-select-all">{{ trim(chunk_split(\Laravel\Fortify\Fortify::currentEncrypter()->decrypt(auth()->user()->two_factor_secret), 4, ' ')) }}</samp>
    </p>
@endif
