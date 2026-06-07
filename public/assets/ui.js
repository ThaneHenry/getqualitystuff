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

    const authDialog = document.querySelector('[data-auth-dialog]');

    document.querySelectorAll('[data-auth-open]').forEach((link) => {
        link.addEventListener('click', (event) => {
            if (!authDialog) {
                return;
            }
            event.preventDefault();
            authDialog.showModal();
            authDialog.querySelector('input[type="email"]')?.focus();
        });
    });

    document.querySelectorAll('[data-auth-close]').forEach((button) => {
        button.addEventListener('click', () => authDialog?.close());
    });

    authDialog?.addEventListener('click', (event) => {
        if (event.target === authDialog) {
            authDialog.close();
        }
    });

    document.querySelectorAll('.featured-panel__image img, .listing-card__image img, .account-entry__image img').forEach((image) => {
        const fallback = image.parentElement?.querySelector(':scope > span');
        const showFallback = () => {
            fallback?.removeAttribute('hidden');
            image.remove();
        };
        const setThumbnailFit = () => {
            const ratio = image.naturalWidth / image.naturalHeight;
            fallback?.setAttribute('hidden', '');
            image.classList.toggle('is-letterboxed', ratio > 1.6 || ratio < 0.625);
        };

        image.addEventListener('error', showFallback, { once: true });
        image.addEventListener('load', setThumbnailFit, { once: true });
        if (image.complete) {
            if (image.naturalWidth === 0) {
                showFallback();
            } else {
                setThumbnailFit();
            }
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
