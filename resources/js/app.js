const cleanupLegacyPwa = async () => {
    if (!('serviceWorker' in navigator) || !('caches' in window)) {
        return;
    }

    const cleanupKey = 'lrms-pwa-cleanup-v1';

    if (window.localStorage.getItem(cleanupKey) === 'done') {
        return;
    }

    try {
        const registrations = await navigator.serviceWorker.getRegistrations();
        await Promise.all(registrations.map((registration) => registration.unregister()));

        const cacheKeys = await caches.keys();
        await Promise.all(cacheKeys.map((cacheKey) => caches.delete(cacheKey)));

        window.localStorage.setItem(cleanupKey, 'done');
    } catch {
        // Leave the marker unset so cleanup can retry on the next page load.
    }
};

void cleanupLegacyPwa();

const createDashboardChartRenderer = (container) => {
    const svg = container.querySelector('.dashboard-chart__svg');
    const surface = container.querySelector('.dashboard-chart__surface');
    const axis = container.querySelector('[data-dashboard-chart-axis]');
    const tooltip = container.querySelector('[data-dashboard-chart-tooltip]');
    const tooltipLabel = container.querySelector('[data-dashboard-chart-tooltip-label]');
    const tooltipValue = container.querySelector('[data-dashboard-chart-tooltip-value]');

    if (!svg || !surface || !axis || !tooltip || !tooltipLabel || !tooltipValue) {
        return null;
    }

    const mobileMedia = window.matchMedia('(max-width: 1023px)');

    const getLayout = () => {
        if (mobileMedia.matches) {
            return {
                isMobile: true,
                width: 360,
                height: 200,
                padding: { top: 14, right: 8, bottom: 36, left: 32 },
            };
        }

        return {
            isMobile: false,
            width: 960,
            height: 320,
            padding: { top: 18, right: 16, bottom: 54, left: 54 },
        };
    };

    const createSvgNode = (tag, attributes = {}) => {
        const node = document.createElementNS('http://www.w3.org/2000/svg', tag);
        Object.entries(attributes).forEach(([key, value]) => {
            node.setAttribute(key, String(value));
        });

        return node;
    };

    const formatValue = (value) => `${value} case${value === 1 ? '' : 's'}`;

    const hideTooltip = () => {
        tooltip.hidden = true;
        tooltip.classList.remove('is-visible');
    };

    const showTooltip = (point, x, y) => {
        tooltipLabel.textContent = point.label;
        tooltipValue.textContent = `${point.value} registered`;
        tooltip.hidden = false;
        tooltip.classList.add('is-visible');

        const bounds = surface.getBoundingClientRect();
        const tooltipRect = tooltip.getBoundingClientRect();
        const left = Math.min(
            Math.max(12, x - (tooltipRect.width / 2)),
            bounds.width - tooltipRect.width - 12,
        );
        const top = Math.max(12, y - tooltipRect.height - 14);

        tooltip.style.left = `${left}px`;
        tooltip.style.top = `${top}px`;
    };

    const sparseIndexes = (length) => [...new Set([
        0,
        Math.floor(Math.max(0, length - 1) * 0.25),
        Math.floor(Math.max(0, length - 1) * 0.5),
        Math.floor(Math.max(0, length - 1) * 0.75),
        Math.max(0, length - 1),
    ])];

    const render = (chart) => {
        container._dashboardChartData = chart;

        const { isMobile, width, height, padding } = getLayout();
        const plotWidth = width - padding.left - padding.right;
        const plotHeight = height - padding.top - padding.bottom;
        const points = Array.isArray(chart?.points) ? chart.points : [];
        const maxValue = Math.max(1, ...points.map((point) => Number(point.value) || 0));
        const rawStep = Math.max(1, maxValue / 3);
        const magnitude = 10 ** Math.floor(Math.log10(rawStep));
        const normalizedStep = rawStep / magnitude;
        const niceStep = normalizedStep <= 1
            ? 1
            : normalizedStep <= 2
                ? 2
                : normalizedStep <= 5
                    ? 5
                    : 10;
        const axisStep = niceStep * magnitude;
        const highestTick = Math.max(axisStep, Math.floor(maxValue / axisStep) * axisStep);
        const scaleMax = Math.max(maxValue, highestTick);
        const tickValues = Array.from({ length: Math.floor(highestTick / axisStep) + 1 }, (_, index) => highestTick - (index * axisStep));

        svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
        svg.setAttribute('aria-label', chart?.title || 'Registration trend chart');
        svg.classList.toggle('dashboard-chart__svg--mobile', isMobile);
        svg.replaceChildren();

        const defs = createSvgNode('defs');
        const barGradient = createSvgNode('linearGradient', {
            id: 'dashboardChartBarGradient',
            x1: '0%',
            y1: '0%',
            x2: '0%',
            y2: '100%',
        });

        barGradient.append(
            createSvgNode('stop', {
                offset: '0%',
                'stop-color': '#d4af5a',
            }),
        );

        barGradient.append(
            createSvgNode('stop', {
                offset: '100%',
                'stop-color': '#b78626',
            }),
        );

        defs.append(barGradient);
        svg.append(defs);

        tickValues.forEach((tick) => {
            const y = padding.top + plotHeight - ((tick / scaleMax) * plotHeight);

            svg.append(
                createSvgNode('line', {
                    x1: padding.left,
                    y1: y,
                    x2: width - padding.right,
                    y2: y,
                    class: 'dashboard-chart__grid-line',
                }),
            );

            const label = createSvgNode('text', {
                x: padding.left - 8,
                y: y + 4,
                'text-anchor': 'end',
                class: 'dashboard-chart__grid-label',
            });
            label.textContent = String(tick);
            svg.append(label);
        });

        const baselineY = padding.top + plotHeight;
        svg.append(
            createSvgNode('line', {
                x1: padding.left,
                y1: baselineY,
                x2: width - padding.right,
                y2: baselineY,
                class: 'dashboard-chart__baseline',
            }),
        );

        const xPositions = points.map((point, index) => {
            if (points.length === 1) {
                return padding.left + (plotWidth / 2);
            }

            return padding.left + ((plotWidth / Math.max(points.length - 1, 1)) * index);
        });

        const activeLine = createSvgNode('line', {
            y1: padding.top,
            y2: baselineY,
            class: 'dashboard-chart__active-line',
            visibility: 'hidden',
        });

        svg.append(activeLine);

        if (chart?.type === 'line') {
            const denseAxisIndexes = points.length > 12
                ? sparseIndexes(points.length)
                : points.map((_, index) => index);

            const lineCoordinates = points.map((point, index) => {
                const x = xPositions[index];
                const y = padding.top + plotHeight - (((Number(point.value) || 0) / scaleMax) * plotHeight);

                return { x, y };
            });

            const linePath = lineCoordinates
                .map((coordinate, index) => `${index === 0 ? 'M' : 'L'} ${coordinate.x} ${coordinate.y}`)
                .join(' ');

            const areaPath = `${linePath} L ${xPositions.at(-1) ?? padding.left} ${baselineY} L ${xPositions[0] ?? padding.left} ${baselineY} Z`;

            svg.append(
                createSvgNode('path', {
                    d: areaPath,
                    class: 'dashboard-chart__area',
                }),
            );

            svg.append(
                createSvgNode('path', {
                    d: linePath,
                    class: 'dashboard-chart__line',
                }),
            );

            // Dense day dots clutter phone screens; keep the line and tap tooltips instead.
            if (! isMobile || points.length <= 12) {
                lineCoordinates.forEach((coordinate) => {
                    svg.append(
                        createSvgNode('circle', {
                            cx: coordinate.x,
                            cy: coordinate.y,
                            r: isMobile ? 3 : 4,
                            class: 'dashboard-chart__point',
                        }),
                    );
                });
            }

            denseAxisIndexes.forEach((index) => {
                const point = points[index];

                if (!point) {
                    return;
                }

                const label = createSvgNode('text', {
                    x: xPositions[index],
                    y: height - (isMobile ? 8 : 10),
                    'text-anchor': 'middle',
                    class: 'dashboard-chart__svg-axis-label',
                });
                label.textContent = point.short_label;
                svg.append(label);
            });
        } else {
            const slotWidth = points.length > 1 ? plotWidth / Math.max(points.length, 1) : plotWidth;
            const barWidth = Math.max(isMobile ? 8 : 10, Math.min(isMobile ? 16 : 22, slotWidth * 0.42));

            points.forEach((point, index) => {
                const value = Number(point.value) || 0;
                const xCenter = padding.left + (slotWidth * index) + (slotWidth / 2);
                const barHeight = value === 0 ? 0 : Math.max(isMobile ? 10 : 14, (value / scaleMax) * plotHeight);
                const y = baselineY - barHeight;

                if (barHeight > 0) {
                    svg.append(
                        createSvgNode('rect', {
                            x: xCenter - (barWidth / 2),
                            y,
                            width: barWidth,
                            height: barHeight,
                            rx: isMobile ? 4 : 7,
                            class: 'dashboard-chart__bar',
                        }),
                    );
                }

                if (! isMobile) {
                    svg.append(
                        createSvgNode('circle', {
                            cx: xCenter,
                            cy: baselineY,
                            r: 2.6,
                            class: 'dashboard-chart__bar-anchor',
                        }),
                    );
                }

                xPositions[index] = xCenter;
            });

            sparseIndexes(points.length).forEach((labelIndex) => {
                const point = points[labelIndex];

                if (!point) {
                    return;
                }

                const label = createSvgNode('text', {
                    x: xPositions[labelIndex],
                    y: height - (isMobile ? 8 : 10),
                    'text-anchor': 'middle',
                    class: 'dashboard-chart__svg-axis-label',
                });
                label.textContent = point.short_label;
                svg.append(label);
            });
        }

        axis.replaceChildren();
        axis.hidden = true;

        points.forEach((point, index) => {
            const slotWidth = points.length === 1 ? plotWidth : plotWidth / Math.max(points.length, 1);
            const targetX = xPositions[index] ?? padding.left;
            const hitWidth = chart?.type === 'line'
                ? Math.max(isMobile ? 18 : 28, plotWidth / Math.max(points.length, 6))
                : Math.max(isMobile ? 16 : 22, slotWidth * 0.82);
            const hitX = chart?.type === 'line'
                ? targetX - (hitWidth / 2)
                : padding.left + (slotWidth * index) + ((slotWidth - hitWidth) / 2);

            const hotspot = createSvgNode('rect', {
                x: hitX,
                y: padding.top,
                width: hitWidth,
                height: plotHeight,
                fill: 'transparent',
                class: 'dashboard-chart__hitbox',
                tabindex: '0',
                role: 'button',
                'aria-label': `${point.label}: ${formatValue(Number(point.value) || 0)}`,
            });

            const activate = () => {
                activeLine.setAttribute('x1', String(targetX));
                activeLine.setAttribute('x2', String(targetX));
                activeLine.setAttribute('visibility', 'visible');

                const bounds = surface.getBoundingClientRect();
                const xRatio = targetX / width;
                const yValue = padding.top + plotHeight - (((Number(point.value) || 0) / scaleMax) * plotHeight);
                const yRatio = yValue / height;

                showTooltip(
                    point,
                    bounds.width * xRatio,
                    bounds.height * yRatio,
                );
            };

            const deactivate = () => {
                activeLine.setAttribute('visibility', 'hidden');
                hideTooltip();
            };

            hotspot.addEventListener('mouseenter', activate);
            hotspot.addEventListener('focus', activate);
            hotspot.addEventListener('mouseleave', deactivate);
            hotspot.addEventListener('blur', deactivate);
            hotspot.addEventListener('click', (event) => {
                event.preventDefault();
                activate();
            });

            svg.append(hotspot);
        });
    };

    mobileMedia.addEventListener('change', () => {
        if (container._dashboardChartData) {
            render(container._dashboardChartData);
        }
    });

    return render;
};

