<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AgletUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MoviesAndFavouritesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Three TMDB pages of 20 movies: ids 1-60.
        Http::fake(function ($request) {
            $page = (int) $request['page'];

            return Http::response([
                'page' => $page,
                'total_pages' => 500,
                'results' => collect(range(($page - 1) * 20 + 1, $page * 20))
                    ->map(fn ($id) => [
                        'id' => $id,
                        'title' => "Popular movie {$id}",
                        'adult' => false,
                        'poster_path' => "/p{$id}.jpg",
                        'release_date' => '2025-01-01',
                    ])->all(),
            ]);
        });
    }

    public function test_home_page_shows_nine_of_forty_five_popular_movies(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Popular movie 1')
            ->assertSee('Popular movie 9')
            ->assertDontSee('Popular movie 10');

        $this->get('/?page=5')->assertOk()->assertSee('Popular movie 45')->assertDontSee('Popular movie 46');
        $this->get('/?page=6')->assertNotFound();
        $this->get('/?page=abc')->assertNotFound();

        Http::assertSentCount(3);
    }

    public function test_default_user_can_log_in_with_username_or_email(): void
    {
        $this->seed(AgletUserSeeder::class);

        $this->post('/login', ['login' => 'jointheteam', 'password' => '@TeamAglet'])->assertRedirect('/');
        $this->assertAuthenticated();

        $this->post('/logout');
        $this->assertGuest();

        $this->post('/login', ['login' => 'jointheteam@aglet.co.za', 'password' => '@TeamAglet'])->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->seed(AgletUserSeeder::class);

        $this->post('/login', ['login' => 'jointheteam', 'password' => 'wrong'])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_favourites_page_requires_login(): void
    {
        $this->get('/favourites')->assertRedirect('/login');
        $this->post('/favourites', ['tmdb_movie_id' => 1])->assertRedirect('/login');
    }

    public function test_user_can_add_list_and_remove_a_favourite_only_once(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/favourites', ['tmdb_movie_id' => 3])
            ->assertSessionHas('status', 'Added to favourites.');
        $this->actingAs($user)->post('/favourites', ['tmdb_movie_id' => 3])
            ->assertSessionHas('status', 'Already in your favourites.');

        $this->assertDatabaseCount('favourites', 1);
        $this->actingAs($user)->get('/favourites')->assertOk()->assertSee('Popular movie 3');

        $this->actingAs($user)->delete('/favourites/3');
        $this->assertDatabaseCount('favourites', 0);
    }

    public function test_users_only_see_and_remove_their_own_favourites(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($owner)->post('/favourites', ['tmdb_movie_id' => 7]);

        $this->actingAs($other)->get('/favourites')->assertOk()->assertDontSee('Popular movie 7');
        $this->actingAs($other)->delete('/favourites/7');

        $this->assertDatabaseHas('favourites', ['user_id' => $owner->id, 'tmdb_movie_id' => 7]);
    }

    public function test_invalid_favourite_ids_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/favourites', ['tmdb_movie_id' => 'abc'])->assertSessionHasErrors('tmdb_movie_id');
        $this->actingAs($user)->post('/favourites', ['tmdb_movie_id' => 0])->assertSessionHasErrors('tmdb_movie_id');

        $this->assertDatabaseCount('favourites', 0);
    }

    public function test_contact_page_is_linked_and_shows_details(): void
    {
        $this->get('/')->assertSee(route('contact'));
        $this->get('/contact')->assertOk()->assertSee('Musawenkosi Ndlela')->assertSee('github.com/musawenkos');
    }
}
