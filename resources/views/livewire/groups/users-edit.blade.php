<div>
    {{-- @dd($activeComponent) --}}
    <x-modal :activeComponent="$activeComponent">
        <x-slot name="title">
            Hello World
        </x-slot>
    
        <x-slot name="content">
            Hi! 👋
        </x-slot>
    
        <x-slot name="buttons">
            Buttons go here...
        </x-slot>
    </x-modal>
</div>