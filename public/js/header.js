
document.addEventListener('DOMContentLoaded', () => {
    const header = document.getElementById('main-header');
    const spacer = document.getElementById('header-spacer');
    const content = document.getElementById('header-content');
    const title = document.getElementById('header-title');
    const logo = document.getElementById('header-logo');

    if (!header || !spacer || !content || !title || !logo) {
        console.error('Faltan elementos del encabezado.');
        return;
    }

    const compactAt = 80;
    const expandAt = 20;

    let isCompact = window.scrollY > compactAt;
    let ticking = false;

    function applyHeaderState(compact) {
        const isMobile = window.innerWidth < 768;

        header.classList.toggle('is-compact', isMobile ? false : compact);

        if (isMobile) {
            // En móvil, el header conserva su tamaño y no se queda fijo.
            header.style.height = '95dvh';
            logo.style.width = '';
            title.style.fontSize = '';
            spacer.style.height = '95dvh';
            return;
        }

        // Comportamiento existente para tablet y escritorio.
        header.style.height = compact ? '20dvh' : '95dvh';
        logo.style.width = compact
            ? 'clamp(60px, 7.5vw, 120px)'
            : 'clamp(56px, 12vw, 192px)';
        title.style.fontSize = compact
            ? 'clamp(12px, 1.5vw, 24px)'
            : 'clamp(24px, 4vw, 48px)';
        spacer.style.height = '45dvh';
    }

    function updateHeader() {
        const y = window.scrollY;
        let nextState = isCompact;

        if (!isCompact && y > compactAt) {
            nextState = true;
        } else if (isCompact && y < expandAt) {
            nextState = false;
        }

        if (nextState !== isCompact) {
            isCompact = nextState;
            applyHeaderState(isCompact);
        }

        ticking = false;
    }

    function onScroll() {
        if (!ticking) {
            ticking = true;
            requestAnimationFrame(updateHeader);
        }
    }

    applyHeaderState(isCompact);

    window.addEventListener('scroll', onScroll, {
        passive: true
    });

    window.addEventListener('resize', () => {
        applyHeaderState(isCompact);
    });
});
