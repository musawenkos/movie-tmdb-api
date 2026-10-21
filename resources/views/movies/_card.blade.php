<div class="card movie-card h-100 border-0 rounded-3 overflow-hidden shadow-sm">
    <button type="button" class="movie-card-poster p-0 border-0 bg-transparent"
            data-movie-id="{{ $movie['tmdb_movie_id'] }}"
            aria-label="More about {{ $movie['title'] }}">
        @if ($movie['poster_path'])
            <img src="{{ config('services.tmdb.img_url') . $movie['poster_path'] }}"
                 class="card-img-top"
                 alt="Poster for {{ $movie['title'] }}"
                 loading="lazy">
        @else
            <div class="bg-secondary text-white d-flex align-items-center justify-content-center"
                 style="height: 300px">No poster</div>
        @endif
    </button>

    <div class="card-body d-flex flex-column">
        <h2 class="h6 card-title">{{ $movie['title'] }}</h2>
        <p class="card-text text-muted small">
            {{ $movie['release_date']
                ? \Illuminate\Support\Carbon::parse($movie['release_date'])->format('Y-m-d')
                : 'Release date unknown' }}
        </p>

        <div class="mt-auto d-grid gap-2">
            <button type="button" class="btn btn-sm btn-outline-info" data-movie-id="{{ $movie['tmdb_movie_id'] }}">
                More info
            </button>
            @auth
                @if ($isFavourite)
                    <form method="POST" action="{{ route('favourites.destroy', $movie['tmdb_movie_id']) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger w-100">♥ Remove</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('favourites.store') }}">
                        @csrf
                        <input type="hidden" name="tmdb_movie_id" value="{{ $movie['tmdb_movie_id'] }}">
                        <button type="submit" class="btn btn-sm btn-outline-light w-100">♡ Add to favourites</button>
                    </form>
                @endif
            @else
                <a href="{{ route('login') }}" class="btn btn-sm btn-outline-secondary w-100">Log in to favourite</a>
            @endauth
        </div>
    </div>
</div>