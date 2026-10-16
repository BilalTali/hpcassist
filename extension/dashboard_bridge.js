// HPC Assist Extension - Active Dashboard School Bridge
// Automatically syncs the logged-in school from ctetmonktest.fun into extension storage
(function () {
    try {
        const config = window.__HPC_CONFIG;
        if (config && config.school_id && typeof chrome !== 'undefined' && chrome.storage && chrome.storage.local) {
            chrome.storage.local.set({
                hpc_active_school: {
                    school_id: config.school_id,
                    school_name: config.school_name || '',
                    udise_code: config.udise_code || '',
                    teacher_id: config.teacher_id || '',
                    teacher_name: config.teacher_name || '',
                    role: config.role || (config.teacher_id ? 'teacher' : 'school'),
                    progress_token: config.progress_token || config.token || '',
                    token: config.token || '',
                    server_url: config.server_url || '',
                    synced_at: Date.now()
                }
            }, () => {
                console.log('[HPC Assist Extension] Synced active context to extension storage:', config.school_name, '(ID: ' + config.school_id + ', UDISE: ' + config.udise_code + ') Role:', config.role || 'school');
            });
        }
    } catch(e) {
        console.warn('[HPC Assist Extension] Bridge error:', e);
    }
})();
