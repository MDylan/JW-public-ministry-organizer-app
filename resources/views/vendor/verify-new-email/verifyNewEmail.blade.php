@component('mail::message')
<h1>@lang('Hello!')</h1>

@lang('email.verifyNewEmail.line_1')

@component('mail::button', ['url' => $url])
@lang('Verify Email Address')
@endcomponent

@lang('email.verifyNewEmail.line_2')<br/>

@lang('Regards'),<br/>
{{ config('app.name') }}
@endcomponent