@extends('layouts.app')

@section('title', 'Popular movies')

@section('content')
    <h1 class="h3 mb-4">Popular movies</h1>

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
@endsection