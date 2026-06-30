document.addEventListener('DOMContentLoaded', function () {

    /* =========================
       ELEMENT CACHE (OPTIMASI)
    ========================= */
    const html = document.documentElement;
    const themeToggle = document.getElementById('theme-toggle');
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const sidebar = document.querySelector('.sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    /* =========================
       DARK MODE (IMPROVED)
    ========================= */
    const savedTheme = localStorage.getItem('theme') || 'light';
    setTheme(savedTheme);

    function setTheme(theme) {
        html.setAttribute('data-theme', theme); 
        localStorage.setItem('theme', theme);
        updateThemeIcon(theme);
    }

    function updateThemeIcon(theme) {
        const icon = themeToggle?.querySelector('i');
        if (!icon) return;

        icon.className = theme === 'dark'
            ? 'fas fa-sun'
            : 'fas fa-moon';
    }

    themeToggle?.addEventListener('click', () => {
        const current = html.getAttribute('data-theme');
        const next = current === 'dark' ? 'light' : 'dark';
        setTheme(next);
    });

    /* =========================
       MOBILE SIDEBAR (SAFE)
    ========================= */
    function closeSidebar() {
        sidebar?.classList.remove('mobile-open');
        sidebarOverlay?.classList.remove('active');
        document.body.classList.remove('no-scroll');
    }

    function openSidebar() {
        sidebar?.classList.add('mobile-open');
        sidebarOverlay?.classList.add('active');
        document.body.classList.add('no-scroll');
    }

    mobileMenuToggle?.addEventListener('click', () => {
        if (!sidebar) return;
        sidebar.classList.contains('mobile-open') ? closeSidebar() : openSidebar();
    });

    sidebarOverlay?.addEventListener('click', closeSidebar);

    window.addEventListener('resize', () => {
        if (window.innerWidth > 1024) closeSidebar();
    });

    /* =========================
       DROPDOWN MENU (FIXED)
    ========================= */
    document.querySelectorAll('.has-dropdown').forEach(menu => {
        menu.addEventListener('click', function (e) {
            e.preventDefault();

            const parent = this.closest('li');
            if (!parent) return;

            document.querySelectorAll('.sidebar li.open').forEach(item => {
                if (item !== parent) item.classList.remove('open');
            });

            parent.classList.toggle('open');
        });
    });

    /* =========================
       SCROLL ANIMATION (OPTIMIZED)
    ========================= */
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (!entry.isIntersecting) return;

            entry.target.style.opacity = '1';
            entry.target.style.transform = 'translateY(0)';
            observer.unobserve(entry.target); // stop observe after show
        });
    }, {
        threshold: 0.1
    });

    document.querySelectorAll('.card, .stat-card').forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(15px)';
        el.style.transition = '0.5s ease';
        observer.observe(el);
    });

    /* =========================
       RIPPLE EFFECT (FIXED VERSION)
    ========================= */
    document.querySelectorAll('.btn, .sidebar a, .icon-btn').forEach(el => {
        el.addEventListener('click', function (e) {

            const ripple = document.createElement('span');
            ripple.classList.add('ripple');

            const rect = this.getBoundingClientRect();

            const size = Math.max(rect.width, rect.height);
            ripple.style.width = ripple.style.height = size + 'px';

            ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
            ripple.style.top = (e.clientY - rect.top - size / 2) + 'px';

            this.appendChild(ripple);

            setTimeout(() => ripple.remove(), 600);
        });
    });

});