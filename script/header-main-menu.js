// NENÍ potřeba DOMContentLoaded, protože atribut defer to zařídí
const hamburger = document.getElementById('hamburger');
const mobileMenu = document.getElementById('mobileMenu');
const closeMenu = document.getElementById('closeMenu');
const body = document.body;

if (hamburger && mobileMenu && closeMenu) {
    // ... (stejná logika pro mobilní menu jako v Možnosti 1) ...
    if (closeMenu.innerHTML.includes('×') || closeMenu.querySelector('i')) {
        closeMenu.setAttribute('translate', 'no');
    }
    if (hamburger.querySelectorAll('span')) {
        hamburger.querySelectorAll('span').forEach(span => {
            span.setAttribute('translate', 'no');
        });
    }

    hamburger.addEventListener('click', () => {
        const isActive = hamburger.classList.toggle('active');
        mobileMenu.classList.toggle('active');
        body.style.overflow = mobileMenu.classList.contains('active') ? 'hidden' : '';
        hamburger.setAttribute('aria-expanded', isActive ? 'true' : 'false');
        if (mobileMenu) mobileMenu.setAttribute('aria-hidden', !isActive);
    });

    closeMenu.addEventListener('click', () => {
        hamburger.classList.remove('active');
        mobileMenu.classList.remove('active');
        body.style.overflow = '';
        hamburger.setAttribute('aria-expanded', 'false');
        if (mobileMenu) mobileMenu.setAttribute('aria-hidden', 'true');
        if (hamburger) hamburger.focus();
    });

    const mobileNavLinksList = mobileMenu.querySelectorAll('ul.mobile-links a, a.btn');
    mobileNavLinksList.forEach(link => {
        link.addEventListener('click', () => {
            if (!link.target || link.target === '_self') {
                hamburger.classList.remove('active');
                mobileMenu.classList.remove('active');
                body.style.overflow = '';
                if (hamburger) hamburger.setAttribute('aria-expanded', 'false');
                if (mobileMenu) mobileMenu.setAttribute('aria-hidden', 'true');
            }
        });
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && mobileMenu && mobileMenu.classList.contains('active')) {
            closeMenu.click();
        }
    });
}