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

const heroSlider = document.querySelector('[data-hero-slider]');

if (heroSlider instanceof HTMLElement) {
    const heroCarousel = heroSlider.closest('.landing-hero');
    const heroSlides = [...heroSlider.querySelectorAll('[data-hero-slide]')];
    const heroDots = [...document.querySelectorAll('[data-hero-slide-to]')];
    const heroToggle = document.querySelector('[data-hero-toggle]');
    const reducedMotionPreference = window.matchMedia('(prefers-reduced-motion: reduce)');

    if (heroCarousel instanceof HTMLElement && heroSlides.length > 1 && heroToggle instanceof HTMLButtonElement) {
        let activeHeroSlide = 0;
        let rotationTimer;
        let userPaused = reducedMotionPreference.matches;
        let temporarilyPaused = false;

        const stopRotation = () => {
            window.clearInterval(rotationTimer);
            rotationTimer = undefined;
        };

        const startRotation = () => {
            stopRotation();

            if (userPaused || temporarilyPaused || document.hidden) {
                return;
            }

            rotationTimer = window.setInterval(() => {
                showHeroSlide(activeHeroSlide + 1);
            }, 6000);
        };

        const showHeroSlide = (index) => {
            activeHeroSlide = (index + heroSlides.length) % heroSlides.length;

            heroSlides.forEach((slide, slideIndex) => {
                const isActive = slideIndex === activeHeroSlide;
                slide.classList.toggle('is-active', isActive);
                slide.setAttribute('aria-hidden', String(!isActive));
            });

            heroDots.forEach((dot, dotIndex) => {
                if (!(dot instanceof HTMLButtonElement)) {
                    return;
                }

                const isActive = dotIndex === activeHeroSlide;
                dot.classList.toggle('is-active', isActive);
                dot.setAttribute('aria-pressed', String(isActive));
            });

            startRotation();
        };

        const syncToggle = () => {
            heroToggle.setAttribute('aria-pressed', String(userPaused));
            heroToggle.setAttribute(
                'aria-label',
                userPaused
                    ? heroToggle.dataset.resumeLabel
                    : heroToggle.dataset.pauseLabel,
            );
            const toggleIcon = heroToggle.querySelector('span');
            if (toggleIcon instanceof HTMLSpanElement) {
                toggleIcon.textContent = userPaused ? '▶' : 'Ⅱ';
            }
        };

        heroDots.forEach((dot) => {
            if (!(dot instanceof HTMLButtonElement)) {
                return;
            }

            dot.addEventListener('click', () => {
                const index = Number(dot.dataset.heroSlideTo);
                if (Number.isInteger(index)) {
                    showHeroSlide(index);
                }
            });
        });

        heroToggle.addEventListener('click', () => {
            userPaused = !userPaused;
            syncToggle();
            startRotation();
        });

        heroCarousel.addEventListener('mouseenter', () => {
            temporarilyPaused = true;
            stopRotation();
        });
        heroCarousel.addEventListener('mouseleave', () => {
            temporarilyPaused = false;
            startRotation();
        });
        heroCarousel.addEventListener('focusin', () => {
            temporarilyPaused = true;
            stopRotation();
        });
        heroCarousel.addEventListener('focusout', () => {
            window.setTimeout(() => {
                if (!heroCarousel.contains(document.activeElement)) {
                    temporarilyPaused = false;
                    startRotation();
                }
            });
        });
        document.addEventListener('visibilitychange', startRotation);

        syncToggle();
        startRotation();
    }
}
