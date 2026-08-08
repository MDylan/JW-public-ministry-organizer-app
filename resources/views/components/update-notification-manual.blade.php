{{--
    Új FŐVERZIÓ érhető el, amit a rendszer szándékosan nem telepít magától.

    Ez nem a szokásos frissítési kártya gomb nélkül: itt a teendő is más. A
    magasabb major eltérő PHP- és Laravel-környezetet feltételez, ezért a
    lépéseket a kiadás leírása (a csatorna `description` mezője) mondja meg -
    így az utasítás a szerverről szerkeszthető, kódmódosítás nélkül.

    A tiltást nem ez a nézet tartja: az /updater.update címet az
    App\Http\Middleware\EnsureUpdateWithinBranch zárja le. Itt CSAK azért nincs
    Frissítés gomb, mert nem vinne sehova.
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
