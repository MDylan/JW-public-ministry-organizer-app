<div class="row m-2">
    <div class="col-12">
        <div class="card card-warning card-outline collapsed-card">
            <div class="card-header">
                <h5 class="card-title text-primary">
                    <i class="far fa-arrow-alt-circle-up mr-1"></i>
                    {{ __('laraupdater.Update_Available') }}
                    <span class="badge badge-pill badge-primary ml-1">
                        {{ (new \pcinaglia\laraupdater\LaraUpdaterController)->getCurrentVersion() }}
                        <i class="fas fa-angle-double-right mx-2"></i>
                        {{ $version }}</span>
                </h5>
                <div class="card-tools">
                    <button type="button" class="btn btn-sm btn-primary" data-card-widget="collapse" title="Collapse">
                        <i class="fas fa-plus mr-1"></i> @lang('app.show')
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="callout callout-info">
                    <h5><i class="icon fas fa-info mr-2"></i> @lang('app.update_description')</h5>
                    {{ $description }}
                </div>
                <div class="alert alert-warning alert-dismissible">
                    <h5><i class="icon fas fa-exclamation-triangle"></i>@lang('app.urgent')!</h5>
                    @lang('app.update_info')
                </div>
                <a role="button" href="updater.update" class="btn btn-warning">
                    <i class="far fa-check-circle mr-1"></i>
                    {{ __('laraupdater.Update_Now') }}</a>
            </div>
        </div>
    </div>
</div>