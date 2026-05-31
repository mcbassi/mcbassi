(function () {
    const shellContent = document.querySelector('.js-shell-content');
    if (!shellContent) {
        return;
    }

    const basePath = (document.body.dataset.basePath || '').replace(/\/+$/, '');
    let currentRequestId = 0;

    const sidebarOpenGroupStorageKey = 'prodcol_sidebar_open_group';

    function readSidebarOpenGroup() {
        try {
            return window.localStorage.getItem(sidebarOpenGroupStorageKey);
        } catch (error) {
            return null;
        }
    }

    function writeSidebarOpenGroup(groupId) {
        try {
            if (groupId) {
                window.localStorage.setItem(sidebarOpenGroupStorageKey, groupId);
            } else {
                window.localStorage.removeItem(sidebarOpenGroupStorageKey);
            }
        } catch (error) {
            // localStorage pode estar indisponível em alguns navegadores/modos privados.
        }
    }

    function sidebarGroupHasActiveLink(group) {
        return !!group.querySelector('.menu-link.is-active, .quick-pill.is-active');
    }

    function setSidebarGroupOpen(group, isOpen) {
        const toggle = group.querySelector('[data-menu-toggle]');

        group.classList.toggle('is-collapsed', !isOpen);

        if (toggle) {
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        }
    }

    function closeAllSidebarGroups(exceptGroup) {
        Array.from(document.querySelectorAll('[data-menu-group]')).forEach(function (group) {
            if (exceptGroup && group === exceptGroup) {
                return;
            }
            setSidebarGroupOpen(group, false);
        });
    }

    function openOnlySidebarGroup(group, persist) {
        if (!group) {
            return;
        }

        closeAllSidebarGroups(group);
        setSidebarGroupOpen(group, true);

        if (persist) {
            writeSidebarOpenGroup(group.dataset.menuGroup || '');
        }
    }

    function toggleSidebarGroupFromButton(group) {
        if (!group) {
            return;
        }

        const isCurrentlyOpen = !group.classList.contains('is-collapsed');

        if (isCurrentlyOpen) {
            setSidebarGroupOpen(group, false);
            writeSidebarOpenGroup('');
            return;
        }

        openOnlySidebarGroup(group, true);
    }

    function initSidebarAccordion() {
        const groups = Array.from(document.querySelectorAll('[data-menu-group]'));
        if (!groups.length) {
            return;
        }

        let groupToOpen = groups.find(sidebarGroupHasActiveLink) || null;
        const savedGroupId = readSidebarOpenGroup();

        if (!groupToOpen && savedGroupId) {
            groupToOpen = groups.find(function (group) {
                return group.dataset.menuGroup === savedGroupId;
            }) || null;
        }

        if (!groupToOpen) {
            groupToOpen = groups[0];
        }

        groups.forEach(function (group, index) {
            if (!group.dataset.menuGroup) {
                group.dataset.menuGroup = 'group_' + index;
            }

            const toggle = group.querySelector('[data-menu-toggle]');
            setSidebarGroupOpen(group, group === groupToOpen);

            if (toggle && toggle.dataset.accordionBound !== 'model-click') {
                toggle.dataset.accordionBound = 'model-click';
                toggle.addEventListener('click', function (event) {
                    event.preventDefault();
                    toggleSidebarGroupFromButton(group);
                });
            }
        });

        writeSidebarOpenGroup(groupToOpen.dataset.menuGroup || '');
    }

    function openActiveSidebarGroup() {
        const activeGroup = Array.from(document.querySelectorAll('[data-menu-group]')).find(sidebarGroupHasActiveLink) || null;
        if (activeGroup) {
            openOnlySidebarGroup(activeGroup, true);
        }
    }

    function normalizePath(pathname) {
        let path = pathname || '/';
        path = path.replace(/\/+/g, '/');

        if (basePath && path.indexOf(basePath) === 0) {
            path = path.slice(basePath.length) || '/';
        }

        if (!path.startsWith('/')) {
            path = '/' + path;
        }

        path = path.replace(/\/index\.php$/, '/');
        path = path.length > 1 ? path.replace(/\/+$/, '') : path;

        return path || '/';
    }

    function shouldHandleLink(link) {
        if (!link) {
            return false;
        }

        if (link.dataset.shellNav === 'off') {
            return false;
        }

        if (link.target && link.target !== '_self') {
            return false;
        }

        if (link.hasAttribute('download')) {
            return false;
        }

        const rawHref = link.getAttribute('href') || '';
        if (
            rawHref === '' ||
            rawHref.startsWith('#') ||
            rawHref.startsWith('mailto:') ||
            rawHref.startsWith('tel:') ||
            rawHref.startsWith('javascript:')
        ) {
            return false;
        }

        const url = new URL(link.href, window.location.href);
        if (url.origin !== window.location.origin) {
            return false;
        }

        if (/\/api\//.test(url.pathname)) {
            return false;
        }

        return true;
    }

    function markLoading(isLoading) {
        shellContent.classList.toggle('is-loading', isLoading);
        document.body.classList.toggle('shell-is-loading', isLoading);
    }

    function toPartialUrl(urlValue) {
        const url = new URL(urlValue, window.location.href);
        url.searchParams.set('_partial', '1');
        return url;
    }

    function syncStyles(container) {
        const links = Array.from(container.querySelectorAll('link[rel="stylesheet"][href]'));

        links.forEach(function (link) {
            const href = link.href;
            const existing = document.head.querySelector('link[data-shell-style="' + CSS.escape(href) + '"]');
            if (!existing) {
                const clone = document.createElement('link');
                clone.rel = 'stylesheet';
                clone.href = href;
                clone.dataset.shellStyle = href;
                document.head.appendChild(clone);
            }
            link.remove();
        });
    }

    function isScriptAlreadyLoaded(src) {
        if (!src) {
            return false;
        }

        const absolute = new URL(src, window.location.href).href;
        return Array.from(document.scripts).some(function (script) {
            if (!script.src) {
                return false;
            }
            if (shellContent.contains(script)) {
                return false;
            }
            return new URL(script.src, window.location.href).href === absolute && script.dataset.shellPending !== '1';
        });
    }

    function loadExternalScript(src) {
        return new Promise(function (resolve) {
            if (!src) {
                resolve();
                return;
            }

            if (isScriptAlreadyLoaded(src)) {
                resolve();
                return;
            }

            const script = document.createElement('script');
            script.src = new URL(src, window.location.href).href;
            script.async = false;
            script.dataset.shellPending = '1';
            script.onload = function () {
                delete script.dataset.shellPending;
                resolve();
            };
            script.onerror = function () {
                delete script.dataset.shellPending;
                console.warn('Não foi possível carregar script externo:', src);
                resolve();
            };
            document.head.appendChild(script);
        });
    }

    function ensureChartJsIfNeeded(code) {
        if (!code || !/\bChart\b/.test(code) || window.Chart) {
            return Promise.resolve();
        }

        return loadExternalScript('https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js');
    }

    async function executeScripts(container) {
        const scripts = Array.from(container.querySelectorAll('script'));

        for (const oldScript of scripts) {
            const src = oldScript.getAttribute('src');

            if (src) {
                await loadExternalScript(src);
                oldScript.remove();
                continue;
            }

            const code = oldScript.textContent || '';
            await ensureChartJsIfNeeded(code);

            if (/\bChart\b/.test(code) && !window.Chart) {
                console.warn('Chart.js não carregou; script de gráfico ignorado para não travar a tela.');
                oldScript.remove();
                continue;
            }

            const script = document.createElement('script');
            Array.from(oldScript.attributes).forEach(function (attribute) {
                script.setAttribute(attribute.name, attribute.value);
            });
            script.textContent = code;

            try {
                oldScript.replaceWith(script);
            } catch (error) {
                console.error('Erro ao executar script da tela carregada pelo SPA:', error);
                oldScript.remove();
            }
        }
    }

    function translateLoadedContent(container) {
        const i18n = window.APP_I18N || {};
        const map = i18n.auto_map || {};
        const entries = Object.keys(map)
            .filter(function (source) {
                return source && map[source] && source !== map[source];
            })
            .sort(function (a, b) {
                return b.length - a.length;
            })
            .map(function (source) {
                return [source, map[source]];
            });

        if (!entries.length || !container) {
            return;
        }

        function translateText(value) {
            if (!value || !value.trim()) {
                return value;
            }

            entries.forEach(function (pair) {
                value = value.split(pair[0]).join(pair[1]);
            });

            return value;
        }

        const blocked = 'script,style,textarea,pre,code,[data-no-i18n],[translate="no"]';
        const walker = document.createTreeWalker(container, NodeFilter.SHOW_TEXT, {
            acceptNode: function (node) {
                if (!node.nodeValue || !node.nodeValue.trim()) {
                    return NodeFilter.FILTER_REJECT;
                }
                if (node.parentElement && node.parentElement.closest(blocked)) {
                    return NodeFilter.FILTER_REJECT;
                }
                return NodeFilter.FILTER_ACCEPT;
            }
        });

        const textNodes = [];
        while (walker.nextNode()) {
            textNodes.push(walker.currentNode);
        }

        textNodes.forEach(function (node) {
            node.nodeValue = translateText(node.nodeValue || '');
        });

        Array.from(container.querySelectorAll('[placeholder], [title], [aria-label], [alt], [data-title], [data-label], [data-confirm], [data-placeholder], [data-bs-original-title], [data-bs-title]')).forEach(function (element) {
            if (element.closest('[data-no-i18n], [translate="no"]')) return;
            ['placeholder', 'title', 'aria-label', 'alt', 'data-title', 'data-label', 'data-confirm', 'data-placeholder', 'data-bs-original-title', 'data-bs-title'].forEach(function (attribute) {
                if (element.hasAttribute(attribute)) {
                    element.setAttribute(attribute, translateText(element.getAttribute(attribute) || ''));
                }
            });
        });

        Array.from(container.querySelectorAll('input[type=button], input[type=submit], input[type=reset]')).forEach(function (element) {
            if (element.closest('[data-no-i18n], [translate="no"]')) return;
            if (element.value) {
                element.value = translateText(element.value);
            }
        });
    }

    window.APP_TRANSLATE_CONTENT = translateLoadedContent;
    document.addEventListener('app:i18n:refresh', function (event) {
        const detail = event.detail || {};
        translateLoadedContent(detail.container || shellContent);
    });

    function updateActiveNavigation(path) {
        const normalizedPath = normalizePath(path);
        const navLinks = document.querySelectorAll('[data-nav-prefix]');

        navLinks.forEach(function (link) {
            const prefix = (link.dataset.navPrefix || '/').trim() || '/';
            const isActive = prefix === '/'
                ? normalizedPath === '/'
                : normalizedPath === prefix || normalizedPath.indexOf(prefix + '/') === 0;

            link.classList.toggle('is-active', isActive);
        });

        shellContent.dataset.currentPath = normalizedPath;
        openActiveSidebarGroup();
    }

    function translateGlobalWidgets() {
        ['#chat-window', '#chat-widget-button', '#avatarModal', '.modal', '[data-i18n-scope="global"]'].forEach(function (selector) {
            Array.from(document.querySelectorAll(selector)).forEach(function (element) {
                translateLoadedContent(element);
            });
        });
    }

    function refreshTranslations() {
        if (!shellContent.querySelector('[data-shell-no-auto-i18n]')) {
            translateLoadedContent(shellContent);
        }
        translateGlobalWidgets();
    }

    async function afterSwap() {
        syncStyles(shellContent);
        await executeScripts(shellContent);
        refreshTranslations();
        window.setTimeout(refreshTranslations, 0);
        window.setTimeout(refreshTranslations, 250);
        document.dispatchEvent(new CustomEvent('shell:navigated', {
            detail: {
                path: shellContent.dataset.currentPath || '/'
            }
        }));
    }

    async function navigate(urlValue, pushState) {
        const requestId = ++currentRequestId;
        const targetUrl = new URL(urlValue, window.location.href);

        markLoading(true);

        try {
            const partialUrl = toPartialUrl(targetUrl);
            const response = await fetch(partialUrl.toString(), {
                headers: {
                    'X-Shell-Partial': '1',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });

            if (!response.ok) {
                window.location.href = targetUrl.toString();
                return;
            }

            const html = await response.text();

            if (requestId !== currentRequestId) {
                return;
            }

            shellContent.innerHTML = html;

            const currentPath = response.headers.get('X-Current-Path') || targetUrl.pathname;
            const pageTitle = response.headers.get('X-Page-Title');

            updateActiveNavigation(currentPath);

            if (pageTitle) {
                document.title = pageTitle + ' · Lab Produtividad';
            }

            await afterSwap();

            if (pushState) {
                window.history.pushState({ path: currentPath }, '', targetUrl.toString());
            }

            window.scrollTo({ top: 0, behavior: 'smooth' });
        } catch (error) {
            window.location.href = targetUrl.toString();
        } finally {
            if (requestId === currentRequestId) {
                markLoading(false);
            }
        }
    }

    document.addEventListener('click', function (event) {
        const link = event.target.closest('a');
        if (!shouldHandleLink(link)) {
            return;
        }

        if (!link.closest('.app-shell')) {
            return;
        }

        event.preventDefault();
        navigate(link.href, true);
    });

    window.addEventListener('popstate', function () {
        navigate(window.location.href, false);
    });

    initSidebarAccordion();
    updateActiveNavigation(shellContent.dataset.currentPath || window.location.pathname);
    afterSwap();
})();
