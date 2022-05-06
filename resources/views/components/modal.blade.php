<div>
    <div class="modal fade show" id="{{ $modalId }}" tabindex="-1" aria-labelledby="" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog {{ $modalSize }}">
            <div class="modal-content">
                <div class="modal-header">
                    @if(isset($title))
                        <h5 class="modal-title" id="exampleModalLabel">{{ $title }}</h5>
                    @endif
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                </div>
                <div class="modal-body">
                    {{ $content }}
                </div>
                <div class="modal-footer">
                    {{ $buttons }}
                </div>
            </div>
        </div>
    </div>
</div>