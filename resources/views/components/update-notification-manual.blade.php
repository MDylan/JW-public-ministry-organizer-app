{{--
    A new MAJOR VERSION is available, which the system deliberately does not
    install on its own.

    This isn't just the usual update card without a button: what to do here
    is different too. A higher major version assumes a different PHP and
    Laravel environment, so the steps are given by the release's description
    (the channel's `description` field) - this way the instructions can be
    edited from the server, without a code change.

    This view does not hold the restriction: the /updater.update URL is
    locked down by App\Http\Middleware\EnsureUpdateWithinBranch. There is
    ONLY no Update button here because it would not lead anywhere.
--}}
<div class="row m-2">
    <div class="col-12">
        <div class="card card-danger card-outline collapsed-card">
            <div class="card-header">
                <h5 class="card-title text-danger">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    {{ __('app.update_manual_title') }}
                    <span class="badge badge-pill badge-danger ml-1">
                        {{ (new \MDylan\LaraUpdater\LaraUpdaterController)->getCurrentVersion() }}
                        <i class="fas fa-angle-double-right mx-2"></i>
                        {{ $version }}</span>
                </h5>
                <div class="card-tools">
                    <button type="button" class="btn btn-sm btn-danger" data-card-widget="collapse" title="Collapse">
                        <i class="fas fa-plus mr-1"></i> @lang('app.show')
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="callout callout-info">
                    <h5><i class="icon fas fa-info mr-2"></i> @lang('app.update_description')</h5>
                    {{ $description }}
                </div>
                <div class="alert alert-danger">
                    <h5><i class="icon fas fa-exclamation-triangle"></i>@lang('app.urgent')!</h5>
                    @lang('app.update_manual_info')
                </div>
                <a role="button" href="{{ config('events.github_url') }}" target="_blank" rel="noopener" class="btn btn-outline-danger">
                    <i class="fas fa-external-link-alt mr-1"></i>
                    @lang('settings.software_url')</a>
            </div>
        </div>
    </div>
</div>
