<!DOCTYPE html>
<html html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Movies')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand bg-body-tertiary mb-4">
        <div class="container">
            <a class="navbar-brand" href="{{ route('movies.index') }}">Movies</a>

            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('contact') }}">Contact</a>
                </li>

                @auth
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('favourites.index') }}">Favourites</a>
                    </li>
                    <li class="nav-item">
                        <span class="navbar-text me-3">{{ auth()->user()->username }}</span>
                    </li>
                    <li class="nav-item">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary btn-sm">Log out</button>
                        </form>
                    </li>
                @else
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('login') }}">Log in</a>
                    </li>
                @endauth
            </ul>
        </div>
    </nav>

    <main class="container pb-5">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if ($errors->has('tmdb_movie_id'))
            <div class="alert alert-danger">{{ $errors->first('tmdb_movie_id') }}</div>
        @endif
        @yield('content')
    </main>
</body>
</html>