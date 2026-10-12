// HPC Assist UDISE+ Helper Content Script
(function () {
    console.log('[HPC Assist] UDISE+ Extension active.');

    function getSchoolId() {
        return (location.href.match(/school\/(\d+)/)?.[1]) ||
               (location.hash.match(/school\/(\d+)/)?.[1]) ||
               (window.performance?.getEntriesByType?.('resource')?.map(r => r.name)?.find(u => u.includes('/snapshot/students/all/'))?.match(/all\/(\d+)/)?.[1]) ||
               (window.performance?.getEntriesByType?.('resource')?.map(r => r.name)?.find(u => u.includes('/cy/school/'))?.match(/school\/(\d+)/)?.[1]) ||
               null;
    }

    function createFloatingWidget() {
        if (document.getElementById('hpc-assist-widget')) return;

        const widget = document.createElement('div');
        widget.id = 'hpc-assist-widget';
        widget.style.cssText = `
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 999999;
            background: #ffffff;
            color: #0f172a;
            border: 2px solid #10b981;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            padding: 14px 18px;
            width: 320px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            font-size: 13px;
        `;

        widget.innerHTML = `
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: #10b981;"></span>
                    <strong style="font-size: 13px; color: #065f46;">HPC Assist Sync</strong>
                </div>
                <button id="hpc-close-btn" style="background: none; border: none; font-size: 16px; cursor: pointer; color: #94a3b8;">&times;</button>
            </div>
            <div id="hpc-status-text" style="font-size: 12px; color: #475569; line-height: 1.4;">
                Ready to extract students with unmasked APAAR IDs.
            </div>
            <div style="display: flex; gap: 8px;">
                <button id="hpc-download-btn" style="
                    flex: 1;
                    background: #10b981;
                    color: white;
                    border: none;
                    padding: 8px 12px;
                    border-radius: 10px;
                    font-size: 12px;
                    font-weight: 700;
                    cursor: pointer;
                    transition: background 0.2s;
                ">📥 Download JSON</button>
                <button id="hpc-sync-btn" style="
                    flex: 1;
                    background: #6366f1;
                    color: white;
                    border: none;
                    padding: 8px 12px;
                    border-radius: 10px;
                    font-size: 12px;
                    font-weight: 700;
                    cursor: pointer;
                    transition: background 0.2s;
                ">⚡ Sync to HPC</button>
            </div>
        `;

        document.body.appendChild(widget);

        document.getElementById('hpc-close-btn').addEventListener('click', () => {
            widget.style.display = 'none';
        });

        document.getElementById('hpc-download-btn').addEventListener('click', () => fetchAndHandle(false));
        document.getElementById('hpc-sync-btn').addEventListener('click', () => fetchAndHandle(true));
    }

    async function fetchAndHandle(syncToLive = false) {
        const statusEl = document.getElementById('hpc-status-text');
        const dlBtn = document.getElementById('hpc-download-btn');
        const syncBtn = document.getElementById('hpc-sync-btn');

        let schoolId = getSchoolId();
        if (!schoolId) {
            schoolId = prompt('Enter UDISE internal School ID (digits from URL):');
        }

        if (!schoolId) {
            if (statusEl) statusEl.textContent = '❌ School ID required. Please navigate to Student module.';
            return;
        }

        if (statusEl) statusEl.textContent = `⏳ Fetching students for School ${schoolId}...`;
        if (dlBtn) dlBtn.disabled = true;
        if (syncBtn) syncBtn.disabled = true;

        try {
            let res = await fetch('/p0/api/cy/snapshot/students/all/' + schoolId, { credentials: 'include' });
            let json = await res.json();
            let students = json.data || json.students || json.result || json;

            if (!Array.isArray(students) || !students.length) {
                res = await fetch('/p0/api/cy/students/all/' + schoolId, { credentials: 'include' });
                json = await res.json();
                students = json.data || json.students || json.result || json;
            }

            if (!Array.isArray(students) || !students.length) {
                if (statusEl) statusEl.textContent = '⚠️ 0 students found. Navigate to "Student → List of All Students" and retry.';
                if (dlBtn) dlBtn.disabled = false;
                if (syncBtn) syncBtn.disabled = false;
                return;
            }

            // Always download the JSON file
            const blob = new Blob([JSON.stringify(students, null, 2)], { type: 'application/json' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = `udise_students_${schoolId}.json`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);

            if (syncToLive) {
                if (statusEl) statusEl.textContent = `📡 Uploading ${students.length} students to HPC Assist...`;
                try {
                    const postRes = await fetch('https://ctetmonktest.fun/udise/ingest', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            school_id: schoolId,
                            students: students
                        })
                    });
                    const postData = await postRes.json();
                    if (postData.ok) {
                        if (statusEl) statusEl.innerHTML = `<span style="color:#059669;font-weight:bold;">✅ Synced ${students.length} students to HPC Assist!</span>`;
                    } else {
                        if (statusEl) statusEl.innerHTML = `<span style="color:#b45309;">⚠️ Downloaded JSON. (Upload on dashboard: ${postData.error || 'Server error'})</span>`;
                    }
                } catch (postErr) {
                    if (statusEl) statusEl.innerHTML = `<span style="color:#059669;">✅ Downloaded ${students.length} students to Downloads folder!</span>`;
                }
            } else {
                if (statusEl) statusEl.innerHTML = `<span style="color:#059669;font-weight:bold;">✅ Downloaded ${students.length} students to udise_students_${schoolId}.json!</span>`;
            }

        } catch (err) {
            if (statusEl) statusEl.textContent = '❌ Error: ' + err.message;
        } finally {
            if (dlBtn) dlBtn.disabled = false;
            if (syncBtn) syncBtn.disabled = false;
        }
    }

    // Initialize widget when page loads or URL changes
    function init() {
        if (location.href.includes('sdms.udiseplus.gov.in')) {
            createFloatingWidget();
        }
    }

    init();
    setInterval(init, 3000);
})();
