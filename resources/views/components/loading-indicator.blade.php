<div wire:loading.delay.longer @if(isset($target)) wire:target="{{ $target }}"@endif>
    <div style="display: flex; justify-content: center; align-items: center;background-color:black; position: fixed;top:0px;left:0px;z-index:9999;width:100%;height:100%;opacity:.75">
        <div class="la-ball-clip-rotate la-2x">
            <div></div>
        </div>
    </div>
</div>