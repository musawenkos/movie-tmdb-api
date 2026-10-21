<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class TmdbService
{
    private const CACHE_KEY = 'movies:popular:45';

    private const TOTAL = 45;

    private const TMDB_PAGES = 3;

    private const AUTOCOMPLETE_LIMIT = 8;

    private const SEARCH_CACHE_TTL = 600;

    /**
     * TMDB's adult flag misses a lot of softcore titles, and search makes them easy to reach,
     * so titles containing any of these whole words are dropped as well.
     */
    private const BLOCKED_TITLE_WORDS = [
        'sex', 'sexual', 'sexy', 'erotic', 'erotica', 'erotique', 'porn', 'porno', 'pornographic',
        'xxx', 'nude', 'nudes', 'nudist', 'softcore', 'hentai', 'playboy', 'orgy', 'fetish', 'kinky',
    ];

    /**
     * The 45 most popular movies, cached.
     *
     * @return array<int, array{tmdb_movie_id: int, title: string, poster_path: ?string, release_date: ?string}>
     */
    public function popularMovies(): array
    {
        return Cache::remember(
            self::CACHE_KEY,
            config('services.tmdb.cache_ttl'),
            fn () => $this->fetchList('movie/popular')
        );
    }

    /**
     * Up to 45 movies matching the query, cached per query.
     *
     * @return array<int, array{tmdb_movie_id: int, title: string, poster_path: ?string, release_date: ?string}>
     */
    public function search(string $query): array
    {
        $query = $this->normaliseQuery($query);

        return Cache::remember(
            'movies:search:'.md5($query),
            self::SEARCH_CACHE_TTL,
            fn () => $this->fetchList('search/movie', ['query' => $query])
        );
    }

    /**
     * A short list of titles for the search box. Uses one TMDB page only, so typing stays cheap.
     *
     * @return array<int, array{tmdb_movie_id: int, title: string, poster_path: ?string, release_date: ?string}>
     */
    public function autocomplete(string $query): array
    {
        $query = $this->normaliseQuery($query);

        return Cache::remember(
            'movies:autocomplete:'.md5($query),
            self::SEARCH_CACHE_TTL,
            fn () => array_slice(
                $this->fetchList('search/movie', ['query' => $query], pages: 1),
                0,
                self::AUTOCOMPLETE_LIMIT
            )
        );
    }

    /**
     * Full details for one movie, or null if TMDB doesn't know it or it is adult content.
     *
     * @return array{tmdb_movie_id: int, title: string, poster_path: ?string, release_date: ?string, overview: ?string, tagline: ?string, runtime: ?int, vote_average: ?float, genres: array<int, string>}|null
     */
    public function movie(int $id): ?array
    {
        return Cache::remember(
            'movies:details:'.$id,
            config('services.tmdb.cache_ttl'),
            function () use ($id) {
                try {
                    $movie = $this->request("movie/{$id}");
                } catch (RequestException $e) {
                    if ($e->response->notFound()) {
                        return null;
                    }

                    throw $e;
                }

                if (! $this->isSafe($movie)) {
                    return null;
                }

                return $this->normalise($movie) + [
                    'overview' => ($movie['overview'] ?? '') !== '' ? $movie['overview'] : null,
                    'tagline' => ($movie['tagline'] ?? '') !== '' ? $movie['tagline'] : null,
                    'runtime' => ($movie['runtime'] ?? 0) > 0 ? $movie['runtime'] : null,
                    'vote_average' => ($movie['vote_count'] ?? 0) > 0 ? round($movie['vote_average'], 1) : null,
                    'genres' => collect($movie['genres'] ?? [])->pluck('name')->all(),
                ];
            }
        );
    }

    /**
     * Fetch a paged TMDB list, drop adult titles and keep the first 45.
     *
     * @param  array<string, mixed>  $params
     * @return array<int, array{tmdb_movie_id: int, title: string, poster_path: ?string, release_date: ?string}>
     */
    private function fetchList(string $path, array $params = [], int $pages = self::TMDB_PAGES): array
    {
        $results = collect();

        for ($page = 1; $page <= $pages; $page++) {
            $response = $this->request($path, $params + ['page' => $page]);

            $results = $results->merge($response['results'] ?? []);

            if ($page >= ($response['total_pages'] ?? 1)) {
                break;
            }
        }

        return $results
            ->filter(fn ($movie) => $this->isSafe($movie))
            ->unique('id')
            ->take(self::TOTAL)
            ->map(fn ($movie) => $this->normalise($movie))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function request(string $path, array $params = []): array
    {
        return Http::withToken(config('services.tmdb.token'))
            ->baseUrl(config('services.tmdb.base_url'))
            ->acceptJson()
            ->timeout(10)
            ->retry(2, 200, fn ($e) => ! $e instanceof RequestException || $e->response->serverError())
            ->get($path, $params + ['include_adult' => 'false'])
            ->throw()
            ->json();
    }

    /**
     * Only movies TMDB explicitly marks as not adult, and whose title has no blocked word, are shown.
     *
     * @param  array<string, mixed>  $movie
     */
    private function isSafe(array $movie): bool
    {
        if (($movie['adult'] ?? true) !== false) {
            return false;
        }

        $pattern = '/\b('.implode('|', self::BLOCKED_TITLE_WORDS).')\b/iu';

        return ! preg_match($pattern, ($movie['title'] ?? '').' '.($movie['original_title'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $movie
     * @return array{tmdb_movie_id: int, title: string, poster_path: ?string, release_date: ?string}
     */
    private function normalise(array $movie): array
    {
        return [
            'tmdb_movie_id' => $movie['id'],
            'title' => $movie['title'],
            'poster_path' => $movie['poster_path'] ?? null,
            'release_date' => ($movie['release_date'] ?? '') !== '' ? $movie['release_date'] : null,
        ];
    }

    private function normaliseQuery(string $query): string
    {
        return mb_strtolower(preg_replace('/\s+/', ' ', trim($query)));
    }
}
