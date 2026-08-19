document.addEventListener("DOMContentLoaded", () => {

    // =====================================================
    // GSAP SETUP
    // =====================================================

    gsap.registerPlugin(ScrollTrigger);

    const $ = (selector, parent = document) => parent.querySelector(selector);
    const $$ = (selector, parent = document) => [...parent.querySelectorAll(selector)];

    // =====================================================
    // CACHE DOM
    // =====================================================

    const bookingModal = $("#bookingModal");
    const profileModal = $("#profileModal");

    const bookingForm = $("#bookingForm");

    const heroVideo = $(".hero-video");
    const soundToggle = $(".sound-toggle");

    const packageCards = $$(".package-card");
    const filterPills = $$(".filter-pill");

    // =====================================================
    // NAVBAR HIDE / SHOW
    // =====================================================

    const header = $(".site-header");

    let lastScroll = 0;

    if (header) {

        gsap.set(header, {
            y: 0
        });

        window.addEventListener("scroll", () => {

            const current = window.scrollY;

            if (current > lastScroll && current > 120) {

                gsap.to(header, {
                    y: "-100%",
                    duration: .35,
                    ease: "power2.out"
                });

            } else {

                gsap.to(header, {
                    y: 0,
                    duration: .35,
                    ease: "power2.out"
                });

            }

            lastScroll = current;

        });

    }

    // =====================================================
    // SCROLL PROGRESS BAR
    // =====================================================

    const progress = document.createElement("div");

    progress.className = "scroll-progress";

    document.body.appendChild(progress);

    gsap.set(progress, {
        transformOrigin: "left center",
        scaleX: 0
    });

    window.addEventListener("scroll", () => {

        const total =
            document.documentElement.scrollHeight -
            window.innerHeight;

        const percent = window.scrollY / total;

        gsap.set(progress, {
            scaleX: percent
        });

    });

    // =====================================================
    // HERO PARALLAX
    // =====================================================

    const hero = $(".hero");

    if (hero) {

        gsap.to(hero, {

            backgroundPositionY: "35%",

            ease: "none",

            scrollTrigger: {

                trigger: hero,

                start: "top top",

                end: "bottom top",

                scrub: true

            }

        });

    }

    // =====================================================
    // HERO TITLE
    // =====================================================

    const heroTitle = $(".hero-content h1");

    if (heroTitle) {

        const split = new SplitType(heroTitle, {

            types: "chars, words"

        });

        gsap.from(split.chars, {

            opacity: 0,

            y: 80,

            rotateX: -90,

            stagger: .025,

            duration: .8,

            ease: "power4.out"

        });

    }

    // =====================================================
    // SECTION TITLES
    // =====================================================

    $$(".section-title h2").forEach(title => {

        const split = new SplitType(title, {

            types: "words"

        });

        gsap.from(split.words, {

            opacity: 0,

            y: 50,

            stagger: .05,

            duration: .7,

            ease: "power3.out",

            scrollTrigger: {

                trigger: title,

                start: "top 85%"

            }

        });

    });

    // =====================================================
    // FADE UP ELEMENTS
    // =====================================================

    gsap.utils.toArray(

        ".card,.package-card,.destination-card,.feature-card"

    ).forEach(el => {

        gsap.from(el, {

            opacity: 0,

            y: 40,

            duration: .8,

            ease: "power3.out",

            scrollTrigger: {

                trigger: el,

                start: "top 88%"

            }

        });

    });

    // =====================================================
    // PACKAGE CARD HOVER
    // =====================================================

    packageCards.forEach(card => {

        card.addEventListener("mousemove", e => {

            const rect = card.getBoundingClientRect();

            const x = e.clientX - rect.left;

            const y = e.clientY - rect.top;

            const rotateY =

                ((x / rect.width) - .5) * 12;

            const rotateX =

                ((rect.height / 2 - y) / rect.height) * 12;

            gsap.to(card, {

                rotateX,

                rotateY,

                duration: .4,

                transformPerspective: 900,

                ease: "power2.out"

            });

        });

        card.addEventListener("mouseleave", () => {

            gsap.to(card, {

                rotateX: 0,

                rotateY: 0,

                duration: .5,

                ease: "power3.out"

            });

        });

    });

    // =====================================================
    // VIDEO CONTROLS
    // =====================================================

    if (soundToggle && heroVideo) {

        heroVideo.muted = true;

        heroVideo.volume = .4;

        soundToggle.innerHTML = "🔇";

        soundToggle.addEventListener("click", () => {

            heroVideo.muted = !heroVideo.muted;

            soundToggle.innerHTML = heroVideo.muted

                ? "🔇"

                : "🔊";

            soundToggle.classList.toggle(

                "active",

                !heroVideo.muted

            );

            gsap.fromTo(

                soundToggle,

                {

                    scale: .8

                },

                {

                    scale: 1,

                    duration: .25

                }

            );

        });

    }

    // =====================================================
    // MODAL HELPERS
    // =====================================================

    function openModal(modal) {

        if (!modal) return;

        modal.style.display = "flex";

        gsap.set(modal, {

            opacity: 0

        });

        gsap.set(

            $(".modal-content", modal),

            {

                y: 40,

                scale: .9,

                opacity: 0

            }

        );

        gsap.to(modal, {

            opacity: 1,

            duration: .25

        });

        gsap.to(

            $(".modal-content", modal),

            {

                y: 0,

                scale: 1,

                opacity: 1,

                duration: .45,

                ease: "back.out(1.8)"

            }

        );

    }

    function closeModal(modal) {

        if (!modal) return;

        gsap.to(

            $(".modal-content", modal),

            {

                y: 30,

                opacity: 0,

                scale: .9,

                duration: .25,

                ease: "power2.in"

            }

        );

        gsap.to(modal, {

            opacity: 0,

            duration: .25,

            onComplete: () => {

                modal.style.display = "none";

            }

        });

    }

    // =====================================================
    // STICKY HEADER SHADOW ON SCROLL
    // =====================================================

    const stickyHeaders = document.querySelectorAll('.site-header, header[class*="sticky"]');

    stickyHeaders.forEach(header => {
      const onScroll = () => {
        if (window.scrollY > 8) {
          header.classList.add('is-scrolled');
        } else {
          header.classList.remove('is-scrolled');
        }
      };
      window.addEventListener('scroll', onScroll, { passive: true });
      onScroll();
    });

    // ===== CONTINUES IN PART 2 =====
});