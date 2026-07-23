import { registerSW } from 'virtual:pwa-register';

registerSW({ immediate: false });

document.addEventListener('DOMContentLoaded', async () => {
    const toggle = document.querySelector('.nav-toggle');
    const navigation = document.querySelector('.primary-nav');

    if (toggle && navigation) {
        toggle.addEventListener('click', () => {
            const expanded = toggle.getAttribute('aria-expanded') === 'true';

            toggle.setAttribute('aria-expanded', String(! expanded));
            navigation.classList.toggle('is-open');
        });
    }

    const primaryLinks = document.querySelector('[data-primary-links]');
    const navIndicator = document.querySelector('[data-nav-indicator]');

    if (primaryLinks && navIndicator && window.matchMedia('(min-width: 768px)').matches) {
        const activeItem = primaryLinks.querySelector('[aria-current="page"]');
        const storageKey = 'lrms-nav-indicator';

        const moveIndicator = (element, { animate = true } = {}) => {
            if (! element) {
                navIndicator.style.opacity = '0';
                return;
            }

            const linksRect = primaryLinks.getBoundingClientRect();
            const itemRect = element.getBoundingClientRect();
            const width = Math.max(itemRect.width + 8, 24);
            const left = itemRect.left - linksRect.left + (itemRect.width - width) / 2;

            if (! animate) {
                navIndicator.style.transition = 'none';
            }

            navIndicator.style.width = `${width}px`;
            navIndicator.style.left = `${left}px`;
            navIndicator.classList.add('is-ready');

            if (! animate) {
                // Force reflow so the next move can animate.
                void navIndicator.offsetWidth;
                navIndicator.style.transition = '';
            }

            sessionStorage.setItem(storageKey, JSON.stringify({ left, width }));
        };

        const previous = sessionStorage.getItem(storageKey);

        if (previous && activeItem) {
            try {
                const { left, width } = JSON.parse(previous);
                navIndicator.style.transition = 'none';
                navIndicator.style.width = `${width}px`;
                navIndicator.style.left = `${left}px`;
                navIndicator.classList.add('is-ready');
                void navIndicator.offsetWidth;
                navIndicator.style.transition = '';
            } catch {
                // Ignore invalid stored values.
            }
        }

        requestAnimationFrame(() => moveIndicator(activeItem, { animate: Boolean(previous) }));

        window.addEventListener('resize', () => moveIndicator(activeItem, { animate: false }));
    }

    document.querySelectorAll('[data-dropdown]').forEach((dropdown) => {
        const trigger = dropdown.querySelector('[data-dropdown-trigger]');
        const menu = dropdown.querySelector('[data-dropdown-menu]');

        if (! trigger || ! menu) {
            return;
        }

        const close = () => {
            trigger.setAttribute('aria-expanded', 'false');
            menu.classList.add('hidden');
        };

        const open = () => {
            document.querySelectorAll('[data-dropdown]').forEach((other) => {
                if (other === dropdown) {
                    return;
                }

                other.querySelector('[data-dropdown-trigger]')?.setAttribute('aria-expanded', 'false');
                other.querySelector('[data-dropdown-menu]')?.classList.add('hidden');
            });

            trigger.setAttribute('aria-expanded', 'true');
            menu.classList.remove('hidden');
        };

        trigger.addEventListener('click', (event) => {
            event.stopPropagation();
            const expanded = trigger.getAttribute('aria-expanded') === 'true';

            if (expanded) {
                close();
            } else {
                open();
            }
        });

        document.addEventListener('click', (event) => {
            if (! dropdown.contains(event.target)) {
                close();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                close();
            }
        });
    });

    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.getAttribute('data-password-toggle'));

            if (! input) {
                return;
            }

            const showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            button.setAttribute('aria-pressed', String(! showing));
            button.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
        });
    });

    const gateway = document.querySelector('[data-auth-gateway]');

    if (gateway) {
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const loginUrl = gateway.dataset.loginUrl;
        const registerUrl = gateway.dataset.registerUrl;
        const titles = {
            login: gateway.dataset.loginTitle,
            register: gateway.dataset.registerTitle,
        };
        let activeMode = gateway.dataset.authMode || 'login';
        let animating = false;

        const wait = (ms) => new Promise((resolve) => {
            window.setTimeout(resolve, reduceMotion ? 0 : ms);
        });

        const setPanelState = (mode) => {
            gateway.querySelectorAll('[data-auth-panel]').forEach((panel) => {
                const isActive = panel.dataset.authPanel === mode;
                panel.classList.toggle('is-active', isActive);
                panel.toggleAttribute('hidden', ! isActive);
                panel.toggleAttribute('inert', ! isActive);
            });

            gateway.querySelectorAll('[data-auth-brand]').forEach((copy) => {
                const isActive = copy.dataset.authBrand === mode;
                copy.classList.toggle('is-active', isActive);
                copy.toggleAttribute('hidden', ! isActive);
            });

            activeMode = mode;
            gateway.dataset.authMode = mode;
            document.title = titles[mode] || document.title;

            const focusTarget = mode === 'register'
                ? document.getElementById('staff_number')
                : document.getElementById('login_email');

            focusTarget?.focus({ preventScroll: true });
        };

        const switchAuthMode = async (mode, { updateHistory = true } = {}) => {
            if (mode === activeMode || animating || (mode !== 'login' && mode !== 'register')) {
                return;
            }

            animating = true;

            const currentPanel = gateway.querySelector(`[data-auth-panel="${activeMode}"]`);
            const nextPanel = gateway.querySelector(`[data-auth-panel="${mode}"]`);
            const currentBrand = gateway.querySelector(`[data-auth-brand="${activeMode}"]`);
            const nextBrand = gateway.querySelector(`[data-auth-brand="${mode}"]`);

            currentPanel?.classList.add('is-leaving');
            currentBrand?.classList.add('is-leaving');
            await wait(220);

            currentPanel?.classList.remove('is-active', 'is-leaving');
            currentBrand?.classList.remove('is-active', 'is-leaving');
            currentPanel?.setAttribute('hidden', '');
            currentBrand?.setAttribute('hidden', '');
            currentPanel?.setAttribute('inert', '');

            nextPanel?.removeAttribute('hidden');
            nextBrand?.removeAttribute('hidden');
            nextPanel?.removeAttribute('inert');
            nextPanel?.classList.add('is-entering');
            nextBrand?.classList.add('is-entering');

            await wait(20);

            nextPanel?.classList.remove('is-entering');
            nextBrand?.classList.remove('is-entering');
            nextPanel?.classList.add('is-active');
            nextBrand?.classList.add('is-active');

            activeMode = mode;
            gateway.dataset.authMode = mode;
            document.title = titles[mode] || document.title;

            if (updateHistory) {
                const url = mode === 'register' ? registerUrl : loginUrl;
                window.history.pushState({ authMode: mode }, titles[mode], url);
            }

            const focusTarget = mode === 'register'
                ? document.getElementById('staff_number')
                : document.getElementById('login_email');

            focusTarget?.focus({ preventScroll: true });
            animating = false;
        };

        setPanelState(activeMode);
        window.history.replaceState({ authMode: activeMode }, document.title, window.location.href);

        gateway.addEventListener('click', (event) => {
            const link = event.target.closest('[data-auth-switch]');

            if (! link || ! gateway.contains(link)) {
                return;
            }

            event.preventDefault();
            switchAuthMode(link.dataset.authSwitch);
        });

        window.addEventListener('popstate', (event) => {
            const mode = event.state?.authMode
                || (window.location.pathname.includes('register') ? 'register' : 'login');

            switchAuthMode(mode, { updateHistory: false });
        });
    }

    const reader = document.getElementById('qr-reader');
    if (! reader) {
        return;
    }

    const { Html5Qrcode } = await import('html5-qrcode');
    const scanner = new Html5Qrcode('qr-reader');
    let activeTarget = null;
    let running = false;
    const status = document.getElementById('scanner-status');

    const stop = async () => {
        if (running) {
            await scanner.stop();
            scanner.clear();
            running = false;
        }
    };

    document.querySelectorAll('[data-scan-target]').forEach((button) => {
        button.addEventListener('click', async () => {
            activeTarget = document.getElementById(button.dataset.scanTarget);
            await stop();
            reader.classList.remove('hidden');
            status.textContent = 'Camera active — hold the QR code inside the frame.';
            try {
                await scanner.start(
                    { facingMode: 'environment' },
                    { fps: 12, qrbox: { width: 240, height: 240 } },
                    async (value) => {
                        if (activeTarget.tagName === 'TEXTAREA') {
                            const values = activeTarget.value.split(/\s+/).filter(Boolean);
                            if (! values.includes(value)) {
                                values.push(value);
                                activeTarget.value = values.join('\n');
                            }
                        } else {
                            activeTarget.value = value;
                        }
                        status.textContent = 'QR captured successfully.';
                        await stop();
                        reader.classList.add('hidden');
                        activeTarget.focus();
                    },
                );
                running = true;
            } catch {
                status.textContent = 'Camera unavailable. Use a USB scanner or enter the QR identifier.';
                reader.classList.add('hidden');
            }
        });
    });
});
