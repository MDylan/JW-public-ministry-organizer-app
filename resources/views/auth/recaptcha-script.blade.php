@section('footer_scripts')
<script src="https://www.google.com/recaptcha/api.js?render={{ config('services.recaptcha.site_key') }}"></script>
    <script>
        grecaptcha.ready(function () {
            document.getElementById("{{$formId}}").addEventListener("submit", function (event) {
                event.preventDefault();
                grecaptcha.execute('{{ config('services.recaptcha.site_key') }}', { action: 'register' })
                    .then(function (token) {
                        document.getElementById("recaptcha_token").value = token;
                        document.getElementById("{{$formId}}").submit();
                    });
            });
        });
    </script>
@endsection