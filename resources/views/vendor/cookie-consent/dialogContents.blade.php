<div class="js-cookie-consent cookie-consent fixed bottom-0 inset-x-0 pb-2">
    <div class="w-100 mx-auto px-6 text-white">
        <div class="container text-center">
            <div class="row justify-content-center py-2">
                {{-- <div class="w-0 flex-1 items-center hidden md:inline"> --}}
                <div class="col-md-5 my-auto">
                    <p class="cookie-consent__message my-auto">
                        {!! trans('cookie-consent::texts.message') !!}
                    </p>
                    
                </div>
                <div class="col-md-2">
                    <a class="btn btn-danger js-cookie-consent-agree cookie-consent__agree rounded">
                        {{ trans('cookie-consent::texts.agree') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
