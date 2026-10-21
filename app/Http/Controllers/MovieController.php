<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchMoviesRequest;
use App\Services\TmdbService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class MovieController extends Controller
{
    private const PER_PAGE = 9;

    public function index(Request $request, TmdbService $tmdb): View
    {
        $all = $this->fromTmdb(fn () => $tmdb->popularMovies());

        return view('movies.index', [
            'movies' => $this->paginate($request, $all),
            'favouriteIds' => $this->favouriteIds($request),
        ]);
    }

    public function search(SearchMoviesRequest $request, TmdbService $tmdb): View
    {
        $query = $request->string('q')->trim()->value();
        $all = $this->fromTmdb(fn () => $tmdb->search($query));

        return view('movies.search', [
            'query' => $query,
            'movies' => $this->paginate($request, $all)->appends(['q' => $query]),
            'favouriteIds' => $this->favouriteIds($request),
        ]);
    }

    public function autocomplete(SearchMoviesRequest $request, TmdbService $tmdb): JsonResponse
    {
        $movies = $this->fromTmdb(fn () => $tmdb->autocomplete($request->string('q')->trim()->value()));

        return response()->json([
            'data' => $movies->map(fn ($movie) => [
                'id' => $movie['tmdb_movie_id'],
                'title' => $movie['title'],
                'year' => $movie['release_date'] ? substr($movie['release_date'], 0, 4) : null,
            ])->values(),
        ]);
    }

    public function show(Request $request, int $tmdbMovieId, TmdbService $tmdb): JsonResponse
    {
        $movie = $this->fromTmdb(fn () => $tmdb->movie($tmdbMovieId));

        abort_if($movie->isEmpty(), 404);

        return response()->json([
            'data' => [
                ...$movie->all(),
                'poster_url' => $movie['poster_path']
                    ? config('services.tmdb.img_url').$movie['poster_path']
                    : null,
                'is_favourite' => in_array($tmdbMovieId, $this->favouriteIds($request)),
            ],
        ]);
    }

    /**
     * Run a TMDB call and turn a TMDB outage into a 503 instead of a stack trace.
     */
    private function fromTmdb(callable $call): Collection
    {
        try {
            return collect($call());
        } catch (ConnectionException|RequestException $e) {
            Log::error('TMDB request failed', ['message' => $e->getMessage()]);
            abort(503, 'Movies are temporarily unavailable. Please try again shortly.');
        }
    }

    /**
     * Cut a page of 9 from the full list. Pages outside the list return a 404.
     */
    private function paginate(Request $request, Collection $all): LengthAwarePaginator
    {
        $lastPage = max(1, (int) ceil($all->count() / self::PER_PAGE));
        $page = filter_var($request->query('page', 1), FILTER_VALIDATE_INT);

        if ($page === false || $page < 1 || $page > $lastPage) {
            abort(404);
        }

        return new LengthAwarePaginator(
            $all->forPage($page, self::PER_PAGE)->values(),
            $all->count(),
            self::PER_PAGE,
            $page,
            ['path' => $request->url()]
        );
    }

    /**
     * @return array<int, int>
     */
    private function favouriteIds(Request $request): array
    {
        return $request->user()
            ? $request->user()->favourites()->pluck('tmdb_movie_id')->all()
            : [];
    }
}
