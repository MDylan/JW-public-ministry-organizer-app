<div>
    <div wire:ignore.self class="modal fade" id="form" tabindex="-1" aria-labelledby="ModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl eventModal"> 
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-0">
                    @if (isset($error) && $error !== false)
                    <div class="callout callout-danger m-5">
                        <h5>@lang('app.error')</h5>
                        <p>{{ $error }}</p>
                      </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fa fa-times mr-1"></i> @lang('Close')</button>
                </div>
            </div>
        </div>
    </div>
</div>