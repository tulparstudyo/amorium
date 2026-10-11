document.addEventListener('DOMContentLoaded', () => {
    const navbar = document.getElementById('navbar');
    const hasHero = document.body.classList.contains('has-hero');

    const syncNavbar = () => {
        if (!navbar) {
            return;
        }

        const scrolled = document.body.scrollTop >= 50 || document.documentElement.scrollTop >= 50;

        if (scrolled || !hasHero) {
            navbar.classList.add('is-sticky');
        } else {
            navbar.classList.remove('is-sticky');
        }
    };

    const backToTop = document.getElementById('back-to-top');

    const syncBackToTop = () => {
        if (!backToTop) {
            return;
        }

        const visible = document.body.scrollTop > 500 || document.documentElement.scrollTop > 500;
        backToTop.classList.toggle('hidden', !visible);
        backToTop.classList.toggle('inline-flex', visible);
    };

    syncNavbar();
    syncBackToTop();

    window.addEventListener('scroll', () => {
        syncNavbar();
        syncBackToTop();
    }, { passive: true });

    document.querySelectorAll('[data-collapse]').forEach((toggle) => {
        const collapseId = toggle.getAttribute('data-collapse');
        const collapseEl = document.getElementById(collapseId);

        if (!collapseEl) {
            return;
        }

        toggle.addEventListener('click', () => {
            const willOpen = collapseEl.classList.contains('hidden');
            collapseEl.classList.toggle('hidden', !willOpen);
            toggle.setAttribute('aria-expanded', String(willOpen));
        });
    });

    document.querySelectorAll('[data-dropdown]').forEach((dropdown) => {
        const toggle = dropdown.querySelector('[data-dropdown-toggle]');
        const menu = dropdown.querySelector('[data-dropdown-menu]');

        if (!toggle || !menu) {
            return;
        }

        const setOpen = (open) => {
            menu.classList.toggle('hidden', !open);
            toggle.setAttribute('aria-expanded', String(open));
        };

        toggle.addEventListener('click', () => setOpen(menu.classList.contains('hidden')));

        document.addEventListener('click', (event) => {
            if (!dropdown.contains(event.target)) {
                setOpen(false);
            }
        });

        dropdown.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                setOpen(false);
                toggle.focus();
            }
        });
    });

    if (backToTop) {
        backToTop.addEventListener('click', (event) => {
            event.preventDefault();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }
});
