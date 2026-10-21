@extends('layouts.app')

@section('title', 'My favourites')

@section('content')
    <h1 class="h3 mb-4">My favourites</h1>

    @if ($favourites->isEmpty())
        <p class="text-muted">
            You haven't added any favourites yet.
            <a href="{{ route('movies.index') }}">Browse the movies</a>.
        </p>
    @else
        <div class="row row-cols-2 row-cols-md-3 g-3">
            @foreach ($favourites as $favourite)
                <div class="col">
                    @include('movies._card', ['movie' => $favourite, 'isFavourite' => true])
                </div>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $favourites->links() }}
        </div>
    @endif
@endsection