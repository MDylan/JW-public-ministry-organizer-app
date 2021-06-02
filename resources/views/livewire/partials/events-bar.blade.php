<div>
    @foreach ($events as $event)
        <p>{{$event['day_name']}},
            {{$event['full_time']}}<br/>
            {{$event['groups']['name']}} </p>
    @endforeach
</div>
