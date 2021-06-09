<div>
    @if ($error)
    <div class="callout callout-danger">
        <h5>@lang('app.error')</h5>
        <p>{{ $error }}</p>
      </div>
    @endif
</div>