document.addEventListener('DOMContentLoaded', async () => {
    const updateDashboardChart = async (form) => {
        const panel = document.querySelector('[data-dashboard-chart-panel]');
        const chartContainer = document.querySelector('[data-dashboard-chart]');
        const chartTitle = document.querySelector('[data-chart-title]');
        const chartPeriod = document.querySelector('[data-chart-period]');
        const modeSelect = form.querySelector('[data-chart-mode-select]');
        const monthSelect = form.querySelector('[data-chart-month-select]');

        if (!panel || !chartContainer || !chartTitle || !chartPeriod || !modeSelect || !monthSelect) {
            return;
        }

        const renderer = chartContainer._dashboardChartRenderer
            ?? createDashboardChartRenderer(chartContainer);

        if (!renderer) {
            return;
        }

        chartContainer._dashboardChartRenderer = renderer;

        const params = new URLSearchParams();
        params.set('chart', modeSelect.value);

        if (modeSelect.value === 'month') {
            params.set('month', monthSelect.value);
        }

        panel.classList.add('is-loading');

        try {
            const response = await fetch(`${form.action}?${params.toString()}`, {
                cache: 'no-store',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error(`Chart request failed with ${response.status}`);
            }

            const data = await response.json();
            const chart = data.registrationChart;

            chartContainer.setAttribute('data-chart', JSON.stringify(chart));
            chartTitle.textContent = chart.title;
            chartPeriod.textContent = chart.period_label;
            renderer(chart);

            const nextUrl = new URL(window.location.href);
            nextUrl.search = params.toString();
            window.history.replaceState({}, '', nextUrl);
        } catch (error) {
            window.console.error(error);
        } finally {
            panel.classList.remove('is-loading');
        }
    };

    document.querySelectorAll('[data-chart-filter-form]').forEach((form) => {
        const modeSelect = form.querySelector('[data-chart-mode-select]');
        const monthField = form.querySelector('[data-chart-month-field]');
        const monthSelect = form.querySelector('[data-chart-month-select]');

        if (!modeSelect || !monthField || !monthSelect) {
            return;
        }

        const syncMonthField = () => {
            const showMonth = modeSelect.value === 'month';
            monthField.classList.toggle('hidden', !showMonth);
            monthSelect.disabled = !showMonth;
            form.classList.toggle('has-month', showMonth);
        };

        modeSelect.addEventListener('change', () => {
            syncMonthField();
            void updateDashboardChart(form);
        });

        monthSelect.addEventListener('change', () => {
            if (modeSelect.value === 'month') {
                void updateDashboardChart(form);
            }
        });

        syncMonthField();
    });

    document.querySelectorAll('[data-dashboard-chart]').forEach((container) => {
        const payload = container.getAttribute('data-chart');
        if (!payload) {
            return;
        }

        let chart;

        try {
            chart = JSON.parse(payload);
        } catch {
            return;
        }

        const renderer = createDashboardChartRenderer(container);

        if (!renderer) {
            return;
        }

        container._dashboardChartRenderer = renderer;
        renderer(chart);
    });

    const sidebarShell = document.querySelector('[data-sidebar-shell]');
    const sidebar = document.querySelector('[data-app-sidebar]');
    const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
    const sidebarOverlay = document.querySelector('[data-sidebar-overlay]');
    const sidebarCollapse = document.querySelector('[data-sidebar-collapse]');

    if (sidebarShell && sidebar) {
        const desktopMedia = window.matchMedia('(min-width: 1024px)');
        const tabletMedia = window.matchMedia('(min-width: 768px) and (max-width: 1279px)');
        const collapseStorageKey = 'lrms-sidebar-collapsed';
        const floatingTooltip = document.createElement('div');
        let activeTooltipTarget = null;

        floatingTooltip.className = 'sidebar-floating-tooltip';
        document.body.appendChild(floatingTooltip);

        const hideTooltip = () => {
            activeTooltipTarget = null;
            floatingTooltip.classList.remove('is-visible');
        };

        const positionTooltip = (target) => {
            const rect = target.getBoundingClientRect();
            const tooltipRect = floatingTooltip.getBoundingClientRect();
            const maxLeft = window.innerWidth - tooltipRect.width - 8;
            const left = Math.min(rect.right + 14, maxLeft);
            const top = Math.min(
                Math.max(8, rect.top + (rect.height / 2) - (tooltipRect.height / 2)),
                window.innerHeight - tooltipRect.height - 8,
            );

            floatingTooltip.style.left = `${left}px`;
            floatingTooltip.style.top = `${top}px`;
        };

        const showTooltip = (target) => {
            if (!sidebarShell.classList.contains('is-collapsed') || !desktopMedia.matches) {
                hideTooltip();
                return;
            }

            const tooltipText = target.getAttribute('data-tooltip');

            if (!tooltipText) {
                hideTooltip();
                return;
            }

            activeTooltipTarget = target;
            floatingTooltip.textContent = tooltipText;
            floatingTooltip.classList.add('is-visible');
            positionTooltip(target);
        };

        const getStoredPreference = () => {
            const value = window.localStorage.getItem(collapseStorageKey);

            return value === null ? null : value === 'true';
        };

        const setDrawerState = (open) => {
            sidebarShell.classList.toggle('is-mobile-open', open);
            sidebarToggle?.setAttribute('aria-expanded', String(open));
            sidebarOverlay?.classList.toggle('hidden', !open);
            document.body.style.overflow = open ? 'hidden' : '';
        };

        const setCollapsedState = (collapsed, { persist = true } = {}) => {
            sidebarShell.classList.toggle('is-collapsed', collapsed && desktopMedia.matches);
            sidebarCollapse?.setAttribute('aria-pressed', String(collapsed && desktopMedia.matches));
            sidebarCollapse?.setAttribute('data-tooltip', collapsed && desktopMedia.matches ? 'Expand sidebar' : 'Collapse sidebar');
            hideTooltip();

            if (persist) {
                window.localStorage.setItem(collapseStorageKey, String(collapsed));
            }
        };

        const syncSidebarState = () => {
            const stored = getStoredPreference();

            if (!desktopMedia.matches) {
                sidebarShell.classList.remove('is-collapsed');
                sidebarCollapse?.setAttribute('aria-pressed', 'false');
                setDrawerState(false);
                return;
            }

            const shouldCollapse = stored ?? tabletMedia.matches;
            setCollapsedState(shouldCollapse, { persist: stored !== null });
        };

        sidebarToggle?.addEventListener('click', () => {
            const isOpen = sidebarShell.classList.contains('is-mobile-open');
            setDrawerState(!isOpen);
        });

        sidebarOverlay?.addEventListener('click', () => {
            setDrawerState(false);
        });

        sidebarCollapse?.addEventListener('click', () => {
            const nextState = !sidebarShell.classList.contains('is-collapsed');
            setCollapsedState(nextState);
        });

        sidebar.querySelectorAll('[data-sidebar-link], [data-sidebar-group-panel] a').forEach((link) => {
            link.addEventListener('click', () => {
                if (!desktopMedia.matches) {
                    setDrawerState(false);
                }
            });
        });

        sidebar.querySelectorAll('[data-tooltip]').forEach((target) => {
            target.addEventListener('mouseenter', () => {
                showTooltip(target);
            });

            target.addEventListener('focus', () => {
                showTooltip(target);
            });

            target.addEventListener('mouseleave', hideTooltip);
            target.addEventListener('blur', hideTooltip);
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                setDrawerState(false);
                hideTooltip();
            }
        });

        window.addEventListener('scroll', () => {
            if (activeTooltipTarget) {
                positionTooltip(activeTooltipTarget);
            }
        }, true);

        window.addEventListener('resize', () => {
            if (activeTooltipTarget) {
                if (!sidebarShell.classList.contains('is-collapsed') || !desktopMedia.matches) {
                    hideTooltip();
                    return;
                }

                positionTooltip(activeTooltipTarget);
            }
        });

        desktopMedia.addEventListener('change', syncSidebarState);
        tabletMedia.addEventListener('change', syncSidebarState);
        syncSidebarState();
    }

    (() => {
        const overlays = [...document.querySelectorAll('[data-mobile-sheet]')];

        if (overlays.length === 0) {
            return;
        }

        const closeAllSheets = () => {
            overlays.forEach((overlay) => {
                overlay.classList.add('hidden');
                overlay.setAttribute('aria-hidden', 'true');
            });

            document.querySelectorAll('[data-mobile-sheet-open]').forEach((trigger) => {
                trigger.setAttribute('aria-expanded', 'false');
            });

            document.body.style.overflow = '';
        };

        const openSheet = (name) => {
            const overlay = document.querySelector(`[data-mobile-sheet="${name}"]`);

            if (! overlay) {
                return;
            }

            closeAllSheets();
            overlay.classList.remove('hidden');
            overlay.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';

            document.querySelectorAll(`[data-mobile-sheet-open="${name}"]`).forEach((trigger) => {
                trigger.setAttribute('aria-expanded', 'true');
            });
        };

        document.querySelectorAll('[data-mobile-sheet-open]').forEach((trigger) => {
            trigger.addEventListener('click', () => {
                const name = trigger.getAttribute('data-mobile-sheet-open');
                const overlay = document.querySelector(`[data-mobile-sheet="${name}"]`);
                const isOpen = overlay && ! overlay.classList.contains('hidden');

                if (isOpen) {
                    closeAllSheets();
                } else {
                    openSheet(name);
                }
            });
        });

        document.querySelectorAll('[data-mobile-sheet-close]').forEach((trigger) => {
            trigger.addEventListener('click', closeAllSheets);
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeAllSheets();
            }
        });
    })();

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

    document.querySelectorAll('[data-flash]').forEach((banner) => {
        window.setTimeout(() => {
            banner.classList.add('is-dismissing');

            window.setTimeout(() => {
                banner.remove();
            }, 280);
        }, 4000);
    });

    document.querySelectorAll('[data-list-filter-form], [data-case-filter-form]').forEach((form) => {
        const searchInput = form.querySelector('[data-list-filter-search], [data-case-filter-search]');
        const selects = form.querySelectorAll('select[data-list-filter-select], [data-case-filter-status]');
        let submitTimer = null;

        const submitForm = (delay = 0) => {
            window.clearTimeout(submitTimer);
            submitTimer = window.setTimeout(() => {
                form.requestSubmit();
            }, delay);
        };

        searchInput?.addEventListener('input', () => {
            submitForm(350);
        });

        selects.forEach((select) => {
            select.addEventListener('change', () => {
                submitForm();
            });
        });
    });

    document.querySelectorAll('[data-category-registry]').forEach((panel) => {
        const searchInput = panel.querySelector('[data-category-registry-search]');
        const scrollArea = panel.querySelector('[data-category-registry-scroll]');
        const rows = [...panel.querySelectorAll('[data-category-registry-row]')];
        const emptyState = panel.querySelector('[data-category-registry-empty]');
        const emptyTitle = emptyState?.querySelector('.list-page__empty-title');
        const emptyMessage = emptyState?.querySelector('.list-page__empty-message');
        const footer = panel.querySelector('[data-category-registry-footer]');
        const threshold = Number(scrollArea?.dataset.categoryScrollThreshold || 5);

        if (! searchInput || rows.length === 0) {
            return;
        }

        const syncPanelState = (visibleCount) => {
            if (! scrollArea) {
                return;
            }

            const isEmpty = visibleCount === 0;
            scrollArea.classList.add('dashboard-category-table--framed');
            scrollArea.classList.toggle('dashboard-category-table--empty', isEmpty);
            scrollArea.classList.toggle('dashboard-category-table--scroll', ! isEmpty && visibleCount > threshold);
            emptyState?.classList.toggle('hidden', ! isEmpty);

            if (isEmpty && emptyTitle) {
                emptyTitle.textContent = 'No categories match this search';
            }

            if (isEmpty && emptyMessage) {
                emptyMessage.textContent = 'Try another category code or name.';
            }
        };

        const syncFooter = (visibleCount) => {
            if (! footer) {
                return;
            }

            if (visibleCount === 0) {
                footer.textContent = 'No matching categories';
                return;
            }

            const label = visibleCount === 1 ? 'category' : 'categories';
            footer.textContent = `Showing ${visibleCount} ${label} with activity`;
        };

        const applyFilter = () => {
            const query = searchInput.value.trim().toLowerCase();
            let visibleCount = 0;

            rows.forEach((row) => {
                const haystack = row.dataset.categorySearch || '';
                const matches = query === '' || haystack.includes(query);
                row.classList.toggle('hidden', ! matches);

                if (matches) {
                    visibleCount += 1;
                }
            });

            syncPanelState(visibleCount);
            syncFooter(visibleCount);
        };

        searchInput.addEventListener('input', applyFilter);
        syncPanelState(rows.length);
    });

    document.addEventListener('click', (event) => {
        const row = event.target.closest('[data-row-href]');

        if (! row || event.target.closest('a, button, input, label, select, textarea, [data-row-ignore]')) {
            return;
        }

        const href = row.getAttribute('data-row-href');

        if (! href) {
            return;
        }

        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button === 1) {
            window.open(href, '_blank');

            return;
        }

        window.location.assign(href);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }

        const row = event.target.closest('[data-row-href]');

        if (! row || event.target !== row) {
            return;
        }

        const href = row.getAttribute('data-row-href');

        if (! href) {
            return;
        }

        event.preventDefault();
        window.location.assign(href);
    });

    document.querySelectorAll('[data-infinite-list]').forEach((list) => {
        const sentinel = list.parentElement?.querySelector('[data-infinite-sentinel]');
        const status = list.parentElement?.querySelector('[data-infinite-status]');
        const modalsHost = document.querySelector('[data-infinite-modals]');
        const windowSize = Number(list.dataset.infiniteWindow || 100);
        let nextPage = list.dataset.infiniteNext || '';
        let loading = false;

        if (! sentinel || ! nextPage) {
            return;
        }

        const setStatus = (message) => {
            if (! status) {
                return;
            }

            if (! message) {
                status.hidden = true;
                status.textContent = '';

                return;
            }

            status.hidden = false;
            status.textContent = message;
        };

        const bindApprovalForm = (form) => {
            const overrideToggle = form.querySelector('[data-override-toggle]');
            const overridePanel = form.querySelector('[data-override-panel]');
            const positionIdInput = form.querySelector('[data-position-id]');
            const positionSelect = form.querySelector('[data-position-select]');

            const syncPositionId = () => {
                if (! positionIdInput || ! positionSelect) {
                    return;
                }

                positionIdInput.value = positionSelect.value;
            };

            const setOverrideState = (expanded) => {
                if (! overrideToggle || ! overridePanel || ! positionIdInput || ! positionSelect) {
                    return;
                }

                overrideToggle.checked = expanded;
                overridePanel.classList.toggle('hidden', ! expanded);

                if (! expanded) {
                    const defaultValue = positionIdInput.dataset.defaultPosition || positionSelect.value;
                    positionSelect.value = defaultValue;
                }

                syncPositionId();
            };

            overrideToggle?.addEventListener('change', () => {
                setOverrideState(overrideToggle.checked);
            });

            positionSelect?.addEventListener('change', syncPositionId);
            syncPositionId();
        };

        const bindModalShell = (modal) => {
            modal.addEventListener('click', (event) => {
                if (event.target === modal) {
                    closeModal(modal);
                }
            });

            modal.querySelectorAll('[data-modal-close]').forEach((trigger) => {
                trigger.addEventListener('click', () => {
                    closeModal(modal);
                });
            });

            modal.querySelectorAll('[data-approval-form]').forEach((form) => {
                bindApprovalForm(form);
            });
        };

        const trimWindow = () => {
            const cards = [...list.children];
            const overflow = cards.length - windowSize;

            if (overflow <= 0) {
                return;
            }

            let removedHeight = 0;
            cards.slice(0, overflow).forEach((card) => {
                removedHeight += card.getBoundingClientRect().height;
                const style = window.getComputedStyle(card);
                removedHeight += Number.parseFloat(style.marginTop) + Number.parseFloat(style.marginBottom);

                const infiniteId = card.dataset.infiniteId;
                if (infiniteId && modalsHost) {
                    modalsHost.querySelectorAll(`[data-infinite-id="${infiniteId}"]`).forEach((modal) => {
                        modal.remove();
                    });
                }

                card.remove();
            });

            if (removedHeight > 0) {
                window.scrollBy(0, -removedHeight);
            }
        };

        const loadMore = async () => {
            if (loading || ! nextPage) {
                return;
            }

            loading = true;
            setStatus('Loading more…');

            const url = new URL(window.location.href);
            url.searchParams.set('feed', '1');
            url.searchParams.set('page', nextPage);

            try {
                const response = await fetch(url.toString(), {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (! response.ok) {
                    throw new Error(`Feed failed with ${response.status}`);
                }

                const data = await response.json();
                const template = document.createElement('template');
                template.innerHTML = String(data.html || '').trim();
                list.append(template.content);
                trimWindow();

                if (modalsHost && data.modals) {
                    const modalsTemplate = document.createElement('template');
                    modalsTemplate.innerHTML = String(data.modals).trim();
                    const newModals = [...modalsTemplate.content.querySelectorAll('[data-modal]')];
                    modalsHost.append(modalsTemplate.content);
                    newModals.forEach((modal) => {
                        bindModalShell(modal);
                    });
                }

                nextPage = data.next_page ? String(data.next_page) : '';
                list.dataset.infiniteNext = nextPage;

                if (! nextPage) {
                    sentinel.classList.add('hidden');
                }

                setStatus('');
            } catch (error) {
                window.console.error(error);
                setStatus('Could not load more. Scroll to retry.');
            } finally {
                loading = false;
            }
        };

        const observer = new IntersectionObserver((entries) => {
            if (entries.some((entry) => entry.isIntersecting)) {
                void loadMore();
            }
        }, {
            rootMargin: '240px 0px',
        });

        observer.observe(sentinel);
    });

    document.querySelectorAll('[data-history-filter-form]').forEach((form) => {
        const periodSelect = form.querySelector('[data-history-period]');
        const specificDateField = form.querySelector('[data-history-date]');
        const rangeFromField = form.querySelector('[data-history-from]');
        const rangeToField = form.querySelector('[data-history-to]');

        const syncHistoryFilters = () => {
            const period = periodSelect?.value;
            const showSpecificDate = period === 'specific_date';
            const showCustomRange = period === 'custom_range';

            specificDateField?.classList.toggle('hidden', ! showSpecificDate);
            rangeFromField?.classList.toggle('hidden', ! showCustomRange);
            rangeToField?.classList.toggle('hidden', ! showCustomRange);
        };

        syncHistoryFilters();
        periodSelect?.addEventListener('change', syncHistoryFilters);
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

    document.querySelectorAll('[data-approval-form]').forEach((form) => {
        const overrideToggle = form.querySelector('[data-override-toggle]');
        const overridePanel = form.querySelector('[data-override-panel]');
        const positionIdInput = form.querySelector('[data-position-id]');
        const positionSelect = form.querySelector('[data-position-select]');

        const syncPositionId = () => {
            if (!positionIdInput || !positionSelect) {
                return;
            }

            positionIdInput.value = positionSelect.value;
        };

        const setOverrideState = (expanded) => {
            if (!overrideToggle || !overridePanel || !positionIdInput || !positionSelect) {
                return;
            }

            overrideToggle.checked = expanded;
            overridePanel.classList.toggle('hidden', !expanded);

            if (!expanded) {
                const defaultValue = positionIdInput.dataset.defaultPosition || positionSelect.value;
                positionSelect.value = defaultValue;
            }

            syncPositionId();
        };

        overrideToggle?.addEventListener('change', () => {
            setOverrideState(overrideToggle.checked);
        });

        positionSelect?.addEventListener('change', syncPositionId);

        syncPositionId();
    });

    document.querySelectorAll('[data-inline-edit]').forEach((form) => {
        const editTrigger = form.querySelector('[data-inline-edit-trigger]');
        const actionGroup = form.querySelector('[data-inline-edit-actions]');
        const cancelTrigger = form.querySelector('[data-inline-cancel]');
        const displays = form.querySelectorAll('[data-inline-display]');
        const inputs = form.querySelectorAll('[data-inline-input]');

        if (!editTrigger || !actionGroup || !inputs.length) {
            return;
        }

        const resetValues = () => {
            inputs.forEach((input) => {
                if (input.dataset.initialValue === undefined) {
                    input.dataset.initialValue = input.value;
                }

                input.value = input.dataset.initialValue;
            });
        };

        const setEditing = (editing) => {
            displays.forEach((display) => {
                display.classList.toggle('hidden', editing);
            });

            inputs.forEach((input) => {
                input.classList.toggle('hidden', !editing);
            });

            editTrigger.classList.toggle('hidden', editing);
            actionGroup.classList.toggle('hidden', !editing);
            actionGroup.classList.toggle('flex', editing);

            if (!editing) {
                resetValues();
            }
        };

        resetValues();
        setEditing(false);

        editTrigger.addEventListener('click', () => {
            setEditing(true);
            inputs[0]?.focus();
        });

        cancelTrigger?.addEventListener('click', () => {
            setEditing(false);
        });
    });

    let activeModal = null;

    const closeModal = (modal) => {
        if (! modal) {
            return;
        }

        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');

        if (activeModal === modal) {
            document.body.classList.remove('overflow-hidden');
            activeModal = null;
        }
    };

    const openModal = (modal) => {
        if (! modal) {
            return;
        }

        if (activeModal && activeModal !== modal) {
            closeModal(activeModal);
        }

        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');
        activeModal = modal;
        modal.querySelector('input, select, textarea, button, a')?.focus();
    };

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-modal-open]');
        if (! trigger) {
            return;
        }

        openModal(document.querySelector(`[data-modal="${trigger.dataset.modalOpen}"]`));
    });

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-storage-label-print-url]');
        if (! trigger) {
            return;
        }

        let frame = document.getElementById('storage-label-print-frame');
        if (! frame) {
            frame = document.createElement('iframe');
            frame.id = 'storage-label-print-frame';
            frame.title = 'Storage label print';
            frame.setAttribute('aria-hidden', 'true');
            frame.style.cssText = 'position:fixed;left:-10000px;top:0;width:80mm;height:40mm;border:0;opacity:0;pointer-events:none;';
            document.body.appendChild(frame);
        }

        const rackSign = trigger.dataset.storageLabelKind === 'rack';
        frame.style.width = rackSign ? '210mm' : '80mm';
        frame.style.height = rackSign ? '297mm' : '40mm';

        const url = new URL(trigger.dataset.storageLabelPrintUrl, window.location.origin);
        url.searchParams.set('embed', '1');
        url.searchParams.set('_print', Date.now().toString());
        frame.onload = () => {
            frame.contentWindow?.focus();
            frame.contentWindow?.print();
        };
        frame.src = url.href;
    });

    document.querySelectorAll('[data-file-label-modal]').forEach((modal) => {
        const printButton = modal.querySelector('[data-file-label-print]');
        const pdfLink = modal.querySelector('[data-file-label-pdf]');
        const typeButtons = [...modal.querySelectorAll('[data-file-label-type]')];
        const previews = [...modal.querySelectorAll('[data-file-label-preview]')];
        const printBase = modal.getAttribute('data-print-url') || '';
        const pdfBase = modal.getAttribute('data-pdf-url') || '';
        let selectedType = 'front';

        const withType = (url, type) => {
            const next = new URL(url, window.location.origin);
            next.searchParams.set('type', type);

            return next.pathname + next.search;
        };

        const showPreview = (type) => {
            previews.forEach((preview) => {
                preview.classList.toggle('hidden', preview.getAttribute('data-file-label-preview') !== type);
            });
        };

        const sync = () => {
            typeButtons.forEach((button) => {
                button.classList.toggle('is-active', button.getAttribute('data-file-label-type') === selectedType);
            });

            showPreview(selectedType);

            if (printButton) {
                printButton.setAttribute('data-print-href', withType(printBase, selectedType));
            }

            if (pdfLink) {
                pdfLink.href = withType(pdfBase, selectedType);
            }
        };

        const printLabel = (url) => {
            let frame = document.getElementById('file-label-print-frame');
            if (! frame) {
                frame = document.createElement('iframe');
                frame.id = 'file-label-print-frame';
                frame.setAttribute('aria-hidden', 'true');
                frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;opacity:0;pointer-events:none;';
                document.body.appendChild(frame);
            }

            const runPrint = () => {
                try {
                    frame.contentWindow?.focus();
                    frame.contentWindow?.print();
                } catch (error) {
                    window.open(url, '_blank', 'noopener');
                }
            };

            frame.onload = runPrint;
            frame.src = url + (url.includes('?') ? '&' : '?') + '_print=' + Date.now();
        };

        typeButtons.forEach((button) => {
            button.addEventListener('click', () => {
                selectedType = button.getAttribute('data-file-label-type') || 'front';
                sync();
            });
        });

        printButton?.addEventListener('click', () => {
            const href = printButton.getAttribute('data-print-href') || withType(printBase, selectedType);
            printLabel(href);
        });

        sync();
    });

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-reject-completion]');
        if (! trigger) {
            return;
        }

        const modal = document.querySelector('[data-modal="reject-completion"]');
        const form = modal?.querySelector('[data-reject-completion-form]');
        const ref = modal?.querySelector('[data-reject-completion-ref]');
        const notes = form?.querySelector('textarea[name="notes"]');

        if (! modal || ! form) {
            return;
        }

        form.setAttribute('action', trigger.dataset.rejectUrl || '');
        if (ref) {
            ref.textContent = trigger.dataset.rejectRef || '';
        }
        if (notes) {
            notes.value = '';
        }

        openModal(modal);
    });

    document.querySelectorAll('[data-modal]').forEach((modal) => {
        if (! modal.classList.contains('hidden')) {
            activeModal = modal;
            document.body.classList.add('overflow-hidden');
        }

        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeModal(modal);
            }
        });

        modal.querySelectorAll('[data-modal-close]').forEach((trigger) => {
            trigger.addEventListener('click', () => {
                closeModal(modal);
            });
        });
    });

    document.querySelectorAll('[data-case-type-form]').forEach((form) => {
        const select = form.querySelector('[data-case-type-select]');
        const panels = Array.from(form.querySelectorAll('[data-case-type-panel]'));

        const syncCaseTypePanels = (type) => {
            panels.forEach((panel) => {
                const active = panel.dataset.caseTypePanel === type;
                panel.classList.toggle('hidden', ! active);
                panel.querySelectorAll('input, textarea, select').forEach((field) => {
                    field.disabled = ! active;
                    if (field.hasAttribute('data-case-type-required')) {
                        field.required = active;
                    }
                });
            });
        };

        const initialType = select?.value
            || panels.find((panel) => ! panel.classList.contains('hidden'))?.dataset.caseTypePanel
            || '';

        syncCaseTypePanels(initialType);

        select?.addEventListener('change', () => {
            syncCaseTypePanels(select.value);
        });
    });

    document.querySelectorAll('[data-related-files]').forEach((root) => {
        const searchInput = root.querySelector('[data-related-search]');
        const addButton = root.querySelector('[data-related-add]');
        const suggestions = root.querySelector('[data-related-suggestions]');
        const list = root.querySelector('[data-related-list]');
        const emptyState = root.querySelector('[data-related-empty]');
        const feedback = root.querySelector('[data-related-feedback]');

        if (! searchInput || ! addButton || ! suggestions || ! list) {
            return;
        }

        let linkable = [];
        try {
            linkable = JSON.parse(root.getAttribute('data-linkable-files') || '[]');
        } catch {
            linkable = [];
        }

        let activeIndex = -1;
        let matches = [];

        const selectedIds = () => new Set(
            Array.from(list.querySelectorAll('[data-related-id]')).map((item) => Number(item.dataset.relatedId)),
        );

        const setFeedback = (message = '') => {
            if (feedback) {
                feedback.textContent = message;
            }
        };

        const syncEmptyState = () => {
            const hasItems = list.children.length > 0;
            emptyState?.classList.toggle('hidden', hasItems);
        };

        const hideSuggestions = () => {
            suggestions.innerHTML = '';
            suggestions.classList.add('hidden');
            suggestions.hidden = true;
            activeIndex = -1;
            matches = [];
        };

        const labelFor = (file) => `${file.reference_number} — ${file.purchaser || '—'}`;

        const renderSuggestions = () => {
            suggestions.innerHTML = '';

            if (matches.length === 0) {
                hideSuggestions();

                return;
            }

            matches.forEach((file, index) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = `related-files-suggestions__item${index === activeIndex ? ' is-active' : ''}`;
                button.setAttribute('role', 'option');
                button.textContent = labelFor(file);
                button.addEventListener('mousedown', (event) => {
                    event.preventDefault();
                    addFile(file);
                });
                suggestions.appendChild(button);
            });

            suggestions.classList.remove('hidden');
            suggestions.hidden = false;
        };

        const filterFiles = (query) => {
            const needle = query.trim().toLowerCase();
            if (needle.length < 1) {
                return [];
            }

            const selected = selectedIds();

            return linkable
                .filter((file) => ! selected.has(Number(file.id)))
                .filter((file) => {
                    const haystack = `${file.reference_number} ${file.purchaser || ''}`.toLowerCase();

                    return haystack.includes(needle);
                })
                .slice(0, 8);
        };

        const addFile = (file) => {
            if (! file || selectedIds().has(Number(file.id))) {
                setFeedback(file ? 'That file is already linked.' : 'No matching file found.');
                hideSuggestions();

                return;
            }

            const item = document.createElement('li');
            item.className = 'related-files-list__item';
            item.dataset.relatedId = String(file.id);
            item.innerHTML = `
                <div class="min-w-0">
                    <p class="font-semibold text-stone-800"></p>
                    <p class="truncate text-sm text-stone-500"></p>
                </div>
                <button class="btn-secondary" type="button" data-related-remove>Remove</button>
                <input type="hidden" name="related_file_ids[]" value="${Number(file.id)}">
            `;
            item.querySelector('p.font-semibold').textContent = file.reference_number;
            item.querySelector('p.text-sm').textContent = file.purchaser || '—';
            list.appendChild(item);

            searchInput.value = '';
            hideSuggestions();
            setFeedback('');
            syncEmptyState();
            searchInput.focus();
        };

        const resolveFromQuery = () => {
            const needle = searchInput.value.trim().toLowerCase();
            if (! needle) {
                return null;
            }

            if (matches.length === 1) {
                return matches[0];
            }

            if (activeIndex >= 0 && matches[activeIndex]) {
                return matches[activeIndex];
            }

            const selected = selectedIds();
            const exact = linkable.find((file) => {
                if (selected.has(Number(file.id))) {
                    return false;
                }

                return String(file.reference_number).toLowerCase() === needle;
            });

            return exact || null;
        };

        searchInput.addEventListener('input', () => {
            matches = filterFiles(searchInput.value);
            activeIndex = matches.length ? 0 : -1;
            renderSuggestions();
            setFeedback(matches.length || ! searchInput.value.trim() ? '' : 'No matching files.');
        });

        searchInput.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown' && matches.length) {
                event.preventDefault();
                activeIndex = (activeIndex + 1) % matches.length;
                renderSuggestions();
            } else if (event.key === 'ArrowUp' && matches.length) {
                event.preventDefault();
                activeIndex = (activeIndex - 1 + matches.length) % matches.length;
                renderSuggestions();
            } else if (event.key === 'Enter') {
                event.preventDefault();
                addFile(resolveFromQuery());
            } else if (event.key === 'Escape') {
                hideSuggestions();
            }
        });

        searchInput.addEventListener('blur', () => {
            window.setTimeout(hideSuggestions, 150);
        });

        addButton.addEventListener('click', () => {
            addFile(resolveFromQuery());
        });

        list.addEventListener('click', (event) => {
            const removeButton = event.target.closest('[data-related-remove]');
            if (! removeButton) {
                return;
            }

            removeButton.closest('[data-related-id]')?.remove();
            syncEmptyState();
            setFeedback('');
        });

        syncEmptyState();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && activeModal) {
            closeModal(activeModal);
        }
    });

    document.querySelectorAll('[data-category-edit-form]').forEach((form) => {
        const categoryId = form.dataset.categoryId;
        const confirmModal = document.querySelector(`[data-modal="confirm-edit-category-${categoryId}"]`);
        const confirmSubmit = confirmModal?.querySelector('[data-confirm-edit-submit]');
        const confirmDetails = confirmModal?.querySelector('[data-confirm-edit-details]');
        const confirmField = form.querySelector('[data-confirm-linked-updates]');
        const nameInput = form.querySelector('[data-category-name]');
        const codeInput = form.querySelector('[data-category-code]');
        const linkedFiles = Number(form.dataset.linkedFiles || '0');
        const originalName = form.dataset.originalName || '';
        const originalCode = (form.dataset.originalCode || '').toUpperCase();

        form.addEventListener('submit', (event) => {
            if (! confirmField || linkedFiles < 1 || ! confirmModal) {
                return;
            }

            if (confirmField.value === '1') {
                return;
            }

            const nextName = (nameInput?.value || '').trim();
            const nextCode = (codeInput?.value || '').trim().toUpperCase();
            const nameChanged = nextName !== originalName;
            const codeChanged = nextCode !== originalCode;

            if (! nameChanged && ! codeChanged) {
                return;
            }

            event.preventDefault();

            const parts = [];
            if (nameChanged) {
                parts.push(`Category label on linked files will become “${nextName}”.`);
            }
            parts.push(
                `File numbers matching CODE/YEAR/NUMBER will be rewritten to use “${nextCode}” (year and running number stay the same).`,
            );
            if (confirmDetails) {
                confirmDetails.textContent = parts.join(' ');
            }

            openModal(confirmModal);
        });

        confirmSubmit?.addEventListener('click', () => {
            if (confirmField) {
                confirmField.value = '1';
            }
            closeModal(confirmModal);
            form.requestSubmit();
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

    const shelfPrefix = 'LRMS:SHELF:1:';
    const uuidPattern = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
    const shelfScan = document.getElementById('shelf_scan');
    const shelfQr = document.getElementById('shelf_qr');
    const status = document.getElementById('scanner-status');
    let shelfLookupTimer;
    let shelfLookupVersion = 0;

    const resolveShelf = async (payload) => {
        const version = ++shelfLookupVersion;
        shelfQr.value = '';
        if (!payload.startsWith(shelfPrefix) || !uuidPattern.test(payload.slice(shelfPrefix.length))) {
            status.textContent = 'Scan a valid shelf QR code.';
            return;
        }

        status.textContent = 'Checking shelf…';
        try {
            const identifier = payload.slice(shelfPrefix.length);
            const response = await fetch(shelfScan.dataset.lookupUrl.replace('__IDENTIFIER__', identifier), {
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) throw new Error('Shelf lookup failed');
            const shelf = await response.json();
            if (version !== shelfLookupVersion) return;
            shelfQr.value = payload;
            shelfScan.value = shelf.location;
            status.textContent = '';
        } catch {
            if (version !== shelfLookupVersion) return;
            status.textContent = 'Shelf QR is invalid or the shelf is inactive.';
        }
    };

    if (shelfScan) {
        if (shelfScan.dataset.initialQr) resolveShelf(shelfScan.dataset.initialQr);
        shelfScan.addEventListener('focus', () => shelfScan.select());
        shelfScan.addEventListener('input', () => {
            clearTimeout(shelfLookupTimer);
            shelfLookupVersion++;
            shelfQr.value = '';
            shelfLookupTimer = setTimeout(() => resolveShelf(shelfScan.value.trim()), 180);
        });
        shelfScan.form.addEventListener('submit', (event) => {
            if (shelfQr.value) return;
            event.preventDefault();
            status.textContent = 'Scan a valid shelf QR code before storing the file.';
            shelfScan.focus();
        });
    }
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' || !event.target.matches('input[data-qr-kind]')) return;
        event.preventDefault();
        if (event.target === shelfScan) {
            clearTimeout(shelfLookupTimer);
            resolveShelf(shelfScan.value.trim());
        }
    });

    const { Html5Qrcode } = await import('html5-qrcode');
    const scanner = new Html5Qrcode('qr-reader');
    let activeTarget = null;
    let activeButton = null;
    let running = false;

    const stop = async () => {
        if (running) {
            await scanner.stop();
            scanner.clear();
            running = false;
        }
        if (activeButton) {
            activeButton.textContent = activeButton.dataset.originalLabel;
            activeButton = null;
        }
    };

    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-scan-target]');
        if (!button) return;
            if (running && activeTarget?.id === button.dataset.scanTarget) {
                await stop();
                reader.classList.add('hidden');
                status.textContent = 'Camera stopped.';
                return;
            }
            activeTarget = document.getElementById(button.dataset.scanTarget);
            if (!activeTarget) return;
            await stop();
            activeButton = button;
            button.dataset.originalLabel ??= button.textContent;
            reader.classList.remove('hidden');
            status.textContent = 'Camera active — hold the QR code inside the frame.';
            try {
                await scanner.start(
                    { facingMode: 'environment' },
                    { fps: 12, qrbox: { width: 240, height: 240 } },
                    async (value) => {
                        const kind = activeTarget.dataset.qrKind;
                        const valid = kind === 'shelf'
                            ? value.startsWith(shelfPrefix) && uuidPattern.test(value.slice(shelfPrefix.length))
                            : !kind || uuidPattern.test(value);
                        if (!valid) {
                            status.textContent = `Wrong QR type. Scan a ${kind === 'shelf' ? 'shelf' : 'file or staff'} QR.`;
                            return;
                        }
                        if (activeTarget.tagName === 'TEXTAREA') {
                            const values = activeTarget.value.split(/\s+/).filter(Boolean);
                            if (! values.includes(value)) {
                                values.push(value);
                                activeTarget.value = values.join('\n');
                                status.textContent = `${values.length} file QR code(s) captured. Scan another file or tap Scan file to stop.`;
                            } else {
                                status.textContent = 'File already scanned. Scan another file or tap Scan file to stop.';
                            }
                            return;
                        } else {
                            activeTarget.value = value;
                        }
                        if (kind === 'shelf') {
                            await resolveShelf(value);
                        } else {
                            status.textContent = 'QR captured successfully.';
                        }
                        await stop();
                        reader.classList.add('hidden');
                        activeTarget.focus();
                    },
                );
                running = true;
                if (activeTarget.tagName === 'TEXTAREA') button.textContent = 'Stop scanning';
            } catch {
                await stop();
                status.textContent = 'Camera unavailable. Use a USB scanner or enter the QR identifier.';
                reader.classList.add('hidden');
            }
    });
});
