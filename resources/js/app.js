const menuToggle = document.querySelector('.menu-toggle');
const mobileMenu = document.querySelector('#mobile-menu');
document.documentElement.classList.add('js-enabled');

if (menuToggle instanceof HTMLButtonElement && mobileMenu instanceof HTMLElement) {
    menuToggle.addEventListener('click', () => {
        const isExpanded = menuToggle.getAttribute('aria-expanded') === 'true';

        menuToggle.setAttribute('aria-expanded', String(!isExpanded));
        mobileMenu.hidden = isExpanded;
    });

    mobileMenu.addEventListener('click', (event) => {
        if (event.target instanceof HTMLAnchorElement) {
            mobileMenu.hidden = true;
            menuToggle.setAttribute('aria-expanded', 'false');
        }
    });
}

const adminSidebar = document.querySelector('#admin-sidebar');
const adminSidebarToggle = document.querySelector('.admin-sidebar-toggle');
const adminSidebarBackdrop = document.querySelector('.admin-sidebar-backdrop');
const adminMobileQuery = window.matchMedia('(max-width: 900px)');

if (
    adminSidebar instanceof HTMLElement
    && adminSidebarToggle instanceof HTMLButtonElement
    && adminSidebarBackdrop instanceof HTMLButtonElement
) {
    const syncAdminSidebar = () => {
        if (!adminMobileQuery.matches) {
            adminSidebar.classList.remove('is-open');
            adminSidebar.removeAttribute('inert');
            adminSidebarBackdrop.hidden = true;
            adminSidebarToggle.setAttribute('aria-expanded', 'false');
            return;
        }

        if (!adminSidebar.classList.contains('is-open')) {
            adminSidebar.setAttribute('inert', '');
        }
    };

    const closeAdminSidebar = () => {
        adminSidebar.classList.remove('is-open');
        adminSidebarToggle.setAttribute('aria-expanded', 'false');
        adminSidebar.setAttribute('inert', '');
        adminSidebarBackdrop.hidden = true;
    };

    syncAdminSidebar();
    adminMobileQuery.addEventListener('change', syncAdminSidebar);

    adminSidebarToggle.addEventListener('click', () => {
        const isExpanded = adminSidebarToggle.getAttribute('aria-expanded') === 'true';

        adminSidebarToggle.setAttribute('aria-expanded', String(!isExpanded));
        adminSidebar.classList.toggle('is-open', !isExpanded);
        adminSidebar.toggleAttribute('inert', isExpanded);
        adminSidebarBackdrop.hidden = isExpanded;
    });

    adminSidebarBackdrop.addEventListener('click', closeAdminSidebar);
    adminSidebar.addEventListener('click', (event) => {
        if (event.target instanceof HTMLAnchorElement) {
            closeAdminSidebar();
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && adminSidebar.classList.contains('is-open')) {
            closeAdminSidebar();
            adminSidebarToggle.focus();
        }
    });
}

document.querySelectorAll('[data-project-gallery]').forEach((gallery) => {
    if (!(gallery instanceof HTMLElement)) {
        return;
    }

    const imageData = gallery.querySelector('[data-gallery-images]');
    const mainImage = gallery.querySelector('[data-gallery-main]');
    const lightbox = gallery.querySelector('[data-gallery-lightbox]');
    const lightboxImage = gallery.querySelector('[data-lightbox-image]');
    const counter = gallery.querySelector('[data-gallery-counter]');

    if (
        !(imageData instanceof HTMLScriptElement)
        || !(mainImage instanceof HTMLImageElement)
        || !(lightbox instanceof HTMLDialogElement)
        || !(lightboxImage instanceof HTMLImageElement)
        || !(counter instanceof HTMLElement)
    ) {
        return;
    }

    const images = JSON.parse(imageData.textContent ?? '[]');
    if (!Array.isArray(images) || images.length === 0) {
        images.push({ src: mainImage.src, alt: mainImage.alt });
    }

    let activeIndex = 0;

    const showImage = (index) => {
        activeIndex = (index + images.length) % images.length;
        const image = images[activeIndex];

        mainImage.src = image.src;
        mainImage.alt = image.alt;
        lightboxImage.src = image.src;
        lightboxImage.alt = image.alt;
        counter.textContent = `${activeIndex + 1} / ${images.length}`;

        gallery.querySelectorAll('[data-gallery-thumbnail]').forEach((thumbnail, thumbnailIndex) => {
            if (!(thumbnail instanceof HTMLButtonElement)) {
                return;
            }

            const isActive = thumbnailIndex === activeIndex;
            thumbnail.classList.toggle('is-active', isActive);
            thumbnail.setAttribute('aria-pressed', String(isActive));
        });
    };

    gallery.querySelectorAll('[data-gallery-thumbnail]').forEach((thumbnail) => {
        thumbnail.addEventListener('click', () => {
            const index = Number(thumbnail.getAttribute('data-gallery-thumbnail'));
            if (Number.isInteger(index)) {
                showImage(index);
            }
        });
    });

    gallery.querySelectorAll('[data-gallery-previous]').forEach((button) => {
        button.addEventListener('click', () => showImage(activeIndex - 1));
    });
    gallery.querySelectorAll('[data-gallery-next]').forEach((button) => {
        button.addEventListener('click', () => showImage(activeIndex + 1));
    });

    gallery.querySelector('[data-gallery-open]')?.addEventListener('click', () => lightbox.showModal());
    gallery.querySelector('[data-gallery-close]')?.addEventListener('click', () => lightbox.close());
    lightbox.addEventListener('click', (event) => {
        if (event.target === lightbox) {
            lightbox.close();
        }
    });
    lightbox.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowLeft') {
            showImage(activeIndex - 1);
        } else if (event.key === 'ArrowRight') {
            showImage(activeIndex + 1);
        }
    });

    showImage(0);
});
