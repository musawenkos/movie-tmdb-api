<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MovieSearchTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function tmdbMovie(int $id, string $title, ?bool $adult = false): array
    {
        $movie = [
            'id' => $id,
            'title' => $title,
            'poster_path' => "/poster{$id}.jpg",
            'release_date' => '2024-05-01',
        ];

        if ($adult !== null) {
            $movie['adult'] = $adult;
        }

        return $movie;
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     */
    private function fakeTmdbList(array $results, int $totalPages = 1): void
    {
        Http::fake([
            'api.themoviedb.org/3/search/movie*' => Http::response([
                'page' => 1,
                'results' => $results,
                'total_pages' => $totalPages,
            ]),
        ]);
    }

    public function test_search_page_lists_matching_movies_and_hides_adult_ones(): void
    {
        $this->fakeTmdbList([
            $this->tmdbMovie(1, 'Alien'),
            $this->tmdbMovie(2, 'Aliens'),
            $this->tmdbMovie(3, 'Explicit title', adult: true),
            $this->tmdbMovie(4, 'Unflagged title', adult: null),
            $this->tmdbMovie(5, 'Sexual Alien', adult: false),
            $this->tmdbMovie(6, 'Erotic Alien Nights', adult: false),
            $this->tmdbMovie(7, 'Alien Sexton'),
        ]);

        $this->get('/search?q=alien')
            ->assertOk()
            ->assertSee('Alien')
            ->assertSee('Aliens')
            ->assertDontSee('Explicit title')
            ->assertDontSee('Unflagged title')
            ->assertDontSee('Sexual Alien')
            ->assertDontSee('Erotic Alien Nights')
            ->assertSee('Alien Sexton')
            ->assertSee('3 movies found');

        Http::assertSent(fn ($request) => $request['query'] === 'alien'
            && $request['include_adult'] === 'false'
            && $request->hasHeader('Authorization'));
    }

    public function test_search_results_are_paginated_nine_per_page(): void
    {
        $this->fakeTmdbList(collect(range(1, 12))
            ->map(fn ($i) => $this->tmdbMovie($i, "Star movie {$i}"))
            ->all());

        $this->get('/search?q=star')->assertOk()->assertSee('Star movie 9')->assertDontSee('Star movie 10');
        $this->get('/search?q=star&page=2')->assertOk()->assertSee('Star movie 10')->assertSee('q=star', false);
        $this->get('/search?q=star&page=3')->assertNotFound();
    }

    public function test_search_requires_at_least_two_characters(): void
    {
        Http::fake();

        $this->get('/search?q=a')->assertSessionHasErrors('q');
        $this->getJson('/search/autocomplete?q=')->assertUnprocessable();

        Http::assertNothingSent();
    }

    public function test_autocomplete_returns_a_short_json_list(): void
    {
        $this->fakeTmdbList(collect(range(1, 20))
            ->map(fn ($i) => $this->tmdbMovie($i, "Matrix {$i}"))
            ->all(), totalPages: 5);

        $this->getJson('/search/autocomplete?q=matrix')
            ->assertOk()
            ->assertJsonCount(8, 'data')
            ->assertJsonPath('data.0', ['id' => 1, 'title' => 'Matrix 1', 'year' => '2024']);

        Http::assertSentCount(1);
    }

    public function test_autocomplete_results_are_cached(): void
    {
        $this->fakeTmdbList([$this->tmdbMovie(1, 'Dune')]);

        $this->getJson('/search/autocomplete?q=Dune')->assertOk();
        $this->getJson('/search/autocomplete?q=%20%20dune%20')->assertOk();

        Http::assertSentCount(1);
    }

    public function test_movie_details_endpoint_returns_popup_data(): void
    {
        Http::fake([
            'api.themoviedb.org/3/movie/603*' => Http::response([
                'id' => 603,
                'title' => 'The Matrix',
                'adult' => false,
                'poster_path' => '/matrix.jpg',
                'release_date' => '1999-03-31',
                'overview' => 'A hacker learns the truth.',
                'tagline' => 'Welcome to the Real World.',
                'runtime' => 136,
                'vote_average' => 8.217,
                'vote_count' => 25000,
                'genres' => [['id' => 28, 'name' => 'Action'], ['id' => 878, 'name' => 'Science Fiction']],
            ]),
        ]);

        $this->getJson('/movies/603')
            ->assertOk()
            ->assertJsonPath('data.title', 'The Matrix')
            ->assertJsonPath('data.runtime', 136)
            ->assertJsonPath('data.vote_average', 8.2)
            ->assertJsonPath('data.genres', ['Action', 'Science Fiction'])
            ->assertJsonPath('data.poster_url', 'https://image.tmdb.org/t/p/w342/matrix.jpg')
            ->assertJsonPath('data.is_favourite', false);
    }

    public function test_movie_details_hides_adult_and_unknown_movies(): void
    {
        Http::fake([
            'api.themoviedb.org/3/movie/1*' => Http::response(['id' => 1, 'title' => 'Explicit', 'adult' => true]),
            'api.themoviedb.org/3/movie/2*' => Http::response(['status_message' => 'Not found'], 404),
        ]);

        $this->getJson('/movies/1')->assertNotFound();
        $this->getJson('/movies/2')->assertNotFound();
        $this->getJson('/movies/abc')->assertNotFound();
    }

    public function test_tmdb_outage_returns_503(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        $this->getJson('/search/autocomplete?q=dune')->assertServiceUnavailable();
        $this->getJson('/movies/5')->assertServiceUnavailable();
    }

    public function test_a_search_result_can_be_added_to_favourites(): void
    {
        $user = User::factory()->create();

        Http::fake([
            'api.themoviedb.org/3/movie/popular*' => Http::response(['results' => [], 'total_pages' => 1]),
            'api.themoviedb.org/3/movie/550*' => Http::response([
                'id' => 550,
                'title' => 'Fight Club',
                'adult' => false,
                'poster_path' => '/fc.jpg',
                'release_date' => '1999-10-15',
            ]),
        ]);

        $this->actingAs($user)
            ->post('/favourites', ['tmdb_movie_id' => 550])
            ->assertSessionHas('status', 'Added to favourites.');

        $this->assertDatabaseHas('favourites', [
            'user_id' => $user->id,
            'tmdb_movie_id' => 550,
            'title' => 'Fight Club',
        ]);

        $this->actingAs($user)->getJson('/movies/550')->assertJsonPath('data.is_favourite', true);
    }
}
