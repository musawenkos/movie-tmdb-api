<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFavouriteRequest;
use App\Services\TmdbService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FavouriteController extends Controller
{
    public function index(Request $request)
    {
        $favourites = $request->user()->favourites()->latest()->paginate(9);

        return view('favourites.index', compact('favourites'));
    }

    public function store(StoreFavouriteRequest $request, TmdbService $tmdb)
    {
        $id = $request->integer('tmdb_movie_id');

        try {
            $movie = collect($tmdb->popularMovies())->firstWhere('tmdb_movie_id', $id);
        } catch (ConnectionException | RequestException $e) {
            Log::error('TMDB request failed', ['message' => $e->getMessage()]);

            return back()->withErrors(['tmdb_movie_id' => 'Movies are temporarily unavailable. Try again shortly.']);
        }

        if (! $movie) {
            return back()->withErrors(['tmdb_movie_id' => 'That movie is not in the list.']);
        }

        $favourite = $request->user()->favourites()->createOrFirst(
            ['tmdb_movie_id' => $id],
            [
                'title'        => $movie['title'],
                'poster_path'  => $movie['poster_path'],
                'release_date' => $movie['release_date'],
            ]
        );

        return back()->with('status', $favourite->wasRecentlyCreated
            ? 'Added to favourites.'
            : 'Already in your favourites.');
    }

    public function destroy(Request $request, int $tmdbMovieId)
    {
        $request->user()->favourites()
            ->where('tmdb_movie_id', $tmdbMovieId)
            ->delete();

        return back()->with('status', 'Removed from favourites.');
    }
}