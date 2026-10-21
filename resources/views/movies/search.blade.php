@extends('layouts.app')

@section('title', 'Search: '.$query)

@section('content')
    <h1 class="h3 mb-1">Results for “{{ $query }}”</h1>
    <p class="text-muted mb-4">
        {{ $movies->total() }} {{ Str::plural('movie', $movies->total()) }} found
    </p>

    @if ($movies->isEmpty())
        <p class="text-muted">
            No movies match that search.
            <a href="{{ route('movies.index') }}">Back to popular movies</a>.
        </p>
    @else
        <div class="row row-cols-2 row-cols-md-3 g-3">
            @foreach ($movies as $movie)
                <div class="col">
                    @include('movies._card', [
                        'movie' => $movie,
                        'isFavourite' => in_array($movie['tmdb_movie_id'], $favouriteIds),
                    ])
                </div>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $movies->links() }}
        </div>
    @endif
@endsection
