// HPC Assist Extension - Active Dashboard School Bridge
// Automatically syncs the logged-in school from ctetmonktest.fun into extension storage
(function () {
    console.log('[HPC Assist Bridge] Content script initialized on', location.href);

    function extractConfig() {
        // 1. Try reading the hidden DOM bridge element (accessible across isolated worlds)
        const el = document.getElementById('hpc-extension-bridge');
        if (el) {
            const rawId = el.getAttribute('data-school-id');
            const schoolId = rawId ? parseInt(rawId.replace(/^school_/, ''), 10) : null;
            if (schoolId) {
                return {
                    school_id: schoolId,
                    school_name: el.getAttribute('data-school-name') || '',
                    udise_code: el.getAttribute('data-udise-code') || '',
                    token: el.getAttribute('data-token') || '',
                    progress_token: el.getAttribute('data-progress-token') || '',
                    server_url: el.getAttribute('data-server-url') || location.origin,
                    teacher_id: el.getAttribute('data-teacher-id') || '',
                    teacher_name: el.getAttribute('data-teacher-name') || '',
                    role: el.getAttribute('data-role') || 'school',
                    synced_at: Date.now()
                };
            }
        }

        // 2. Fallback: window.__HPC_CONFIG if running in same execution world
        if (typeof window !== 'undefined' && window.__HPC_CONFIG && window.__HPC_CONFIG.school_id) {
            const c = window.__HPC_CONFIG;
            const schoolId = parseInt(String(c.school_id).replace(/^school_/, ''), 10);
            return {
                school_id: schoolId,
                school_name: c.school_name || '',
                udise_code: c.udise_code || '',
                token: c.token || '',
                progress_token: c.progress_token || '',
                server_url: c.server_url || location.origin,
                teacher_id: c.teacher_id || '',
                teacher_name: c.teacher_name || '',
                role: c.role || 'school',
                synced_at: Date.now()
            };
        }

        return null;
    }

    function applyConfig(cfg) {
        if (!cfg || !cfg.school_id) return;
        if (typeof chrome === 'undefined' || !chrome.storage || !chrome.storage.local) return;

        chrome.storage.local.get(['hpc_active_school'], (res) => {
            const prev = res?.hpc_active_school;
            const schoolChanged = !prev || prev.school_id !== cfg.school_id || prev.udise_code !== cfg.udise_code;

            chrome.storage.local.set({
                hpc_active_school: cfg,
                hpc_active_school_id: cfg.school_id,
                hpc_active_udise: cfg.udise_code,
                hpc_active_school_name: cfg.school_name,
                hpc_synced_at: Date.now()
            }, () => {
                console.log('[HPC Assist Bridge] Synced active school to extension storage:', cfg.school_name, `(ID: ${cfg.school_id}, UDISE: ${cfg.udise_code}) Role:`, cfg.role);
                if (schoolChanged) {
                    console.log('[HPC Assist Bridge] Active school changed! Purging old cached queues.');
                    chrome.storage.local.remove(['hpc_student_queue', 'hpc_teacher_queue']);
                }
                syncTeacherQueue(cfg.school_id);
            });
        });
    }

    async function syncTeacherQueue(schoolId) {
        if (!schoolId || typeof chrome === 'undefined' || !chrome.storage || !chrome.storage.local) return;
        try {
            const res = await fetch(`/hpc/teacher/evaluations/queue?school_id=${schoolId}&_ts=${Date.now()}`);
            if (res.ok) {
                const data = await res.json();
                if (data && data.ok && Array.isArray(data.students) && data.students.length > 0) {
                    chrome.storage.local.set({
                        hpc_teacher_queue: {
                            school_id: schoolId,
                            school_name: data.school_name || '',
                            students: data.students,
                            timestamp: Date.now()
                        }
                    }, () => {
                        console.log('[HPC Assist Bridge] Synced teacher queue (' + data.students.length + ' students) to chrome.storage.local');
                    });
                }
            }
        } catch (e) {
            console.warn('[HPC Assist Bridge] Could not preload teacher evaluations queue:', e.message);
        }
    }

    // Run extraction immediately
    const initialConfig = extractConfig();
    if (initialConfig) {
        applyConfig(initialConfig);
    }

    // Listen for custom postMessage events from dashboard scripts
    window.addEventListener('message', (event) => {
        if (event.data && (event.data.type === 'HPC_ACTIVE_SCHOOL_SYNC' || event.data.type === 'HPC_BRIDGE_SYNC')) {
            const cfg = event.data.config || extractConfig();
            if (cfg) applyConfig(cfg);
        }
    });

    document.addEventListener('HPC_ACTIVE_SCHOOL_SYNC', (event) => {
        const cfg = event.detail || extractConfig();
        if (cfg) applyConfig(cfg);
    });

    // Observe DOM for dynamic element insertions
    if (document.body) {
        const observer = new MutationObserver(() => {
            const cfg = extractConfig();
            if (cfg) {
                applyConfig(cfg);
                observer.disconnect();
            }
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }
})();
