{{--
    The passkey browser client (roadmap TODO 69).

    Loads the vendored official client (public/vendor/passkeys, see its
    README) as ES modules; the import map resolves the one bare import inside
    it. Include this once per page, before any other module script, and mark
    the controls with data attributes:

    - data-passkey="login|confirm|register" on a button, with data-options and
      data-submit carrying the two route URLs;
    - data-passkey-name on the register button: the selector of the name input;
    - data-passkey-error on an element to receive the error text;
    - data-passkey-block on a wrapper that should appear with the buttons.

    Buttons start hidden (d-none) and are shown only where the browser
    supports passkeys, so a browser without WebAuthn never sees a dead button.
--}}
<script type="importmap">
{!! json_encode(['imports' => ['@simplewebauthn/browser' => asset('vendor/passkeys/simplewebauthn-browser/esm/index.js')]], JSON_UNESCAPED_SLASHES) !!}
</script>
<script type="module">
    import { Passkeys } from {!! json_encode(asset('vendor/passkeys/laravel-passkeys/index.js'), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!};

    const messages = @json(__('user.passkeys.errors'));

    Passkeys.configure({ fetch: { headers: { 'X-CSRF-TOKEN': @json(csrf_token()) } } });

    const describe = (error) => ({
        UserCancelledError: messages.cancelled,
        PasskeyExistsError: messages.exists,
        InvalidDomainError: messages.domain,
        NotSupportedError: messages.unsupported,
    })[error?.name] ?? messages.failed;

    const showError = (button, text) => {
        const target = button.closest('[data-passkey-scope]')?.querySelector('[data-passkey-error]')
            ?? document.querySelector('[data-passkey-error]');
        if (target) {
            target.textContent = text;
            target.classList.remove('d-none');
        }
    };

    if (Passkeys.isSupported()) {
        document.querySelectorAll('[data-passkey-block]').forEach((block) => block.classList.remove('d-none'));

        document.querySelectorAll('[data-passkey]').forEach((button) => {
            button.classList.remove('d-none');

            button.addEventListener('click', async () => {
                const routes = { options: button.dataset.options, submit: button.dataset.submit };
                button.disabled = true;

                try {
                    if (button.dataset.passkey === 'register') {
                        const input = document.querySelector(button.dataset.passkeyName);
                        const name = (input?.value ?? '').trim();
                        if (name === '') {
                            showError(button, messages.name_required);
                            return;
                        }
                        await Passkeys.register({ name, routes });
                        window.location.reload();
                        return;
                    }

                    const result = await Passkeys.verify({
                        routes,
                        remember: () => document.getElementById('remember')?.checked ?? false,
                    });
                    window.location.assign(result?.redirect ?? window.location.href);
                } catch (error) {
                    showError(button, describe(error));
                } finally {
                    button.disabled = false;
                }
            });
        });
    }
</script>
