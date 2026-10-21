<?php

namespace App\Http\Controllers;

use App\Services\TmdbService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class MovieController extends Controller
{
    private const PER_PAGE = 9;

    public function index(Request $request, TmdbService $tmdb)
    {
        try {
            $all = collect($tmdb->popularMovies());
        } catch (ConnectionException | RequestException $e) {
            Log::error('TMDB request failed', ['message' => $e->getMessage()]);
            abort(503, 'Movies are temporarily unavailable. Please try again shortly.');
        }

        $lastPage = max(1, (int) ceil($all->count() / self::PER_PAGE));
        $page = filter_var($request->query('page', 1), FILTER_VALIDATE_INT);

        if ($page === false || $page < 1 || $page > $lastPage) {
            abort(404);
        }

        $movies = new LengthAwarePaginator(
            $all->forPage($page, self::PER_PAGE)->values(),
            $all->count(),
            self::PER_PAGE,
            $page,
            ['path' => $request->url()]
        );

        $favouriteIds = $request->user()
            ? $request->user()->favourites()->pluck('tmdb_movie_id')->all()
            : [];

        return view('movies.index', compact('movies', 'favouriteIds'));
    }
}