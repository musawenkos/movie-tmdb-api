# Movie list: Aglet backend assessment

A Laravel app that shows popular movies from The Movie Database (TMDB), lets a
logged-in user keep a list of favourites, and has a contact page. This is my
submission for Brief 1.

## What it does

- **Movie list:** 45 popular movies, 9 per page, over 5 pages. Each card shows
  the poster, title, release date and a favourite button.
- **Login and logout:** with the default test user from the brief.
- **Favourites:** a logged-in user can add and remove favourites, and has a page
  that lists only their own.
- **Contact page:** my name, contact details and social links.

## What you need

- PHP [[8.3]] and Composer
- Docker, only to run MySQL (any MySQL 8 server will work instead)
- A free TMDB account, for an API token

## Setting it up

1. Clone the repo and install the packages:

```
   git clone [[repo url]]
   cd movie-tmdb-api
   composer install
```

2. Copy the environment file and generate the app key:

```
   cp .env.example .env
   php artisan key:generate
```

3. Start MySQL. I run it in a container:

```
   docker run -d --name aglet_mysql \
     -e MYSQL_DATABASE=aglet_movies \
     -e MYSQL_USER=aglet \
     -e MYSQL_PASSWORD=your-local-password \
     -e MYSQL_ROOT_PASSWORD=your-root-password \
     -p 3306:3306 \
     -v aglet_mysql_data:/var/lib/mysql \
     mysql:8.0
```

   Give it about 30 seconds on the first start. The values must match
   `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD` in `.env`. If port 3306 is
   already in use on your machine, change the left side of `-p` and set
   `DB_PORT` to match.

4. Get a TMDB token. In your TMDB account settings, under API, copy the
   **API Read Access Token** (the long one) and put it in `.env`:

```
   TMDB_TOKEN=your-token-here
```

5. Create the tables and the default user:

```
   php artisan migrate --seed
```

   Or, if you would rather use the database dump, create an empty database and
   import `database/[[aglet_movies.sql]]`:

```
   docker exec -i aglet_mysql mysql -u aglet -p aglet_movies < database/[[aglet_movies.sql]]
```

6. Start the app and open http://127.0.0.1:8000

```
   php artisan serve
```

Cache: the app uses the `[[database]]` cache driver, so you don't need Redis.

Login from the brief: username `jointheteam`, email `jointheteam@aglet.co.za`,
password `@TeamAglet`. You can log in with either the username or the email.

## Why I built it this way

**Laravel and PHP.** PHP is on your list of allowed languages, and Laravel is
what the role uses. I built the app the way a Laravel project is normally
built: routes, controllers, a service class for the TMDB calls, form requests
for validation, and Blade views.

**MySQL.** It is in the allowed list and it is the database the role works with.

**Server-rendered pages.** I used Blade pages and normal session login, with no
separate front end. The brief says the backend role is judged on layout and
thinking, not front-end skill, so I spent the time on validation, security and
data. The styling is Bootstrap from a CDN, so there is no build step to run.

**Caching the list.** TMDB returns 20 movies per page, and the brief wants 9 per
page and 45 in total. So the app fetches the first three TMDB pages (60 movies),
removes adult titles, keeps the first 45, and caches that one list. My pages of 9
are cut from the cached list, so they don't depend on how TMDB paginates. The
cache time is a setting (`TMDB_CACHE_TTL`, three hours by default). The popular
list changes slowly, and a long cache keeps the page fast and the number of
TMDB requests low.

**Adult content.** The brief says no explicit content. The app drops any movie
that isn't explicitly marked as not adult. I don't rely on a TMDB request
parameter for that, because the popular endpoint doesn't document one.

**The TMDB token.** I use the read access token, sent as a header, and not the
API key in the URL, so it can't end up in URLs or logs. It lives in `.env`,
which is not committed, and the app reads it through `config/services.php`.

**Favourites keep a copy of the movie.** The favourites table stores the TMDB
movie id plus the title, poster path and release date. If it stored only the id,
every visit to the favourites page would need one TMDB request per favourite,
which is slow and would hit TMDB's rate limit. With the copy, the page is one
database query. The cost is that a saved title or poster can go out of date if
TMDB changes it. For a favourites list I decided that is fine.

**The browser only sends a movie id.** When someone adds a favourite, the form
sends just the id. The server finds the title, poster and date in its own cached
list. That way a user can't save made-up data, and an id that isn't in the list
is rejected.

**A movie can't be favourited twice.** There is a unique constraint on the user
and the movie id in the database, so the rule holds even if the app code has a
bug or two requests arrive together. The user sees "Already in your favourites".
Deleting a user deletes their favourites.

**Users only touch their own favourites.** Every favourites query goes through
the logged-in user, and the user id is never taken from the request. Removing a
favourite that isn't yours does nothing.

**Login.** One field takes a username or an email. A wrong username and a wrong
password give the same message, so nobody can use the form to find out which
usernames exist. Login is limited to 5 attempts a minute. The session id is
regenerated after login, and logout is a POST that invalidates the session.
Forms use CSRF tokens, and movie data is escaped in the views.

**Rate limits.** The favourite actions are limited to 30 requests a minute per
user. This is separate from the cache: the cache protects TMDB, and the limits
protect my own endpoints.

**Bad input.** A page number that isn't a whole number between 1 and 5 returns a
404, and the favourite form rejects ids that aren't whole numbers.

## Trade-offs and limits

- The movie list can be up to three hours out of date.
- The first visit after the cache expires is slower, because it makes three
  requests to TMDB.
- If TMDB is down and the cache is empty, people see an error page instead of
  wrong data. The failure is logged and is never cached.
- Saved favourites can go stale, as described above.
- Only the popular list is shown. There is no search or filtering.
- Adding or removing a favourite reloads the page.
- I have not written automated tests. Yet

## If I had more time

- Add and remove favourites without reloading the page, with a JSON response
  from the same controller actions.
- Write feature tests for login, the favourite rules and the page limits.
