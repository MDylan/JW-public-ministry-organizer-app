@section('footer_scripts')
{{-- `action` was PREVIOUSLY hardcoded to `register` on all three forms, and
     the server did not even look at the returned field. From now on the
     real action goes out per form, and App\Http\Middleware\CheckRecaptcha
     expects that same value back in Google's response. --}}
<script src="https://www.google.com/recaptcha/api.js?render={{ config('services.recaptcha.site_key') }}"></script>
    <script>
        grecaptcha.ready(function () {
            document.getElementById("{{$formId}}").addEventListener("submit", function (event) {
                event.preventDefault();
                grecaptcha.execute('{{ config('services.recaptcha.site_key') }}', { action: '{{ $action }}' })
                    .then(function (token) {
                        document.getElementById("recaptcha_token").value = token;
                        document.getElementById("{{$formId}}").submit();
                    });
            });
        });
    </script>
@endsection
