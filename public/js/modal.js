$(document).ready(function() {
    window.addEventListener('hide-modal', event => {
        id = event.detail.id;
        $('#' + id).modal('hide');
        if(event.detail.message) {
            toastr.success(event.detail.message, event.detail.savedMessage);
        }
        console.log('hide-modal', id);
    });
    window.addEventListener('show-modal', event => {
        id = event.detail.id;
        setTimeout(() => {
            $('#'+id).modal('show');
            console.log('show-modal', id);
            // this.activeComponent = id;
            // this.showActiveComponent = true;
            // this.modalWidth = this.getActiveComponentModalAttribute('maxWidthClass');
        }, 300);
        

        $('#'+id).on('hide.bs.modal', function (e) {
            Livewire.emit('hideModal', id);
            console.log('hideModal', id);
        });

        $('#'+id).on('hidden.bs.modal', function (e) {
            Livewire.emit('hiddenModal', id);
            console.log('hiddenModal', id);
        });
    });
});