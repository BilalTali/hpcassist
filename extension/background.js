// HPC Assist - Background Service Worker (Manifest V3)
// Provides secure, CSP-immune cross-origin network fetching and storage relay for content scripts

chrome.runtime.onInstalled.addListener(() => {
    console.log('[HPC Assist Background] Service worker installed.');
});

// Relay fetch requests from content scripts (bypasses webpage CSP on parakh.ncert.gov.in)
chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
    if (!request || !request.action) return false;

    if (request.action === 'HPC_FETCH') {
        const { url, options = {} } = request;
        
        fetch(url, options)
            .then(async (res) => {
                const contentType = res.headers.get('content-type') || '';
                let data = null;
                if (contentType.includes('application/json')) {
                    try {
                        data = await res.json();
                    } catch (e) {
                        data = null;
                    }
                } else {
                    try {
                        data = await res.text();
                    } catch (e) {
                        data = null;
                    }
                }

                sendResponse({
                    __hpc_fetch_ok: res.ok,
                    status: res.status,
                    statusText: res.statusText,
                    data: data
                });
            })
            .catch((err) => {
                sendResponse({
                    __hpc_fetch_ok: false,
                    status: 0,
                    statusText: err.message || 'NetworkError',
                    error: err.message || String(err)
                });
            });

        return true; // Keep message channel open for async response
    }

    if (request.action === 'HPC_PING') {
        sendResponse({ ok: true, version: chrome.runtime.getManifest().version });
        return false;
    }

    return false;
});
