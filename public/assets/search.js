(function () {
    const normalize = (value) => value.toLowerCase().trim().replace(/[^a-z0-9]+/g, ' ').replace(/\s+/g, ' ');

    document.querySelectorAll('[data-search-form]').forEach((form) => {
        const input = form.querySelector('input[type="search"][name="q"]');
        const dropdown = form.querySelector('.search-panel__dropdown');
        const data = form.querySelector('[data-search-suggestions]');

        if (!input || !dropdown || !data) {
            return;
        }

        let suggestions = [];
        try {
            suggestions = JSON.parse(data.textContent || '[]');
        } catch (error) {
            suggestions = [];
        }

        const close = () => {
            dropdown.hidden = true;
            input.setAttribute('aria-expanded', 'false');
        };

        const submitSearch = () => {
            const url = new URL(form.action, window.location.href);
            url.search = new URLSearchParams(new FormData(form)).toString();
            window.location.href = url.toString();
        };

        const open = () => {
            dropdown.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        };

        const render = () => {
            const query = normalize(input.value);
            dropdown.innerHTML = '';
            const matches = query === ''
                ? suggestions.filter((suggestion) => suggestion.popular).slice(0, 6)
                : suggestions
                    .filter((suggestion) => normalize(suggestion.searchText || suggestion.name || '').includes(query))
                    .slice(0, 6);

            const header = document.createElement('span');
            header.className = 'search-panel__dropdown-header';
            header.textContent = query === '' ? 'Popular' : 'Results';
            dropdown.appendChild(header);

            if (matches.length === 0 && query !== '') {
                const empty = document.createElement('span');
                empty.className = 'search-panel__empty';
                empty.textContent = 'No matching results yet. Press Enter to search anyway.';
                dropdown.appendChild(empty);
            }

            matches.forEach((suggestion) => {
                const link = document.createElement('a');
                link.className = 'search-panel__result';
                link.href = suggestion.href;
                link.setAttribute('role', 'option');

                const name = document.createElement('strong');
                name.textContent = suggestion.name;
                link.appendChild(name);

                const meta = document.createElement('span');
                meta.textContent = [suggestion.type, suggestion.meta].filter(Boolean).join(' · ');
                link.appendChild(meta);

                dropdown.appendChild(link);
            });

            open();
        };

        input.addEventListener('focus', render);
        input.addEventListener('click', render);
        input.addEventListener('input', render);
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                close();
            }

            if (event.key === 'Enter') {
                event.preventDefault();
                submitSearch();
            }
        });

        document.addEventListener('pointerdown', (event) => {
            if (!form.contains(event.target)) {
                close();
            }
        });
    });
}());
