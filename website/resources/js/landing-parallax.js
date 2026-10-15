/**
 * MailTrixy Landing Page — Parallax Controller
 *
 * Usage in Blade:
 *   data-parallax="translateY"  data-speed="0.3"       → moves at 30% of scroll speed
 *   data-parallax="translateX"  data-speed="-0.2"      → moves left at 20% scroll speed
 *   data-parallax="scale"       data-speed="0.1"       → scales up as you scroll
 *   data-parallax="rotate"      data-speed="0.05"      → rotates on scroll
 *   data-parallax="opacity"     data-start="0.8" data-end="0"  → fades out
 *
 * Mouse tracking (3D tilt on cards):
 *   data-tilt                                           → enables mouse-tracking 3D tilt
 *   data-tilt-max="15"                                  → max tilt degrees (default 10)
 *   data-tilt-perspective="1000"                        → perspective value (default 1000)
 *   data-tilt-glare                                     → adds glare overlay on tilt
 */

class ParallaxController {
  constructor() {
    this.elements = [];
    this.tiltElements = [];
    this.scrollY = 0;
    this.ticking = false;
    this.isMobile = window.matchMedia('(max-width: 768px)').matches;
    this.prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (!this.prefersReducedMotion) {
      this.init();
    }
  }

  init() {
    document.querySelectorAll('[data-parallax]').forEach(el => {
      this.elements.push({
        el,
        type: el.dataset.parallax,
        speed: parseFloat(el.dataset.speed || 0.3),
        start: parseFloat(el.dataset.start ?? 1),
        end: parseFloat(el.dataset.end ?? 0),
      });
    });

    if (!this.isMobile) {
      document.querySelectorAll('[data-tilt]').forEach(el => {
        const maxTilt = parseInt(el.dataset.tiltMax || 10);
        const perspective = parseInt(el.dataset.tiltPerspective || 1000);
        const hasGlare = el.hasAttribute('data-tilt-glare');

        if (hasGlare) {
          const glare = document.createElement('div');
          glare.className = 'pointer-events-none absolute inset-0 rounded-2xl opacity-0 transition-opacity duration-300';
          glare.style.background = 'linear-gradient(135deg, rgba(255,255,255,0.15) 0%, transparent 50%)';
          glare.dataset.glare = '';
          el.style.position = 'relative';
          el.style.overflow = 'hidden';
          el.appendChild(glare);
        }

        el.style.transformStyle = 'preserve-3d';
        el.style.willChange = 'transform';

        el.addEventListener('mousemove', (e) => this.handleTilt(e, el, maxTilt, perspective));
        el.addEventListener('mouseleave', () => this.resetTilt(el));

        this.tiltElements.push(el);
      });
    }

    window.addEventListener('scroll', () => {
      this.scrollY = window.scrollY;
      if (!this.ticking) {
        requestAnimationFrame(() => {
          this.updateParallax();
          this.ticking = false;
        });
        this.ticking = true;
      }
    }, { passive: true });

    this.updateParallax();
  }

  updateParallax() {
    if (this.isMobile) return;

    this.elements.forEach(({ el, type, speed, start, end }) => {
      const rect = el.getBoundingClientRect();
      const viewportHeight = window.innerHeight;

      if (rect.top > viewportHeight * 1.5 || rect.bottom < -viewportHeight * 0.5) return;

      const progress = 1 - (rect.top / viewportHeight);

      switch (type) {
        case 'translateY':
          el.style.transform = `translate3d(0, ${this.scrollY * speed * -1}px, 0)`;
          break;
        case 'translateX':
          el.style.transform = `translate3d(${this.scrollY * speed}px, 0, 0)`;
          break;
        case 'scale': {
          const scale = 1 + (progress * speed);
          el.style.transform = `translate3d(0, 0, 0) scale(${Math.max(0.5, Math.min(1.5, scale))})`;
          break;
        }
        case 'rotate':
          el.style.transform = `translate3d(0, 0, 0) rotate(${this.scrollY * speed}deg)`;
          break;
        case 'opacity': {
          const opacity = start + (progress * (end - start));
          el.style.opacity = Math.max(0, Math.min(1, opacity));
          break;
        }
      }
    });
  }

  handleTilt(e, el, maxTilt, perspective) {
    const rect = el.getBoundingClientRect();
    const centerX = rect.left + rect.width / 2;
    const centerY = rect.top + rect.height / 2;
    const mouseX = e.clientX - centerX;
    const mouseY = e.clientY - centerY;

    const rotateX = (mouseY / (rect.height / 2)) * -maxTilt;
    const rotateY = (mouseX / (rect.width / 2)) * maxTilt;

    el.style.transform = `perspective(${perspective}px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale3d(1.02, 1.02, 1.02)`;
    el.style.transition = 'transform 0.1s ease-out';

    const glare = el.querySelector('[data-glare]');
    if (glare) {
      glare.style.opacity = '1';
      glare.style.background = `linear-gradient(${Math.atan2(mouseY, mouseX) * 180 / Math.PI + 90}deg, rgba(255,255,255,0.15) 0%, transparent 80%)`;
    }
  }

  resetTilt(el) {
    el.style.transform = 'perspective(1000px) rotateX(0) rotateY(0) scale3d(1, 1, 1)';
    el.style.transition = 'transform 0.5s cubic-bezier(0.16, 1, 0.3, 1)';

    const glare = el.querySelector('[data-glare]');
    if (glare) glare.style.opacity = '0';
  }
}

export default ParallaxController;
