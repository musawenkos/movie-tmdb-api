// Search autocomplete and the movie details popup. Every value from the server is
// inserted with textContent, never innerHTML, so movie data can't inject markup.
(function () {
    'use strict';

    const modalEl = document.getElementById('movie-modal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    function el(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined && text !== null) node.textContent = text;
        return node;
    }

    // ---- Details popup -------------------------------------------------------

    let detailsRequest = null;

    function showPart(name) {
        ['loading', 'error', 'content'].forEach((part) => {
            modalEl.querySelector('.movie-modal-' + part).classList.toggle('d-none', part !== name);
        });
    }

    function favouriteAction(movie) {
        const favouritesUrl = modalEl.dataset.favouritesUrl;

        if (!favouritesUrl) {
            const link = el('a', 'btn btn-outline-secondary', 'Log in to favourite');
            link.href = modalEl.dataset.loginUrl;
            return link;
        }

        const form = el('form');
        form.method = 'POST';

        const token = el('input');
        token.type = 'hidden';
        token.name = '_token';
        token.value = csrfToken;
        form.append(token);

        if (movie.is_favourite) {
            form.action = favouritesUrl + '/' + encodeURIComponent(movie.tmdb_movie_id);
            const method = el('input');
            method.type = 'hidden';
            method.name = '_method';
            method.value = 'DELETE';
            form.append(method, el('button', 'btn btn-outline-danger', '♥ Remove from favourites'));
        } else {
            form.action = favouritesUrl;
            const id = el('input');
            id.type = 'hidden';
            id.name = 'tmdb_movie_id';
            id.value = movie.tmdb_movie_id;
            form.append(id, el('button', 'btn btn-outline-light', '♡ Add to favourites'));
        }

        form.querySelector('button').type = 'submit';
        return form;
    }

    function renderMovie(movie) {
        const year = movie.release_date ? ' (' + movie.release_date.slice(0, 4) + ')' : '';
        modalEl.querySelector('.modal-title').textContent = movie.title + year;

        const poster = modalEl.querySelector('.movie-modal-poster');
        const noPoster = modalEl.querySelector('.movie-modal-no-poster');
        poster.classList.toggle('d-none', !movie.poster_url);
        noPoster.classList.toggle('d-none', !!movie.poster_url);
        if (movie.poster_url) {
            poster.src = movie.poster_url;
            poster.alt = 'Poster for ' + movie.title;
        } else {
            poster.removeAttribute('src');
        }

        modalEl.querySelector('.movie-modal-tagline').textContent = movie.tagline || '';

        const meta = [
            movie.release_date ? 'Released ' + movie.release_date : 'Release date unknown',
            movie.runtime ? movie.runtime + ' min' : null,
            movie.vote_average !== null ? '★ ' + movie.vote_average + ' / 10' : null,
        ].filter(Boolean);
        modalEl.querySelector('.movie-modal-meta').textContent = meta.join(' · ');

        const genres = modalEl.querySelector('.movie-modal-genres');
        genres.replaceChildren(...movie.genres.map((name) => el('span', 'badge text-bg-secondary me-1', name)));

        modalEl.querySelector('.movie-modal-overview').textContent = movie.overview || 'No description available.';
        modalEl.querySelector('.movie-modal-actions').replaceChildren(favouriteAction(movie));

        showPart('content');
    }

    function openMovie(id) {
        if (detailsRequest) detailsRequest.abort();
        detailsRequest = new AbortController();

        modalEl.querySelector('.modal-title').textContent = 'Movie details';
        showPart('loading');
        modal.show();

        fetch(modalEl.dataset.detailsUrl + '/' + encodeURIComponent(id), {
            headers: { Accept: 'application/json' },
            signal: detailsRequest.signal,
        })
            .then((response) => {
                if (response.status === 404) throw new Error('We couldn’t find that movie.');
                if (response.status === 429) throw new Error('Too many requests. Wait a moment and try again.');
                if (!response.ok) throw new Error('Movie details are unavailable right now. Try again shortly.');
                return response.json();
            })
            .then((body) => renderMovie(body.data))
            .catch((error) => {
                if (error.name === 'AbortError') return;
                const message = modalEl.querySelector('.movie-modal-error');
                message.textContent = error.message;
                showPart('error');
            });
    }

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-movie-id]');
        if (trigger) openMovie(trigger.dataset.movieId);
    });

    // ---- Search autocomplete ------------------------------------------------

    const form = document.querySelector('.movie-search');
    if (!form) return;

    const input = form.querySelector('input[name="q"]');
    const list = form.querySelector('.dropdown-menu');
    let suggestions = [];
    let activeIndex = -1;
    let debounce = null;
    let searchRequest = null;

    function setOpen(open) {
        list.classList.toggle('show', open);
        input.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    function setActive(index) {
        activeIndex = index;
        list.querySelectorAll('.dropdown-item').forEach((item, i) => {
            item.classList.toggle('active', i === index);
            item.setAttribute('aria-selected', i === index ? 'true' : 'false');
        });
    }

    function renderSuggestions(movies, query) {
        suggestions = movies;
        activeIndex = -1;

        if (movies.length === 0) {
            list.replaceChildren(el('li', 'dropdown-item-text text-muted small', 'No matches for “' + query + '”'));
            setOpen(true);
            return;
        }

        const items = movies.map((movie, i) => {
            const li = el('li');
            const button = el('button', 'dropdown-item d-flex justify-content-between gap-3');
            button.type = 'button';
            button.setAttribute('role', 'option');
            button.append(el('span', 'text-truncate', movie.title), el('span', 'text-muted small', movie.year || ''));
            button.addEventListener('mousedown', (event) => event.preventDefault());
            button.addEventListener('click', () => choose(i));
            li.append(button);
            return li;
        });

        const all = el('li');
        const allButton = el('button', 'dropdown-item small text-info', 'See all results for “' + query + '”');
        allButton.type = 'button';
        allButton.addEventListener('mousedown', (event) => event.preventDefault());
        allButton.addEventListener('click', () => form.submit());
        all.append(allButton);

        const divider = el('li');
        divider.append(el('hr', 'dropdown-divider'));

        list.replaceChildren(...items, divider, all);
        setOpen(true);
    }

    function choose(index) {
        const movie = suggestions[index];
        if (!movie) return;
        setOpen(false);
        input.value = movie.title;
        openMovie(movie.id);
    }

    function fetchSuggestions() {
        const query = input.value.trim();

        if (query.length < 2) {
            if (searchRequest) searchRequest.abort();
            setOpen(false);
            return;
        }

        if (searchRequest) searchRequest.abort();
        searchRequest = new AbortController();

        fetch(form.dataset.autocompleteUrl + '?q=' + encodeURIComponent(query), {
            headers: { Accept: 'application/json' },
            signal: searchRequest.signal,
        })
            .then((response) => {
                if (!response.ok) throw new Error('failed');
                return response.json();
            })
            .then((body) => renderSuggestions(body.data, query))
            .catch((error) => {
                // The search box still works without suggestions, so failures stay quiet.
                if (error.name !== 'AbortError') setOpen(false);
            });
    }

    input.addEventListener('input', () => {
        clearTimeout(debounce);
        debounce = setTimeout(fetchSuggestions, 250);
    });

    input.addEventListener('keydown', (event) => {
        const open = list.classList.contains('show');

        if (event.key === 'ArrowDown' && open && suggestions.length) {
            event.preventDefault();
            setActive((activeIndex + 1) % suggestions.length);
        } else if (event.key === 'ArrowUp' && open && suggestions.length) {
            event.preventDefault();
            setActive(activeIndex <= 0 ? suggestions.length - 1 : activeIndex - 1);
        } else if (event.key === 'Enter' && open && activeIndex >= 0) {
            event.preventDefault();
            choose(activeIndex);
        } else if (event.key === 'Escape') {
            setOpen(false);
        }
    });

    input.addEventListener('blur', () => setOpen(false));
    input.addEventListener('focus', () => {
        if (suggestions.length && input.value.trim().length >= 2) setOpen(true);
    });
})();
