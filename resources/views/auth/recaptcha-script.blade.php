@section('footer_scripts')
{{-- Az `action` KORÁBBAN mindhárom űrlapon a hardkódolt `register` volt, a
     szerver pedig meg sem nézte a visszakapott mezőt. Innentől űrlaponként a
     valódi művelet megy ki, és az App\Http\Middleware\CheckRecaptcha ugyanazt
     az értéket várja vissza a Google válaszában. --}}
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
