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
                    progress_token: config.progress_token || config.token || '',
                    server_url: config.server_url || ''
                }
            }, () => {
                console.log('[HPC Assist Extension] Synced active school to extension storage:', config.school_name, '(ID: ' + config.school_id + ')');
            });
        }
    } catch(e) {
        console.warn('[HPC Assist Extension] Bridge error:', e);
    }
})();
