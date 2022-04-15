$(document).ready(function() {
    window.addEventListener('hide-modal', event => {
        id = event.detail.id;
        $('#' + id).modal('hide');
        if(event.detail.message) {
            toastr.success(event.detail.message, event.detail.savedMessage);
        }
    });
    window.addEventListener('show-modal', event => {
        id = event.detail.id;
        $('#'+id).modal('show');
        $('#'+id).one('hide.bs.modal', function (e) {
            if(event.detail.livewire) {
                Livewire.emitTo(event.detail.livewire, 'hideModal', event.detail.parameters_back);
            }
        });

        $('#'+id).one('hidden.bs.modal', function (e) {
            if(event.detail.livewire) {
                Livewire.emitTo(event.detail.livewire, 'hiddenModal', event.detail.parameters_back);
            }
        });
    });
});