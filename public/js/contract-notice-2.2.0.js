'use strict';

var contractNoticeRoot = window.CFG_GLPI && window.CFG_GLPI.root_doc
    ? window.CFG_GLPI.root_doc
    : '';
var contractNoticeFeedUrl = contractNoticeRoot + '/plugins/contractnotice/front/feed.php';
var contractNoticeAcknowledgementUrl = contractNoticeRoot + '/plugins/contractnotice/front/ack-2.2.0.php';
var contractNoticeSeen = {};

function contractNoticeKey(notice, sessionKey) {
    return ['glpi-contractnotice-v5', sessionKey, notice.id, notice.date_mod].join(':');
}

function contractNoticeWasSeen(key) {
    if (contractNoticeSeen[key]) {
        return true;
    }
    try {
        return window.localStorage.getItem(key) === '1';
    } catch (error) {
        return false;
    }
}

function contractNoticeMarkSeen(key) {
    contractNoticeSeen[key] = true;
    try {
        window.localStorage.setItem(key, '1');
    } catch (error) {
        // The in-memory fallback still prevents duplicate modals.
    }
}

function contractNoticeReturnKey(notice, userId) {
    return ['glpi-contractnotice-v5-return', userId, notice.id, notice.date_mod].join(':');
}

function contractNoticeRememberReturnSuppression(notices, userId) {
    if (!userId || !notices) {
        return;
    }
    try {
        for (var index = 0; index < notices.length; index++) {
            window.localStorage.setItem(contractNoticeReturnKey(notices[index], userId), '1');
        }
    } catch (error) {
        // If storage is unavailable, normal session-based behavior is kept.
    }
}

function contractNoticeConsumeReturnSuppression(notice, userId) {
    var key = contractNoticeReturnKey(notice, userId);
    try {
        if (window.localStorage.getItem(key) !== '1') {
            return false;
        }
        window.localStorage.removeItem(key);
        return true;
    } catch (error) {
        return false;
    }
}

function contractNoticeAcknowledgeDaily(notice, payload) {
    if (!payload || !payload.csrf_token || !window.fetch) {
        return;
    }
    var body = '_glpi_csrf_token=' + encodeURIComponent(payload.csrf_token)
        + '&id=' + encodeURIComponent(notice.id)
        + '&date_mod=' + encodeURIComponent(notice.date_mod);
    window.fetch(contractNoticeAcknowledgementUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-Glpi-Csrf-Token': payload.csrf_token
        },
        body: body
    }).catch(function () {
        // The local acknowledgement still prevents duplicate modals in this session.
    });
}

function contractNoticeShow(notice, acknowledgementPayload) {
    if (document.getElementById('plugin-contractnotice-modal')) {
        return;
    }

    var overlay = document.createElement('div');
    overlay.id = 'plugin-contractnotice-modal';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-labelledby', 'plugin-contractnotice-title');
    overlay.style.cssText = 'position:fixed;inset:0;z-index:10000;display:flex;align-items:center;justify-content:center;padding:1rem;background:rgba(0,0,0,.55)';

    var card = document.createElement('section');
    card.style.cssText = 'width:min(100%,680px);max-height:calc(100vh - 2rem);overflow:auto;border-radius:.5rem;background:#fff;color:#182433;box-shadow:0 1rem 3rem rgba(0,0,0,.35)';

    var header = document.createElement('div');
    header.style.cssText = 'padding:1.25rem 1.5rem;border-bottom:1px solid #dfe3e7';
    var title = document.createElement('h2');
    title.id = 'plugin-contractnotice-title';
    title.textContent = notice.name;
    title.style.cssText = 'margin:0;font-size:1.25rem';
    header.appendChild(title);

    var body = document.createElement('div');
    body.style.cssText = 'padding:1.5rem;line-height:1.55;white-space:pre-wrap';
    body.textContent = notice.content;

    var footer = document.createElement('div');
    footer.style.cssText = 'padding:1rem 1.5rem;border-top:1px solid #dfe3e7;text-align:right';
    var acknowledge = document.createElement('button');
    acknowledge.type = 'button';
    acknowledge.textContent = 'Ciente';
    acknowledge.style.cssText = 'border:0;border-radius:.25rem;padding:.55rem 1rem;background:#206bc4;color:#fff;font-weight:600;cursor:pointer';

    function close() {
        document.removeEventListener('keydown', onKeyDown);
        overlay.remove();
    }
    function onKeyDown(event) {
        if (event.key === 'Escape') {
            close();
        }
    }

    acknowledge.addEventListener('click', function () {
        if (notice.delivery_mode === 'daily_login') {
            contractNoticeAcknowledgeDaily(notice, acknowledgementPayload);
        }
        close();
    });
    document.addEventListener('keydown', onKeyDown);
    footer.appendChild(acknowledge);
    card.appendChild(header);
    card.appendChild(body);
    card.appendChild(footer);
    overlay.appendChild(card);
    document.body.appendChild(overlay);
    acknowledge.focus();
}

function contractNoticeLoad(mode) {
    if (!window.fetch) {
        return;
    }
    window.fetch(contractNoticeFeedUrl + '?mode=' + encodeURIComponent(mode), {
        credentials: 'same-origin',
        headers: {Accept: 'application/json'}
    }).then(function (response) {
        var contentType = response.headers.get('content-type') || '';
        if (!response.ok || contentType.indexOf('application/json') === -1) {
            return null;
        }
        return response.json();
    }).then(function (payload) {
        if (!payload) {
            return;
        }
        if (payload.is_impersonating) {
            contractNoticeRememberReturnSuppression(
                payload.return_suppression_notices || [],
                payload.impersonator_id
            );
            return;
        }
        if (!payload.notices || !payload.notices.length || !payload.session_key) {
            return;
        }
        var notice = payload.notices[0];
        var key = contractNoticeKey(notice, payload.session_key);
        if (contractNoticeConsumeReturnSuppression(notice, payload.user_id)) {
            contractNoticeMarkSeen(key);
            return;
        }
        if (!contractNoticeWasSeen(key)) {
            contractNoticeMarkSeen(key);
            contractNoticeShow(notice, payload);
        }
    }).catch(function () {
        // A background check must never interfere with GLPI usage.
    });
}

function contractNoticeStart() {
    if (window.contractNoticeStarted) {
        return;
    }
    window.contractNoticeStarted = true;
    contractNoticeLoad('initial');
    window.setInterval(function () {
        contractNoticeLoad('poll');
    }, 30000);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', contractNoticeStart);
} else {
    contractNoticeStart();
}
