import { Html5Qrcode } from 'html5-qrcode';
import { registerSW } from 'virtual:pwa-register';

registerSW({ immediate: false });

document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.querySelector('.nav-toggle');
    const navigation = document.querySelector('.primary-nav');

    if (toggle && navigation) {
        toggle.addEventListener('click', () => {
            const expanded = toggle.getAttribute('aria-expanded') === 'true';

            toggle.setAttribute('aria-expanded', String(! expanded));
            navigation.classList.toggle('hidden');
        });
    }

    const reader = document.getElementById('qr-reader');
    if (! reader) {
        return;
    }

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
