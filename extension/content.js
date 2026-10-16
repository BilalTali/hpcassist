// HPC Assist UDISE+ Automatic Sync Content Script
(function () {
    console.log('[HPC Assist] UDISE+ Sync script loaded.');

    let activeHpcConfig = {
        school_id: null,
        school_name: '',
        udise_code: '',
        token: '',
        progress_token: '',
        server_url: 'https://ctetmonktest.fun'
    };

    function syncConfigWithExtensionStorage(callback) {
        if (typeof chrome !== 'undefined' && chrome.storage && chrome.storage.local) {
            chrome.storage.local.get(['hpc_active_school'], (result) => {
                if (result?.hpc_active_school) {
                    const stored = result.hpc_active_school;
                    activeHpcConfig = Object.assign({}, activeHpcConfig, stored);
                    window.__HPC_CONFIG = Object.assign({}, window.__HPC_CONFIG || {}, activeHpcConfig);
                    console.log('[HPC Assist] Synced active school context:', activeHpcConfig.school_name, '(UDISE:', activeHpcConfig.udise_code, 'ID:', activeHpcConfig.school_id, ')');

                    // Clear stale custom manual UDISE in localStorage if it doesn't match active school
                    const staleManual = localStorage.getItem('hpc_assist_custom_udise');
                    if (staleManual && activeHpcConfig.udise_code && staleManual !== activeHpcConfig.udise_code) {
                        console.log('[HPC Assist] Cleared stale manual UDISE override:', staleManual);
                        localStorage.removeItem('hpc_assist_custom_udise');
                    }
                }
                if (typeof callback === 'function') callback();
            });
        } else {
            if (window.__HPC_CONFIG) {
                activeHpcConfig = Object.assign({}, activeHpcConfig, window.__HPC_CONFIG);
            }
            if (typeof callback === 'function') callback();
        }
    }

    if (typeof chrome !== 'undefined' && chrome.storage && chrome.storage.onChanged) {
        chrome.storage.onChanged.addListener((changes, areaName) => {
            if (areaName === 'local' && changes.hpc_active_school) {
                const newVal = changes.hpc_active_school.newValue;
                if (newVal) {
                    activeHpcConfig = Object.assign({}, activeHpcConfig, newVal);
                    window.__HPC_CONFIG = Object.assign({}, window.__HPC_CONFIG || {}, activeHpcConfig);
                    console.log('[HPC Assist] Updated active school via storage change:', activeHpcConfig);
                    checkAndRun();
                }
            }
        });
    }

    function parseJwtPayload(tokenStr) {
        try {
            if (typeof tokenStr !== 'string') return null;
            const parts = tokenStr.split('.');
            if (parts.length !== 3) return null;
            const payload = JSON.parse(atob(parts[1].replace(/-/g, '+').replace(/_/g, '/')));
            return payload;
        } catch(e) {
            return null;
        }
    }

    function isJwtValid(payload) {
        if (!payload) return false;
        if (payload.exp && (payload.exp * 1000) < (Date.now() - 60000)) {
            return false; // Token has expired
        }
        return true;
    }

    function extractUdiseFromJwtPayload(payload) {
        if (!payload) return '';
        const fields = [
            payload.udiseCode,
            payload.schUdiseCode,
            payload.udise_code,
            payload.sub,
            payload.userName,
            payload.username,
            payload.preferred_username
        ];
        for (const f of fields) {
            if (f && typeof f === 'string') {
                const clean = f.trim().replace(/[^0-9]/g, '');
                if (clean.length === 11) return clean;
            } else if (typeof f === 'number' && String(f).length === 11) {
                return String(f);
            }
        }
        return '';
    }

    function getAllSchoolCandidates() {
        const candidates = [];
        const domUdise = detectUdiseCodeFromDOM();
        const targetUdise = (activeHpcConfig.udise_code || domUdise || '').trim();

        // Inspect sessionStorage first (active tab session), then localStorage
        for (const storage of [sessionStorage, localStorage]) {
            try {
                for (let i = 0; i < storage.length; i++) {
                    const key = storage.key(i);
                    if (!key || key.startsWith('hpc_')) continue;
                    const val = storage.getItem(key);
                    if (!val) continue;

                    let jwtStr = null;
                    let payload = null;
                    let candidateSid = null;
                    let candidateUdise = null;

                    let rawVal = typeof val === 'string' ? val.trim() : '';
                    if (rawVal.startsWith('Bearer ')) rawVal = rawVal.substring(7).trim();

                    if (rawVal.startsWith('ey') && rawVal.split('.').length === 3) {
                        jwtStr = rawVal;
                        payload = parseJwtPayload(jwtStr);
                    } else if (typeof val === 'string' && val.startsWith('{')) {
                        try {
                            const parsed = JSON.parse(val);
                            if (parsed.schoolId || parsed.schId || parsed.school_id) {
                                candidateSid = String(parsed.schoolId || parsed.schId || parsed.school_id);
                                candidateUdise = String(parsed.udiseCode || parsed.schUdiseCode || parsed.udise_code || '');
                            }
                            let tok = parsed.token || parsed.access_token || parsed.id_token || parsed.authToken || parsed.jwt;
                            if (typeof tok === 'string') {
                                tok = tok.trim();
                                if (tok.startsWith('Bearer ')) tok = tok.substring(7).trim();
                                if (tok.startsWith('ey') && tok.split('.').length === 3) {
                                    jwtStr = tok;
                                    payload = parseJwtPayload(jwtStr);
                                }
                            }
                        } catch(e) {}
                    }

                    if (payload) {
                        if (!isJwtValid(payload)) continue; // Skip expired tokens
                        const sid = payload.schoolId || payload.schId || payload.school_id || payload.sch_id || candidateSid;
                        const ucode = extractUdiseFromJwtPayload(payload) || candidateUdise;
                        if (sid) {
                            const isConflict = Boolean(targetUdise && ucode && ucode !== targetUdise);
                            candidates.push({
                                schoolId: String(sid),
                                udiseCode: ucode ? String(ucode) : '',
                                jwt: jwtStr,
                                isSession: storage === sessionStorage,
                                matchesTarget: Boolean(targetUdise && ucode && String(ucode) === targetUdise),
                                isConflict: isConflict
                            });
                        }
                    } else if (candidateSid) {
                        const isConflict = Boolean(targetUdise && candidateUdise && candidateUdise !== targetUdise);
                        candidates.push({
                            schoolId: candidateSid,
                            udiseCode: candidateUdise || '',
                            jwt: null,
                            isSession: storage === sessionStorage,
                            matchesTarget: Boolean(targetUdise && candidateUdise && String(candidateUdise) === targetUdise),
                            isConflict: isConflict
                        });
                    }
                }
            } catch(e) {}
        }

        return candidates;
    }

    function formatAuthHeader(jwt) {
        if (!jwt) return null;
        const clean = jwt.replace(/^Bearer\s+/i, '').trim();
        return clean ? 'Bearer ' + clean : null;
    }

    function getAuthHeaders(targetUdise = '') {
        const headers = { 'Content-Type': 'application/json' };
        const candidates = getAllSchoolCandidates();

        // 1. Prefer token matching target UDISE
        if (targetUdise) {
            const match = candidates.find(c => c.jwt && c.udiseCode === targetUdise);
            if (match && match.jwt) {
                const formatted = formatAuthHeader(match.jwt);
                if (formatted) {
                    headers['Authorization'] = formatted;
                    return headers;
                }
            }
        }

        // 2. Prefer token from active tab's sessionStorage (not in conflict with target)
        const sessionMatch = candidates.find(c => c.jwt && c.isSession && !c.isConflict);
        if (sessionMatch && sessionMatch.jwt) {
            const formatted = formatAuthHeader(sessionMatch.jwt);
            if (formatted) {
                headers['Authorization'] = formatted;
                return headers;
            }
        }

        // 3. Any non-conflicting valid token
        const nonConflicting = candidates.find(c => c.jwt && !c.isConflict);
        if (nonConflicting && nonConflicting.jwt) {
            const formatted = formatAuthHeader(nonConflicting.jwt);
            if (formatted) {
                headers['Authorization'] = formatted;
                return headers;
            }
        }

        return headers;
    }

    function detectUdiseCodeFromDOM() {
        try {
            // Check headers, navbars, and school banner elements first
            const headerEl = document.querySelector('header, nav, .school-name, .udise-code, .user-profile, .card-header');
            if (headerEl) {
                const headerText = headerEl.innerText || '';
                const match = headerText.match(/\b(01\d{9}|\d{11})\b/);
                if (match && match[1]) return match[1];
            }

            const bodyText = document.body ? document.body.innerText : '';
            // Match 11-digit numbers (UDISE code standard)
            const matches = bodyText.match(/\b\d{11}\b/g) || [];
            // Prefer codes starting with 01 (J&K) or return first
            const jkCode = matches.find(m => m.startsWith('01'));
            if (jkCode) return jkCode;
            if (matches.length > 0) return matches[0];
        } catch(e) {}
        return '';
    }

    function getSchoolId() {
        // 1. Current active URL or hash
        const urlMatch = (location.href.match(/\/school\/(\d+)/)?.[1]) ||
                         (location.hash.match(/\/school\/(\d+)/)?.[1]);
        if (urlMatch) return urlMatch;

        // 2. Active DOM anchor on the visible page
        const domLink = document.querySelector('a[href*="/school/"]')?.getAttribute('href')?.match(/\/school\/(\d+)/)?.[1];
        if (domLink) return domLink;

        // 3. Performance network entries - within last 45s, search newest first
        try {
            const freshEntries = (window.performance?.getEntriesByType?.('resource') || [])
                .filter(r => r.startTime > (performance.now() - 45000))
                .map(r => r.name);

            const recentMatch = freshEntries.slice().reverse().find(u =>
                u.includes('/snapshot/students/all/') ||
                u.includes('/cy/school/') ||
                u.includes('/students/all/')
            );
            if (recentMatch) {
                const match = recentMatch.match(/(?:all|school)\/(\d+)/)?.[1];
                if (match) return match;
            }
        } catch(e) {}

        const targetUdise = (activeHpcConfig.udise_code || detectUdiseCodeFromDOM() || '').trim();
        const candidates = getAllSchoolCandidates();

        // 4. Candidate matching target UDISE from active HPC Assist config
        const targetMatch = candidates.find(c => c.matchesTarget);
        if (targetMatch) return targetMatch.schoolId;

        // 5. Candidate from current active sessionStorage (not conflicting)
        const sessionCandidate = candidates.find(c => c.isSession && !c.isConflict);
        if (sessionCandidate) return sessionCandidate.schoolId;

        // 6. Safe non-conflicting candidate
        const safeCandidate = candidates.find(c => !c.isConflict);
        if (safeCandidate) return safeCandidate.schoolId;

        // 7. Only return first candidate if targetUdise is empty
        if (!targetUdise && candidates.length > 0) return candidates[0].schoolId;

        return null;
    }

    function getActiveUdiseCode() {
        // 1. Authoritative config from active HPC Assist session
        if (activeHpcConfig.udise_code && /^\d{11}$/.test(activeHpcConfig.udise_code)) {
            return activeHpcConfig.udise_code;
        }

        // 2. Candidates matching target or from active session
        const candidates = getAllSchoolCandidates();
        const match = candidates.find(c => c.matchesTarget) || candidates.find(c => c.isSession && c.udiseCode);
        if (match && match.udiseCode && /^\d{11}$/.test(match.udiseCode)) {
            return match.udiseCode;
        }

        // 3. DOM detection
        const domCode = detectUdiseCodeFromDOM();
        if (domCode && /^\d{11}$/.test(domCode)) {
            return domCode;
        }

        // 4. Any candidate with valid UDISE code
        const anyCandidate = candidates.find(c => c.udiseCode && /^\d{11}$/.test(c.udiseCode) && !c.isConflict);
        if (anyCandidate) return anyCandidate.udiseCode;

        // 5. Stored custom UDISE code in localStorage (only if non-conflicting)
        const manual = localStorage.getItem('hpc_assist_custom_udise');
        if (manual && /^\d{11}$/.test(manual)) {
            if ((!activeHpcConfig.udise_code || manual === activeHpcConfig.udise_code) &&
                (!domCode || manual === domCode)) {
                return manual;
            }
        }

        return '';
    }

    let isSyncing = false;

    async function performAutoSync(force = false) {
        // Refresh active school config from extension storage first
        await new Promise(r => syncConfigWithExtensionStorage(r));

        const schoolId = getSchoolId();
        if (!schoolId) {
            console.log('[HPC Assist] Waiting for active school login in UDISE+...');
            return;
        }

        const activeUdise = getActiveUdiseCode();
        const activeSchoolId = activeHpcConfig.school_id || 'sid';
        const syncKey = 'hpc_synced_' + activeSchoolId + '_' + (activeUdise || schoolId);

        if (force) {
            sessionStorage.removeItem(syncKey);
        } else if (sessionStorage.getItem(syncKey)) {
            renderWidget('synced', `School <strong>${activeHpcConfig.school_name || activeUdise}</strong> already synced in this session. Click below to re-fetch if needed.`, null, activeUdise);
            return;
        }

        if (isSyncing) return;
        isSyncing = true;
        renderWidget('syncing', `Fetching students for School ${activeHpcConfig.school_name || schoolId}...`, null, activeUdise);

        try {
            const authHeaders = getAuthHeaders(activeUdise);
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
                renderWidget('idle', '⚠️ No students returned. Navigate to Student → List of All Students in UDISE+ and click Re-Sync.', null, activeUdise);
                isSyncing = false;
                return;
            }

            // Ground-truth UDISE code from multiple sources
            const rosterUdiseCode = students.find(s => s?.schUdiseCode)?.schUdiseCode || '';
            const domUdiseCode = detectUdiseCodeFromDOM();
            const candidates = getAllSchoolCandidates();
            const candidateForSchool = candidates.find(c => c.schoolId === String(schoolId));
            const candidateUdise = candidateForSchool?.udiseCode || '';
            const targetUdiseCode = (activeHpcConfig.udise_code || '').trim();

            const detectedSchoolUdise = rosterUdiseCode || candidateUdise || domUdiseCode;

            // Guard against uploading wrong school's data if extension is active for a different school
            if (targetUdiseCode && detectedSchoolUdise && detectedSchoolUdise !== targetUdiseCode) {
                renderWidget(
                    'error',
                    `⚠️ <strong>School Mismatch Detected:</strong><br><br>` +
                    `Your current UDISE+ portal session belongs to school <code>${detectedSchoolUdise}</code>.<br><br>` +
                    `However, your active HPC Assist school is <strong>${activeHpcConfig.school_name || 'HPC School'}</strong> (UDISE: <code>${targetUdiseCode}</code>).<br><br>` +
                    `Please log out of UDISE+ and log in with your school credentials (UDISE: <code>${targetUdiseCode}</code>) to sync student data for this school.`,
                    null,
                    detectedSchoolUdise
                );
                isSyncing = false;
                return;
            }

            // Authoritative UDISE code for ingest
            let finalUdiseCode = targetUdiseCode || rosterUdiseCode || candidateUdise || domUdiseCode || activeUdise;
            const schoolName = activeHpcConfig.school_name ||
                               students.find(s => s?.schoolName)?.schoolName ||
                               '';

            // Filter strictly to ensure only students of this unique school UDISE are ingested
            if (finalUdiseCode) {
                const cleanUdise = String(finalUdiseCode).trim().replace(/[^0-9]/g, '');
                students = students.filter(s => {
                    const sCode = s?.schUdiseCode || s?.udiseCode || s?.udise_code;
                    if (!sCode) return true; // UDISE+ snapshot API sets schUdiseCode to null on individual rows
                    const cleanS = String(sCode).trim().replace(/[^0-9]/g, '');
                    return cleanS === cleanUdise;
                });
            }

            renderWidget('syncing', `Uploading ${students.length} students to HPC Assist (${schoolName || finalUdiseCode})...`, null, finalUdiseCode);

            const serverBase = (activeHpcConfig.server_url || 'https://ctetmonktest.fun').replace(/\/+$/, '');
            const ingestUrl = window.location.hostname.includes('ctetmonktest.fun')
                ? '/udise/ingest'
                : serverBase + '/udise/ingest';

            const payload = {
                udise_code: finalUdiseCode,
                school_id: activeHpcConfig.school_id || null, // Never pass internal UDISE ID as school_id
                udise_school_id: schoolId,
                token: activeHpcConfig.token || '',
                school_name: schoolName,
                students: students
            };

            const postRes = await fetch(ingestUrl, {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            if (!postRes.ok) {
                let errorMsg = `Server error (${postRes.status})`;
                try {
                    const errData = await postRes.json();
                    if (errData?.error) errorMsg = errData.error;
                } catch(e) {}
                renderWidget('error', errorMsg, null, finalUdiseCode);
                return;
            }

            const postData = await postRes.json();

            if (postData.ok) {
                sessionStorage.setItem(syncKey, 'true');
                renderWidget('success', `🎉 Synced ${postData.total} students (${postData.apaar_count} APAAR IDs) to ${postData.school_name || 'HPC Assist'}!`, postData.redirect, finalUdiseCode);
            } else {
                renderWidget('error', postData.error || 'Sync rejected by server', null, finalUdiseCode);
            }
        } catch (err) {
            console.error('[HPC Assist] Sync error:', err);
            renderWidget('error', 'Sync failed: ' + (err.message || 'Network error'), null, activeUdise);
        } finally {
            isSyncing = false;
        }
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
                width: 360px;
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
            const dashUrl = redirectUrl || (activeHpcConfig.server_url || 'https://ctetmonktest.fun') + '/dashboard?step=2';
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
                            <input id="hpc-udise-code-input" value="${currentUdise || ''}" placeholder="e.g. 01041801101" maxlength="11" style="
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

        const schoolLabel = activeHpcConfig.school_name ? `<div style="font-size:11px; color:#475569; font-weight:600; margin-top:2px;">🏫 Target School: <strong>${activeHpcConfig.school_name}</strong></div>` : '';
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
            ${schoolLabel}
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
        syncConfigWithExtensionStorage(() => {
            const schoolId = getSchoolId();
            if (schoolId) {
                performAutoSync(false);
            }
        });
    }

    checkAndRun();
    setInterval(checkAndRun, 3000);
    window.addEventListener('hashchange', checkAndRun);
})();
