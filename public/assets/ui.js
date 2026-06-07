(function () {
    const searchDialog = document.querySelector('[data-global-search-dialog]');
    const primarySearch = document.querySelector('[data-primary-search]');
    let primarySearchHighlightTimer;

    const focusPrimarySearch = () => {
        if (!primarySearch) {
            return false;
        }

        primarySearch.scrollIntoView({
            behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
            block: 'center',
        });
        primarySearch.focus({ preventScroll: true });
        primarySearch.dispatchEvent(new Event('input', { bubbles: true }));
        primarySearch.classList.remove('is-nav-search-highlighted');
        window.clearTimeout(primarySearchHighlightTimer);
        void primarySearch.offsetWidth;
        primarySearch.classList.add('is-nav-search-highlighted');
        primarySearchHighlightTimer = window.setTimeout(
            () => primarySearch.classList.remove('is-nav-search-highlighted'),
            1600
        );
        return true;
    };

    const openSearch = () => {
        if (focusPrimarySearch()) {
            return;
        }

        if (!searchDialog) {
            return;
        }
        searchDialog.showModal();
        const input = searchDialog.querySelector('input[type="search"]');
        input?.focus();
        input?.dispatchEvent(new Event('input', { bubbles: true }));
    };

    document.querySelectorAll('[data-global-search-open]').forEach((button) => {
        button.addEventListener('click', openSearch);
    });

    document.querySelectorAll('[data-global-search-close]').forEach((button) => {
        button.addEventListener('click', () => searchDialog?.close());
    });

    searchDialog?.addEventListener('click', (event) => {
        if (event.target === searchDialog) {
            searchDialog.close();
        }
    });

    document.addEventListener('keydown', (event) => {
        if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            openSearch();
        }
    });

    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.getAttribute('aria-controls'));
            if (!input) {
                return;
            }

            const visible = input.type === 'text';
            input.type = visible ? 'password' : 'text';
            button.textContent = visible ? 'Show' : 'Hide';
            button.setAttribute('aria-pressed', visible ? 'false' : 'true');
        });
    });

    document.querySelectorAll('[data-filter-dialog]').forEach((dialog) => {
        const form = dialog.closest('form');
        form?.querySelector('[data-filter-open]')?.addEventListener('click', () => {
            if (window.matchMedia('(max-width: 680px)').matches) {
                dialog.showModal();
                return;
            }
            dialog.open ? dialog.close() : dialog.show();
        });
        dialog.querySelector('[data-filter-close]')?.addEventListener('click', () => dialog.close());
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
                dialog.close();
            }
        });
    });
}());
