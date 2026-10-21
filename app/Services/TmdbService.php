<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class TmdbService
{
    private const CACHE_KEY = 'movies:popular:45';
    private const TOTAL = 45;
    private const TMDB_PAGES = 3; 

    public function popularMovies(): array
    {
        return Cache::remember(
            self::CACHE_KEY,
            config('services.tmdb.cache_ttl'),
            fn () => $this->fetchPopular()
        );
    }

    private function fetchPopular(): array
    {
        $results = collect();

        for ($page = 1; $page <= self::TMDB_PAGES; $page++) {
            $response = Http::withToken(config('services.tmdb.token'))
                ->baseUrl(config('services.tmdb.base_url'))
                ->acceptJson()
                ->timeout(10)
                ->retry(2, 200)
                ->get('movie/popular', [
                    'page' => $page,
                    'include_adult' => false,
                ])
                ->throw();

            $results = $results->merge($response->json('results', []));
        }

        $movies = $results
                        ->reject(fn ($movie) => $movie['adult'] ?? false)
                        ->take(45)
                        ->map(fn ($movie) => [
                        'tmdb_movie_id' => $movie['id'],
                        'title' => $movie['title'],
                        'poster_path' => $movie['poster_path'] ?? null,
                        'release_date' => ($movie['release_date'] ?? '') !== ''
                            ? $movie['release_date'] : null,
                        ])
                        ->values()
                        ->all();
        return $movies;
    }
}