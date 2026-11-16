/**
 * Offline drafts for the pharmacy app.
 *
 * Layer 1 (localStorage): written synchronously on every change, so a power
 * cut cannot lose what was typed.
 * Layer 2 (server): the same draft is upserted every ~1s while typing.
 * The local copy is deleted only after the server confirms it was saved.
 *
 * Drafts are namespaced per user id because Windows/browser profiles are
 * shared between employees on the same PC.
 */
(function () {
    'use strict';

    var cfg = window.PsychDraftConfig;
    var TYPES = [
        'invoice', 'dispensed_medicine', 'medicine', 'medicine_edit',
        'stock', 'stock_edit', 'stock_transfer', 'unit', 'unit_edit'
    ];
    var SYNC_DELAY = 1000;
    var RETRY_BASE = 1500;
    var RETRY_MAX = 30000;

    if (!cfg || !cfg.userId || !cfg.routes || !cfg.routes.sync) {
        return;
    }

    var PREFIX = 'psych_drafts:v1:u' + cfg.userId + ':';
    var INDEX_KEY = PREFIX + '__index';

    // ================= storage =================

    function uuidv4() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            try { return window.crypto.randomUUID(); } catch (e) { /* insecure context */ }
        }

        var bytes = new Uint8Array(16);
        if (window.crypto && window.crypto.getRandomValues) {
            window.crypto.getRandomValues(bytes);
        } else {
            for (var i = 0; i < 16; i++) bytes[i] = Math.floor(Math.random() * 256);
        }

        bytes[6] = (bytes[6] & 0x0f) | 0x40;
        bytes[8] = (bytes[8] & 0x3f) | 0x80;

        var hex = [];
        for (var j = 0; j < 16; j++) hex.push(bytes[j].toString(16).padStart(2, '0'));

        return hex.slice(0, 4).join('') + '-' + hex.slice(4, 6).join('') + '-' +
            hex.slice(6, 8).join('') + '-' + hex.slice(8, 10).join('') + '-' +
            hex.slice(10, 16).join('');
    }

    function key(uuid) {
        return PREFIX + uuid;
    }

    function readIndex() {
        try {
            var raw = localStorage.getItem(INDEX_KEY);
            var list = raw ? JSON.parse(raw) : [];
            return Array.isArray(list) ? list : [];
        } catch (e) {
            return [];
        }
    }

    function writeIndex(list) {
        try {
            localStorage.setItem(INDEX_KEY, JSON.stringify(Array.from(new Set(list))));
        } catch (e) { /* quota / private mode */ }
    }

    function addToIndex(uuid) {
        var list = readIndex();
        if (list.indexOf(uuid) === -1) {
            list.push(uuid);
            writeIndex(list);
        }
    }

    function removeFromIndex(uuid) {
        writeIndex(readIndex().filter(function (id) { return id !== uuid; }));
    }

    function readEntry(uuid) {
        try {
            var raw = localStorage.getItem(key(uuid));
            return raw ? JSON.parse(raw) : null;
        } catch (e) {
            return null;
        }
    }

    function writeEntry(entry) {
        try {
            localStorage.setItem(key(entry.uuid), JSON.stringify(entry));
            addToIndex(entry.uuid);
        } catch (e) {
            setState('error');
        }
    }

    function removeEntry(uuid) {
        try { localStorage.removeItem(key(uuid)); } catch (e) { /* ignore */ }
        removeFromIndex(uuid);
    }

    function localEntries() {
        var out = [];
        readIndex().forEach(function (uuid) {
            var entry = readEntry(uuid);
            if (entry && entry.uuid) out.push(entry);
        });
        return out;
    }

    function cleanIndex() {
        var keep = [];
        readIndex().forEach(function (uuid) {
            var entry = readEntry(uuid);
            if (entry && entry.uuid) keep.push(uuid);
        });
        writeIndex(keep);
    }

    // ================= handles =================

    function make(type, uuid) {
        return {
            uuid: uuid || uuidv4(),
            type: type,
            revision: 0,
            closed: false,
            blocked: false,
            pendingPayload: null,
            lastPayloadJson: null,
            lastSyncedJson: null,
            timerSync: null,
            retries: 0
        };
    }

    function resetHandle(handle) {
        handle.uuid = uuidv4();
        handle.revision = 0;
        handle.closed = false;
        handle.blocked = false;
        handle.pendingPayload = null;
        handle.lastPayloadJson = null;
        handle.lastSyncedJson = null;
        handle.retries = 0;
    }

    function persist(handle) {
        if (!handle.pendingPayload) return;

        writeEntry({
            uuid: handle.uuid,
            type: handle.type,
            payload: handle.pendingPayload,
            revision: handle.revision,
            updatedAt: Date.now(),
            dirty: handle.lastSyncedJson !== JSON.stringify(handle.pendingPayload)
        });
    }

    function save(handle, payload) {
        if (!handle || !payload) return handle;

        if (handle.closed) resetHandle(handle);

        var json = JSON.stringify(payload);

        if (json === handle.lastPayloadJson) {
            return handle;
        }

        handle.pendingPayload = payload;
        handle.lastPayloadJson = json;
        handle.revision += 1;

        persist(handle);
        setState('local');
        scheduleSync(handle);

        return handle;
    }

    function scheduleSync(handle) {
        clearTimeout(handle.timerSync);
        handle.timerSync = setTimeout(function () { enqueue(handle); }, SYNC_DELAY);
    }

    // ================= server sync =================

    var chain = Promise.resolve();

    function enqueue(handle) {
        chain = chain.then(function () { return doSync(handle); }).catch(function () { /* handled inside */ });
        return chain;
    }

    function doSync(handle) {
        if (handle.closed || handle.blocked || !handle.pendingPayload) return Promise.resolve();

        var json = JSON.stringify(handle.pendingPayload);

        if (json === handle.lastSyncedJson) {
            persist(handle);
            return Promise.resolve();
        }

        setState('syncing');

        return fetch(cfg.routes.sync, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': cfg.csrf,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                uuid: handle.uuid,
                type: handle.type,
                payload: handle.pendingPayload,
                revision: handle.revision
            })
        }).then(function (res) {
            if (res.status === 403) {
                handle.closed = true;
                setState('error');
                notify('هذه المسودة تخص مستخدماً آخر ولم يتم إرسالها من حسابك.', 'error');
                return null;
            }

            if (res.status >= 400 && res.status < 500) {
                // Permanent client-side problem: retrying will not help.
                // The local copy stays safe and is retried after the next page load.
                handle.blocked = true;
                setState('error');
                notify(
                    res.status === 419
                        ? 'انتهت الجلسة. أعد تسجيل الدخول لاستكمال حفظ المسودة (النسخة المحلية محفوظة).'
                        : 'تعذر حفظ المسودة على السيرفر. النسخة المحلية محفوظة.',
                    'error'
                );
                return null;
            }

            if (!res.ok) {
                throw new Error('sync-' + res.status);
            }

            return res.json();
        }).then(function (data) {
            if (!data) return;

            if (data.status && data.status !== 'pending') {
                // Saved (or discarded) elsewhere: our local copy is obsolete.
                removeEntry(handle.uuid);
                handle.closed = true;
                handle.pendingPayload = null;
                setState('synced');
                notify('تم حفظ هذه المسودة في قاعدة البيانات، وتم حذف النسخة المحلية.', 'success');
                return;
            }

            handle.lastSyncedJson = json;
            handle.retries = 0;
            setState('synced');

            var entry = readEntry(handle.uuid);
            if (entry) {
                entry.dirty = false;
                entry.revision = handle.revision;
                writeEntry(entry);
            }
        }).catch(function () {
            handle.retries += 1;
            setState('offline');

            var delay = Math.min(RETRY_MAX, RETRY_BASE * Math.pow(2, handle.retries - 1));
            clearTimeout(handle.timerSync);
            handle.timerSync = setTimeout(function () { enqueue(handle); }, delay);
        });
    }

    // ================= boot reconciliation =================

    function handleFromEntry(entry) {
        var handle = make(entry.type, entry.uuid);
        handle.revision = entry.revision || 0;
        handle.pendingPayload = entry.payload;
        handle.lastPayloadJson = JSON.stringify(entry.payload);
        handle.lastSyncedJson = entry.dirty ? null : JSON.stringify(entry.payload);
        return handle;
    }

    function boot() {
        return fetch(cfg.routes.mine, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) {
            if (!res.ok) throw new Error('boot-' + res.status);
            return res.json();
        }).then(function (data) {
            var closed = {};
            (data.closed || []).forEach(function (uuid) { closed[uuid] = true; });

            var serverPending = {};
            (data.pending || []).forEach(function (draft) { serverPending[draft.uuid] = draft; });

            localEntries().forEach(function (entry) {
                if (closed[entry.uuid]) {
                    removeEntry(entry.uuid);
                    return;
                }

                var server = serverPending[entry.uuid];

                if (server) {
                    if (entry.dirty || (entry.revision || 0) > (server.revision || 0)) {
                        enqueue(handleFromEntry(entry));
                    } else {
                        entry.payload = server.payload;
                        entry.revision = server.revision;
                        entry.dirty = false;
                        entry.updatedAt = Date.parse(server.updated_at) || entry.updatedAt;
                        writeEntry(entry);
                    }
                    delete serverPending[entry.uuid];
                } else if (entry.dirty) {
                    enqueue(handleFromEntry(entry));
                }
            });

            // Server-only drafts still get a device copy for extra safety.
            Object.keys(serverPending).forEach(function (uuid) {
                var draft = serverPending[uuid];
                writeEntry({
                    uuid: draft.uuid,
                    type: draft.type,
                    payload: draft.payload,
                    revision: draft.revision || 1,
                    updatedAt: Date.parse(draft.updated_at) || Date.now(),
                    dirty: false
                });
            });

            cleanIndex();
            setState('idle');
        }).catch(function () {
            setState('offline');
        });
    }

    // ================= form binding =================

    function serialize(form) {
        var out = {};
        var data = new FormData(form);

        data.forEach(function (value, name) {
            if (!name || value instanceof File) return;
            if (name === '_token' || name === '_method' || name === 'draft_uuid') return;
            if (/password/i.test(name)) return;
            setPath(out, name, value);
        });

        return out;
    }

    function setPath(target, name, value) {
        var parts = name.replace(/\]/g, '').split('[');
        var cursor = target;

        for (var i = 0; i < parts.length; i++) {
            var part = parts[i];

            if (part === '') continue;

            var isIndex = /^\d+$/.test(part);

            if (i === parts.length - 1) {
                if (isIndex) {
                    if (!Array.isArray(cursor)) return;
                    cursor[parseInt(part, 10)] = value;
                } else {
                    cursor[part] = value;
                }
                return;
            }

            if (isIndex) {
                var idx = parseInt(part, 10);
                if (!Array.isArray(cursor)) return;
                if (cursor[idx] === undefined || cursor[idx] === null) cursor[idx] = {};
                cursor = cursor[idx];
            } else {
                if (cursor[part] === undefined || cursor[part] === null || typeof cursor[part] !== 'object') {
                    cursor[part] = /^\d+$/.test(parts[i + 1] || '') ? [] : {};
                }
                cursor = cursor[part];
            }
        }
    }

    function resolveType(base, form, payload) {
        var method = form.querySelector('input[name="_method"]');

        if (base === 'medicine') {
            if ((method && String(method.value).toUpperCase() === 'PUT') || payload.medicine_id) {
                return 'medicine_edit';
            }
        }

        if (base === 'stock' && payload.stock_id) return 'stock_edit';
        if (base === 'unit' && payload.unit_type_id) return 'unit_edit';

        return base;
    }

    function attachForm(form) {
        if (form.__draftBound) return;

        var baseType = form.getAttribute('data-draft-type');

        if (!baseType || TYPES.indexOf(baseType) === -1) return;

        form.__draftBound = true;

        var handle = make(baseType);
        form.__draftHandle = handle;

        var initialJson = JSON.stringify(serialize(form));
        var started = false;

        function capture() {
            var payload = serialize(form);

            if (!started) {
                if (JSON.stringify(payload) === initialJson) return;
                started = true;
            }

            var resolved = resolveType(baseType, form, payload);

            if (handle.type !== resolved) {
                handle.type = resolved;
                handle.lastPayloadJson = null;
            }

            save(handle, payload);
        }

        form.addEventListener('input', capture);
        form.addEventListener('change', capture);

        // Programmatic population (edit buttons) should start a draft too.
        form.addEventListener('draft:capture', function () {
            started = true;
            capture();
        });

        // Dispensed / AJAX screens tell us the server saved successfully.
        form.addEventListener('draft:saved', function () {
            close(handle);
            started = false;
            initialJson = JSON.stringify(serialize(form));
        });
    }

    function attachAll() {
        document.querySelectorAll('form[data-draft-type]').forEach(attachForm);
    }

    // Inject draft_uuid into every real submit so the server can close it.
    document.addEventListener('submit', function (event) {
        var form = event.target;
        var handle = form && form.__draftHandle;

        if (!handle || !handle.pendingPayload) return;

        var input = form.querySelector('input[name="draft_uuid"]');

        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'draft_uuid';
            form.appendChild(input);
        }

        input.value = handle.uuid;
        persist(handle);
    }, true);

    function close(handle) {
        if (!handle) return;

        clearTimeout(handle.timerSync);
        removeEntry(handle.uuid);
        handle.closed = true;
        handle.pendingPayload = null;
        handle.lastPayloadJson = null;
        handle.lastSyncedJson = null;
        setState('synced');
    }

    // ================= unload safety =================

    function flush() {
        localEntries().forEach(function (entry) {
            if (!entry.dirty) return;

            try {
                fetch(cfg.routes.sync, {
                    method: 'POST',
                    keepalive: true,
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': cfg.csrf,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        uuid: entry.uuid,
                        type: entry.type,
                        payload: entry.payload,
                        revision: entry.revision
                    })
                }).catch(function () { /* offline: local copy remains */ });
            } catch (e) { /* ignore */ }
        });
    }

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'hidden') flush();
    });

    window.addEventListener('pagehide', flush);
    window.addEventListener('online', flush);

    // ================= status pill =================

    var PILL_TEXT = {
        idle: 'المسودات جاهزة',
        local: 'تم الحفظ على الجهاز',
        syncing: 'جاري حفظ المسودة…',
        synced: 'المسودة محفوظة',
        offline: 'لا يوجد اتصال — محفوظة محلياً',
        error: 'تعذر حفظ المسودة'
    };
    var PILL_ICON = {
        idle: 'fa-cloud',
        local: 'fa-hdd',
        syncing: 'fa-sync-alt fa-spin',
        synced: 'fa-check-circle',
        offline: 'fa-plug',
        error: 'fa-exclamation-triangle'
    };
    var pillHideTimer = null;

    function setState(state) {
        var pill = document.getElementById('draftSyncPill');

        if (!pill) return;

        if (state === 'idle') {
            pill.hidden = true;
            return;
        }

        pill.hidden = false;
        pill.setAttribute('data-state', state);

        var icon = pill.querySelector('[data-draft-icon]');
        var text = pill.querySelector('[data-draft-text]');

        if (icon) icon.className = 'fas ' + (PILL_ICON[state] || 'fa-cloud');
        if (text) text.textContent = PILL_TEXT[state] || PILL_TEXT.idle;

        clearTimeout(pillHideTimer);

        if (state === 'synced') {
            pillHideTimer = setTimeout(function () {
                pill.hidden = true;
            }, 2500);
        }
    }

    function notify(message, level) {
        if (window.showToast && typeof window.showToast === 'function') {
            try { window.showToast(message, level || 'success'); return; } catch (e) { /* ignore */ }
        }

        if (level === 'error') {
            console.warn('[drafts]', message);
        }
    }

    // ================= public api =================

    window.PsychDrafts = {
        make: make,
        save: save,
        close: close,
        flush: flush,
        serialize: serialize,
        attachForm: attachForm,
        handleFor: function (form) { return form ? form.__draftHandle : null; },
        uuid: uuidv4
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            attachAll();
            boot();
        });
    } else {
        attachAll();
        boot();
    }
})();
