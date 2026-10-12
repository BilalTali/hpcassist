// HPC Assist UDISE+ Automatic Sync Content Script
(function () {
    console.log('[HPC Assist] UDISE+ Auto-Sync initialized.');

    function getSchoolId() {
        return (location.href.match(/school\/(\d+)/)?.[1]) ||
               (location.hash.match(/school\/(\d+)/)?.[1]) ||
               (window.performance?.getEntriesByType?.('resource')?.map(r => r.name)?.find(u => u.includes('/snapshot/students/all/'))?.match(/all\/(\d+)/)?.[1]) ||
               (window.performance?.getEntriesByType?.('resource')?.map(r => r.name)?.find(u => u.includes('/cy/school/'))?.match(/school\/(\d+)/)?.[1]) ||
               null;
    }

    let isSyncing = false;

    async function performAutoSync(force = false) {
        const schoolId = getSchoolId();
        if (!schoolId) return;

        // Prevent repeated sync in the same browser session unless forced
        const syncKey = 'hpc_synced_' + schoolId;
        if (!force && sessionStorage.getItem(syncKey)) {
            renderWidget('synced', `School ${schoolId} already synced!`);
            return;
        }

        if (isSyncing) return;
        isSyncing = true;
        renderWidget('syncing', `Auto-syncing students for School ${schoolId}...`);

        try {
            // 1. Fetch from UDISE session
            let res = await fetch('/p0/api/cy/snapshot/students/all/' + schoolId, { credentials: 'include' });
            let json = await res.json();
            let students = json.data || json.students || json.result || json;

            if (!Array.isArray(students) || !students.length) {
                res = await fetch('/p0/api/cy/students/all/' + schoolId, { credentials: 'include' });
                json = await res.json();
                students = json.data || json.students || json.result || json;
            }

            if (!Array.isArray(students) || !students.length) {
                renderWidget('idle', 'No students found on this page yet. Go to Student → All Students.');
                isSyncing = false;
                return;
            }

            const udiseCode = students[0]?.schUdiseCode || '';

            // 2. Automatically POST to HPC Assist server
            renderWidget('syncing', `Uploading ${students.length} students to HPC Assist...`);

            const ingestUrl = window.location.hostname.includes('ctetmonktest.fun') 
                ? '/udise/ingest' 
                : 'https://ctetmonktest.fun/udise/ingest';

            const postRes = await fetch(ingestUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    udise_code: udiseCode,
                    school_id: schoolId,
                    students: students
                })
            });

            const postData = await postRes.json();

            if (postData.ok) {
                sessionStorage.setItem(syncKey, 'true');
                renderWidget('success', `🎉 Synced ${postData.total} students (${postData.apaar_count} APAAR IDs) to HPC Assist!`, postData.redirect);
            } else {
                renderWidget('error', `Sync failed: ${postData.error || 'Unknown error'}`);
            }

        } catch (err) {
            console.error('[HPC Assist] Sync error:', err);
            renderWidget('error', 'Error: ' + err.message);
        } finally {
            isSyncing = false;
        }
    }

    function renderWidget(status, message, redirectUrl = null) {
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
                box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.2), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
                font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                padding: 14px 18px;
                width: 340px;
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
            title = 'Syncing In Progress...';
        } else if (status === 'success' || status === 'synced') {
            bgBadge = '#10b981';
            title = 'UDISE Synced!';
            const dashUrl = redirectUrl || 'https://ctetmonktest.fun/dashboard?step=2';
            actionBtn = `
                <a href="${dashUrl}" target="_blank" style="
                    display: block;
                    text-align: center;
                    background: #10b981;
                    color: white;
                    text-decoration: none;
                    padding: 8px 14px;
                    border-radius: 10px;
                    font-size: 12px;
                    font-weight: 700;
                    margin-top: 4px;
                ">🚀 Open HPC Assist Dashboard →</a>
            `;
        } else if (status === 'error') {
            bgBadge = '#ef4444';
            title = 'Sync Notice';
            actionBtn = `
                <button id="hpc-retry-btn" style="
                    background: #6366f1;
                    color: white;
                    border: none;
                    padding: 7px 12px;
                    border-radius: 8px;
                    font-size: 11px;
                    font-weight: 700;
                    cursor: pointer;
                ">🔄 Retry Sync</button>
            `;
        } else {
            actionBtn = `
                <button id="hpc-manual-sync-btn" style="
                    background: #10b981;
                    color: white;
                    border: none;
                    padding: 8px 12px;
                    border-radius: 8px;
                    font-size: 12px;
                    font-weight: 700;
                    cursor: pointer;
                ">⚡ Sync Students Now</button>
            `;
        }

        widget.innerHTML = `
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: ${bgBadge};"></span>
                    <strong style="font-size: 13px; color: #0f172a;">${title}</strong>
                </div>
                <button id="hpc-close-widget" style="background: none; border: none; font-size: 18px; cursor: pointer; color: #94a3b8;">&times;</button>
            </div>
            <div style="font-size: 12px; color: #475569; line-height: 1.4;">${message}</div>
            ${actionBtn}
        `;

        document.getElementById('hpc-close-widget')?.addEventListener('click', () => {
            widget.style.display = 'none';
        });

        document.getElementById('hpc-retry-btn')?.addEventListener('click', () => performAutoSync(true));
        document.getElementById('hpc-manual-sync-btn')?.addEventListener('click', () => performAutoSync(true));
    }

    // Check periodically for school login and auto-trigger
    function checkAndRun() {
        if (!location.href.includes('sdms.udiseplus.gov.in')) return;
        const schoolId = getSchoolId();
        if (schoolId) {
            performAutoSync(false);
        }
    }

    // Run on load and URL hash changes
    checkAndRun();
    setInterval(checkAndRun, 2500);
    window.addEventListener('hashchange', checkAndRun);
})();
