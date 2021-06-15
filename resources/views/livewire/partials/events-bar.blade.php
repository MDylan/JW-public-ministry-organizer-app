<div>
    @foreach ($events as $event)
        @if (isset($event['groups']['name']))
        <p>{{$event['day_name']}},
            {{$event['full_time']}}<br/>
            {{$event['groups']['name']}} </p>    
        @endif        
    @endforeach
</div>
