// HPC Assist UDISE+ Automatic Sync Content Script
(function () {
    console.log('[HPC Assist] UDISE+ Sync script loaded.');

    function getSchoolId() {
        return (location.href.match(/school\/(\d+)/)?.[1]) ||
               (location.hash.match(/school\/(\d+)/)?.[1]) ||
               (document.querySelector('[href*="school/"]')?.getAttribute('href')?.match(/school\/(\d+)/)?.[1]) ||
               (window.performance?.getEntriesByType?.('resource')?.map(r => r.name)?.find(u => u.includes('/snapshot/students/all/'))?.match(/all\/(\d+)/)?.[1]) ||
               (window.performance?.getEntriesByType?.('resource')?.map(r => r.name)?.find(u => u.includes('/cy/school/'))?.match(/school\/(\d+)/)?.[1]) ||
               (window.performance?.getEntriesByType?.('resource')?.map(r => r.name)?.find(u => u.includes('/students/all/'))?.match(/all\/(\d+)/)?.[1]) ||
               null;
    }

    function getAuthHeaders() {
        const headers = { 'Content-Type': 'application/json' };
        for (const storage of [sessionStorage, localStorage]) {
            try {
                for (let i = 0; i < storage.length; i++) {
                    const key = storage.key(i);
                    const val = storage.getItem(key);
                    if (!val) continue;

                    // Direct JWT string
                    if (typeof val === 'string' && val.startsWith('ey') && val.split('.').length === 3) {
                        headers['Authorization'] = 'Bearer ' + val;
                        return headers;
                    }

                    // JSON object containing token
                    if (typeof val === 'string' && val.startsWith('{')) {
                        try {
                            const parsed = JSON.parse(val);
                            const token = parsed.token || parsed.access_token || parsed.id_token || parsed.authToken || parsed.jwt;
                            if (token && typeof token === 'string') {
                                headers['Authorization'] = token.startsWith('Bearer ') ? token : 'Bearer ' + token;
                                return headers;
                            }
                        } catch(e) {}
                    }
                }
            } catch(e) {}
        }
        return headers;
    }

    function detectUdiseCodeFromDOM() {
        try {
            const bodyText = document.body ? document.body.innerText : '';
            // Match 11 digit numbers (standard UDISE code format)
            const matches = bodyText.match(/\b\d{11}\b/g) || [];
            // Prefer codes starting with 01 (J&K) or return first
            const jkCode = matches.find(m => m.startsWith('01'));
            if (jkCode) return jkCode;
            if (matches.length > 0) return matches[0];
        } catch(e) {}
        return '';
    }

    function detectUdiseCodeFromStorage() {
        for (const storage of [sessionStorage, localStorage]) {
            try {
                for (let i = 0; i < storage.length; i++) {
                    const key = storage.key(i);
                    const val = storage.getItem(key);
                    if (!val) continue;

                    const match = val.match(/"(?:udiseCode|schUdiseCode|userName|udise_code)"\s*:\s*"(\d{11})"/i);
                    if (match && match[1]) return match[1];

                    const codeMatch = val.match(/\b(01\d{9}|\d{11})\b/);
                    if (codeMatch && codeMatch[1]) return codeMatch[1];
                }
            } catch(e) {}
        }
        return '';
    }

    async function detectUdiseCodeFromApi(schoolId) {
        if (!schoolId) return '';
        try {
            const res = await fetch('/p0/api/cy/school/' + schoolId, {
                credentials: 'include',
                headers: getAuthHeaders()
            });
            if (res.ok) {
                const json = await res.json();
                const school = json?.data || json?.school || json?.result || json;
                const code = school?.udiseCode || school?.schUdiseCode || school?.udise_code;
                if (code) return String(code);
            }
        } catch(e) {}
        return '';
    }

    let isSyncing = false;

    async function performAutoSync(force = false) {
        const schoolId = getSchoolId();
        if (!schoolId) return;

        const syncKey = 'hpc_synced_' + schoolId;
        if (force) {
            sessionStorage.removeItem(syncKey);
        } else if (sessionStorage.getItem(syncKey)) {
            renderWidget('synced', `School ${schoolId} already synced in this session. Click below to re-fetch if needed.`, null, getActiveUdiseCode());
            return;
        }

        if (isSyncing) return;
        isSyncing = true;
        renderWidget('syncing', `Fetching students for School ${schoolId}...`, null, getActiveUdiseCode());

        try {
            const authHeaders = getAuthHeaders();
            let json = null;

            // 1. Fetch from snapshot endpoint
            try {
                let res = await fetch('/p0/api/cy/snapshot/students/all/' + schoolId, {
                    credentials: 'include',
                    headers: authHeaders
                });
                if (res.ok) {
                    json = await res.json();
                }
            } catch(e) {}

            let students = json?.data || json?.students || json?.result || (Array.isArray(json) ? json : null);

            // 2. Fallback to active students endpoint
            if (!Array.isArray(students) || !students.length) {
                try {
                    let res = await fetch('/p0/api/cy/students/all/' + schoolId, {
                        credentials: 'include',
                        headers: authHeaders
                    });
                    if (res.ok) {
                        json = await res.json();
                        students = json?.data || json?.students || json?.result || (Array.isArray(json) ? json : null);
                    }
                } catch(e) {}
            }

            // 3. Fallback to generic endpoint
            if (!Array.isArray(students) || !students.length) {
                try {
                    let res = await fetch('/p0/api/students/all/' + schoolId, {
                        credentials: 'include',
                        headers: authHeaders
                    });
                    if (res.ok) {
                        json = await res.json();
                        students = json?.data || json?.students || json?.result || (Array.isArray(json) ? json : null);
                    }
                } catch(e) {}
            }

            if (!Array.isArray(students) || !students.length) {
                renderWidget('idle', '⚠️ No students returned. Navigate to Student → List of All Students in UDISE+ and click Re-Sync.', null, getActiveUdiseCode());
                isSyncing = false;
                return;
            }

            // Detect UDISE code across all sources
            let udiseCode = getActiveUdiseCode();
            if (!udiseCode) {
                udiseCode = students.find(s => s?.schUdiseCode)?.schUdiseCode || '';
            }
            if (!udiseCode) {
                udiseCode = detectUdiseCodeFromStorage();
            }
            if (!udiseCode) {
                udiseCode = detectUdiseCodeFromDOM();
            }
            if (!udiseCode) {
                udiseCode = await detectUdiseCodeFromApi(schoolId);
            }

            const schoolName = window.__HPC_CONFIG?.school_name ||
                               students.find(s => s?.schoolName)?.schoolName ||
                               '';

            renderWidget('syncing', `Uploading ${students.length} students to HPC Assist (UDISE: ${udiseCode || 'Auto-matching'})...`, null, udiseCode);

            const serverBase = window.__HPC_CONFIG?.server_url || 'https://ctetmonktest.fun';
            const ingestUrl = window.location.hostname.includes('ctetmonktest.fun')
                ? '/udise/ingest'
                : serverBase + '/udise/ingest';

            const payload = {
                udise_code: udiseCode,
                school_id: window.__HPC_CONFIG?.school_id || schoolId,
                udise_school_id: schoolId,
                token: window.__HPC_CONFIG?.token || '',
                school_name: schoolName,
                students: students
            };

            const postRes = await fetch(ingestUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const postData = await postRes.json();

            if (postData.ok) {
                sessionStorage.setItem(syncKey, 'true');
                if (udiseCode) localStorage.setItem('hpc_assist_last_udise', udiseCode);
                renderWidget('success', `🎉 Synced ${postData.total} students (${postData.apaar_count} APAAR IDs) to ${postData.school_name || 'HPC Assist'}!`, postData.redirect, udiseCode);
            } else {
                renderWidget('error', postData.error || 'Sync rejected by server', null, udiseCode);
            }

        } catch (err) {
            console.error('[HPC Assist] Sync error:', err);
            renderWidget('error', 'Error: ' + err.message, null, getActiveUdiseCode());
        } finally {
            isSyncing = false;
        }
    }

    function getActiveUdiseCode() {
        const manual = localStorage.getItem('hpc_assist_custom_udise');
        if (manual) return manual;
        const configCode = window.__HPC_CONFIG?.udise_code;
        if (configCode) return configCode;
        const last = localStorage.getItem('hpc_assist_last_udise');
        if (last) return last;
        return '';
    }

    function renderWidget(status, message, redirectUrl = null, currentUdise = '') {
        let widget = document.getElementById('hpc-assist-widget');
        if (!widget) {
            widget = document.createElement('div');
            widget.id = 'hpc-assist-widget';
            widget.style.cssText = `
                position: fixed;
                bottom: 24px;
                right: 24px;
                z-index: 9999999;
                background: #ffffff;
                color: #0f172a;
                border: 2px solid #10b981;
                border-radius: 16px;
                box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.25), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
                font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                padding: 16px 20px;
                width: 350px;
                display: flex;
                flex-direction: column;
                gap: 10px;
                font-size: 13px;
                transition: all 0.3s ease;
            `;
            document.body.appendChild(widget);
        }

        let bgBadge = '#10b981';
        let title = 'HPC Assist Auto-Sync';
        let actionBtn = '';

        if (status === 'syncing') {
            bgBadge = '#f59e0b';
            title = 'Syncing Students...';
            widget.style.borderColor = '#f59e0b';
        } else if (status === 'success' || status === 'synced') {
            bgBadge = '#10b981';
            title = 'UDISE Synced!';
            widget.style.borderColor = '#10b981';
            const dashUrl = redirectUrl || (window.__HPC_CONFIG?.server_url || 'https://ctetmonktest.fun') + '/dashboard?step=2';
            actionBtn = `
                <div style="display:flex; flex-direction:column; gap:6px; margin-top: 4px;">
                    <a href="${dashUrl}" target="_blank" style="
                        display: block;
                        text-align: center;
                        background: #10b981;
                        color: white;
                        text-decoration: none;
                        padding: 9px 14px;
                        border-radius: 10px;
                        font-size: 12px;
                        font-weight: 700;
                    ">🚀 Open HPC Assist Dashboard →</a>
                    <button id="hpc-resync-btn" style="
                        background: #f1f5f9;
                        color: #334155;
                        border: 1px solid #cbd5e1;
                        padding: 7px 10px;
                        border-radius: 8px;
                        font-size: 11px;
                        font-weight: 700;
                        cursor: pointer;
                    ">🔄 Re-Sync Students Now</button>
                </div>
            `;
        } else if (status === 'error') {
            bgBadge = '#ef4444';
            title = 'Sync Notice';
            widget.style.borderColor = '#ef4444';
            actionBtn = `
                <div style="display:flex; flex-direction:column; gap:8px; margin-top:4px;">
                    <div style="background:#fef2f2; border:1px solid #fee2e2; border-radius:8px; padding:6px 10px;">
                        <label style="font-size:11px; font-weight:700; color:#991b1b; display:block; margin-bottom:3px;">
                            School UDISE Code (11 digits):
                        </label>
                        <div style="display:flex; gap:6px;">
                            <input id="hpc-udise-code-input" value="${currentUdise || ''}" placeholder="e.g. 01041800702" maxlength="11" style="
                                flex: 1;
                                border: 1px solid #f87171;
                                border-radius: 6px;
                                padding: 5px 8px;
                                font-size: 12px;
                                font-weight: bold;
                                outline: none;
                            " />
                            <button id="hpc-save-code-btn" style="
                                background: #ef4444;
                                color: white;
                                border: none;
                                border-radius: 6px;
                                padding: 5px 10px;
                                font-size: 11px;
                                font-weight: bold;
                                cursor: pointer;
                            ">Save</button>
                        </div>
                    </div>
                    <button id="hpc-retry-btn" style="
                        background: #6366f1;
                        color: white;
                        border: none;
                        padding: 8px 12px;
                        border-radius: 8px;
                        font-size: 12px;
                        font-weight: 700;
                        cursor: pointer;
                    ">🔄 Retry Sync Now</button>
                </div>
            `;
        } else {
            widget.style.borderColor = '#10b981';
            actionBtn = `
                <div style="display:flex; flex-direction:column; gap:6px; margin-top:4px;">
                    <button id="hpc-manual-sync-btn" style="
                        background: #10b981;
                        color: white;
                        border: none;
                        padding: 9px 12px;
                        border-radius: 8px;
                        font-size: 12px;
                        font-weight: 700;
                        cursor: pointer;
                    ">⚡ Sync Students to HPC Assist</button>
                </div>
            `;
        }

        const udiseDisplay = currentUdise ? `<span style="font-size:11px; color:#64748b; font-weight:600;">UDISE: ${currentUdise}</span>` : '';

        widget.innerHTML = `
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: ${bgBadge};"></span>
                    <strong style="font-size: 13px; color: #0f172a;">${title}</strong>
                </div>
                <div style="display:flex; align-items:center; gap:8px;">
                    ${udiseDisplay}
                    <button id="hpc-close-widget" style="background: none; border: none; font-size: 18px; cursor: pointer; color: #94a3b8; line-height: 1;">&times;</button>
                </div>
            </div>
            <div style="font-size: 12px; color: #475569; line-height: 1.45;">${message}</div>
            ${actionBtn}
        `;

        document.getElementById('hpc-close-widget')?.addEventListener('click', () => {
            widget.style.display = 'none';
        });

        document.getElementById('hpc-save-code-btn')?.addEventListener('click', () => {
            const val = document.getElementById('hpc-udise-code-input')?.value?.trim();
            if (val) {
                localStorage.setItem('hpc_assist_custom_udise', val);
                performAutoSync(true);
            }
        });

        document.getElementById('hpc-retry-btn')?.addEventListener('click', () => {
            const val = document.getElementById('hpc-udise-code-input')?.value?.trim();
            if (val) localStorage.setItem('hpc_assist_custom_udise', val);
            performAutoSync(true);
        });

        document.getElementById('hpc-manual-sync-btn')?.addEventListener('click', () => performAutoSync(true));
        document.getElementById('hpc-resync-btn')?.addEventListener('click', () => performAutoSync(true));
    }

    // Check periodically for school login and auto-trigger
    function checkAndRun() {
        if (!location.href.includes('sdms.udiseplus.gov.in')) return;
        const schoolId = getSchoolId();
        if (schoolId) {
            performAutoSync(false);
        }
    }

    checkAndRun();
    setInterval(checkAndRun, 3000);
    window.addEventListener('hashchange', checkAndRun);
})();
