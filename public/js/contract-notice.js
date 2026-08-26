(() => {
    'use strict';

    const rootDoc = window.CFG_GLPI?.root_doc ?? '';
    const feedUrl = `${rootDoc}/plugins/contractnotice/front/feed.php`;
    const seenInMemory = new Set();

    const getKey = (notice, sessionKey) => [
        'glpi-contractnotice-v2',
        sessionKey,
        notice.id,
        notice.date_mod,
    ].join(':');

    const wasSeen = (key) => {
        if (seenInMemory.has(key)) {
            return true;
        }
        try {
            return window.sessionStorage.getItem(key) === '1';
        } catch (error) {
            return false;
        }
    };

    const markSeen = (key) => {
        seenInMemory.add(key);
        try {
            window.sessionStorage.setItem(key, '1');
        } catch (error) {
            // The in-memory fallback still prevents duplicates in this page.
        }
    };

    const showNotice = (notice) => {
        if (document.getElementById('plugin-contractnotice-modal')) {
            return;
        }

        const overlay = document.createElement('div');
        overlay.id = 'plugin-contractnotice-modal';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-labelledby', 'plugin-contractnotice-title');
        overlay.style.cssText = [
            'position:fixed', 'inset:0', 'z-index:10000', 'display:flex',
            'align-items:center', 'justify-content:center', 'padding:1rem',
            'background:rgba(0,0,0,.55)',
        ].join(';');

        const card = document.createElement('section');
        card.style.cssText = [
            'width:min(100%, 680px)', 'max-height:calc(100vh - 2rem)',
            'overflow:auto', 'border-radius:.5rem', 'background:#fff',
            'color:#182433', 'box-shadow:0 1rem 3rem rgba(0,0,0,.35)',
        ].join(';');

        const header = document.createElement('div');
        header.style.cssText = 'padding:1.25rem 1.5rem;border-bottom:1px solid #dfe3e7';
        const title = document.createElement('h2');
        title.id = 'plugin-contractnotice-title';
        title.textContent = notice.name;
        title.style.cssText = 'margin:0;font-size:1.25rem';
        header.appendChild(title);

        const body = document.createElement('div');
        body.style.cssText = 'padding:1.5rem;line-height:1.55;white-space:pre-wrap';
        body.textContent = notice.content;

        const footer = document.createElement('div');
        footer.style.cssText = 'padding:1rem 1.5rem;border-top:1px solid #dfe3e7;text-align:right';
        const acknowledge = document.createElement('button');
        acknowledge.type = 'button';
        acknowledge.textContent = 'Ciente';
        acknowledge.style.cssText = [
            'border:0', 'border-radius:.25rem', 'padding:.55rem 1rem',
            'background:#206bc4', 'color:#fff', 'font-weight:600', 'cursor:pointer',
        ].join(';');

        const close = () => {
            document.removeEventListener('keydown', onKeyDown);
            overlay.remove();
        };
        const onKeyDown = (event) => {
            if (event.key === 'Escape') {
                close();
            }
        };

        acknowledge.addEventListener('click', close);
        document.addEventListener('keydown', onKeyDown);
        footer.appendChild(acknowledge);
        card.append(header, body, footer);
        overlay.appendChild(card);
        document.body.appendChild(overlay);
        acknowledge.focus();
    };

    const loadNotices = async (mode) => {
        try {
            const response = await window.fetch(`${feedUrl}?mode=${encodeURIComponent(mode)}`, {
                credentials: 'same-origin',
                headers: {Accept: 'application/json'},
            });
            if (!response.ok || !response.headers.get('content-type')?.includes('application/json')) {
                return;
            }

            const payload = await response.json();
            const notice = payload.notices?.[0];
            if (!notice || !payload.session_key) {
                return;
            }

            const key = getKey(notice, payload.session_key);
            if (!wasSeen(key)) {
                markSeen(key);
                showNotice(notice);
            }
        } catch (error) {
            // A background check must never interfere with GLPI usage.
        }
    };

    const start = () => {
        loadNotices('initial');
        window.setInterval(() => loadNotices('poll'), 30000);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, {once: true});
    } else {
        start();
    }
})();
