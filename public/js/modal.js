$(document).ready(function() {
    window.addEventListener('hide-modal', event => {
        id = event.detail.id;
        $('#' + id).modal('hide');
        if(event.detail.message) {
            toastr.success(event.detail.message, event.detail.savedMessage);
        }
        console.log('hide-modal 2', id);
    });
    window.addEventListener('show-modal', event => {
        id = event.detail.id;
        $('#'+id).modal('show');
        console.log('show-modal', id);
        // setTimeout(() => {
        //     $('#'+id).modal('show');
            
        //     // this.activeComponent = id;
        //     // this.showActiveComponent = true;
        //     // this.modalWidth = this.getActiveComponentModalAttribute('maxWidthClass');
        // }, 100);
        

        $('#'+id).one('hide.bs.modal', function (e) {
            if(event.detail.livewire) {
                Livewire.emitTo(event.detail.livewire, 'hideModal', event.detail.parameters_back);
                console.log('hidemodal livewire_event', event.detail.livewire, id, event.detail.parameters_back);
            }
        });

        $('#'+id).one('hidden.bs.modal', function (e) {
            if(event.detail.livewire) {
                Livewire.emitTo(event.detail.livewire, 'hiddenModal', event.detail.parameters_back);        
                console.log('hiddenmodal livewire_event', event.detail.livewire, id, event.detail.parameters_back);
            }
        });
    });
});