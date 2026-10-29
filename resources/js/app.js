import {
    docZoomAnchorScroll,
    docZoomCanPan,
    docZoomCenterScroll,
    docZoomImageMargin,
    docZoomOverflows,
} from './doc-zoom-layout.js';

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
    const entryMore = document.querySelector('[data-entry-more]');
    if (entryMore) {
        const moreToggle = entryMore.querySelector('[data-entry-more-toggle]');
        const morePanel = entryMore.querySelector('[data-entry-more-panel]');

        const closeEntryMore = () => {
            morePanel?.classList.add('hidden');
            moreToggle?.setAttribute('aria-expanded', 'false');
        };

        moreToggle?.addEventListener('click', (event) => {
            event.stopPropagation();
            const open = morePanel?.classList.toggle('hidden') === false;
            moreToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        document.addEventListener('click', (event) => {
            if (! entryMore.contains(event.target)) {
                closeEntryMore();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeEntryMore();
            }
        });
    }

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

    // Windows Chrome can still paint OS accent selection on clickable rows even with
    // user-select:none — that dark navy + dark ink makes cell text disappear.
    const isEntryInteractiveRow = (node) => Boolean(
        node?.closest?.(
            '.entry-ops__table tbody tr, .entry-print-station__table tbody tr, button.entry-card--action, tr.table-row-link',
        ),
    );

    document.addEventListener('selectstart', (event) => {
        if (isEntryInteractiveRow(event.target)) {
            event.preventDefault();
        }
    });

    document.addEventListener('mousedown', (event) => {
        if (! isEntryInteractiveRow(event.target)) {
            return;
        }

        const selection = window.getSelection?.();
        if (selection && selection.rangeCount > 0) {
            selection.removeAllRanges();
        }
    });

    // Chrome blank-print traps (device-dependent):
    // - display:none / width:0 / visibility:hidden → empty or partial sheets
    // - CSS mm on iframe boxes → 0×0 on some DPI/browser builds
    // Keep a real px box off-screen, visible to the print engine.
    const mmToPx = (mm) => `${Math.ceil((Number(mm) * 96) / 25.4)}px`;

    const ensurePrintFrame = (id, { width, height, title = 'Print' } = {}) => {
        let frame = document.getElementById(id);
        if (! frame) {
            frame = document.createElement('iframe');
            frame.id = id;
            frame.title = title;
            frame.setAttribute('aria-hidden', 'true');
            document.body.appendChild(frame);
        }

        frame.style.cssText = [
            'position:fixed',
            'left:-10000px',
            'top:0',
            `width:${width}`,
            `height:${height}`,
            'border:0',
            'opacity:0.01',
            'pointer-events:none',
            'z-index:-1',
        ].join(';');

        return frame;
    };

    const fileLabelPrintFrameSize = (url) => {
        try {
            const type = new URL(url, window.location.origin).searchParams.get('type') || 'front';
            if (type === 'side') {
                return { width: mmToPx(80), height: mmToPx(40) };
            }
            if (type === 'both') {
                return { width: mmToPx(150), height: mmToPx(200) };
            }
        } catch (_error) {
            // fall through to front size
        }

        return { width: mmToPx(150), height: mmToPx(100) };
    };

    const sameOriginPrintUrl = (url) => {
        try {
            const parsed = new URL(url, window.location.origin);
            // Always print against the host the user is on (ignore stale APP_URL absolutes).
            return `${parsed.pathname}${parsed.search}${parsed.hash}`;
        } catch (_error) {
            return url;
        }
    };

    const waitForPrintFrameReady = (frame, timeoutMs = 3000) => {
        const doc = frame.contentDocument;
        if (! doc) {
            // Cross-origin iframe: cannot inspect; caller should fall back to window.open.
            return Promise.reject(new Error('print-frame-cross-origin'));
        }

        const images = [...doc.images];
        const imagesReady = Promise.all(images.map((img) => {
            if (img.complete) {
                return Promise.resolve();
            }

            return new Promise((resolve) => {
                img.addEventListener('load', resolve, { once: true });
                img.addEventListener('error', resolve, { once: true });
            });
        }));

        // ponytail: 3s ceiling so a hung QR never blocks the print dialog forever.
        return Promise.race([
            imagesReady,
            new Promise((resolve) => window.setTimeout(resolve, timeoutMs)),
        ]);
    };

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-storage-label-print-url]');
        if (! trigger) {
            return;
        }

        const rackSign = trigger.dataset.storageLabelKind === 'rack';
        const frame = ensurePrintFrame('storage-label-print-frame', {
            width: rackSign ? mmToPx(210) : mmToPx(80),
            height: rackSign ? mmToPx(297) : mmToPx(40),
            title: 'Storage label print',
        });

        const url = new URL(
            sameOriginPrintUrl(trigger.dataset.storageLabelPrintUrl || ''),
            window.location.origin,
        );
        url.searchParams.set('embed', '1');
        url.searchParams.set('_print', Date.now().toString());
        frame.onload = () => {
            waitForPrintFrameReady(frame).then(() => {
                frame.contentWindow?.focus();
                frame.contentWindow?.print();
            }).catch(() => {
                window.open(url.pathname + url.search, '_blank', 'noopener');
            });
        };
        frame.src = url.pathname + url.search;
    });

    document.querySelectorAll('[data-file-label-modal]').forEach((modal) => {
        const printButton = modal.querySelector('[data-file-label-print]');
        const pdfLink = modal.querySelector('[data-file-label-pdf]');
        const deleteButton = modal.querySelector('[data-file-label-delete]');
        const typeButtons = [...modal.querySelectorAll('[data-file-label-type]')];
        const previews = [...modal.querySelectorAll('[data-file-label-preview]')];
        const dimLabels = [...modal.querySelectorAll('[data-file-label-dims-for]')];
        const hintLabels = [...modal.querySelectorAll('[data-file-label-hint-for]')];
        const documentImage = modal.querySelector('[data-file-label-document]');
        const previewWrap = modal.querySelector('[data-file-label-preview-wrap]');
        const queueStatus = modal.querySelector('[data-file-label-queue-status]');
        const printBase = modal.getAttribute('data-print-url') || '';
        const pdfBase = modal.getAttribute('data-pdf-url') || '';
        const queueUrl = modal.getAttribute('data-print-queue-url') || '';
        const legalFileId = modal.getAttribute('data-legal-file-id') || '';
        let selectedType = modal.getAttribute('data-file-label-initial-type') || 'front';
        let queueBusy = false;

        const withType = (url, type) => {
            const next = new URL(url, window.location.origin);
            next.searchParams.set('type', type);

            return next.pathname + next.search;
        };

        const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        const setQueueStatus = (message, kind = '') => {
            if (! queueStatus) {
                return;
            }

            queueStatus.textContent = message || '';
            queueStatus.classList.toggle('hidden', ! message);
            queueStatus.classList.toggle('is-ok', kind === 'ok');
            queueStatus.classList.toggle('is-error', kind === 'error');
        };

        const showQueueSuccessModal = (message, alreadyQueued = false) => {
            const successModal = document.querySelector('[data-modal="entry-print-queued"]');
            if (! successModal) {
                setQueueStatus(message, 'ok');
                return;
            }

            const title = successModal.querySelector('[data-print-queue-success-title]');
            const body = successModal.querySelector('[data-print-queue-success-message]');
            if (title) {
                title.textContent = alreadyQueued ? 'Sudah dihantar' : 'Berjaya dihantar';
            }
            if (body) {
                body.textContent = message || (alreadyQueued
                    ? 'Job sudah dihantar ke stesen cetak.'
                    : 'Job berjaya dihantar ke stesen cetak.');
            }

            setQueueStatus('');
            openModal(successModal);
        };

        const fitPreview = (preview) => {
            const scaleEl = preview.querySelector('.file-label-preview-scale');
            if (! scaleEl || preview.classList.contains('hidden')) {
                return;
            }

            // transform does not change layout size, so offsetWidth is the natural sticker width.
            const naturalPx = scaleEl.offsetWidth;
            const width = preview.clientWidth;
            if (width > 0 && naturalPx > 0) {
                scaleEl.style.setProperty('--file-label-scale', String(width / naturalPx));
            }
        };

        const fitVisiblePreview = () => {
            previews.forEach((preview) => fitPreview(preview));
        };

        const showPreview = (type) => {
            previews.forEach((preview) => {
                preview.classList.toggle('hidden', preview.getAttribute('data-file-label-preview') !== type);
            });
        };

        const ensureDocumentLoaded = () => {
            if (! documentImage) {
                return;
            }

            const src = documentImage.getAttribute('data-src') || '';
            if (src !== '' && ! documentImage.getAttribute('src')) {
                documentImage.setAttribute('src', src);
            }
        };

        const sync = () => {
            const isDocument = selectedType === 'document';
            const isData = selectedType === 'data';
            const hidePrintActions = isDocument || isData;

            typeButtons.forEach((button) => {
                button.classList.toggle('is-active', button.getAttribute('data-file-label-type') === selectedType);
            });

            dimLabels.forEach((label) => {
                label.classList.toggle('hidden', label.getAttribute('data-file-label-dims-for') !== selectedType);
            });

            hintLabels.forEach((hint) => {
                const forKind = hint.getAttribute('data-file-label-hint-for');
                const show = forKind === selectedType
                    || (forKind === 'label' && (selectedType === 'front' || selectedType === 'side'));
                hint.classList.toggle('hidden', ! show);
            });

            showPreview(selectedType);

            previewWrap?.classList.toggle('file-label-modal__preview-wrap--form', isData);
            previewWrap?.classList.toggle('file-label-modal__preview-wrap--document', isDocument);

            const overlayOpen = ! modal.closest('[data-modal]')?.classList.contains('hidden');
            // Load scan image when visible: Dokumen tab, or operator review (always under data).
            if (overlayOpen && (isDocument || (isData && modal.classList.contains('operator-review-modal')))) {
                ensureDocumentLoaded();
                if (isDocument) {
                    // Refit zoom after the dokumen pane becomes visible.
                    requestAnimationFrame(() => {
                        documentImage?.dispatchEvent(new Event('load'));
                    });
                }
            }

            if (printButton) {
                printButton.classList.toggle('hidden', hidePrintActions);
                if (! hidePrintActions) {
                    printButton.setAttribute('data-print-href', withType(printBase, selectedType));
                }
            }

            if (pdfLink) {
                pdfLink.classList.toggle('hidden', hidePrintActions);
                if (! hidePrintActions) {
                    pdfLink.href = withType(pdfBase, selectedType);
                }
            }

            if (deleteButton) {
                deleteButton.classList.toggle('hidden', hidePrintActions);
            }

            if (! hidePrintActions) {
                requestAnimationFrame(fitVisiblePreview);
            }
        };

        const printLabel = (url) => {
            const href = sameOriginPrintUrl(url);
            const frame = ensurePrintFrame('file-label-print-frame', {
                ...fileLabelPrintFrameSize(href),
                title: 'File label print',
            });

            frame.onload = () => {
                waitForPrintFrameReady(frame).then(() => {
                    try {
                        frame.contentWindow?.focus();
                        frame.contentWindow?.print();
                    } catch (error) {
                        window.open(href, '_blank', 'noopener');
                    }
                }).catch(() => {
                    window.open(href, '_blank', 'noopener');
                });
            };
            frame.src = href + (href.includes('?') ? '&' : '?') + '_print=' + Date.now();
        };

        const queuePrintJob = async () => {
            if (! queueUrl || ! legalFileId || queueBusy) {
                return;
            }

            queueBusy = true;
            printButton?.setAttribute('disabled', 'disabled');
            setQueueStatus('Menghantar…');

            try {
                const response = await fetch(queueUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        legal_file_id: Number(legalFileId),
                        type: selectedType,
                    }),
                });

                const payload = await response.json().catch(() => ({}));
                if (! response.ok) {
                    throw new Error(payload.message || 'Gagal menghantar job.');
                }

                showQueueSuccessModal(
                    payload.message || 'Job berjaya dihantar ke stesen cetak',
                    Boolean(payload.already_queued),
                );
            } catch (error) {
                setQueueStatus(error instanceof Error ? error.message : 'Gagal menghantar job.', 'error');
            } finally {
                queueBusy = false;
                printButton?.removeAttribute('disabled');
            }
        };

        typeButtons.forEach((button) => {
            button.addEventListener('click', () => {
                selectedType = button.getAttribute('data-file-label-type') || 'front';
                sync();
            });
        });

        printButton?.addEventListener('click', () => {
            if (queueUrl) {
                queuePrintJob();
                return;
            }

            const href = printButton.getAttribute('data-print-href') || withType(printBase, selectedType);
            printLabel(href);
        });

        if (typeof ResizeObserver !== 'undefined') {
            const observer = new ResizeObserver(() => fitVisiblePreview());
            previews.forEach((preview) => observer.observe(preview));
        }

        const overlay = modal.closest('[data-modal]');
        if (overlay) {
            const modalObserver = new MutationObserver(() => {
                if (! overlay.classList.contains('hidden')) {
                    if (selectedType === 'document'
                        || (selectedType === 'data' && modal.classList.contains('operator-review-modal'))) {
                        ensureDocumentLoaded();
                    }
                    requestAnimationFrame(fitVisiblePreview);
                }
            });
            modalObserver.observe(overlay, { attributes: true, attributeFilter: ['class', 'aria-hidden'] });
        }

        sync();
    });

    document.querySelectorAll('[data-operator-review-tabs]').forEach((modal) => {
        const buttons = [...modal.querySelectorAll('[data-operator-review-tab-btn]')];
        if (buttons.length === 0) {
            return;
        }

        const setTab = (tab) => {
            const next = tab === 'dokumen' ? 'dokumen' : 'maklumat';
            modal.setAttribute('data-operator-review-tab', next);
            buttons.forEach((button) => {
                const active = button.getAttribute('data-operator-review-tab-btn') === next;
                button.classList.toggle('is-active', active);
                button.setAttribute('aria-selected', active ? 'true' : 'false');
            });

            if (next === 'dokumen') {
                const img = modal.querySelector('[data-doc-zoom-img]');
                const src = img?.getAttribute('data-src') || '';
                if (img && src !== '' && ! img.getAttribute('src')) {
                    img.setAttribute('src', src);
                }
                // Refit after the dokumen pane becomes visible.
                requestAnimationFrame(() => {
                    img?.dispatchEvent(new Event('load'));
                });
            }
        };

        buttons.forEach((button) => {
            button.addEventListener('click', () => {
                setTab(button.getAttribute('data-operator-review-tab-btn') || 'maklumat');
            });
        });
    });

    document.querySelectorAll('[data-doc-zoom]').forEach((root) => {
        const viewport = root.querySelector('[data-doc-zoom-viewport]');
        const img = root.querySelector('[data-doc-zoom-img]');
        const label = root.querySelector('[data-doc-zoom-label]');
        const zoomIn = root.querySelector('[data-doc-zoom-in]');
        const zoomOut = root.querySelector('[data-doc-zoom-out]');
        const zoomReset = root.querySelector('[data-doc-zoom-reset]');
        const pagePrev = root.querySelector('[data-doc-page-prev]');
        const pageNext = root.querySelector('[data-doc-page-next]');
        const pageLabel = root.querySelector('[data-doc-page-label]');
        if (! viewport || ! img) {
            return;
        }

        let pages = [];
        try {
            const raw = root.getAttribute('data-doc-pages') || '[]';
            const parsed = JSON.parse(raw);
            pages = Array.isArray(parsed) ? parsed.filter((url) => typeof url === 'string' && url !== '') : [];
        } catch (error) {
            pages = [];
        }
        if (pages.length === 0) {
            const fallback = img.getAttribute('data-src') || img.getAttribute('src') || '';
            if (fallback) {
                pages = [fallback];
            }
        }

        const min = 1;
        const max = 4;
        const step = 0.25;
        let scale = 1;
        let pageIndex = 0;

        img.draggable = false;
        img.addEventListener('dragstart', (event) => event.preventDefault());

        const syncPageChrome = () => {
            if (pageLabel) {
                pageLabel.textContent = `${pageIndex + 1} / ${pages.length}`;
            }
            pagePrev?.toggleAttribute('disabled', pageIndex <= 0 || pages.length <= 1);
            pageNext?.toggleAttribute('disabled', pageIndex >= pages.length - 1 || pages.length <= 1);
        };

        const canPanNow = () => docZoomCanPan(
            scale,
            viewport.scrollWidth,
            viewport.clientWidth,
            viewport.scrollHeight,
            viewport.clientHeight,
        );

        const apply = ({ center = false, focus = null } = {}) => {
            const natW = img.naturalWidth;
            const natH = img.naturalHeight;
            if (natW > 0 && natH > 0) {
                const beforeW = img.offsetWidth;
                const beforeH = img.offsetHeight;
                let focusRel = null;
                if (focus && beforeW > 0 && beforeH > 0) {
                    const imgRect = img.getBoundingClientRect();
                    const vpRect = viewport.getBoundingClientRect();
                    focusRel = {
                        relX: imgRect.width > 0 ? (focus.clientX - imgRect.left) / imgRect.width : 0.5,
                        relY: imgRect.height > 0 ? (focus.clientY - imgRect.top) / imgRect.height : 0.5,
                        cursorX: focus.clientX - vpRect.left,
                        cursorY: focus.clientY - vpRect.top,
                    };
                }

                const fit = Math.min(viewport.clientWidth / natW, viewport.clientHeight / natH) || 1;
                const width = Math.max(1, natW * fit * scale);
                const height = Math.max(1, width * (natH / natW));
                img.style.width = `${width}px`;
                img.style.height = 'auto';
                img.style.maxWidth = 'none';
                img.style.maxHeight = 'none';

                const overflows = docZoomOverflows(width, height, viewport.clientWidth, viewport.clientHeight);
                img.style.margin = docZoomImageMargin(overflows);

                if (! overflows) {
                    viewport.scrollLeft = 0;
                    viewport.scrollTop = 0;
                } else if (focusRel) {
                    // Layout must settle before offsetWidth reflects the new size.
                    requestAnimationFrame(() => {
                        viewport.scrollLeft = docZoomAnchorScroll(
                            focusRel.relX,
                            img.offsetWidth,
                            focusRel.cursorX - img.offsetLeft,
                            viewport.clientWidth,
                        );
                        viewport.scrollTop = docZoomAnchorScroll(
                            focusRel.relY,
                            img.offsetHeight,
                            focusRel.cursorY - img.offsetTop,
                            viewport.clientHeight,
                        );
                    });
                } else if (center) {
                    // Layout must settle before scrollWidth/Height reflect the new size.
                    requestAnimationFrame(() => {
                        viewport.scrollLeft = docZoomCenterScroll(viewport.scrollWidth, viewport.clientWidth);
                        viewport.scrollTop = docZoomCenterScroll(viewport.scrollHeight, viewport.clientHeight);
                    });
                }
            }

            if (label) {
                label.textContent = scale <= 1 ? 'Muat' : `${Math.round(scale * 100)}%`;
            }
            zoomOut?.toggleAttribute('disabled', scale <= min);
            zoomIn?.toggleAttribute('disabled', scale >= max);
            viewport.classList.toggle('is-pannable', canPanNow() || scale > 1);
        };

        const setScale = (next, snap = true, focus = null) => {
            const prev = scale;
            const clamped = Math.min(max, Math.max(min, next));
            scale = snap ? Math.round(clamped / step) * step : clamped;
            scale = Math.min(max, Math.max(min, scale));
            if (scale === prev) {
                return;
            }
            // Focus point (cursor / pinch mid) wins; else center once when leaving fit.
            apply({
                focus,
                center: ! focus && snap && prev <= 1 && scale > 1,
            });
        };

        const showPage = (index) => {
            if (pages.length === 0) {
                return;
            }
            pageIndex = Math.min(pages.length - 1, Math.max(0, index));
            const url = pages[pageIndex];
            img.setAttribute('data-src', url);
            if (img.getAttribute('src') !== url) {
                img.setAttribute('src', url);
            }
            scale = 1;
            viewport.scrollLeft = 0;
            viewport.scrollTop = 0;
            syncPageChrome();
            if (img.complete && img.naturalWidth > 0) {
                apply();
            }
        };

        const touchDistance = (a, b) => Math.hypot(a.clientX - b.clientX, a.clientY - b.clientY);

        let pinchStartDistance = 0;
        let pinchStartScale = 1;
        let usingGestureEvents = false;
        let panLastX = 0;
        let panLastY = 0;
        let panning = false;
        let panMoved = false;
        let lastTapAt = 0;

        const endPan = () => {
            panning = false;
            viewport.classList.remove('is-panning');
        };

        const viewportCenterFocus = () => {
            const rect = viewport.getBoundingClientRect();

            return {
                clientX: rect.left + rect.width / 2,
                clientY: rect.top + rect.height / 2,
            };
        };

        zoomIn?.addEventListener('click', () => setScale(scale + step, true, viewportCenterFocus()));
        zoomOut?.addEventListener('click', () => setScale(scale - step, true, viewportCenterFocus()));
        zoomReset?.addEventListener('click', () => {
            setScale(1);
            viewport.scrollLeft = 0;
            viewport.scrollTop = 0;
        });
        pagePrev?.addEventListener('click', () => showPage(pageIndex - 1));
        pageNext?.addEventListener('click', () => showPage(pageIndex + 1));

        // Wheel / trackpad over the image zooms toward the cursor (no Ctrl required).
        viewport.addEventListener('wheel', (event) => {
            event.preventDefault();
            const focus = { clientX: event.clientX, clientY: event.clientY };
            if (event.ctrlKey || event.metaKey) {
                setScale(scale * Math.exp(-event.deltaY * 0.01), false, focus);
                return;
            }
            setScale(scale + (event.deltaY < 0 ? step : -step), true, focus);
        }, { passive: false });

        viewport.addEventListener('dblclick', (event) => {
            event.preventDefault();
            if (scale > 1) {
                setScale(1);
                viewport.scrollLeft = 0;
                viewport.scrollTop = 0;
            } else {
                setScale(2, true, { clientX: event.clientX, clientY: event.clientY });
            }
        });

        viewport.addEventListener('pointerdown', (event) => {
            if (event.pointerType === 'touch' || event.button !== 0) {
                return;
            }
            if (! canPanNow()) {
                return;
            }
            event.preventDefault();
            panning = true;
            panMoved = false;
            panLastX = event.clientX;
            panLastY = event.clientY;
            viewport.classList.add('is-panning');
            viewport.setPointerCapture?.(event.pointerId);
        });

        viewport.addEventListener('pointermove', (event) => {
            if (! panning || event.pointerType === 'touch') {
                return;
            }
            const dx = event.clientX - panLastX;
            const dy = event.clientY - panLastY;
            if (dx !== 0 || dy !== 0) {
                panMoved = true;
            }
            panLastX = event.clientX;
            panLastY = event.clientY;
            viewport.scrollLeft -= dx;
            viewport.scrollTop -= dy;
        });

        viewport.addEventListener('pointerup', endPan);
        viewport.addEventListener('pointercancel', endPan);
        viewport.addEventListener('lostpointercapture', endPan);

        const touchMidpoint = (a, b) => ({
            clientX: (a.clientX + b.clientX) / 2,
            clientY: (a.clientY + b.clientY) / 2,
        });

        // Mobile: pinch-to-zoom toward midpoint + one-finger pan (page zoom locked with maximum-scale=1).
        viewport.addEventListener('touchstart', (event) => {
            if (usingGestureEvents) {
                return;
            }
            if (event.touches.length === 2) {
                panning = false;
                panMoved = false;
                pinchStartDistance = touchDistance(event.touches[0], event.touches[1]);
                pinchStartScale = scale;
                return;
            }
            if (event.touches.length === 1) {
                pinchStartDistance = 0;
                panning = true;
                panMoved = false;
                panLastX = event.touches[0].clientX;
                panLastY = event.touches[0].clientY;
            }
        }, { passive: true });

        viewport.addEventListener('touchmove', (event) => {
            if (usingGestureEvents) {
                return;
            }
            if (event.touches.length === 2 && pinchStartDistance > 0) {
                event.preventDefault();
                const distance = touchDistance(event.touches[0], event.touches[1]);
                setScale(
                    pinchStartScale * (distance / pinchStartDistance),
                    false,
                    touchMidpoint(event.touches[0], event.touches[1]),
                );
                return;
            }
            if (event.touches.length === 1 && panning) {
                event.preventDefault();
                const x = event.touches[0].clientX;
                const y = event.touches[0].clientY;
                const dx = x - panLastX;
                const dy = y - panLastY;
                if (Math.abs(dx) > 2 || Math.abs(dy) > 2) {
                    panMoved = true;
                }
                panLastX = x;
                panLastY = y;
                viewport.scrollLeft -= dx;
                viewport.scrollTop -= dy;
            }
        }, { passive: false });

        viewport.addEventListener('touchend', (event) => {
            if (usingGestureEvents) {
                return;
            }
            if (event.touches.length >= 2) {
                return;
            }
            if (pinchStartDistance > 0) {
                pinchStartDistance = 0;
                setScale(scale, true);
                panning = false;
                return;
            }
            const wasPan = panMoved;
            panning = false;
            if (wasPan || event.changedTouches.length !== 1) {
                return;
            }
            const now = Date.now();
            if (now - lastTapAt < 320) {
                lastTapAt = 0;
                const tap = event.changedTouches[0];
                if (scale > 1) {
                    setScale(1);
                    viewport.scrollLeft = 0;
                    viewport.scrollTop = 0;
                } else {
                    setScale(2, true, { clientX: tap.clientX, clientY: tap.clientY });
                }
            } else {
                lastTapAt = now;
            }
        }, { passive: true });

        // iOS Safari: prefer gesture* (has event.scale) so touch + gesture don't double-apply.
        viewport.addEventListener('gesturestart', (event) => {
            event.preventDefault();
            usingGestureEvents = true;
            pinchStartDistance = 0;
            panning = false;
            pinchStartScale = scale;
        }, { passive: false });

        viewport.addEventListener('gesturechange', (event) => {
            event.preventDefault();
            setScale(pinchStartScale * event.scale, false, {
                clientX: event.clientX,
                clientY: event.clientY,
            });
        }, { passive: false });

        viewport.addEventListener('gestureend', () => {
            usingGestureEvents = false;
            setScale(scale, true);
        }, { passive: true });

        img.addEventListener('load', () => {
            apply();
        });

        if (typeof ResizeObserver !== 'undefined') {
            new ResizeObserver(() => apply()).observe(viewport);
        }

        syncPageChrome();
        apply();
    });

    (() => {
        const station = document.querySelector('[data-entry-print-station]');
        if (! station) {
            return;
        }

        const jobsUrl = station.getAttribute('data-jobs-url') || '';
        const statusUrl = station.getAttribute('data-status-url') || '';
        const batchPrintUrl = station.getAttribute('data-batch-print-url') || '';
        const listEl = station.querySelector('[data-print-station-list]');
        const countEl = station.querySelector('[data-print-station-count]');
        const countLabelEl = station.querySelector('[data-print-station-count-label]');
        const pendingCountEl = station.querySelector('[data-print-station-pending-count]');
        const statusEl = station.querySelector('[data-print-station-status]');
        const printSelectedBtn = station.querySelector('[data-print-station-print-selected]');
        const markPrintedBtn = station.querySelector('[data-print-station-mark-printed]');
        const markPendingBtn = station.querySelector('[data-print-station-mark-pending]');
        const refreshBtn = station.querySelector('[data-print-station-refresh]');
        const searchInput = station.querySelector('[data-print-station-search]');
        const desktopStatusSelect = station.querySelector('[data-print-station-status-filter]');
        const desktopReviewSelect = station.querySelector('[data-print-station-review-filter]');
        const desktopOperatorSelect = station.querySelector('[data-print-station-operator]');
        const mobileFilterInputs = station.querySelectorAll('[data-print-station-mobile-filter]');
        const previewModal = document.querySelector('[data-modal="entry-print-preview"]');
        const previewFrame = previewModal?.querySelector('[data-print-preview-frame]');
        const previewTitle = previewModal?.querySelector('[data-print-preview-title]');
        const previewPrintBtn = previewModal?.querySelector('[data-print-preview-print]');
        const previewDeleteBtn = previewModal?.querySelector('[data-print-preview-delete]');
        const previewMarkStatusBtn = previewModal?.querySelector('[data-print-preview-mark-status]');
        const desktopFilterQuery = window.matchMedia('(min-width: 900px)');
        const pagerEl = station.querySelector('[data-print-station-pager]');
        let jobs = [];
        let busy = false;
        let previewJob = null;
        let currentPage = Number(new URLSearchParams(window.location.search).get('page')) || 1;
        if (! Number.isFinite(currentPage) || currentPage < 1) {
            currentPage = 1;
        }

        const jobIsPending = (job) => job?.is_pending !== false && job?.status !== 'printed';

        const statusFilter = () => desktopStatusSelect?.value
            || station.getAttribute('data-status-filter')
            || 'pending';

        const countLabelFor = (filter) => {
            if (filter === 'printed') {
                return 'dicetak';
            }
            if (filter === 'all') {
                return 'job';
            }

            return 'belum dicetak';
        };

        const printSelectedLabel = (filter) => (
            filter === 'printed' ? 'Cetak semula' : 'Cetak'
        );

        const allSelectBoxes = () => [...(listEl?.querySelectorAll('[data-print-station-select]') || [])];

        // Cards + table both render the same jobs; dedupe by value for counts/selection.
        const rowCheckboxes = () => {
            const unique = new Map();
            allSelectBoxes().forEach((box) => {
                if (! unique.has(box.value)) {
                    unique.set(box.value, box);
                }
            });

            return [...unique.values()];
        };

        const setBoxesChecked = (idsOrAll, checked) => {
            const all = idsOrAll === true;
            const idSet = all ? null : new Set([...idsOrAll].map(String));
            allSelectBoxes().forEach((box) => {
                if (all || idSet.has(box.value)) {
                    box.checked = checked;
                }
            });
        };

        const selectedJobs = () => {
            const ids = new Set(
                rowCheckboxes()
                    .filter((box) => box.checked)
                    .map((box) => Number(box.value)),
            );

            return jobs.filter((job) => ids.has(job.id));
        };

        const syncSelectionUi = () => {
            const boxes = rowCheckboxes();
            const checkedCount = boxes.filter((box) => box.checked).length;
            const allChecked = boxes.length > 0 && checkedCount === boxes.length;
            const indeterminate = checkedCount > 0 && checkedCount < boxes.length;
            const selected = selectedJobs();
            const hasPending = selected.some(jobIsPending);
            const hasPrinted = selected.some((job) => ! jobIsPending(job));

            listEl?.querySelectorAll('[data-print-station-select-all]').forEach((selectAll) => {
                selectAll.checked = allChecked;
                selectAll.indeterminate = indeterminate;
            });

            if (printSelectedBtn) {
                printSelectedBtn.disabled = checkedCount === 0 || busy;
                printSelectedBtn.textContent = printSelectedLabel(statusFilter());
            }

            if (markPrintedBtn) {
                markPrintedBtn.disabled = ! hasPending || busy;
            }

            if (markPendingBtn) {
                markPendingBtn.disabled = ! hasPrinted || busy;
            }
        };

        const syncPreviewMarkStatus = () => {
            if (! previewMarkStatusBtn || ! previewJob) {
                return;
            }

            const pending = jobIsPending(previewJob);
            previewMarkStatusBtn.textContent = pending ? 'Tanda dicetak' : 'Tanda belum dicetak';
            previewMarkStatusBtn.disabled = busy;
        };

        const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        const setStatus = (message, kind = '') => {
            if (! statusEl) {
                return;
            }

            statusEl.textContent = message || '';
            statusEl.classList.toggle('hidden', ! message);
            statusEl.classList.toggle('is-ok', kind === 'ok');
            statusEl.classList.toggle('is-error', kind === 'error');
        };

        const escapeHtml = (value) => String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;');

        const requestedByParam = () => {
            if (desktopOperatorSelect && desktopFilterQuery.matches) {
                return desktopOperatorSelect.value || '';
            }

            return station.getAttribute('data-requested-by') || '';
        };

        const reviewFilter = () => {
            if (desktopReviewSelect && desktopFilterQuery.matches) {
                return desktopReviewSelect.value || 'all';
            }

            return station.getAttribute('data-review-filter') || 'all';
        };

        const searchQuery = () => {
            const fromInput = searchInput?.value?.trim() || '';
            if (fromInput !== '') {
                return fromInput;
            }

            return (station.getAttribute('data-q') || '').trim();
        };

        const syncFilterControlMode = () => {
            const desktop = desktopFilterQuery.matches;
            mobileFilterInputs.forEach((input) => {
                input.disabled = desktop;
            });
            [desktopStatusSelect, desktopReviewSelect, desktopOperatorSelect].forEach((select) => {
                if (select) {
                    select.disabled = ! desktop;
                }
            });
        };

        const applyDesktopFilters = () => {
            if (desktopStatusSelect) {
                station.setAttribute('data-status-filter', desktopStatusSelect.value);
                const hiddenStatus = station.querySelector('[data-print-station-mobile-filter][name="status"]');
                if (hiddenStatus) {
                    hiddenStatus.value = desktopStatusSelect.value;
                }
            }

            if (desktopReviewSelect) {
                station.setAttribute('data-review-filter', desktopReviewSelect.value);
                const hiddenReview = station.querySelector('[data-print-station-mobile-filter][name="review"]');
                if (hiddenReview) {
                    hiddenReview.value = desktopReviewSelect.value;
                }
            }

            if (desktopOperatorSelect) {
                station.setAttribute('data-requested-by', desktopOperatorSelect.value);
                const hiddenOperator = station.querySelector('[data-print-station-mobile-filter][name="requested_by"]');
                if (hiddenOperator) {
                    hiddenOperator.value = desktopOperatorSelect.value;
                }
            }

            currentPage = 1;
            reloadJobs();
        };

        const previewUrlFor = (job) => {
            if (job.preview_url) {
                return sameOriginPrintUrl(job.preview_url);
            }

            const printUrl = sameOriginPrintUrl(job.print_url || '');
            if (! printUrl) {
                return '';
            }

            return printUrl + (printUrl.includes('?') ? '&' : '?') + 'embed=1';
        };

        const openPreview = (job) => {
            const url = previewUrlFor(job);
            if (! previewModal || ! previewFrame || ! url) {
                return;
            }

            previewJob = job;
            if (previewTitle) {
                previewTitle.textContent = job.preview_title
                    || `${job.reference_number || 'Label'} · ${job.type_label || ''}`.trim();
            }

            if (previewDeleteBtn) {
                previewDeleteBtn.hidden = ! job.delete_url;
                previewDeleteBtn.disabled = busy || ! job.delete_url;
            }

            syncPreviewMarkStatus();

            const frameWrap = previewModal.querySelector('.entry-print-preview__frame-wrap');
            if (frameWrap) {
                frameWrap.style.aspectRatio = job.type === 'side' ? '2 / 1' : '3 / 2';
            }

            previewFrame.src = url + (url.includes('?') ? '&' : '?') + '_preview=' + Date.now();
            openModal(previewModal);
        };

        const closePreview = () => {
            if (previewFrame) {
                previewFrame.src = 'about:blank';
            }
            previewJob = null;
            if (previewModal) {
                closeModal(previewModal);
            }
        };

        const renderPager = (html = '') => {
            if (! pagerEl) {
                return;
            }

            pagerEl.innerHTML = html || '';
        };

        const renderJobs = (nextJobs, pendingCount = null, total = null, paginationHtml = '') => {
            const previouslySelected = new Set(
                rowCheckboxes()
                    .filter((box) => box.checked)
                    .map((box) => Number(box.value)),
            );
            jobs = nextJobs;
            const filter = statusFilter();
            if (countEl) {
                countEl.textContent = String(total ?? jobs.length);
            }
            if (countLabelEl) {
                countLabelEl.textContent = countLabelFor(filter);
            }
            if (pendingCountEl && pendingCount !== null) {
                pendingCountEl.textContent = String(pendingCount);
            }
            renderPager(paginationHtml);

            if (! listEl) {
                return;
            }

            if (jobs.length === 0) {
                const emptyTitle = filter === 'printed'
                    ? 'Tiada job dicetak'
                    : (filter === 'all' ? 'Tiada job' : 'Tiada job menunggu');
                listEl.innerHTML = `
                    <div class="entry-empty" data-print-station-empty>
                        <p class="entry-empty__title">${emptyTitle}</p>
                        <p class="entry-empty__text">Apabila operator tekan Hantar ke stesen, job akan muncul di sini.</p>
                    </div>
                `;
                syncSelectionUi();
                return;
            }

            const cards = [];
            const rows = [];

            jobs.forEach((job) => {
                const pending = jobIsPending(job);
                const badgeClass = pending ? 'pending' : 'printed';
                const statusLabel = escapeHtml(job.status_label || (pending ? 'Belum dicetak' : 'Dicetak'));
                const reviewStatus = escapeHtml(job.review_status || 'pending');
                const reviewLabel = escapeHtml(job.review_status_label || 'Belum semak');
                const sentAt = escapeHtml(job.created_at || '—');
                const printedAt = escapeHtml(job.printed_at || '—');
                const ref = escapeHtml(job.reference_number || '—');
                const typeLabel = escapeHtml(job.type_label || '');
                const operator = escapeHtml(job.operator || '—');
                const printUrl = escapeHtml(job.print_url || '');
                const previewUrl = escapeHtml(previewUrlFor(job));
                const previewTitleText = escapeHtml(`${job.reference_number || 'Label'} · ${job.type_label || ''}`.trim());
                const deleteUrl = escapeHtml(job.delete_url || '');
                const checked = previouslySelected.has(job.id) ? ' checked' : '';
                const status = escapeHtml(job.status || (pending ? 'pending' : 'printed'));
                const reviewBadge = `<span class="entry-ops__badge entry-ops__badge--review-${reviewStatus}">${reviewLabel}</span>`;
                const attrs = `
                    data-print-job-id="${job.id}"
                    data-job-status="${status}"
                    data-print-url="${printUrl}"
                    data-preview-url="${previewUrl}"
                    data-preview-title="${previewTitleText}"
                    data-delete-url="${deleteUrl}"
                `;

                cards.push(`
                <article class="entry-print-station__card" ${attrs}>
                    <label class="entry-print-station__check">
                        <input
                            type="checkbox"
                            data-print-station-select
                            value="${job.id}"
                            aria-label="Pilih ${ref}"${checked}
                        >
                    </label>
                    <div class="entry-print-station__card-body">
                        <div class="entry-print-station__card-head">
                            <p class="entry-print-station__ref">${ref}</p>
                            <span class="entry-print-station__badge entry-print-station__badge--${badgeClass}">${statusLabel}</span>
                        </div>
                        <p class="entry-print-station__card-meta">${operator} · ${typeLabel} · ${reviewBadge}</p>
                    </div>
                </article>
                `);

                rows.push(`
                <tr ${attrs}>
                    <td class="entry-print-station__check">
                        <input
                            type="checkbox"
                            data-print-station-select
                            value="${job.id}"
                            aria-label="Pilih ${ref}"${checked}
                        >
                    </td>
                    <td><span class="entry-print-station__ref">${ref}</span></td>
                    <td>
                        <span class="entry-print-station__badge entry-print-station__badge--${badgeClass}">${statusLabel}</span>
                    </td>
                    <td>${reviewBadge}</td>
                    <td>${operator}</td>
                    <td>${typeLabel}</td>
                    <td class="entry-print-station__when-cell">${sentAt}</td>
                    <td class="entry-print-station__when-cell">${printedAt}</td>
                </tr>
                `);
            });

            const keepSelecting = Boolean(
                listEl.querySelector('[data-print-station-cards].is-selecting')
                || previouslySelected.size > 0,
            );

            listEl.innerHTML = `
                <div class="entry-print-station__cards${keepSelecting ? ' is-selecting' : ''}" data-print-station-cards>
                    <div class="entry-print-station__select-bar">
                        <label class="entry-print-station__select-all">
                            <input type="checkbox" data-print-station-select-all aria-label="Pilih semua">
                            <span>Pilih semua</span>
                        </label>
                    </div>
                    ${cards.join('')}
                </div>
                <div class="entry-print-station__table-wrap">
                    <table class="entry-print-station__table">
                        <thead>
                            <tr>
                                <th class="entry-print-station__check" scope="col">
                                    <input type="checkbox" data-print-station-select-all aria-label="Pilih semua">
                                </th>
                                <th scope="col">Rujukan</th>
                                <th scope="col">Status</th>
                                <th scope="col">Pengesahan</th>
                                <th scope="col">Operator</th>
                                <th scope="col">Jenis</th>
                                <th scope="col">Dihantar</th>
                                <th scope="col">Dicetak</th>
                            </tr>
                        </thead>
                        <tbody>${rows.join('')}</tbody>
                    </table>
                </div>
            `;
            syncSelectionUi();
        };

        const fetchJobs = async () => {
            if (! jobsUrl) {
                return;
            }

            const url = new URL(jobsUrl, window.location.origin);
            const requestedBy = requestedByParam();
            const review = reviewFilter();
            const q = searchQuery();
            if (requestedBy) {
                url.searchParams.set('requested_by', requestedBy);
            }
            if (review && review !== 'all') {
                url.searchParams.set('review', review);
            }
            if (q) {
                url.searchParams.set('q', q);
            }
            url.searchParams.set('status', statusFilter());
            if (currentPage > 1) {
                url.searchParams.set('page', String(currentPage));
            }

            const response = await fetch(url.pathname + url.search, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (! response.ok) {
                throw new Error('Gagal memuat job.');
            }

            const payload = await response.json();
            if (typeof payload.current_page === 'number' && payload.current_page > 0) {
                currentPage = payload.current_page;
            }
            renderJobs(
                Array.isArray(payload.jobs) ? payload.jobs : [],
                typeof payload.pending_count === 'number' ? payload.pending_count : null,
                typeof payload.total === 'number' ? payload.total : null,
                typeof payload.pagination === 'string' ? payload.pagination : '',
            );
        };

        const setJobStatus = async (ids, status) => {
            if (! statusUrl || ids.length === 0) {
                return { marked: [] };
            }

            const response = await fetch(statusUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ ids, status }),
            });

            const payload = await response.json().catch(() => ({}));
            if (! response.ok) {
                const label = status === 'printed' ? 'dicetak' : 'belum dicetak';
                throw new Error(payload.message || `Gagal menanda job sebagai ${label}.`);
            }

            return payload;
        };

        const markSelectedStatus = async (status) => {
            const selected = selectedJobs().filter((job) => (
                status === 'printed' ? jobIsPending(job) : ! jobIsPending(job)
            ));
            if (busy || selected.length === 0) {
                return;
            }

            busy = true;
            syncSelectionUi();
            syncPreviewMarkStatus();
            const label = status === 'printed' ? 'dicetak' : 'belum dicetak';
            setStatus(`Menanda ${selected.length} job sebagai ${label}…`);

            try {
                const payload = await setJobStatus(selected.map((job) => job.id), status);
                const count = Array.isArray(payload.marked) ? payload.marked.length : selected.length;
                setStatus(`${count} job ditanda ${label}`, 'ok');
                await fetchJobs();
            } catch (error) {
                setStatus(error instanceof Error ? error.message : `Gagal menanda sebagai ${label}.`, 'error');
            } finally {
                busy = false;
                syncSelectionUi();
                syncPreviewMarkStatus();
            }
        };

        const printViaFrame = (url) => new Promise((resolve) => {
            const href = sameOriginPrintUrl(url);
            const frame = ensurePrintFrame('entry-print-station-frame', {
                ...fileLabelPrintFrameSize(href),
                title: 'Entry print station',
            });

            let settled = false;
            const finish = () => {
                if (settled) {
                    return;
                }
                settled = true;
                frame.contentWindow?.removeEventListener('afterprint', finish);
                resolve();
            };

            const openFallback = () => {
                window.open(href, '_blank', 'noopener');
                finish();
            };

            frame.onload = () => {
                waitForPrintFrameReady(frame).then(() => {
                    try {
                        const label = frame.contentDocument?.querySelector('.file-label');
                        if (! label) {
                            openFallback();
                            return;
                        }

                        frame.contentWindow?.addEventListener('afterprint', finish);
                        frame.contentWindow?.focus();
                        frame.contentWindow?.print();
                        // Fallback if afterprint never fires (some mobile/desktop browsers).
                        window.setTimeout(finish, 60_000);
                    } catch (error) {
                        openFallback();
                    }
                }).catch(() => {
                    openFallback();
                });
            };

            frame.src = href + (href.includes('?') ? '&' : '?') + '_print=' + Date.now();
        });

        const batchPrintHref = (jobs, type) => {
            if (! batchPrintUrl || jobs.length === 0) {
                return '';
            }

            const url = new URL(batchPrintUrl, window.location.origin);
            url.searchParams.set('ids', jobs.map((job) => job.id).join(','));
            url.searchParams.set('type', type);

            return sameOriginPrintUrl(url.pathname + url.search);
        };

        const printJobs = async (selected, { mark = true } = {}) => {
            if (busy || selected.length === 0) {
                return;
            }

            // Same sticker size → one print dialog. Mixed front/side → one dialog per size.
            const groups = ['front', 'side']
                .map((type) => ({
                    type,
                    jobs: selected.filter((job) => (job.type || 'front') === type && job.id),
                }))
                .filter((group) => group.jobs.length > 0);

            if (groups.length === 0) {
                setStatus('Tiada job sah untuk dicetak.', 'error');
                return;
            }

            busy = true;
            syncSelectionUi();
            setStatus(`Mencetak ${selected.length} job…`);

            try {
                for (const group of groups) {
                    const href = batchPrintHref(group.jobs, group.type)
                        || group.jobs[0]?.print_url
                        || '';
                    if (! href) {
                        continue;
                    }
                    await printViaFrame(href);
                }

                const printedIds = mark
                    ? selected.filter(jobIsPending).map((job) => job.id)
                    : [];

                if (printedIds.length > 0) {
                    await setJobStatus(printedIds, 'printed');
                    setStatus(`${printedIds.length} job ditanda dicetak`, 'ok');
                } else {
                    setStatus(`${selected.length} job dicetak`, 'ok');
                }
                await fetchJobs();
            } catch (error) {
                setStatus(error instanceof Error ? error.message : 'Cetakan gagal.', 'error');
                try {
                    await fetchJobs();
                } catch (_error) {
                    // ignore refresh failure after print error
                }
            } finally {
                busy = false;
                syncSelectionUi();
            }
        };

        const deleteJob = async (job) => {
            if (! job?.delete_url || busy) {
                return;
            }

            if (! window.confirm(`Padam job ${job.reference_number || ''} dari stesen?`)) {
                return;
            }

            busy = true;
            syncSelectionUi();
            setStatus('Memadam job…');

            try {
                const response = await fetch(job.delete_url, {
                    method: 'DELETE',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const payload = await response.json().catch(() => ({}));
                if (! response.ok) {
                    throw new Error(payload.message || 'Gagal memadam job.');
                }

                if (previewJob?.id === job.id) {
                    closePreview();
                }

                setStatus(payload.message || 'Job dipadam dari stesen', 'ok');
                await fetchJobs();
            } catch (error) {
                setStatus(error instanceof Error ? error.message : 'Gagal memadam job.', 'error');
            } finally {
                busy = false;
                syncSelectionUi();
            }
        };

        const reloadJobs = () => {
            fetchJobs().catch((error) => {
                setStatus(error instanceof Error ? error.message : 'Gagal memuat job.', 'error');
            });
        };

        syncFilterControlMode();
        if (typeof desktopFilterQuery.addEventListener === 'function') {
            desktopFilterQuery.addEventListener('change', syncFilterControlMode);
        } else if (typeof desktopFilterQuery.addListener === 'function') {
            desktopFilterQuery.addListener(syncFilterControlMode);
        }

        desktopStatusSelect?.addEventListener('change', applyDesktopFilters);
        desktopReviewSelect?.addEventListener('change', applyDesktopFilters);
        desktopOperatorSelect?.addEventListener('change', applyDesktopFilters);
        refreshBtn?.addEventListener('click', reloadJobs);

        printSelectedBtn?.addEventListener('click', () => {
            printJobs(selectedJobs());
        });

        markPrintedBtn?.addEventListener('click', () => {
            markSelectedStatus('printed');
        });

        markPendingBtn?.addEventListener('click', () => {
            markSelectedStatus('pending');
        });

        previewPrintBtn?.addEventListener('click', () => {
            if (! previewJob?.print_url || busy) {
                return;
            }

            const job = previewJob;
            const reprint = ! jobIsPending(job);
            closePreview();
            printJobs([job], { mark: ! reprint });
        });

        previewMarkStatusBtn?.addEventListener('click', async () => {
            if (! previewJob || busy) {
                return;
            }

            const job = previewJob;
            const nextStatus = jobIsPending(job) ? 'printed' : 'pending';
            const label = nextStatus === 'printed' ? 'dicetak' : 'belum dicetak';

            busy = true;
            syncSelectionUi();
            syncPreviewMarkStatus();
            setStatus(`Menanda job sebagai ${label}…`);

            try {
                await setJobStatus([job.id], nextStatus);
                closePreview();
                setStatus(`Job ditanda ${label}`, 'ok');
                await fetchJobs();
            } catch (error) {
                setStatus(error instanceof Error ? error.message : `Gagal menanda sebagai ${label}.`, 'error');
            } finally {
                busy = false;
                syncSelectionUi();
                syncPreviewMarkStatus();
            }
        });

        previewDeleteBtn?.addEventListener('click', () => {
            if (! previewJob?.delete_url || busy) {
                return;
            }

            deleteJob(previewJob);
        });

        if (previewModal && typeof MutationObserver !== 'undefined') {
            const previewObserver = new MutationObserver(() => {
                if (previewModal.classList.contains('hidden')) {
                    if (previewFrame) {
                        previewFrame.src = 'about:blank';
                    }
                    previewJob = null;
                }
            });
            previewObserver.observe(previewModal, { attributes: true, attributeFilter: ['class'] });
        }

        const cardsRoot = () => listEl?.querySelector('[data-print-station-cards]');

        const isCardSelecting = () => Boolean(cardsRoot()?.classList.contains('is-selecting'));

        const setCardSelecting = (on) => {
            const root = cardsRoot();
            if (! root) {
                return;
            }

            root.classList.toggle('is-selecting', on);
            if (! on) {
                setBoxesChecked(true, false);
            }
            syncSelectionUi();
        };

        const enterCardSelecting = (card) => {
            const root = cardsRoot();
            if (! root || ! card) {
                return;
            }

            root.classList.add('is-selecting');
            const id = card.getAttribute('data-print-job-id');
            if (id) {
                setBoxesChecked([id], true);
            }
            syncSelectionUi();

            if (typeof navigator !== 'undefined' && typeof navigator.vibrate === 'function') {
                navigator.vibrate(12);
            }
        };

        listEl?.addEventListener('change', (event) => {
            const selectAll = event.target.closest('[data-print-station-select-all]');
            if (selectAll) {
                setBoxesChecked(true, selectAll.checked);
                if (selectAll.closest('[data-print-station-cards]') && ! selectAll.checked) {
                    setCardSelecting(false);
                    return;
                }
                if (selectAll.closest('[data-print-station-cards]') && selectAll.checked) {
                    cardsRoot()?.classList.add('is-selecting');
                }
                syncSelectionUi();
                return;
            }

            const box = event.target.closest('[data-print-station-select]');
            if (box) {
                setBoxesChecked([box.value], box.checked);
                if (box.closest('[data-print-station-cards]') && rowCheckboxes().every((entry) => ! entry.checked)) {
                    setCardSelecting(false);
                    return;
                }
                syncSelectionUi();
            }
        });

        let longPressTimer = null;
        let longPressTriggered = false;
        let longPressOrigin = null;
        const clearLongPress = () => {
            if (longPressTimer) {
                window.clearTimeout(longPressTimer);
                longPressTimer = null;
            }
            longPressOrigin = null;
        };

        listEl?.addEventListener('pointerdown', (event) => {
            if (event.pointerType === 'mouse' && event.button !== 0) {
                return;
            }

            const card = event.target.closest('.entry-print-station__card');
            if (! card || ! listEl.contains(card) || event.target.closest('label, input')) {
                return;
            }

            longPressTriggered = false;
            clearLongPress();
            longPressOrigin = { x: event.clientX, y: event.clientY };
            longPressTimer = window.setTimeout(() => {
                longPressTriggered = true;
                longPressTimer = null;
                longPressOrigin = null;
                enterCardSelecting(card);
            }, 420);
        });

        listEl?.addEventListener('pointerup', clearLongPress);
        listEl?.addEventListener('pointercancel', clearLongPress);
        listEl?.addEventListener('pointermove', (event) => {
            if (! longPressTimer || ! longPressOrigin) {
                return;
            }

            const dx = event.clientX - longPressOrigin.x;
            const dy = event.clientY - longPressOrigin.y;
            if ((dx * dx) + (dy * dy) > 100) {
                clearLongPress();
            }
        });

        listEl?.addEventListener('contextmenu', (event) => {
            if (event.target.closest('.entry-print-station__card')) {
                event.preventDefault();
            }
        });

        listEl?.addEventListener('click', (event) => {
            if (event.target.closest('[data-print-station-select], [data-print-station-select-all], label')) {
                return;
            }

            const card = event.target.closest('.entry-print-station__card');
            if (longPressTriggered) {
                longPressTriggered = false;
                return;
            }

            if (card && isCardSelecting()) {
                const box = card.querySelector('[data-print-station-select]');
                if (box) {
                    setBoxesChecked([box.value], ! box.checked);
                    if (rowCheckboxes().every((entry) => ! entry.checked)) {
                        setCardSelecting(false);
                    } else {
                        syncSelectionUi();
                    }
                }
                return;
            }

            const row = event.target.closest('[data-print-job-id]');
            if (! row || ! listEl.contains(row)) {
                return;
            }

            const id = Number(row.getAttribute('data-print-job-id'));
            const job = jobs.find((entry) => entry.id === id) || {
                id,
                print_url: row.getAttribute('data-print-url') || '',
                preview_url: row.getAttribute('data-preview-url') || '',
                preview_title: row.getAttribute('data-preview-title') || '',
                delete_url: row.getAttribute('data-delete-url') || '',
                reference_number: row.querySelector('.entry-print-station__ref')?.textContent?.trim() || 'Label',
                type_label: '',
            };
            openPreview(job);
        });

        // Seed from server-rendered rows so selection works before first poll.
        const seenJobIds = new Set();
        jobs = [...station.querySelectorAll('[data-print-job-id]')].flatMap((row) => {
            const id = Number(row.getAttribute('data-print-job-id'));
            if (seenJobIds.has(id)) {
                return [];
            }
            seenJobIds.add(id);
            const status = row.getAttribute('data-job-status') || 'pending';
            return [{
                id,
                status,
                status_label: row.querySelector('.entry-print-station__badge')?.textContent?.trim() || '',
                is_pending: status === 'pending',
                print_url: row.getAttribute('data-print-url') || '',
                preview_url: row.getAttribute('data-preview-url') || '',
                preview_title: row.getAttribute('data-preview-title') || '',
                delete_url: row.getAttribute('data-delete-url') || '',
                reference_number: row.querySelector('.entry-print-station__ref')?.textContent?.trim() || '—',
                operator: '',
                type_label: '',
                created_at: '',
            }];
        });
        syncSelectionUi();

        window.setInterval(() => {
            if (busy || document.hidden) {
                return;
            }

            fetchJobs().catch(() => {
                // silent poll failure
            });
        }, 5000);
    })();

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
        // Only field panels — never <option data-case-type-*> (hiding those emptied the dropdown).
        const panels = Array.from(form.querySelectorAll('div[data-case-type-panel], section[data-case-type-panel]'));

        const resolvePanelType = () => {
            if (! select) {
                return panels.find((panel) => ! panel.classList.contains('hidden'))?.dataset.caseTypePanel || '';
            }
            const selected = select.selectedOptions?.[0];
            const fromOption = selected?.dataset?.caseTypeFamily || selected?.dataset?.caseTypePanel;
            if (fromOption) {
                return fromOption;
            }

            return select.value || '';
        };

        const syncCaseTypePanels = (panelType) => {
            panels.forEach((panel) => {
                const active = panel.dataset.caseTypePanel === panelType;
                panel.classList.toggle('hidden', ! active);
                panel.querySelectorAll('input, textarea, select').forEach((field) => {
                    field.disabled = ! active;
                    if (field.hasAttribute('data-case-type-required')) {
                        field.required = active;
                    }
                });
            });
        };

        syncCaseTypePanels(resolvePanelType());

        select?.addEventListener('change', () => {
            syncCaseTypePanels(resolvePanelType());
        });

        const borrowersRoot = form.querySelector('[data-borrowers-root]');
        const coBorrowersEl = form.querySelector('[data-co-borrowers]');
        const borrowerTemplate = form.querySelector('[data-borrower-template]');
        const addBorrowerBtn = form.querySelector('[data-add-borrower]');

        const nextBorrowerIndex = () => {
            if (! borrowersRoot) {
                return 1;
            }

            return borrowersRoot.querySelectorAll('[data-borrower-row]').length;
        };

        const addBorrowerRow = (name = '', ic = '') => {
            if (! coBorrowersEl || ! borrowerTemplate) {
                return;
            }

            const index = nextBorrowerIndex();
            const html = borrowerTemplate.innerHTML.replaceAll('__INDEX__', String(index));
            const wrap = document.createElement('div');
            wrap.innerHTML = html.trim();
            const row = wrap.firstElementChild;
            if (! row) {
                return;
            }

            const nameInput = row.querySelector(`[name="borrowers[${index}][name]"]`);
            const icInput = row.querySelector(`[name="borrowers[${index}][ic_number]"]`);
            if (nameInput) {
                nameInput.value = name || '';
            }
            if (icInput) {
                icInput.value = ic || '';
            }
            coBorrowersEl.appendChild(row);
        };

        addBorrowerBtn?.addEventListener('click', (event) => {
            event.preventDefault();
            addBorrowerRow();
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
    const shelfId = document.getElementById('shelf_id');
    const status = document.getElementById('scanner-status');
    const shelfForm = shelfScan?.form;
    const shelfResult = shelfForm?.querySelector('[data-shelf-result]');
    const shelfLocation = shelfForm?.querySelector('[data-shelf-location]');
    const shelfConfirm = shelfForm?.querySelector('[data-shelf-confirm]');
    const rackPick = shelfForm?.querySelector('[data-entry-rack-pick]');
    const shelfPick = shelfForm?.querySelector('[data-entry-shelf-pick]');
    let shelfLookupTimer;
    let shelfLookupVersion = 0;
    let shelfStoring = false;

    const msg = (key, fallback) => status?.dataset?.[key] || fallback;

    const setShelfReady = (ready, location = '') => {
        shelfForm?.classList.toggle('is-ready', ready);
        if (shelfConfirm) shelfConfirm.disabled = !ready;
        if (shelfResult) {
            shelfResult.classList.toggle('hidden', !ready);
            if (shelfLocation) shelfLocation.textContent = location;
        }
    };

    const clearShelfReady = () => {
        if (shelfQr) shelfQr.value = '';
        if (shelfId) shelfId.value = '';
        setShelfReady(false);
    };

    const applyManualShelf = () => {
        if (!shelfPick) return;
        const option = shelfPick.selectedOptions[0];
        const id = shelfPick.value;
        if (!id || !option) {
            if (shelfId) shelfId.value = '';
            if (!shelfQr?.value) setShelfReady(false);
            return;
        }

        shelfLookupVersion++;
        if (shelfQr) shelfQr.value = '';
        if (shelfId) shelfId.value = id;
        const location = option.dataset.location || option.textContent.trim();
        setShelfReady(true, location);
        if (status) status.textContent = msg('msgManualOk', 'Shelf selected. Confirm to store.');
    };

    const syncShelfPickOptions = () => {
        if (!rackPick || !shelfPick) return;
        const rackId = rackPick.value;
        const options = Array.from(shelfPick.querySelectorAll('option[data-rack-id]'));
        let cleared = false;
        options.forEach((option) => {
            const match = !rackId || option.dataset.rackId === rackId;
            option.hidden = !match;
            if (!match && option.selected) {
                shelfPick.value = '';
                cleared = true;
            }
        });
        if (cleared || shelfPick.value) applyManualShelf();
    };

    const resolveShelf = async (payload, { autoStore = false } = {}) => {
        clearTimeout(shelfLookupTimer);
        const version = ++shelfLookupVersion;
        clearShelfReady();
        if (shelfPick) shelfPick.value = '';
        if (!payload.startsWith(shelfPrefix) || !uuidPattern.test(payload.slice(shelfPrefix.length))) {
            status.textContent = msg('msgInvalid', 'Scan a valid shelf QR code.');
            return;
        }

        status.textContent = msg('msgChecking', 'Checking shelf…');
        try {
            const identifier = payload.slice(shelfPrefix.length);
            const response = await fetch(shelfScan.dataset.lookupUrl.replace('__IDENTIFIER__', identifier), {
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) throw new Error('Shelf lookup failed');
            const shelf = await response.json();
            if (version !== shelfLookupVersion) return;
            shelfQr.value = payload;
            if (shelfId) shelfId.value = '';
            shelfScan.value = shelf.location;
            setShelfReady(true, shelf.location);
            status.textContent = msg('msgOk', '');
            if (autoStore && shelfForm?.hasAttribute('data-auto-store') && shelfQr.value && !shelfStoring) {
                shelfStoring = true;
                shelfForm.requestSubmit();
            }
        } catch {
            if (version !== shelfLookupVersion) return;
            status.textContent = msg('msgBad', 'Shelf QR is invalid or the shelf is inactive.');
        }
    };
    if (shelfScan) {
        if (shelfScan.dataset.initialQr) resolveShelf(shelfScan.dataset.initialQr);
        else if (shelfId?.value) applyManualShelf();
        shelfScan.addEventListener('focus', () => shelfScan.select());
        shelfScan.addEventListener('input', () => {
            clearTimeout(shelfLookupTimer);
            shelfLookupVersion++;
            clearShelfReady();
            shelfLookupTimer = setTimeout(
                () => resolveShelf(shelfScan.value.trim(), { autoStore: true }),
                180,
            );
        });
        shelfScan.form.addEventListener('submit', (event) => {
            if (shelfQr?.value || shelfId?.value) return;
            event.preventDefault();
            status.textContent = msg('msgRequired', 'Scan a valid shelf QR code before storing the file.');
        });
    }
    if (rackPick && shelfPick) {
        syncShelfPickOptions();
        rackPick.addEventListener('change', syncShelfPickOptions);
        shelfPick.addEventListener('change', applyManualShelf);
    }
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' || !event.target.matches('input[data-qr-kind]')) return;
        event.preventDefault();
        if (event.target === shelfScan) {
            clearTimeout(shelfLookupTimer);
            resolveShelf(shelfScan.value.trim(), { autoStore: true });
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
            // Preserve icon markup on entry shelf scan button.
            if (activeButton.dataset.labelIdle) {
                const icon = activeButton.querySelector('.entry-shelf__scan-icon');
                activeButton.replaceChildren();
                if (icon) activeButton.append(icon, document.createTextNode(activeButton.dataset.labelIdle));
                else activeButton.textContent = activeButton.dataset.labelIdle;
            } else {
                activeButton.textContent = activeButton.dataset.originalLabel;
            }
            activeButton = null;
        }
    };

    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-scan-target]');
        if (!button) return;
            if (running && activeTarget?.id === button.dataset.scanTarget) {
                await stop();
                reader.classList.add('hidden');
                status.textContent = msg('msgCameraStop', 'Camera stopped.');
                return;
            }
            activeTarget = document.getElementById(button.dataset.scanTarget);
            if (!activeTarget) return;
            await stop();
            activeButton = button;
            button.dataset.originalLabel ??= button.textContent.trim();
            if (button.dataset.labelActive) {
                const icon = button.querySelector('.entry-shelf__scan-icon');
                button.replaceChildren();
                if (icon) button.append(icon, document.createTextNode(button.dataset.labelActive));
                else button.textContent = button.dataset.labelActive;
            }
            reader.classList.remove('hidden');
            status.textContent = msg('msgCamera', 'Camera active — hold the QR code inside the frame.');
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
                            const wrongKind = kind === 'shelf' ? 'shelf' : 'file or staff';
                            status.textContent = status?.dataset.msgWrong
                                || ('Wrong QR type. Scan a ' + wrongKind + ' QR.');
                            return;
                        }
                        if (activeTarget.tagName === 'TEXTAREA') {
                            const values = activeTarget.value.split(/\s+/).filter(Boolean);
                            if (! values.includes(value)) {
                                values.push(value);
                                activeTarget.value = values.join('\n');
                                status.textContent = values.length + ' file QR code(s) captured. Scan another file or tap Scan file to stop.';
                            } else {
                                status.textContent = 'File already scanned. Scan another file or tap Scan file to stop.';
                            }
                            return;
                        } else {
                            activeTarget.value = value;
                        }
                        if (kind === 'shelf') {
                            await resolveShelf(value, { autoStore: true });
                        } else {
                            status.textContent = 'QR captured successfully.';
                        }
                        await stop();
                        reader.classList.add('hidden');
                        if (kind === 'shelf' && shelfForm?.hasAttribute('data-auto-store')) {
                            return;
                        }
                        if (kind === 'shelf' && shelfConfirm && !shelfConfirm.disabled) {
                            shelfConfirm.focus();
                        } else {
                            activeTarget.focus();
                        }
                    },
                );
                running = true;
                if (activeTarget.tagName === 'TEXTAREA') button.textContent = 'Stop scanning';
            } catch {
                await stop();
                status.textContent = msg('msgCameraFail', 'Camera unavailable. Use a USB scanner or enter the QR identifier.');
                reader.classList.add('hidden');
            }
    });

});
