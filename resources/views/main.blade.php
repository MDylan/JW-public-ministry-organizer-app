@extends('public')

@section('title')
  {{ $page->title }}
@endsection

@section('content')

  <div class="card card-primary card-outline">
    <div class="card-body">
          {!! $page->content !!}
    </div>
  </div>


@endsection