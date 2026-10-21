<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Movies')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/movies.css') }}" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg bg-body-tertiary mb-4">
        <div class="container">
            <a class="navbar-brand" href="{{ route('movies.index') }}">Movies</a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#main-nav"
                    aria-controls="main-nav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="main-nav">
                <form class="movie-search position-relative my-2 my-lg-0 mx-lg-4 flex-grow-1" role="search"
                      method="GET" action="{{ route('movies.search') }}"
                      data-autocomplete-url="{{ route('movies.autocomplete') }}">
                    <input class="form-control" type="search" name="q" placeholder="Search movies…"
                           value="{{ request()->routeIs('movies.search') ? request('q') : '' }}"
                           aria-label="Search movies" autocomplete="off" minlength="2" maxlength="100" required
                           role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="search-suggestions">
                    <ul id="search-suggestions" class="dropdown-menu w-100 mt-1" role="listbox"></ul>
                </form>

                <ul class="navbar-nav ms-auto align-items-lg-center">
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
        </div>
    </nav>

    <main class="container pb-5">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if ($errors->has('tmdb_movie_id'))
            <div class="alert alert-danger">{{ $errors->first('tmdb_movie_id') }}</div>
        @endif
        @if ($errors->has('q'))
            <div class="alert alert-warning">{{ $errors->first('q') }}</div>
        @endif
        @yield('content')
    </main>

    {{-- Movie details popup, filled in by public/js/movies.js --}}
    <div class="modal fade" id="movie-modal" tabindex="-1" aria-labelledby="movie-modal-title" aria-hidden="true"
         data-details-url="{{ url('movies') }}"
         data-favourites-url="{{ auth()->check() ? route('favourites.store') : '' }}"
         data-login-url="{{ route('login') }}">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="movie-modal-title">Movie details</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="movie-modal-loading text-center py-5">
                        <div class="spinner-border" role="status"><span class="visually-hidden">Loading…</span></div>
                    </div>
                    <p class="movie-modal-error text-danger d-none mb-0"></p>
                    <div class="movie-modal-content row g-4 d-none">
                        <div class="col-md-4">
                            <img class="movie-modal-poster img-fluid rounded-3" alt="">
                            <div class="movie-modal-no-poster bg-secondary rounded-3 d-flex align-items-center justify-content-center d-none"
                                 style="height: 300px">No poster</div>
                        </div>
                        <div class="col-md-8">
                            <p class="movie-modal-tagline fst-italic text-muted"></p>
                            <p class="movie-modal-meta small text-muted"></p>
                            <div class="movie-modal-genres mb-3"></div>
                            <p class="movie-modal-overview"></p>
                            <div class="movie-modal-actions mt-3"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/movies.js') }}"></script>
</body>
</html>
