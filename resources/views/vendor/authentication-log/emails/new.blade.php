@component('mail::message')
# @lang('Hello!')

@lang('email.new_login.line_1')

> **@lang('email.new_login.account'):** {{ $account->email }}<br/>
> **@lang('email.new_login.time'):** {{ $time->toCookieString() }}<br/>
> **@lang('email.new_login.ip_address'):** {{ $ipAddress }}<br/>
> **@lang('email.new_login.browser'):** {{ $browser }}<br/>
@if ($location && $location['default'] === false)
> **@lang('Location:')** {{ $location['city'] ?? __('Unknown City') }}, {{ $location['state'], __('Unknown State') }}
@endif

@lang('email.new_login.line_2')
<br/><br/>
@lang('Regards')<br/>
{{ config('app.name') }}
@endcomponent
