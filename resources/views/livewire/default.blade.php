<div>
    @if (isset($error) && $error !== false)
    <div class="callout callout-danger m-5">
        <h5>@lang('app.error')</h5>
        <p>{{ $error }}</p>
      </div>
    @endif
</div>