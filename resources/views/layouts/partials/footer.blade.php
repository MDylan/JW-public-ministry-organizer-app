<footer class="main-footer text-sm">
    <a href="{{route('contact')}}">{{__('app.contact')}}</a> 
    <x-Footer></x-Footer> 
    {{-- | <a href="{{route('terms')}}">{{__('app.terms')}}</a> --}}
    <div class="custom-control custom-switch theme-switch" style="float:right;">        
        <input type="checkbox" class="custom-control-input" id="customSwitch1"> @lang('app.dark_theme')
        <label class="custom-control-label" for="customSwitch1" style="float:left;"></label>                
    </div>
</footer>