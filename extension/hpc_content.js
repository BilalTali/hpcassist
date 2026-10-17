// HPC Assist PARAKH Portal Student Auto-Submission Script
(function () {
    console.log('[HPC Assist] PARAKH HPC Automation script loaded on', location.href);

    // SERVER_URL is injected by the dashboard via window.__HPC_CONFIG.
    // Never falls back to a hardcoded domain — an empty SERVER_URL will fail
    // loudly so misconfiguration is obvious rather than silently misdirected.
    let SERVER_URL = (window.__HPC_CONFIG && window.__HPC_CONFIG.server_url)
        ? window.__HPC_CONFIG.server_url.replace(/\/$/, '')
        : 'https://ctetmonktest.fun';
    let SCHOOL_ID  = (window.__HPC_CONFIG && window.__HPC_CONFIG.school_id) ? window.__HPC_CONFIG.school_id : null;
    let HPC_TOKEN  = (window.__HPC_CONFIG && (window.__HPC_CONFIG.progress_token || window.__HPC_CONFIG.token))
        ? (window.__HPC_CONFIG.progress_token || window.__HPC_CONFIG.token)
        : '';
    let currentSchoolName  = (window.__HPC_CONFIG && window.__HPC_CONFIG.school_name) || '';
    let currentTeacherName = (window.__HPC_CONFIG && window.__HPC_CONFIG.teacher_name) || '';
    let currentTeacherId   = (window.__HPC_CONFIG && window.__HPC_CONFIG.teacher_id) || '';
    let availableSchools   = [];

    let CURRENT_MODE = 'school'; // 'school' | 'teacher'

    // School Student Submitter state
    let studentsQueue = [];
    let isRunning = false;
    let currentIndex = 0;

    // Teacher Assessment Evaluation state
    let teacherQueue = [];
    let isTeacherRunning = false;
    let teacherCurrentIndex = 0;

    let lastLogMessage = '';
    let isWidgetMinimized = localStorage.getItem('hpc_widget_minimized') === 'true';
    let showSchoolSelector = false;

    function renderActiveSchoolCard(totalQueueCount) {
        if (!SCHOOL_ID) {
            return `
                <div style="background: #fff7ed; border: 1px solid #fed7aa; border-left: 3.5px solid #ea580c; border-radius: 8px; padding: 9px 12px; margin-bottom: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                    <div style="font-size: 11px; font-weight: 800; color: #9a3412; margin-bottom: 3px;">
                        ⚠️ No School Selected
                    </div>
                    <div style="font-size: 10px; color: #7c2d12; margin-bottom: 6px; line-height: 1.3;">
                        Please select your school to load its student roster safely:
                    </div>
                    <select id="hpc-school-switcher" style="width: 100%; background: #ffffff; color: #0f172a; border: 1.5px solid #ea580c; border-radius: 6px; padding: 5px 8px; font-size: 11px; font-weight: 700; cursor: pointer; outline: none;">
                        <option value="">-- Choose School --</option>
                        ${availableSchools.map(s => `
                            <option value="${s.id}">
                                ${s.name} ${s.udise_code ? `(${s.udise_code})` : ''} - ${s.pending ?? 0} queued
                            </option>
                        `).join('')}
                    </select>
                </div>
            `;
        }

        const activeSchool = availableSchools.find(s => s.id === SCHOOL_ID) || { id: SCHOOL_ID, name: currentSchoolName || 'Active School' };
        const schoolDisplayName = activeSchool.name || currentSchoolName || 'School Under Process';

        return `
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-left: 3.5px solid #FF671F; border-radius: 8px; padding: 9px 12px; margin-bottom: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 4px;">
                    <div style="flex: 1; min-width: 0; padding-right: 6px;">
                        <div style="font-size: 9px; font-weight: 800; color: #ea580c; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px;">
                            🏫 Active School
                        </div>
                        <div style="font-size: 13px; font-weight: 700; color: #1e3a8a; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${schoolDisplayName}">
                            ${schoolDisplayName}
                        </div>
                    </div>
                    ${availableSchools.length > 1 ? `
                        <button id="hpc-toggle-school-select" style="background: #eef2ff; border: 1px solid #c7d2fe; color: #3730a3; font-size: 10px; font-weight: 700; cursor: pointer; padding: 2px 8px; border-radius: 6px; white-space: nowrap;">
                            ${showSchoolSelector ? 'Hide' : 'Switch'}
                        </button>
                    ` : ''}
                </div>
                <div style="display: flex; gap: 8px; font-size: 10px; color: #475569; align-items: center; margin-top: 4px; flex-wrap: wrap;">
                    ${activeSchool.udise_code ? `<span style="background: #e2e8f0; color: #1e293b; padding: 1px 6px; border-radius: 4px; font-family: monospace; font-weight: 600;">UDISE: ${activeSchool.udise_code}</span>` : ''}
                    <span style="color: ${(isRunning || isTeacherRunning) ? '#2563eb' : '#16a34a'}; font-weight: 700;">
                        ● ${(isRunning || isTeacherRunning) ? 'Processing' : (totalQueueCount > 0 ? `${totalQueueCount} queued` : '0 queued')}
                    </span>
                </div>

                ${showSchoolSelector && availableSchools.length > 1 ? `
                    <div style="margin-top: 8px; padding-top: 8px; border-top: 1px dashed #cbd5e1;">
                        <div style="font-size: 10px; color: #64748b; margin-bottom: 4px; font-weight: 600;">Select Different School:</div>
                        <select id="hpc-school-switcher" style="width: 100%; background: #ffffff; color: #0f172a; border: 1px solid #94a3b8; border-radius: 6px; padding: 5px 8px; font-size: 11px; font-weight: 600; cursor: pointer; outline: none;">
                            ${availableSchools.map(s => `
                                <option value="${s.id}" ${s.id === SCHOOL_ID ? 'selected' : ''}>
                                    ${s.name} ${s.pending !== undefined ? `(${s.pending} queued)` : ''}
                                </option>
                            `).join('')}
                        </select>
                    </div>
                ` : ''}
            </div>
        `;
    }

    function detectCurrentMode() {
        const url = location.href.toLowerCase();
        if (url.includes('/en/hpc/') || url.includes('/part-') || url.includes('-stage') || url.includes('hpc-credit')) {
            return 'teacher';
        }
        if (url.includes('/en/students/')) {
            return 'school';
        }
        const saved = localStorage.getItem('hpc_active_mode');
        if (saved === 'teacher' || saved === 'school') return saved;
        return (window.__HPC_CONFIG && window.__HPC_CONFIG.role) || 'school';
    }

    function logStatus(msg) {
        lastLogMessage = msg;
        console.log('[HPC Assist]', msg);
        const el = document.getElementById('hpc-widget-status');
        if (el) el.textContent = msg;
    }

    function setNativeValue(element, value) {
        if (!element) return;
        element.focus();
        const proto = Object.getPrototypeOf(element);
        const set = Object.getOwnPropertyDescriptor(proto, 'value')?.set ||
                    Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value')?.set;
        if (set) {
            set.call(element, value);
        } else {
            element.value = value;
        }
        if (element._valueTracker) {
            element._valueTracker.setValue('');
        }
        element.dispatchEvent(new Event('input', { bubbles: true }));
        element.dispatchEvent(new Event('change', { bubbles: true }));
        element.dispatchEvent(new Event('blur', { bubbles: true }));
    }

    function parseDate(dobStr) {
        if (!dobStr) return null;
        dobStr = String(dobStr).trim();
        const parts = dobStr.split(/[\/\-\.]/);
        if (parts.length === 3) {
            if (parts[0].length === 4) {
                // YYYY-MM-DD
                return {
                    year: parts[0],
                    month: parseInt(parts[1], 10) - 1,
                    day: parseInt(parts[2], 10),
                    formatted: `${parts[2].padStart(2, '0')}/${parts[1].padStart(2, '0')}/${parts[0]}`
                };
            } else {
                // DD/MM/YYYY
                return {
                    year: parts[2],
                    month: parseInt(parts[1], 10) - 1,
                    day: parseInt(parts[0], 10),
                    formatted: `${parts[0].padStart(2, '0')}/${parts[1].padStart(2, '0')}/${parts[2]}`
                };
            }
        }
        return null;
    }

    async function sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    async function fetchSchoolsList() {
        if (!SERVER_URL) return [];
        try {
            const res = await fetch(`${SERVER_URL}/hpc/schools/list`, {
                headers: { 'Accept': 'application/json', 'X-HPC-Token': HPC_TOKEN }
            });
            const data = await res.json();
            if (data.ok && Array.isArray(data.schools)) {
                availableSchools = data.schools;
                return availableSchools;
            }
        } catch(e) {
            console.warn('[HPC Assist] Could not fetch schools list:', e);
        }
        return [];
    }

    async function autoDetectActiveSchool() {
        // 1. Check URL hash parameters (passed by dashboard "Start Session" button)
        if (location.hash && location.hash.includes('hpc_school_id=')) {
            try {
                const hashStr = location.hash.replace(/^#/, '');
                const p = new URLSearchParams(hashStr);
                const hashSchoolId = parseInt(p.get('hpc_school_id'), 10);
                const hashUdise = p.get('hpc_udise');
                const hashRole = p.get('hpc_role');
                if (hashSchoolId) {
                    SCHOOL_ID = hashSchoolId;
                    if (hashRole) CURRENT_MODE = hashRole;
                    console.log('[HPC Assist] Received active school directly from URL session start hash:', hashSchoolId, 'UDISE:', hashUdise);
                    // Purge any stale cross-school queues immediately
                    localStorage.removeItem('hpc_student_queue');
                    localStorage.removeItem('hpc_teacher_queue');
                    studentsQueue = [];
                    teacherQueue = [];
                    // Clean URL hash so the address bar remains tidy
                    history.replaceState(null, '', location.pathname + location.search);
                }
            } catch(e) {}
        }

        // 2. Check chrome.storage.local (synced by dashboard_bridge.js from ctetmonktest.fun)
        if (typeof chrome !== 'undefined' && chrome.storage && chrome.storage.local) {
            try {
                const stored = await new Promise(r => chrome.storage.local.get(['hpc_active_school'], r));
                if (stored && stored.hpc_active_school && stored.hpc_active_school.school_id) {
                    const st = stored.hpc_active_school;
                    if (!SCHOOL_ID) {
                        SCHOOL_ID = st.school_id;
                        currentSchoolName = st.school_name || currentSchoolName;
                    }
                    if (st.teacher_id) currentTeacherId = st.teacher_id;
                    if (st.teacher_name) currentTeacherName = st.teacher_name;
                    if (st.role) CURRENT_MODE = st.role;
                    if (st.server_url) SERVER_URL = st.server_url.replace(/\/$/, '');
                    if (st.progress_token) HPC_TOKEN = st.progress_token;
                    console.log('[HPC Assist] Loaded active school from extension storage:', currentSchoolName, '(ID: ' + SCHOOL_ID + ') Role:', CURRENT_MODE);
                }
            } catch(e) {}
        }

        // 3. Fetch available schools list from server
        await fetchSchoolsList();

        // 4. Detect logged-in HPC portal credentials from portal DOM and local/session storages
        const portalSchool = detectPortalSchool();
        if (portalSchool) {
            console.log('[HPC Assist] Logged-in HPC portal school verified:', portalSchool.name, '(UDISE:', portalSchool.udise_code, 'ID:', portalSchool.id, ')');
            if (SCHOOL_ID !== portalSchool.id) {
                console.log(`[HPC Assist] Switching active school to logged-in portal school: ${portalSchool.name} (was: ${currentSchoolName || 'none'})`);
                SCHOOL_ID = portalSchool.id;
                currentSchoolName = portalSchool.name;
                localStorage.removeItem('hpc_student_queue');
                localStorage.removeItem('hpc_teacher_queue');
                studentsQueue = [];
                teacherQueue = [];
                if (typeof chrome !== 'undefined' && chrome.storage && chrome.storage.local) {
                    chrome.storage.local.set({
                        hpc_active_school: {
                            school_id: portalSchool.id,
                            school_name: portalSchool.name,
                            udise_code: portalSchool.udise_code || '',
                            role: CURRENT_MODE,
                            synced_at: Date.now()
                        }
                    });
                }
            }
        }

        // 5. Update school name if SCHOOL_ID is known
        if (SCHOOL_ID) {
            const matched = availableSchools.find(s => s.id === SCHOOL_ID);
            if (matched) currentSchoolName = matched.name;
        }

        // Note: NO RANDOM FALLBACK. We strictly NEVER select another school's pending queue automatically.
        if (!SCHOOL_ID) {
            console.warn('[HPC Assist] No active school selected or detected. Please select your school in the widget.');
        }
    }

    // Helper: Detect the logged-in school from the PARAKH portal page
    function detectPortalSchool() {
        if (!availableSchools || availableSchools.length === 0) return null;

        // A. Check portal localStorage and sessionStorage for school UDISE or credentials
        const storages = [window.localStorage, window.sessionStorage];
        for (const st of storages) {
            try {
                for (let i = 0; i < st.length; i++) {
                    const key = st.key(i);
                    const val = st.getItem(key);
                    if (!val || typeof val !== 'string') continue;
                    for (const sc of availableSchools) {
                        if (sc.udise_code && sc.udise_code.length >= 8 && val.includes(sc.udise_code)) {
                            return sc;
                        }
                    }
                }
            } catch(e) {}
        }

        // B. Check portal header / user profile badges in DOM
        const headerEls = document.querySelectorAll('header, nav, .navbar, .user-name, .school-name, .user-profile, .profile-name, [class*="school"], [class*="user"], .card-header');
        let headerText = '';
        headerEls.forEach(el => { headerText += ' ' + (el.innerText || ''); });
        if (!headerText) headerText = document.body ? (document.body.innerText || '') : '';
        headerText = headerText.toLowerCase();

        for (const sc of availableSchools) {
            const scUdise = (sc.udise_code || '').trim().toLowerCase();
            const scName  = (sc.name || '').trim().toLowerCase();
            if (scUdise && scUdise.length >= 8 && headerText.includes(scUdise)) {
                return sc;
            }
            if (scName && scName.length >= 6 && headerText.includes(scName)) {
                return sc;
            }
        }

        // C. Check username input if on login page
        const userInput = document.querySelector('input[name="username"], input[name="email"], input[type="text"]');
        if (userInput && userInput.value) {
            const rawVal = userInput.value.trim();
            const match = availableSchools.find(s => s.udise_code === rawVal || (s.name && s.name.length >= 6 && s.name.toLowerCase() === rawVal.toLowerCase()));
            if (match) return match;
        }

        return null;
    }

    // Load queue from localStorage or Server API (Strictly isolated by School ID and UDISE)
    async function loadQueue(forceServer = false) {
        if (!SCHOOL_ID) {
            studentsQueue = [];
            return false;
        }

        const activeSchool = availableSchools.find(s => s.id === SCHOOL_ID);
        const currentUdise = activeSchool?.udise_code || '';

        // Strict cache validation: ONLY use cache if it strictly belongs to THIS SCHOOL and THIS UDISE
        if (!forceServer) {
            try {
                const raw = localStorage.getItem('hpc_student_queue');
                if (raw) {
                    const parsed = JSON.parse(raw);
                    const cacheMatches = parsed.school_id === SCHOOL_ID &&
                        (!currentUdise || !parsed.udise_code || parsed.udise_code === currentUdise);

                    if (cacheMatches && Array.isArray(parsed.students) && parsed.students.length > 0) {
                        studentsQueue = parsed.students;
                        currentSchoolName = parsed.school_name || currentSchoolName;
                        console.log(`[HPC Assist] Loaded ${studentsQueue.length} students from verified school cache (${currentSchoolName}, ID: ${SCHOOL_ID})`);
                        return true;
                    } else {
                        console.log(`[HPC Assist] Cache belonged to another school (cached ID: ${parsed.school_id}, active: ${SCHOOL_ID}). Invalidating.`);
                        localStorage.removeItem('hpc_student_queue');
                    }
                }
            } catch(e) {}
        }

        if (!SERVER_URL) {
            console.warn('[HPC Assist] SERVER_URL not configured — cannot fetch student queue.');
            return false;
        }

        try {
            // Always request explicitly for THIS school ID with timestamp cache busting
            const url = `${SERVER_URL}/hpc/students/queue?school_id=${SCHOOL_ID}&_ts=${Date.now()}`;
            const res = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-HPC-Token': HPC_TOKEN,
                }
            });
            const data = await res.json();
            if (data.ok && Array.isArray(data.students)) {
                // Security verification: Server response must strictly match requested school
                if (data.school_id && data.school_id !== SCHOOL_ID) {
                    console.error('[HPC Assist] Security Mismatch! Requested school_id:', SCHOOL_ID, 'got:', data.school_id);
                    studentsQueue = [];
                    return false;
                }

                studentsQueue = data.students;
                currentSchoolName = data.school_name || currentSchoolName;
                localStorage.setItem('hpc_student_queue', JSON.stringify({
                    school_id: SCHOOL_ID,
                    udise_code: currentUdise || data.udise_code || '',
                    school_name: currentSchoolName,
                    students: data.students,
                    timestamp: Date.now()
                }));
                console.log(`[HPC Assist] Successfully loaded ${studentsQueue.length} fresh students for ${currentSchoolName} (UDISE: ${currentUdise})`);
                return true;
            } else {
                console.warn('[HPC Assist] Queue endpoint returned error or no students:', data);
                studentsQueue = [];
                localStorage.removeItem('hpc_student_queue');
                return false;
            }
        } catch(err) {
            console.warn('[HPC Assist] Could not fetch student queue from server:', err);
            return false;
        }
    }

    async function reportProgress(current, total, name, status, message, done = false, extra = {}) {
        if (!SERVER_URL) return;
        try {
            await fetch(`${SERVER_URL}/hpc/run/progress`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-HPC-Token': HPC_TOKEN,
                },
                body: JSON.stringify({
                    school_id: SCHOOL_ID,
                    student_id: extra.student_id ?? null,
                    admission_no: extra.admission_no ?? null,
                    apaar_id: extra.apaar_id ?? null,
                    current,
                    total,
                    name,
                    status,
                    message,
                    done
                })
            });
        } catch(e) {}
    }

    // Load Teacher Evaluation queue from localStorage or Server API (Strictly isolated by School ID)
    async function loadTeacherQueue(forceServer = false) {
        if (!SCHOOL_ID) {
            teacherQueue = [];
            return false;
        }

        if (!forceServer) {
            try {
                const raw = localStorage.getItem('hpc_teacher_queue');
                if (raw) {
                    const parsed = JSON.parse(raw);
                    if (parsed.school_id === SCHOOL_ID && Array.isArray(parsed.students) && parsed.students.length > 0) {
                        teacherQueue = parsed.students;
                        return true;
                    } else {
                        localStorage.removeItem('hpc_teacher_queue');
                    }
                }
            } catch(e) {}
        }

        if (!SERVER_URL) {
            console.warn('[HPC Assist] SERVER_URL not configured — cannot fetch teacher evaluation queue.');
            return false;
        }

        try {
            const url = `${SERVER_URL}/hpc/teacher/evaluations/queue?school_id=${SCHOOL_ID}&_ts=${Date.now()}`;
            const res = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-HPC-Token': HPC_TOKEN,
                }
            });
            const data = await res.json();
            if (data.ok && Array.isArray(data.students)) {
                if (data.school_id && data.school_id !== SCHOOL_ID) {
                    teacherQueue = [];
                    return false;
                }
                teacherQueue = data.students;
                localStorage.setItem('hpc_teacher_queue', JSON.stringify({
                    school_id: SCHOOL_ID,
                    school_name: data.school_name || currentSchoolName,
                    students: data.students,
                    timestamp: Date.now()
                }));
                return true;
            } else {
                console.warn('[HPC Assist] Teacher evaluations queue returned no students or error:', data);
                teacherQueue = [];
                localStorage.removeItem('hpc_teacher_queue');
                return false;
            }
        } catch(err) {
            console.warn('[HPC Assist] Could not fetch teacher evaluation queue from server:', err);
            return false;
        }
    }

    async function reportTeacherProgress(current, total, name, loginId, status, message, done = false) {
        if (!SERVER_URL) return;
        try {
            await fetch(`${SERVER_URL}/hpc/teacher/evaluations/progress`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-HPC-Token': HPC_TOKEN,
                },
                body: JSON.stringify({
                    school_id: SCHOOL_ID,
                    current,
                    total,
                    name,
                    login_id: loginId,
                    status,
                    message,
                    done
                })
            });
        } catch(e) {}
    }

    // Scoped field finders based on PARAKH DOM (.custom-input-label wrappers)
    function getFieldByLabel(labelKeyword) {
        const labels = Array.from(document.querySelectorAll('.custom-input-label label, label'));
        const lbl = labels.find(l => (l.innerText || l.textContent || '').toLowerCase().includes(labelKeyword.toLowerCase()));
        if (!lbl) return null;
        if (lbl.htmlFor) {
            const el = document.getElementById(lbl.htmlFor);
            if (el) return el;
        }
        const wrapper = lbl.closest('.custom-input-label, .form-group, .input-container, .col, [class*="col-"]') || lbl.parentElement;
        if (!wrapper) return null;
        const inps = Array.from(wrapper.querySelectorAll('input:not([type="hidden"]):not([maxlength="1"])'));
        return inps.length > 0 ? inps[0] : null;
    }

    function getDropdownByLabel(labelKeyword) {
        const labels = Array.from(document.querySelectorAll('.custom-input-label label, label'));
        const lbl = labels.find(l => l.textContent.toLowerCase().includes(labelKeyword.toLowerCase()));
        if (!lbl) return null;
        const wrapper = lbl.closest('.custom-input-label') || lbl.parentElement;
        if (!wrapper) return null;
        return wrapper.querySelector('.custom-dropdown-wrapper') || 
               wrapper.querySelector('.custom-dropdown-header') ||
               wrapper.querySelector('[role="combobox"], select');
    }

    // Precise Form Field Mapping based on PARAKH DOM
    function findFormFields() {
        // PARAKH portal has 4 text inputs with placeholder "Write here":
        // 0: First Name *
        // 1: Last Name
        // 2: Email *
        // 3: School Admission Number
        const writeInputs = Array.from(document.querySelectorAll('input[placeholder*="Write here" i], input[placeholder*="write here" i], input[placeholder*="लिखें" i]'));
        const allTextInputs = Array.from(document.querySelectorAll('input:not([type="hidden"]):not([type="button"]):not([type="submit"]):not([maxlength="1"])'))
            .filter(i => !i.disabled && (i.type === 'text' || i.type === 'email' || !i.type));

        const firstName = getFieldByLabel('first') || writeInputs[0] || allTextInputs[0] || null;

        let lastName = getFieldByLabel('last');
        if (!lastName || lastName === firstName) {
            lastName = writeInputs.find(i => i !== firstName) || allTextInputs.find(i => i !== firstName) || null;
        }

        let email = document.querySelector('input[type="email"]') || getFieldByLabel('email');
        if (!email || email === firstName || email === lastName) {
            email = writeInputs.find(i => i !== firstName && i !== lastName) || 
                    allTextInputs.find(i => i !== firstName && i !== lastName) || null;
        }

        // Admission Number must NEVER be firstName, lastName, or email
        let admission = null;
        const admByLabel = getFieldByLabel('admission') || getFieldByLabel('admn');
        if (admByLabel && admByLabel !== firstName && admByLabel !== lastName && admByLabel !== email) {
            admission = admByLabel;
        }

        if (!admission) {
            if (writeInputs.length >= 4 && writeInputs[3] !== firstName && writeInputs[3] !== lastName && writeInputs[3] !== email) {
                admission = writeInputs[3];
            } else {
                admission = writeInputs.find(i => i !== firstName && i !== lastName && i !== email) ||
                            allTextInputs.find(i => i !== firstName && i !== lastName && i !== email && i.getAttribute('placeholder') !== 'Select') || null;
            }
        }

        // Date of Birth input (React Datepicker wrapped in .custom-input-label)
        const dobLabel = Array.from(document.querySelectorAll('.custom-input-label label, label')).find(l => {
            const t = l.textContent.toLowerCase();
            return t.includes('birth') || t.includes('d-o-b') || t.includes('dob') || t.includes('जन्म');
        });
        let dob = dobLabel?.closest('.custom-input-label')?.querySelector('input') ||
                  document.querySelector('.react-datepicker__input-container input') ||
                  document.querySelector('input[placeholder*="Select" i], input[type="date"]');

        if (!dob || dob === firstName || dob === lastName || dob === admission) {
            const dobCandidates = Array.from(document.querySelectorAll('input:not([type="hidden"])'))
                .filter(i => i !== firstName && i !== lastName && i !== email && i !== admission && i.getAttribute('maxlength') !== '1');
            if (dobCandidates.length > 0) dob = dobCandidates[0];
        }

        // Dropdown elements: Gender, Grade, Section
        const allWrappers = Array.from(document.querySelectorAll('.custom-dropdown-wrapper'));
        const allHeaders  = Array.from(document.querySelectorAll('.custom-dropdown-header, [role="combobox"], select')).filter(d => d.offsetParent !== null);

        const genderTrigger  = getDropdownByLabel('gender') || allWrappers[0] || allHeaders[0] || null;
        const gradeTrigger   = getDropdownByLabel('grade') || allWrappers[1] || allHeaders[1] || null;
        const sectionTrigger = getDropdownByLabel('section') || allWrappers[2] || allHeaders[2] || null;

        // APAAR ID: 12 single character boxes
        const apaarBoxes = Array.from(document.querySelectorAll('.udise-codes input, input[name^="std_apaar_id"], input[maxlength="1"]'));

        return {
            firstName,
            lastName,
            dob,
            genderTrigger,
            email,
            admissionNo: admission,
            gradeTrigger,
            sectionTrigger,
            apaarBoxes
        };
    }

    async function selectDropdownOption(labelKeyword, fallbackIndex, targetValue, fallbacks = []) {
        let trigger = getDropdownByLabel(labelKeyword);
        if (!trigger) {
            const wrappers = Array.from(document.querySelectorAll('.custom-dropdown-wrapper'));
            if (wrappers[fallbackIndex]) trigger = wrappers[fallbackIndex];
        }
        if (!trigger) {
            console.warn(`[HPC Assist] Dropdown not found for "${labelKeyword}"`);
            return false;
        }

        // Native select
        if (trigger.tagName === 'SELECT') {
            const opt = Array.from(trigger.options).find(o => {
                const txt = o.text.toLowerCase().trim();
                const val = o.value.toLowerCase().trim();
                const tgt = String(targetValue).toLowerCase().trim();
                return txt === tgt || txt.includes(tgt) || val === tgt;
            });
            if (opt) {
                trigger.value = opt.value;
                trigger.dispatchEvent(new Event('change', { bubbles: true }));
                return true;
            }
        }

        // Custom Dropdown: Click header/trigger to toggle open
        const header = trigger.querySelector('.custom-dropdown-header') || trigger;
        try { header.scrollIntoView({ block: 'nearest', inline: 'nearest' }); } catch(e) {}

        const headerPropsKey = Object.keys(header).find(k => k.startsWith('__reactProps'));
        if (headerPropsKey && typeof header[headerPropsKey]?.onClick === 'function') {
            try { header[headerPropsKey].onClick({ stopPropagation: () => {}, preventDefault: () => {} }); } catch(e) {}
        }
        header.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, cancelable: true }));
        header.dispatchEvent(new MouseEvent('mouseup', { bubbles: true, cancelable: true }));
        header.click();
        await sleep(350);

        const candidates = [targetValue, ...fallbacks].map(s => String(s).toLowerCase().trim());
        let options = Array.from(trigger.querySelectorAll('.custom-dropdown-item'))
            .concat(Array.from(document.querySelectorAll('.custom-dropdown-menu .custom-dropdown-item, .custom-dropdown-item')));
        options = Array.from(new Set(options)).filter(el => el.offsetParent !== null || el.textContent.trim().length > 0);

        let found = null;
        // PASS 1: Strict Exact Match (CRITICAL: prevents 'female'.includes('male') matching Male)
        for (const tgt of candidates) {
            found = options.find(el => el.textContent.trim().toLowerCase() === tgt);
            if (found) break;
        }

        // PASS 2: Starts with
        if (!found) {
            for (const tgt of candidates) {
                found = options.find(el => el.textContent.trim().toLowerCase().startsWith(tgt));
                if (found) break;
            }
        }

        // PASS 3: Word boundary / option text contains candidate (NEVER candidate includes option text)
        if (!found) {
            for (const tgt of candidates) {
                found = options.find(el => {
                    const txt = el.textContent.trim().toLowerCase();
                    return txt.split(/[\s-]+/).includes(tgt) || txt.includes(tgt);
                });
                if (found) break;
            }
        }

        if (found) {
            const itemPropsKey = Object.keys(found).find(k => k.startsWith('__reactProps'));
            if (itemPropsKey && typeof found[itemPropsKey]?.onClick === 'function') {
                try { found[itemPropsKey].onClick({ stopPropagation: () => {}, preventDefault: () => {} }); } catch(e) {}
            }
            found.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, cancelable: true }));
            found.dispatchEvent(new MouseEvent('mouseup', { bubbles: true, cancelable: true }));
            found.click();
            await sleep(350);
            return true;
        }

        // Search input fallback inside dropdown if search is enabled
        const searchInp = trigger.querySelector('.dropdown-search-box input') || document.querySelector('.dropdown-search-box input');
        if (searchInp) {
            setNativeValue(searchInp, targetValue);
            await sleep(300);
            const filtered = Array.from(document.querySelectorAll('.custom-dropdown-item')).filter(el => el.offsetParent !== null);
            if (filtered.length > 0) {
                const exactFiltered = filtered.find(el => el.textContent.trim().toLowerCase() === String(targetValue).trim().toLowerCase());
                const item = exactFiltered || filtered[0];
                const itemPropsKey = Object.keys(item).find(k => k.startsWith('__reactProps'));
                if (itemPropsKey && typeof item[itemPropsKey]?.onClick === 'function') {
                    try { item[itemPropsKey].onClick({ stopPropagation: () => {}, preventDefault: () => {} }); } catch(e) {}
                }
                item.click();
                await sleep(350);
                return true;
            }
        }

        header.click();
        await sleep(150);
        return false;
    }

    async function fillGender(student) {
        let target;
        if (student.gender) {
            const g = String(student.gender).toLowerCase().trim();
            if (g === 'female' || g.startsWith('f') || g.includes('girl') || g === '2') {
                target = 'Female';
            } else if (g === 'male' || g.startsWith('m') || g.includes('boy') || g === '1') {
                target = 'Male';
            } else if (g.includes('other') || g === '3') {
                target = 'Other';
            }
        }

        if (!target || target === 'Unknown') {
            // Gender is mandatory on the PARAKH portal — do not guess from the name.
            const stName = student.name || `${student.first_name || ''} ${student.last_name || ''}`.trim();
            logStatus(`⚠️ ${stName} has no gender set. Please fix in the roster.`);
            console.warn('[HPC Assist] Missing gender for student:', stName);
            pauseAutomation();
            alert(`⚠️ Student "${stName}" has no gender set.\n\nPlease edit this student in HPC Assist, set the gender, and restart.`);
            return false; // signals to caller that we paused
        }

        logStatus(`Setting Gender: ${target}...`);
        await selectDropdownOption('gender', 0, target, [
            target.toLowerCase(),
            target.toUpperCase(),
            target === 'Female' ? 'Girl' : (target === 'Male' ? 'Boy' : 'Other')
        ]);
        return true;
    }

    async function fillDob(dobField, dobStr) {
        if (!dobField || !dobStr) return;
        const dobObj = parseDate(dobStr);
        if (!dobObj) return;

        const ymd = `${dobObj.year}-${String(dobObj.month + 1).padStart(2, '0')}-${String(dobObj.day).padStart(2, '0')}`;
        const dmy = `${String(dobObj.day).padStart(2, '0')}/${String(dobObj.month + 1).padStart(2, '0')}/${dobObj.year}`;

        logStatus(`Setting DOB: ${ymd}...`);
        
        dobField.removeAttribute('readonly');
        
        // Try standard HTML5 date format (YYYY-MM-DD)
        setNativeValue(dobField, ymd);
        await sleep(150);

        // Fallback to DD/MM/YYYY if not accepted
        if (!dobField.value || dobField.value !== ymd) {
            setNativeValue(dobField, dmy);
            await sleep(150);
        }

        // Also trigger calendar datepicker popup
        dobField.click();
        await sleep(300);

        const yr = document.querySelector('.react-datepicker__year-select, select[class*="year" i]');
        if (yr) {
            yr.value = dobObj.year;
            yr.dispatchEvent(new Event('change', { bubbles: true }));
            await sleep(100);
        }

        const mo = document.querySelector('.react-datepicker__month-select, select[class*="month" i]');
        if (mo) {
            mo.value = String(dobObj.month);
            mo.dispatchEvent(new Event('change', { bubbles: true }));
            await sleep(100);
        }

        const days = Array.from(document.querySelectorAll('.react-datepicker__day:not(.react-datepicker__day--outside-month), [class*="day"]:not([class*="outside"])'));
        const dayEl = days.find(d => d.textContent.trim() === String(dobObj.day));
        if (dayEl) {
            dayEl.click();
            await sleep(200);
        }

        document.body.click();
        await sleep(150);
    }

    // Inject Floating Automation Widget with Persistent Event Delegation and Draggable Support
    function injectWidget() {
        if (document.getElementById('hpc-parakh-widget')) return;

        let initPosCss = 'bottom: 24px; right: 24px;';
        try {
            const p = JSON.parse(localStorage.getItem('hpc_widget_pos'));
            if (p && p.left && p.top) {
                initPosCss = `left: ${p.left}; top: ${p.top};`;
            }
        } catch(e) {}

        const widget = document.createElement('div');
        widget.id = 'hpc-parakh-widget';
        widget.style.cssText = `
            position: fixed;
            ${initPosCss}
            z-index: 9999999;
            background: #ffffff;
            color: #0f172a;
            border-radius: 14px;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            font-size: 13px;
            box-shadow: 0 16px 36px -4px rgba(0, 0, 0, 0.22), 0 4px 12px rgba(0, 0, 0, 0.08);
            border: 1px solid #cbd5e1;
            width: 320px;
            overflow: hidden;
            transition: box-shadow 0.2s ease, border-color 0.2s ease;
        `;

        document.body.appendChild(widget);

        // DRAGGABLE IMPLEMENTATION
        let isDragging = false;
        let startX = 0, startY = 0;
        let startLeft = 0, startTop = 0;

        widget.addEventListener('mousedown', (e) => {
            const dragHandle = e.target.closest('#hpc-widget-drag-header, #hpc-minimized-pill');
            if (!dragHandle) return;
            if (e.target.closest('button, select, a, input')) return;

            isDragging = true;
            startX = e.clientX;
            startY = e.clientY;
            const rect = widget.getBoundingClientRect();
            startLeft = rect.left;
            startTop = rect.top;

            widget.style.right = 'auto';
            widget.style.bottom = 'auto';
            widget.style.left = `${startLeft}px`;
            widget.style.top = `${startTop}px`;
            widget.style.userSelect = 'none';

            function onMouseMove(moveEv) {
                if (!isDragging) return;
                const dx = moveEv.clientX - startX;
                const dy = moveEv.clientY - startY;
                const maxLeft = Math.max(0, window.innerWidth - widget.offsetWidth);
                const maxTop = Math.max(0, window.innerHeight - widget.offsetHeight);
                const newLeft = Math.max(0, Math.min(maxLeft, startLeft + dx));
                const newTop = Math.max(0, Math.min(maxTop, startTop + dy));

                widget.style.left = `${newLeft}px`;
                widget.style.top = `${newTop}px`;
            }

            function onMouseUp() {
                if (!isDragging) return;
                isDragging = false;
                widget.style.userSelect = '';
                document.removeEventListener('mousemove', onMouseMove);
                document.removeEventListener('mouseup', onMouseUp);

                try {
                    localStorage.setItem('hpc_widget_pos', JSON.stringify({
                        left: widget.style.left,
                        top: widget.style.top
                    }));
                } catch(err) {}
            }

            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp);
            e.preventDefault();
        });

        // Event delegation on widget
        widget.addEventListener('click', async (e) => {
            // Minimize / Maximize Toggles
            const minBtn = e.target.closest('#hpc-minimize-btn');
            if (minBtn) {
                isWidgetMinimized = true;
                localStorage.setItem('hpc_widget_minimized', 'true');
                updateWidgetContent(widget);
                return;
            }
            const maxBtn = e.target.closest('#hpc-maximize-btn');
            if (maxBtn) {
                isWidgetMinimized = false;
                localStorage.setItem('hpc_widget_minimized', 'false');
                updateWidgetContent(widget);
                return;
            }

            // School selector toggle
            const toggleSchoolBtn = e.target.closest('#hpc-toggle-school-select');
            if (toggleSchoolBtn) {
                showSchoolSelector = !showSchoolSelector;
                updateWidgetContent(widget);
                return;
            }

            const startBtn = e.target.closest('#hpc-start-btn');
            if (startBtn) {
                console.log('[HPC Assist] User clicked Start Auto-Submit');
                await startAutomation();
                return;
            }
            const pauseBtn = e.target.closest('#hpc-pause-btn');
            if (pauseBtn) {
                console.log('[HPC Assist] User clicked Pause');
                pauseAutomation();
                return;
            }
            const refreshBtn = e.target.closest('#hpc-refresh-btn');
            if (refreshBtn) {
                console.log('[HPC Assist] User clicked Sync');
                logStatus('🔄 Syncing queue from HPC Assist...');
                const success = await loadQueue(true);
                currentIndex = 0;
                localStorage.setItem('hpc_student_index', '0');
                updateWidgetContent();
                if (studentsQueue.length > 0) {
                    logStatus(`✅ Synced ${studentsQueue.length} student(s) from server!`);
                } else if (!success) {
                    logStatus('⚠️ Could not connect to server. Check network or server URL.');
                } else {
                    logStatus('ℹ️ No pending students in queue.');
                }
                return;
            }
            const resetBtn = e.target.closest('#hpc-reset-btn');
            if (resetBtn) {
                console.log('[HPC Assist] User clicked Reset');
                currentIndex = 0;
                localStorage.setItem('hpc_student_index', '0');
                localStorage.setItem('hpc_submission_running', 'false');
                isRunning = false;
                updateWidgetContent();
                return;
            }
            const fetchHpcBtn = e.target.closest('#hpc-fetch-portal-btn');
            if (fetchHpcBtn) {
                console.log('[HPC Assist] User clicked Fetch HPC List');
                await fetchHpcPortalStudents();
                return;
            }

            // Mode Toggle Handler
            const toggleModeBtn = e.target.closest('#hpc-mode-toggle-btn');
            if (toggleModeBtn) {
                CURRENT_MODE = (CURRENT_MODE === 'teacher') ? 'school' : 'teacher';
                localStorage.setItem('hpc_active_mode', CURRENT_MODE);
                logStatus(`Switched to ${CURRENT_MODE === 'teacher' ? 'Teacher Evaluation' : 'School Submitter'} Mode.`);
                if (CURRENT_MODE === 'teacher') {
                    await loadTeacherQueue(true);
                } else {
                    await loadQueue(true);
                }
                updateWidgetContent();
                return;
            }

            // Teacher Mode Action Buttons
            const tStartBtn = e.target.closest('#hpc-teacher-start-btn');
            if (tStartBtn) {
                console.log('[HPC Assist] User clicked Start Teacher Evaluation');
                await startTeacherEvaluation();
                return;
            }
            const tPauseBtn = e.target.closest('#hpc-teacher-pause-btn');
            if (tPauseBtn) {
                console.log('[HPC Assist] User clicked Pause Teacher Evaluation');
                pauseTeacherEvaluation();
                return;
            }
            const tSyncBtn = e.target.closest('#hpc-teacher-sync-btn');
            if (tSyncBtn) {
                console.log('[HPC Assist] User clicked Sync Evaluations');
                logStatus('🔄 Syncing evaluations queue from HPC Assist...');
                const success = await loadTeacherQueue(true);
                teacherCurrentIndex = 0;
                localStorage.setItem('hpc_teacher_index', '0');
                updateWidgetContent();
                if (teacherQueue.length > 0) {
                    logStatus(`✅ Synced ${teacherQueue.length} student evaluation(s)!`);
                } else if (!success) {
                    logStatus('⚠️ Could not connect or no evaluations uploaded.');
                } else {
                    logStatus('ℹ️ No evaluations queued. Upload spreadsheet on dashboard.');
                }
                return;
            }
            const tResetBtn = e.target.closest('#hpc-teacher-reset-btn');
            if (tResetBtn) {
                console.log('[HPC Assist] User clicked Reset Teacher Evaluation');
                teacherCurrentIndex = 0;
                localStorage.setItem('hpc_teacher_index', '0');
                localStorage.setItem('hpc_teacher_running', 'false');
                isTeacherRunning = false;
                localStorage.removeItem('hpc_teacher_active_student');
                logStatus('Evaluation progress reset to Student #1.');
                updateWidgetContent();
                return;
            }
            const tFetchBtn = e.target.closest('#hpc-teacher-fetch-portal-btn');
            if (tFetchBtn) {
                console.log('[HPC Assist] User clicked Fetch Students from Portal');
                await fetchTeacherStudentsFromPortal();
                return;
            }
        });

        // School switcher dropdown handler
        widget.addEventListener('change', async (e) => {
            if (e.target.id === 'hpc-school-switcher') {
                const newId = parseInt(e.target.value, 10);
                if (newId) {
                    SCHOOL_ID = newId;
                    const matched = availableSchools.find(s => s.id === newId);
                    currentSchoolName = matched ? matched.name : '';
                    const newUdise = matched ? (matched.udise_code || '') : '';
                    localStorage.setItem('hpc_active_school_id', String(newId));
                    localStorage.removeItem('hpc_student_queue');
                    localStorage.removeItem('hpc_teacher_queue');
                    studentsQueue = [];
                    teacherQueue = [];
                    if (typeof chrome !== 'undefined' && chrome.storage && chrome.storage.local) {
                        chrome.storage.local.set({
                            hpc_active_school: {
                                school_id: newId,
                                school_name: currentSchoolName,
                                udise_code: newUdise,
                                role: CURRENT_MODE,
                                synced_at: Date.now()
                            }
                        });
                    }
                    showSchoolSelector = false; // Collapse selector back to active school view
                    logStatus(`🏫 Switched to ${currentSchoolName}. Loading roster...`);
                    await loadQueue(true);
                    currentIndex = 0;
                    localStorage.setItem('hpc_student_index', '0');
                    updateWidgetContent();
                }
            }
        });

        updateWidgetContent(widget);
    }

    function updateWidgetContent(widget) {
        if (!widget) widget = document.getElementById('hpc-parakh-widget');
        if (!widget) return;

        // ─── MINIMIZED PILL STATE ────────────────────────────────────────────
        if (isWidgetMinimized) {
            widget.style.width = 'auto';
            widget.style.padding = '8px 14px';
            widget.style.borderRadius = '9999px';
            widget.style.background = '#ffffff';
            widget.style.border = '1.5px solid #cbd5e1';
            widget.style.boxShadow = '0 8px 25px -4px rgba(0, 0, 0, 0.18)';

            const activeLabel = CURRENT_MODE === 'teacher' ? 'Teacher HPC' : 'HPC Assist';
            const progressCount = CURRENT_MODE === 'teacher'
                ? `[${teacherCurrentIndex}/${teacherQueue.length}]`
                : `[${currentIndex}/${studentsQueue.length}]`;
            const isAnyRunning = CURRENT_MODE === 'teacher' ? isTeacherRunning : isRunning;

            widget.innerHTML = `
                <div id="hpc-minimized-pill" style="display: flex; align-items: center; gap: 8px; cursor: move; user-select: none; border-left: 3.5px solid #FF671F; padding-left: 6px;">
                    <span style="font-size: 14px;">${CURRENT_MODE === 'teacher' ? '👩‍🏫' : '🚀'}</span>
                    <span style="font-weight: 700; font-size: 12px; color: #0f172a; white-space: nowrap;">
                        ${activeLabel}
                        <span style="color: #1e40af; font-weight: 700; margin-left: 4px;">${progressCount}</span>
                    </span>
                    <span style="font-size: 9px; padding: 2px 7px; border-radius: 8px; font-weight: 700; background: ${isAnyRunning ? '#dbeafe' : '#dcfce7'}; color: ${isAnyRunning ? '#1d4ed8' : '#15803d'}; border: 1px solid ${isAnyRunning ? '#bfdbfe' : '#bbf7d0'};">
                        ${isAnyRunning ? '⚡ RUNNING' : 'READY'}
                    </span>
                    <button id="hpc-maximize-btn" title="Expand Submitter" style="background: #f1f5f9; border: 1px solid #cbd5e1; color: #334155; width: 22px; height: 22px; border-radius: 6px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 12px; padding: 0; line-height: 1; margin-left: 2px;">
                        ⤢
                    </button>
                </div>
            `;
            return;
        }

        // Expanded styles
        widget.style.width = '320px';
        widget.style.padding = '0';
        widget.style.borderRadius = '14px';
        widget.style.background = '#ffffff';
        widget.style.border = '1px solid #cbd5e1';

        const tricolorBanner = `
            <div style="height: 5px; width: 100%; background: linear-gradient(90deg, #FF671F 0%, #FF9933 33.3%, #ffffff 33.3%, #ffffff 35%, #000080 35%, #000080 65%, #ffffff 65%, #ffffff 66.6%, #138808 66.6%, #046A38 100%);"></div>
        `;

        // ─── TEACHER EVALUATION MODE WIDGET ─────────────────────────────────
        if (CURRENT_MODE === 'teacher') {
            const tTotal = teacherQueue.length;
            const tPct = tTotal > 0 ? Math.round((teacherCurrentIndex / tTotal) * 100) : 0;

            widget.innerHTML = `
                ${tricolorBanner}
                <div style="padding: 14px 18px;">
                    <div id="hpc-widget-drag-header" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; cursor: move; user-select: none; padding-bottom: 8px; border-bottom: 1px solid #e2e8f0;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="font-size: 16px;">👩‍🏫</span>
                            <strong style="font-weight: 800; font-size: 13px; color: #0f172a; letter-spacing: -0.2px;">Teacher Evaluation</strong>
                        </div>
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <span style="font-size: 10px; background: ${isTeacherRunning ? '#dbeafe' : '#dcfce7'}; color: ${isTeacherRunning ? '#1d4ed8' : '#15803d'}; border: 1px solid ${isTeacherRunning ? '#bfdbfe' : '#bbf7d0'}; padding: 2px 8px; border-radius: 12px; font-weight: 700;">
                                ${isTeacherRunning ? '⚡ EVALUATING' : 'READY'}
                            </span>
                            <button id="hpc-minimize-btn" title="Minimize Submitter" style="background: #f1f5f9; border: 1px solid #cbd5e1; color: #475569; width: 22px; height: 22px; border-radius: 6px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; padding: 0; line-height: 1;">
                                ─
                            </button>
                        </div>
                    </div>

                    ${renderActiveSchoolCard(tTotal)}

                    <div style="font-size: 12px; color: #64748b; margin-bottom: 12px; line-height: 1.4;">
                        ${tTotal > 0 ? `<strong>${tTotal}</strong> student evaluations loaded across 15 parts.` : 'No evaluation data loaded. Click Sync or upload spreadsheet in Step 4.'}
                    </div>

                    ${tTotal > 0 ? `
                        <div style="background: #e2e8f0; border-radius: 8px; height: 7px; overflow: hidden; margin-bottom: 6px;">
                            <div style="background: linear-gradient(90deg, #FF671F 0%, #2563eb 50%, #16a34a 100%); height: 100%; width: ${tPct}%; transition: width 0.3s ease;"></div>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 11px; color: #475569; margin-bottom: 12px; font-weight: 500;">
                            <span id="hpc-widget-status">${lastLogMessage || (isTeacherRunning ? `Evaluating ${teacherCurrentIndex + 1} of ${tTotal}...` : `${teacherCurrentIndex}/${tTotal} completed`)}</span>
                            <span style="font-weight: 700; color: #1e40af;">${tPct}%</span>
                        </div>
                    ` : ''}

                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        ${isTeacherRunning ? `
                            <button id="hpc-teacher-pause-btn" style="flex: 1; min-width: 100px; padding: 8px 12px; background: #dc2626; color: white; border: none; border-radius: 8px; font-weight: 700; font-size: 12px; cursor: pointer;">
                                ⏸ Pause
                            </button>
                        ` : `
                            <button id="hpc-teacher-start-btn" ${tTotal === 0 ? 'disabled' : ''} style="flex: 1; min-width: 120px; padding: 8px 12px; background: linear-gradient(135deg, #1e40af, #2563eb); color: white; border: none; border-radius: 8px; font-weight: 700; font-size: 12px; cursor: pointer; box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.25); opacity: ${tTotal === 0 ? '0.5' : '1'};">
                                🚀 ${teacherCurrentIndex >= tTotal ? 'Restart from #1' : (teacherCurrentIndex > 0 ? 'Resume (' + (teacherCurrentIndex + 1) + '/' + tTotal + ')' : 'Start Auto-Evaluation')}
                            </button>
                        `}
                        <button id="hpc-teacher-sync-btn" style="padding: 8px 12px; background: #f8fafc; color: #1e293b; border: 1px solid #cbd5e1; border-radius: 8px; font-weight: 600; font-size: 12px; cursor: pointer;" title="Refresh evaluations queue from HPC Assist server">
                            🔄 Sync
                        </button>
                        ${teacherCurrentIndex > 0 && !isTeacherRunning ? `
                            <button id="hpc-teacher-reset-btn" style="padding: 8px 10px; background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; border-radius: 8px; font-weight: 600; font-size: 11px; cursor: pointer;" title="Reset progress to student 1">
                                ⏮ Reset
                            </button>
                        ` : ''}
                    </div>

                    <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #e2e8f0;">
                        <button id="hpc-teacher-fetch-portal-btn" style="width: 100%; padding: 9px 12px; background: linear-gradient(135deg, #FF671F, #ea580c); color: white; border: none; border-radius: 8px; font-weight: 700; font-size: 12px; cursor: pointer; box-shadow: 0 4px 6px -1px rgba(234, 88, 12, 0.25);" title="Scrape students from this stage table and sync to HPC Assist teacher portal">
                            📥 Fetch Students from This Page
                        </button>
                        <div style="font-size: 10px; color: #64748b; margin-top: 4px; text-align: center;">Syncs live portal roster to your Excel template</div>
                    </div>

                    <div style="margin-top: 10px; padding-top: 8px; border-top: 1px solid #e2e8f0;">
                        <div style="font-size: 10px; font-weight: 700; color: #475569; margin-bottom: 4px; text-transform: uppercase;">Switch HPC Stage:</div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 4px; font-size: 10px;">
                            <a href="https://hpc.parakh.ncert.gov.in/en/hpc/foundational-stage" style="display: block; text-align: center; padding: 4px; background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; border-radius: 6px; text-decoration: none; font-weight: 600;">Foundational</a>
                            <a href="https://hpc.parakh.ncert.gov.in/en/hpc/preparatory-stage" style="display: block; text-align: center; padding: 4px; background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; border-radius: 6px; text-decoration: none; font-weight: 600;">Preparatory</a>
                            <a href="https://hpc.parakh.ncert.gov.in/en/hpc/middle-stage" style="display: block; text-align: center; padding: 4px; background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; border-radius: 6px; text-decoration: none; font-weight: 600;">Middle</a>
                            <a href="https://hpc.parakh.ncert.gov.in/en/hpc/secondary-stage" style="display: block; text-align: center; padding: 4px; background: #f5f3ff; color: #6d28d9; border: 1px solid #ddd6fe; border-radius: 6px; text-decoration: none; font-weight: 600;">Secondary</a>
                        </div>
                    </div>

                    <div style="margin-top: 10px; padding-top: 6px; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                        <button id="hpc-mode-toggle-btn" style="background: none; border: none; color: #64748b; font-size: 11px; cursor: pointer; padding: 2px 4px; text-decoration: underline;">
                            🏫 Switch to School Submitter
                        </button>
                    </div>
                </div>
            `;
            return;
        }

        // ─── SCHOOL SUBMISSION MODE WIDGET (DEFAULT) ────────────────────────
        const total = studentsQueue.length;
        const progressPct = total > 0 ? Math.round((currentIndex / total) * 100) : 0;

        const isStudentListPage = location.pathname.includes('/students') && !location.pathname.includes('/students/add');

        widget.innerHTML = `
            ${tricolorBanner}
            <div style="padding: 14px 18px;">
                <div id="hpc-widget-drag-header" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; cursor: move; user-select: none; padding-bottom: 8px; border-bottom: 1px solid #e2e8f0;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 16px;">🚀</span>
                        <strong style="font-weight: 800; font-size: 13px; color: #0f172a; letter-spacing: -0.2px;">HPC Assist Submitter</strong>
                    </div>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <span style="font-size: 10px; background: ${isRunning ? '#dbeafe' : '#dcfce7'}; color: ${isRunning ? '#1d4ed8' : '#15803d'}; border: 1px solid ${isRunning ? '#bfdbfe' : '#bbf7d0'}; padding: 2px 8px; border-radius: 12px; font-weight: 700;">
                            ${isRunning ? '⚡ RUNNING' : 'READY'}
                        </span>
                        <button id="hpc-minimize-btn" title="Minimize Submitter" style="background: #f1f5f9; border: 1px solid #cbd5e1; color: #475569; width: 22px; height: 22px; border-radius: 6px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; padding: 0; line-height: 1;">
                            ─
                        </button>
                    </div>
                </div>

                ${renderActiveSchoolCard(total)}
                
                <div style="font-size: 12px; color: #64748b; margin-bottom: 12px; line-height: 1.4;">
                    ${total > 0 ? `<strong>${total}</strong> students queued for portal submission.` : 'No student queue found. Select school or upload spreadsheet.'}
                </div>

                ${total > 0 ? `
                    <div style="background: #e2e8f0; border-radius: 8px; height: 7px; overflow: hidden; margin-bottom: 6px;">
                        <div style="background: linear-gradient(90deg, #FF671F 0%, #2563eb 50%, #16a34a 100%); height: 100%; width: ${progressPct}%; transition: width 0.3s ease;"></div>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 11px; color: #475569; margin-bottom: 12px; font-weight: 500;">
                        <span id="hpc-widget-status">${lastLogMessage || (isRunning ? `Submitting ${currentIndex + 1} of ${total}...` : `${currentIndex}/${total} completed`)}</span>
                        <span style="font-weight: 700; color: #1e40af;">${progressPct}%</span>
                    </div>
                ` : ''}

                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    ${isRunning ? `
                        <button id="hpc-pause-btn" style="flex: 1; min-width: 100px; padding: 8px 12px; background: #dc2626; color: white; border: none; border-radius: 8px; font-weight: 700; font-size: 12px; cursor: pointer;">
                            ⏸ Pause
                        </button>
                    ` : `
                        <button id="hpc-start-btn" ${total === 0 ? 'disabled' : ''} style="flex: 1; min-width: 120px; padding: 8px 12px; background: linear-gradient(135deg, #1e40af, #2563eb); color: white; border: none; border-radius: 8px; font-weight: 700; font-size: 12px; cursor: pointer; box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.25); opacity: ${total === 0 ? '0.5' : '1'};">
                            ▶ ${currentIndex >= total ? 'Restart from #1' : (currentIndex > 0 ? 'Resume (' + (currentIndex + 1) + '/' + total + ')' : 'Start Auto-Submit')}
                        </button>
                    `}
                    <button id="hpc-refresh-btn" style="padding: 8px 12px; background: #f8fafc; color: #1e293b; border: 1px solid #cbd5e1; border-radius: 8px; font-weight: 600; font-size: 12px; cursor: pointer;" title="Refresh student queue from HPC Assist server">
                        🔄 Sync
                    </button>
                    ${currentIndex > 0 && !isRunning ? `
                        <button id="hpc-reset-btn" style="padding: 8px 10px; background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; border-radius: 8px; font-weight: 600; font-size: 11px; cursor: pointer;" title="Reset progress to student 1">
                            ⏮ Reset
                        </button>
                    ` : ''}
                </div>

                ${isStudentListPage ? `
                    <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #e2e8f0;">
                        <button id="hpc-fetch-portal-btn" style="width: 100%; padding: 9px 12px; background: linear-gradient(135deg, #FF671F, #ea580c); color: white; border: none; border-radius: 8px; font-weight: 700; font-size: 12px; cursor: pointer; box-shadow: 0 4px 6px -1px rgba(234, 88, 12, 0.25);" title="Scrape all students from this list and mark them as already submitted in HPC Assist">
                            📥 Fetch HPC List → Mark as Done
                        </button>
                        <div style="font-size: 10px; color: #64748b; margin-top: 5px; text-align: center;">Removes already-submitted students from your queue</div>
                    </div>
                ` : ''}

                <div style="margin-top: 12px; padding-top: 8px; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                    <button id="hpc-mode-toggle-btn" style="background: none; border: none; color: #64748b; font-size: 11px; cursor: pointer; padding: 2px 4px; text-decoration: underline;">
                        👩‍🏫 Switch to Teacher Evaluation Mode
                    </button>
                </div>
            </div>
        `;
    }

    /**
     * Scrapes the PARAKH HPC portal's student list page (/en/students) and
     * sends the results to the HPC Assist server so those students are marked
     * as already submitted (Step 3) and removed from the pending queue (Step 2).
     *
     * Handles multi-page tables by clicking "Next" until all rows are scraped.
     */
    async function fetchHpcPortalStudents() {
        if (!SERVER_URL) {
            alert('⚠️ HPC Assist server URL not configured. Please open your dashboard first.');
            return;
        }

        // Navigate to student list if not already there
        if (!location.pathname.includes('/students') || location.pathname.includes('/students/add')) {
            logStatus('Navigating to HPC portal student list...');
            window.location.href = 'https://hpc.parakh.ncert.gov.in/en/students';
            return;
        }

        logStatus('📥 Scanning portal student list...');
        const allStudents = [];
        let pageNum = 0;
        let keepGoing = true;

        while (keepGoing) {
            pageNum++;
            await sleep(1200); // wait for table to render

            // Try to find rows in any table on the page
            const rows = Array.from(document.querySelectorAll('table tbody tr, .student-list tr, [class*="table"] tr'));
            let foundRows = 0;

            for (const row of rows) {
                const cells = Array.from(row.querySelectorAll('td'));
                if (cells.length < 3) continue; // header or spacer row

                // Extract text from each cell
                const texts = cells.map(c => c.textContent.trim());

                // Try to identify columns heuristically
                // Common PARAKH portal layout: S.No | Name | Class | Section | DOB | Admission No | APAAR | Status
                let name = '', grade = '', section = 'A', dob = '', admNo = '', apaar = '', hpcId = '';

                // Look for a data-id or row id attribute
                hpcId = row.getAttribute('data-id') || row.getAttribute('data-student-id') || '';

                // Look for an edit/view link with an ID
                const idLink = row.querySelector('a[href*="/students/"][href*="/edit"], a[href*="/students/"][href*="/view"]');
                if (idLink) {
                    const idMatch = idLink.href.match(/\/students\/(\d+)/);
                    if (idMatch) hpcId = idMatch[1];
                }

                // Guess name (usually 2nd cell, first is serial number)
                for (let i = 0; i < texts.length; i++) {
                    const t = texts[i];
                    // Name: contains only letters and spaces, > 3 chars
                    if (!name && /^[A-Z][A-Z\s]{2,}$/.test(t.toUpperCase()) && t.length > 3 && !/^\d/.test(t)) {
                        const cleanLower = t.toLowerCase().trim();
                        if (['select', 'action', 'actions', 'edit', 'view', 'delete', 'sr no', 's.no', 'sr.no', 'status'].includes(cleanLower)) {
                            continue;
                        }
                        name = t;
                        continue;
                    }
                    // Grade: contains "grade" or roman numerals or "PP"
                    if (!grade && /grade|class|pp-|pp\d|grade\s*\d/i.test(t)) {
                        grade = t;
                        continue;
                    }
                    // DOB: date pattern
                    if (!dob && /\d{2}[\/-]\d{2}[\/-]\d{4}|\d{4}-\d{2}-\d{2}/.test(t)) {
                        dob = t;
                        continue;
                    }
                    // APAAR: 12 digits
                    if (!apaar && /^\d{12}$/.test(t.replace(/\s/g, ''))) {
                        apaar = t.replace(/\s/g, '');
                        continue;
                    }
                    // HPC Login ID: e.g. S3F44CB1E4
                    if (!hpcId && /^S[0-9A-Z]{6,16}$/i.test(t)) {
                        hpcId = t;
                        continue;
                    }
                    // Admission No: short alphanumeric (strictly exclude HPC Login ID pattern)
                    if (!admNo && /^[A-Z0-9]{2,12}$/i.test(t) && t !== apaar && !/^S[0-9A-Z]{6,16}$/i.test(t) && !/^(grade|class|pp)/i.test(t)) {
                        admNo = t;
                    }
                }

                if (!name) continue; // skip rows without a recognisable name

                const nameParts = name.trim().split(/\s+/);
                allStudents.push({
                    hpc_student_id: hpcId,
                    login_id:       hpcId,
                    name:          name.trim(),
                    first_name:    nameParts[0] || '',
                    last_name:     nameParts.slice(1).join(' ') || '',
                    grade:         grade,
                    section:       section,
                    dob:           dob,
                    apaar_id:      apaar,
                    admission_no:  admNo,
                });
                foundRows++;
            }

            logStatus(`📥 Page ${pageNum}: scraped ${foundRows} students (total ${allStudents.length})...`);

            // Try to navigate to next page
            const nextBtn = document.querySelector(
                'button[aria-label*="next" i], a[aria-label*="next" i], ' +
                '.pagination .next:not(.disabled) a, li.next:not(.disabled) a, ' +
                'button:not([disabled])[class*="next"]'
            );
            if (nextBtn && pageNum < 50) {
                nextBtn.click();
                await sleep(1500);
            } else {
                keepGoing = false;
            }
        }

        if (allStudents.length === 0) {
            logStatus('⚠️ No students found on this page. Make sure you are on the portal student list.');
            alert('⚠️ Could not find any student rows on this page.\n\nMake sure you are on:\nhttps://hpc.parakh.ncert.gov.in/en/students');
            return;
        }

        logStatus(`📤 Sending ${allStudents.length} portal students to HPC Assist...`);

        try {
            const res = await fetch(`${SERVER_URL}/hpc/students/sync-from-portal`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-HPC-Token': HPC_TOKEN,
                },
                body: JSON.stringify({
                    school_id: SCHOOL_ID,
                    students:  allStudents,
                }),
            });
            const data = await res.json();
            if (data.ok) {
                logStatus(`✅ Synced! ${data.new} new, ${data.matched} matched. ${data.step2_remaining} students still pending.`);
                alert(`✅ HPC Portal Sync Complete!\n\n• ${data.new} new students added to Step 3\n• ${data.matched} matched & marked as submitted\n• ${data.step2_remaining} students remaining in your queue\n\n${data.message}`);
                // Reload queue so extension reflects updated pending count
                await loadQueue(true);
                updateWidgetContent();
            } else {
                logStatus(`⚠️ Sync failed: ${data.error}`);
                alert(`⚠️ Sync failed: ${data.error}`);
            }
        } catch (err) {
            logStatus(`⚠️ Error syncing: ${err.message}`);
            console.error('[HPC Assist] fetchHpcPortalStudents error:', err);
            alert(`⚠️ Network error syncing with HPC Assist:\n${err.message}`);
        }
    }

    /**
     * Scrapes student rows from PARAKH portal stage table (e.g. /en/hpc/middle-stage)
     * and sends them to HPC Assist /hpc/teacher/sync-roster so that the teacher
     * template is populated exclusively with the teacher's locked live portal students.
     */
    async function fetchTeacherStudentsFromPortal() {
        if (!SERVER_URL) {
            alert('⚠️ HPC Assist server URL not configured. Please open your teacher dashboard on HPC Assist first to establish connection.');
            return;
        }

        const currPath = location.pathname.toLowerCase();
        if (!currPath.includes('-stage') && !currPath.includes('/hpc/')) {
            const goToStage = confirm('⚠️ To fetch students, you must be on an HPC Stage page (e.g. Middle Stage).\n\nWould you like to open Middle Stage now?');
            if (goToStage) {
                window.location.href = 'https://hpc.parakh.ncert.gov.in/en/hpc/middle-stage';
            }
            return;
        }

        logStatus('📥 Scanning PARAKH portal stage student roster...');
        const allStudents = [];
        let pageNum = 0;
        let keepGoing = true;

        // Determine current stage slug from URL (e.g. 'middle-stage')
        const stageSlug = location.pathname.split('/').filter(Boolean).pop() || 'middle-stage';

        while (keepGoing) {
            pageNum++;
            await sleep(1000); // wait for table to settle

            // Find rows in table tbody
            const rows = Array.from(document.querySelectorAll('table tbody tr, tbody tr, .table tr'));
            let pageFound = 0;

            // Dynamically inspect header columns if available
            const headerThs = Array.from(document.querySelectorAll('table thead th, table th')).map(th => th.textContent.trim().toLowerCase());
            let apaarIdx = headerThs.findIndex(t => t.includes('apaar'));
            let loginIdx = headerThs.findIndex(t => t.includes('login') || t.includes('pen') || t.includes('hpc id'));
            let nameIdx = headerThs.findIndex(t => t.includes('name'));
            let statusIdx = headerThs.findIndex(t => t.includes('status'));

            for (const row of rows) {
                const cells = Array.from(row.querySelectorAll('td'));
                if (cells.length < 2) continue; // skip header or empty row

                const texts = cells.map(c => c.textContent.trim());

                let apaarId = (apaarIdx !== -1 && texts[apaarIdx]) ? texts[apaarIdx] : '';
                let loginId = (loginIdx !== -1 && texts[loginIdx]) ? texts[loginIdx] : '';
                let name = (nameIdx !== -1 && texts[nameIdx]) ? texts[nameIdx] : '';
                let status = (statusIdx !== -1 && texts[statusIdx]) ? texts[statusIdx] : 'HPC In Progress';

                // Positional fallback for PARAKH stage table:
                // Column layout: [0] APAAR ID | [1] HPC Login ID | [2] Name | [3] Status | [4] Action
                if (!loginId || !name) {
                    if (texts.length >= 4) {
                        if (/^S[0-9A-Z]{6,16}$/i.test(texts[1])) {
                            apaarId = apaarId || texts[0];
                            loginId = loginId || texts[1];
                            name = name || texts[2];
                            status = texts[3] || status;
                        } else if (/^S[0-9A-Z]{6,16}$/i.test(texts[0])) {
                            loginId = loginId || texts[0];
                            name = name || texts[1];
                            status = texts[2] || status;
                        }
                    } else if (texts.length >= 3) {
                        loginId = loginId || texts[0];
                        name = name || texts[1];
                        status = texts[2] || status;
                    }
                }

                // Generic text heuristics if still not resolved
                if (!name || /^\d+$/.test(name)) {
                    for (let i = 0; i < texts.length; i++) {
                        const t = texts[i];
                        if (!name && /^[A-Z][A-Za-z\s.'-]{2,}$/.test(t) && t.length > 2 && !/^\d+$/.test(t) && !/^S[0-9A-Z]{6,}$/i.test(t)) {
                            const low = t.toLowerCase();
                            if (!['action', 'actions', 'open', 'edit', 'view', 'status', 's.no', 'sr.no', 'select', 'hpc pending', 'hpc completed'].includes(low)) {
                                name = t;
                            }
                        }
                    }
                }

                for (let i = 0; i < texts.length; i++) {
                    const cleanNum = texts[i].replace(/\s+/g, '');
                    if (/^\d{11,12}$/.test(cleanNum)) {
                        if (!apaarId && cleanNum.length === 12) apaarId = cleanNum;
                        if (!loginId) loginId = cleanNum;
                    } else if (/^S[0-9A-Z]{6,16}$/i.test(cleanNum) && !loginId) {
                        loginId = cleanNum;
                    }
                }

                if (!name && !loginId) continue;

                // Extract grade if mentioned or infer from stageSlug
                let grade = '';
                if (stageSlug.includes('middle')) grade = 'Grade 6';
                else if (stageSlug.includes('preparatory')) grade = 'Grade 4';
                else if (stageSlug.includes('foundational')) grade = 'Grade 1';
                else if (stageSlug.includes('secondary')) grade = 'Grade 9';

                allStudents.push({
                    row: allStudents.length + 1,
                    login_id: loginId || `HPC_${allStudents.length + 1}`,
                    name: name || `Student ${allStudents.length + 1}`,
                    apaar_id: apaarId,
                    status: status,
                    stage: stageSlug,
                    grade: grade,
                });
                pageFound++;
            }

            logStatus(`📥 Scraped page ${pageNum}: ${pageFound} students (total ${allStudents.length})...`);

            // Check for next pagination button
            const nextBtn = document.querySelector(
                'button[aria-label*="next" i], a[aria-label*="next" i], ' +
                '.pagination .next:not(.disabled) a, .pagination .next:not(.disabled) button, ' +
                'li.next:not(.disabled) a, li.next:not(.disabled) button, ' +
                '.page-item:not(.disabled) .page-link[aria-label*="Next"]'
            );
            if (nextBtn && pageNum < 30) {
                nextBtn.click();
                await sleep(1500);
            } else {
                keepGoing = false;
            }
        }

        if (allStudents.length === 0) {
            logStatus('⚠️ No student rows found on this page.');
            alert('⚠️ Could not find student rows on this stage page.\n\nPlease make sure the student table is visible on screen before clicking Fetch.');
            return;
        }

        logStatus(`📤 Syncing ${allStudents.length} students to HPC Assist...`);

        try {
            const res = await fetch(`${SERVER_URL}/hpc/teacher/sync-roster`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-HPC-Token': HPC_TOKEN,
                },
                credentials: 'include',
                body: JSON.stringify({
                    school_id: SCHOOL_ID,
                    udise_code: (availableSchools.find(s => s.id === SCHOOL_ID)?.udise_code) || (window.__HPC_CONFIG && window.__HPC_CONFIG.udise_code) || '',
                    stage: stageSlug,
                    students: allStudents,
                }),
            });
            const data = await res.json();
            if (data.ok) {
                logStatus(`✅ Synced ${data.count || allStudents.length} students from PARAKH portal!`);
                alert(`✅ PARAKH Portal Student Sync Complete!\n\n• ${allStudents.length} student(s) fetched from this stage page.\n• Saved directly to your Teacher Profile.\n\nNow return to your HPC Assist dashboard: Step 1 Excel template will be filled strictly with these students!`);
            } else {
                logStatus(`⚠️ Sync error: ${data.error || 'Unknown error'}`);
                alert('⚠️ Sync error: ' + (data.error || 'Server rejected sync'));
            }
        } catch (err) {
            logStatus(`✕ Network error during sync: ${err.message}`);
            alert('✕ Could not reach HPC Assist server: ' + err.message);
        }
    }

    async function startAutomation() {
        if (!studentsQueue.length) {
            await loadQueue(true);
            if (!studentsQueue.length) {
                alert('No student queue loaded. Please upload your student spreadsheet in HPC Assist first.');
                return;
            }
        }

        if (currentIndex >= studentsQueue.length) {
            currentIndex = 0;
            localStorage.setItem('hpc_student_index', '0');
        }

        isRunning = true;
        localStorage.setItem('hpc_submission_running', 'true');
        logStatus(`Starting automation (${currentIndex + 1}/${studentsQueue.length})...`);
        updateWidgetContent();

        if (!location.pathname.includes('/students/add')) {
            logStatus('Navigating to Add Student form...');
            window.location.href = 'https://hpc.parakh.ncert.gov.in/en/students/add';
            return;
        }

        await runAutomationLoop();
    }

    function pauseAutomation() {
        isRunning = false;
        localStorage.setItem('hpc_submission_running', 'false');
        logStatus('Paused.');
        updateWidgetContent();
    }

    /**
     * Main automation loop — replaces the previous recursive processCurrentStudent() tail call.
     * Using a while loop avoids stack overflow for large rosters (200+ students).
     */
    async function runAutomationLoop() {
        while (isRunning && currentIndex < studentsQueue.length) {
            const continueLoop = await processCurrentStudent();
            if (continueLoop === false) break; // processCurrentStudent returns false when it navigated away or paused
        }
    }

    function getGradeCandidates(gradeStr) {
        const raw = String(gradeStr || 'Grade 1').trim();
        const cleanNum = raw.replace(/\D/g, '');
        const isBalvatika = raw.toLowerCase().includes('balvatika') || raw.toLowerCase().includes('pp') || raw.toLowerCase().includes('pre');
        
        let candidates = [raw, raw.toLowerCase(), raw.toUpperCase()];
        if (isBalvatika) {
            const bNum = cleanNum || '1';
            candidates.push(
                `Balvatika ${bNum}`,
                `balvatika ${bNum}`,
                `Balvatika-${bNum}`,
                `PP-${bNum}`,
                `pp-${bNum}`,
                `Pre-Primary ${bNum}`,
                'Balvatika',
                'balvatika'
            );
        } else if (cleanNum) {
            const roman = ['', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X'][parseInt(cleanNum, 10)] || cleanNum;
            candidates.push(
                `Grade ${cleanNum}`,
                `grade ${cleanNum}`,
                `Class ${cleanNum}`,
                `class ${cleanNum}`,
                `Grade ${roman}`,
                `Class ${roman}`,
                roman,
                cleanNum
            );
        }
        return candidates;
    }

    function getSectionCandidates(sectionStr) {
        const sec = String(sectionStr || 'A').toUpperCase().trim().replace(/[^A-Z]/g, '') || 'A';
        return [
            `Section ${sec}`,
            `section ${sec}`,
            sec,
            sec.toLowerCase(),
            `SECTION ${sec}`,
            `Section - ${sec}`,
            `Section-${sec}`
        ];
    }

    async function fillApaarId(apaarStr) {
        const cleanApaar = String(apaarStr || '').replace(/\D/g, '');
        if (cleanApaar.length !== 12) {
            return;
        }

        const boxes = Array.from(document.querySelectorAll('.udise-codes input, input[name^="std_apaar_id"], input[maxlength="1"]')).slice(0, 12);
        if (boxes.length < 12) {
            console.warn('[HPC Assist] Could not find 12 APAAR input boxes');
            return;
        }

        logStatus(`Setting APAAR ID: ${cleanApaar}...`);

        // 1. Direct React Hook Form onPaste handler invocation on the first box (PARAKH module 5522)
        const box0 = boxes[0];
        const box0PropsKey = Object.keys(box0).find(k => k.startsWith('__reactProps'));
        if (box0PropsKey && typeof box0[box0PropsKey]?.onPaste === 'function') {
            try {
                box0[box0PropsKey].onPaste({
                    clipboardData: {
                        getData: () => cleanApaar
                    },
                    preventDefault: () => {},
                    stopPropagation: () => {}
                });
                await sleep(150);
            } catch(e) {}
        }

        // 2. Dispatch synthetic DOM ClipboardEvent paste
        try {
            const pasteDt = new DataTransfer();
            pasteDt.setData('text/plain', cleanApaar);
            pasteDt.setData('text', cleanApaar);
            const pasteEv = new ClipboardEvent('paste', {
                clipboardData: pasteDt,
                bubbles: true,
                cancelable: true
            });
            boxes[0].dispatchEvent(pasteEv);
            await sleep(150);
        } catch(e) {}

        // 3. Set each box individually to ensure React Hook Form state and DOM are synchronized
        for (let b = 0; b < 12; b++) {
            const box = boxes[b];
            const digit = cleanApaar[b];
            setNativeValue(box, digit);
            
            const propsKey = Object.keys(box).find(k => k.startsWith('__reactProps'));
            if (propsKey && typeof box[propsKey]?.onChange === 'function') {
                try {
                    box[propsKey].onChange({
                        target: box,
                        currentTarget: box,
                        preventDefault: () => {},
                        stopPropagation: () => {}
                    });
                } catch(e) {}
            }
            await sleep(30);
        }
    }

    function findAdmissionInput(knownFields = {}) {
        const exclude = [knownFields.firstName, knownFields.lastName, knownFields.email, knownFields.dob].filter(Boolean);

        // 1. Direct match from known fields
        if (knownFields.admissionNo && !exclude.includes(knownFields.admissionNo) && document.body.contains(knownFields.admissionNo)) {
            return knownFields.admissionNo;
        }

        // 2. Strict label matching (only label elements, never generic div or container)
        const labels = Array.from(document.querySelectorAll('label, .custom-input-label label, .custom-label'));
        const admLabel = labels.find(l => {
            const t = (l.innerText || l.textContent || '').toLowerCase().trim();
            return (t.includes('admission') || t.includes('admn') || t.includes('प्रवेश') || t.includes('दाखिला')) && !t.includes('date');
        });

        if (admLabel) {
            if (admLabel.htmlFor) {
                const el = document.getElementById(admLabel.htmlFor);
                if (el && !exclude.includes(el)) return el;
            }
            const wrapper = admLabel.closest('.custom-input-label, .form-group, .col, [class*="col-"]') || admLabel.parentElement;
            if (wrapper) {
                const inps = Array.from(wrapper.querySelectorAll('input:not([type="hidden"]):not([maxlength="1"])')).filter(i => !exclude.includes(i) && !i.disabled);
                if (inps.length > 0) return inps[0];
            }
            if (admLabel.nextElementSibling && admLabel.nextElementSibling.matches('input') && !exclude.includes(admLabel.nextElementSibling)) {
                return admLabel.nextElementSibling;
            }
        }

        // 3. Attribute matching (name, id, aria-label)
        const byAttr = Array.from(document.querySelectorAll('input:not([type="hidden"]):not([maxlength="1"])')).find(i => {
            if (exclude.includes(i) || i.disabled) return false;
            const str = `${i.name || ''} ${i.id || ''} ${i.getAttribute('placeholder') || ''} ${i.getAttribute('aria-label') || ''}`.toLowerCase();
            return str.includes('admission') || str.includes('admn');
        });
        if (byAttr) return byAttr;

        // 4. Positional fallback: 4th "Write here" placeholder input ONLY (First=0, Last=1, Email=2, Admission=3)
        const allWriteHere = Array.from(document.querySelectorAll(
            'input[placeholder*="Write here" i], input[placeholder*="write here" i], input[placeholder*="लिखें" i]'
        )).filter(i => !i.disabled && i.type !== 'hidden');

        if (allWriteHere.length >= 4 && !exclude.includes(allWriteHere[3])) {
            return allWriteHere[3];
        }

        return null;
    }

    async function fillAdmissionNumber(student, knownFields = {}) {
        const admInput = findAdmissionInput(knownFields);
        const admVal = String(
            student.admission_no ?? 
            student.admissionNo ?? 
            student.admnNumber ?? 
            ''
        ).trim().replace(/[^a-zA-Z0-9]/g, '').slice(0, 12);

        // INTEGRITY GUARD: School Admission Number must NEVER be the student's HPC Login ID or APAAR ID!
        if (/^S[0-9A-Z]{6,16}$/i.test(admVal) || admVal === (student.login_id || '') || admVal === (student.apaar_id || '')) {
            console.warn('[HPC Assist] Discarding invalid admission number matching HPC Login ID / APAAR ID:', admVal);
            return;
        }

        if (!admInput) {
            console.warn('[HPC Assist] Could not find School Admission Number input');
            return;
        }

        // ABSOLUTE INTEGRITY GUARD: Never write admission number into First Name or Email!
        if (knownFields.firstName && admInput === knownFields.firstName) {
            console.error('[HPC Assist] INTEGRITY GUARD ABORT: Refusing to write Admission Number into First Name input!');
            return;
        }
        if (knownFields.email && admInput === knownFields.email) {
            console.error('[HPC Assist] INTEGRITY GUARD ABORT: Refusing to write Admission Number into Email input!');
            return;
        }

        if (!admVal) {
            console.log('[HPC Assist] Student has no admission number/PEN. Leaving blank.');
            return;
        }

        logStatus(`Setting Admission No: ${admVal}...`);
        setNativeValue(admInput, admVal);
        await sleep(150);
    }

    async function processCurrentStudent() {
        if (!isRunning) return false;

        if (currentIndex >= studentsQueue.length) {
            isRunning = false;
            localStorage.setItem('hpc_submission_running', 'false');
            await reportProgress(studentsQueue.length, studentsQueue.length, '', 'completed', 'All students submitted successfully!', true);
            alert(`🎉 All ${studentsQueue.length} students have been successfully submitted to PARAKH HPC portal!`);
            updateWidgetContent();
            return false;
        }

        const student = studentsQueue[currentIndex];
        const stName = student.name || `${student.first_name || ''} ${student.last_name || ''}`.trim() || 'Student';
        logStatus(`[${currentIndex + 1}/${studentsQueue.length}] Filling ${stName}...`);

        // Wait up to 5 seconds for form fields to be visible
        let fields = findFormFields();
        let waitAttempts = 0;
        while ((!fields.firstName) && waitAttempts < 10) {
            await sleep(500);
            fields = findFormFields();
            waitAttempts++;
        }

        if (!fields.firstName) {
            logStatus('⚠️ Add Student form not ready. Retrying...');
            await sleep(1500);
            return true; // signal loop to call us again
        }

        try {
            // 1. First Name & Last Name
            const firstName = student.first_name || student.name?.split(' ')?.[0] || 'Student';
            const lastName  = student.last_name || (student.name?.split(' ')?.slice(1)?.join(' ')) || '';

            if (fields.firstName) {
                setNativeValue(fields.firstName, firstName);
                await sleep(150);
            }
            if (fields.lastName && lastName) {
                setNativeValue(fields.lastName, lastName);
                await sleep(150);
            }

            // 2. Date of Birth
            if (fields.dob && student.dob) {
                await fillDob(fields.dob, student.dob);
                await sleep(200);
            }

            // 3. Gender (MANDATORY FIELD)
            const genderOk = await fillGender(student);
            if (!genderOk) return false; // paused due to missing gender
            await sleep(200);

            // 4. Email
            if (fields.email && student.email && fields.email !== fields.firstName) {
                setNativeValue(fields.email, student.email);
                await sleep(150);
            }

            // 5. School Admission Number
            await fillAdmissionNumber(student, fields);

            // 6. Grade Dropdown
            const gradeCandidates = getGradeCandidates(student.grade);
            logStatus(`Setting Grade: ${student.grade || 'Grade 1'}...`);
            await selectDropdownOption('grade', 1, student.grade || 'Grade 1', gradeCandidates);
            await sleep(600); // Allow section options to update in React state

            // 7. Section Dropdown
            const secCandidates = getSectionCandidates(student.section);
            logStatus(`Setting Section: ${student.section || 'A'}...`);
            await selectDropdownOption('section', 2, student.section || 'A', secCandidates);
            await sleep(250);

            // 8. APAAR ID (12 character boxes)
            await fillApaarId(student.apaar_id);
            await sleep(200);

            // 8b. Re-verify School Admission Number in case dropdown re-renders wiped it
            const expectedAdm = String(student.admission_no ?? student.admissionNo ?? student.admnNumber ?? '').trim().replace(/[^a-zA-Z0-9]/g, '').slice(0, 12);
            if (expectedAdm) {
                const curAdmInput = findAdmissionInput(fields);
                if (curAdmInput && curAdmInput !== fields.firstName && curAdmInput.value !== expectedAdm) {
                    console.log('[HPC Assist] Re-applying Admission Number after dropdowns:', expectedAdm);
                    await fillAdmissionNumber(student, fields);
                }
            }

            // 9. Pre-Submission HTML5 Validation Check
            const form = document.querySelector('form');
            if (form && typeof form.checkValidity === 'function' && !form.checkValidity()) {
                console.error('[HPC Assist] Form checkValidity() failed for', stName);
                if (typeof form.reportValidity === 'function') form.reportValidity();
                logStatus(`❌ Form incomplete for ${stName}. Paused.`);
                pauseAutomation();
                alert(`⚠️ Some required fields for ${stName} are missing or invalid.\n\nPlease check the highlighted fields on the screen.`);
                return false;
            }

            // 10. Submit via Save / Save & Add Another
            await sleep(400);
            const isLast = (currentIndex >= studentsQueue.length - 1);
            let targetBtn = null;

            if (!isLast) {
                targetBtn = document.querySelector('button[name="action"][value="reset"]') ||
                            Array.from(document.querySelectorAll('button')).find(b => {
                                const txt = (b.innerText || b.value || '').toLowerCase().trim();
                                return txt.includes('save & add another') || txt.includes('save and add another');
                            });
            }

            if (!targetBtn) {
                targetBtn = document.querySelector('button[name="action"][value="back"]') ||
                            Array.from(document.querySelectorAll('button')).find(b => {
                                const txt = (b.innerText || b.value || '').toLowerCase().trim();
                                return (txt === 'save' || txt.includes('save')) && !txt.includes('cancel');
                            });
            }

            if (!targetBtn) {
                logStatus(`⚠️ Could not find Save button. Please click Save manually.`);
                pauseAutomation();
                return;
            }

            logStatus(`Submitting ${stName}...`);
            targetBtn.click();

            // 11. WAIT AND STRICTLY VERIFY: Did PARAKH accept the submission?
            let confirmedSuccess = false;
            let portalError = null;
            let isDuplicate = false;

            function checkDuplicateInElements() {
                const candidates = Array.from(document.querySelectorAll(
                    '.Toastify__toast, [role="alert"], .alert, .error, [class*="error" i], [class*="invalid" i], .text-danger, .invalid-feedback, #custom_modal, div.modal, [class*="toast" i]'
                )).filter(el => el.offsetParent !== null || (window.getComputedStyle(el).display !== 'none' && el.textContent.trim().length > 0));

                for (const el of candidates) {
                    const txt = (el.innerText || el.textContent || '').toLowerCase().trim();
                    if (
                        txt.includes('already exist') ||
                        txt.includes('already register') ||
                        txt.includes('already added') ||
                        txt.includes('already present') ||
                        txt.includes('already in use') ||
                        txt.includes('duplicate') ||
                        txt.includes('already taken') ||
                        txt.includes('pehley se') ||
                        txt.includes('pahle se')
                    ) {
                        return el.innerText.trim() || 'Student already exists on portal';
                    }
                }
                return null;
            }

            for (let poll = 0; poll < 25; poll++) {
                await sleep(400);

                // A. Check for duplicate error immediately
                const dupMsg = checkDuplicateInElements();
                if (dupMsg) {
                    console.log(`[HPC Assist] Duplicate detected for ${stName}:`, dupMsg);
                    isDuplicate = true;
                    portalError = dupMsg;
                    break;
                }

                // B. Check for custom success modal (#custom_modal or .modal.show or style*="block")
                const successModal = document.querySelector('#custom_modal, .modal.show, div.modal[style*="block"], div[role="dialog"]');
                if (successModal) {
                    const mTxt = (successModal.innerText || successModal.textContent || '').toLowerCase();
                    if (mTxt.includes('successfully') || mTxt.includes('success') || mTxt.includes('added') || mTxt.includes('updated')) {
                        console.log('[HPC Assist] Success modal appeared for', stName);
                        // Click confirmation button in modal-footer to dismiss modal and reset form
                        const okBtn = successModal.querySelector('.modal-footer button.custom-btn:not(.custom-btn-secondary), .modal-footer button, button.close-modal') ||
                                      Array.from(document.querySelectorAll('button')).find(b => {
                                          const t = b.textContent.trim().toLowerCase();
                                          return t === 'ok' || t === 'close' || t === 'done';
                                      });
                        if (okBtn) {
                            const btnPropsKey = Object.keys(okBtn).find(k => k.startsWith('__reactProps'));
                            if (btnPropsKey && typeof okBtn[btnPropsKey]?.onClick === 'function') {
                                try { okBtn[btnPropsKey].onClick({ stopPropagation: () => {}, preventDefault: () => {} }); } catch(e) {}
                            }
                            okBtn.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
                            okBtn.dispatchEvent(new MouseEvent('mouseup', { bubbles: true }));
                            okBtn.click();
                            await sleep(600);
                        }
                        confirmedSuccess = true;
                        break;
                    }
                }

                // C. Check for success toast (.Toastify__toast--success, .alert-success, etc.)
                const successToasts = Array.from(document.querySelectorAll(
                    '.Toastify__toast--success, .alert-success, div[class*="toast--success" i], div[class*="alert-success" i]'
                )).filter(el => el.offsetParent !== null || window.getComputedStyle(el).display !== 'none');
                if (successToasts.length > 0) {
                    console.log('[HPC Assist] Success toast notification appeared for', stName);
                    confirmedSuccess = true;
                    break;
                }

                // D. Check for URL redirect away from /students/add
                if (location.pathname.includes('/students') && !location.pathname.includes('/students/add')) {
                    console.log('[HPC Assist] Portal redirected away from /students/add - save confirmed!');
                    confirmedSuccess = true;
                    break;
                }

                // E. Check if form has cleared/reset (re-query freshly from DOM!)
                const freshFields = findFormFields();
                const freshFirstVal = (freshFields.firstName?.value || '').trim();
                if (poll >= 2 && (!freshFields.firstName || freshFirstVal === '' || (freshFirstVal !== firstName && freshFirstVal.length === 0))) {
                    console.log('[HPC Assist] Form cleared after submission - save confirmed!');
                    confirmedSuccess = true;
                    break;
                }

                // F. Check for error alerts (excluding duplicates handled above)
                const errorElements = Array.from(document.querySelectorAll(
                    '.Toastify__toast--error, [role="alert"], .error, [class*="error" i], [class*="invalid" i], .text-danger, .invalid-feedback, div[class*="tooltip" i]'
                )).filter(el => {
                    if (el.offsetParent === null && window.getComputedStyle(el).display === 'none') return false;
                    const txt = el.textContent.toLowerCase().trim();
                    return txt.includes('required') || txt.includes('mandatory') || txt.includes('invalid') || txt.includes('failed') || txt.includes('error');
                });

                if (errorElements.length > 0) {
                    portalError = errorElements.map(e => e.innerText.trim()).filter(Boolean).join('; ') || 'Form validation failed';
                    break;
                }
            }

            // DUPLICATE ENTRY HANDLING:
            // If the student already exists on PARAKH portal, mark as complete and advance!
            if (isDuplicate) {
                console.log(`[HPC Assist] ${stName} is already registered on PARAKH. Marking as complete and advancing...`);
                logStatus(`ℹ️ [${currentIndex + 1}/${studentsQueue.length}] ${stName} already exists on portal. Advancing...`);
                
                await reportProgress(currentIndex + 1, studentsQueue.length, stName, 'success', `${stName} (already existed on portal)`, false, {
                    student_id:   student.id,
                    admission_no: student.admission_no,
                    apaar_id:     student.apaar_id,
                    already_existed: true
                });

                currentIndex++;
                localStorage.setItem('hpc_student_index', String(currentIndex));
                updateWidgetContent();

                // If form is still holding the duplicate data, reset it or navigate back to /students/add
                if (location.pathname.includes('/students/add')) {
                    const resetBtn = document.querySelector('button[type="reset"], button.reset-btn') ||
                                    Array.from(document.querySelectorAll('button')).find(b => b.textContent.trim().toLowerCase() === 'reset');
                    if (resetBtn) {
                        resetBtn.click();
                        await sleep(500);
                    } else {
                        window.location.href = 'https://hpc.parakh.ncert.gov.in/en/students/add';
                        return false;
                    }
                } else {
                    window.location.href = 'https://hpc.parakh.ncert.gov.in/en/students/add';
                    return false;
                }

                await sleep(1000);
                return true; // continue next student!
            }

            // GENUINE ERROR HANDLING
            if (portalError) {
                console.error('[HPC Assist] Submission rejected by portal:', portalError);
                logStatus(`❌ ${stName} failed: ${portalError}`);
                pauseAutomation();
                alert(`⚠️ PARAKH Portal rejected submission for ${stName}:\n\n"${portalError}"\n\nPlease fix the highlighted field on the page.`);
                return false;
            }

            // POST-POLL VERIFICATION:
            // Re-query fresh fields one final time
            const freshFieldsFinal = findFormFields();
            const finalFirstVal = (freshFieldsFinal.firstName?.value || '').trim();

            if (!confirmedSuccess && !isLast) {
                // If still on /students/add and the form is strictly still filled with identical firstName:
                if (location.pathname.includes('/students/add') && finalFirstVal === firstName) {
                    // Check duplicate one more time in case it appeared late
                    const lateDup = checkDuplicateInElements();
                    if (lateDup) {
                        logStatus(`ℹ️ ${stName} already registered on portal.`);
                        await reportProgress(currentIndex + 1, studentsQueue.length, stName, 'success', `${stName} (already registered)`, false, {
                            student_id: student.id,
                            already_existed: true
                        });
                        currentIndex++;
                        localStorage.setItem('hpc_student_index', String(currentIndex));
                        updateWidgetContent();
                        window.location.href = 'https://hpc.parakh.ncert.gov.in/en/students/add';
                        return false;
                    }

                    console.warn('[HPC Assist] Form did not clear and no success modal appeared.');
                    logStatus(`⚠️ ${stName} submission unconfirmed.`);
                    pauseAutomation();
                    alert(`⚠️ The portal did not confirm saving ${stName}.\n\nPlease check the form and click Save manually.`);
                    return false;
                }
            }

            // Submission SUCCESS!
            logStatus(`✅ Saved [${currentIndex + 1}/${studentsQueue.length}] ${stName}!`);
            await reportProgress(currentIndex + 1, studentsQueue.length, stName, 'success', `Saved ${stName}`, false, {
                student_id:   student.id,
                admission_no: student.admission_no,
                apaar_id:     student.apaar_id,
            });

            currentIndex++;
            localStorage.setItem('hpc_student_index', String(currentIndex));
            updateWidgetContent();

            await sleep(1500);

            if (isRunning && currentIndex >= studentsQueue.length) {
                isRunning = false;
                localStorage.setItem('hpc_submission_running', 'false');
                logStatus(`🎉 All ${studentsQueue.length} students completed!`);
                updateWidgetContent();
                alert(`🎉 All ${studentsQueue.length} students have been successfully submitted to PARAKH HPC portal!`);
                return false;
            }

            if (isRunning && !location.pathname.includes('/students/add')) {
                window.location.href = 'https://hpc.parakh.ncert.gov.in/en/students/add';
                return false; // page navigation — loop ends here; init() will re-start it
            }

            return true; // loop continues

        } catch(err) {
            console.error('[HPC Assist] Error submitting student:', err);
            logStatus(`⚠️ Error on ${stName}: ${err.message}`);
            pauseAutomation();
            alert(`⚠️ Error submitting ${stName}: ${err.message}`);
            return false;
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // TEACHER EVALUATION 15-PART ASSESSMENT RUNNER & FORM FILLERS
    // ═════════════════════════════════════════════════════════════════════════

    function getStageUrlForGrade(grade) {
        const gStr = String(grade || '').toLowerCase();
        const gNum = parseInt(gStr.replace(/[^0-9]/g, ''), 10);
        if (gNum >= 1 && gNum <= 2) {
            return 'https://hpc.parakh.ncert.gov.in/en/hpc/foundational-stage';
        }
        if (gNum >= 3 && gNum <= 5) {
            return 'https://hpc.parakh.ncert.gov.in/en/hpc/preparatory-stage';
        }
        if (gNum >= 6 && gNum <= 8) {
            return 'https://hpc.parakh.ncert.gov.in/en/hpc/middle-stage';
        }
        if (gNum >= 9 && gNum <= 10) {
            return 'https://hpc.parakh.ncert.gov.in/en/hpc/secondary-stage';
        }
        return 'https://hpc.parakh.ncert.gov.in/en/hpc/middle-stage';
    }

    async function clickContinueBtn() {
        await sleep(600);
        const btns = Array.from(document.querySelectorAll('button'));
        const continueBtn = btns.find(b => {
            const t = (b.innerText || '').trim().toLowerCase();
            return t === 'continue' || t === 'next' || t.includes('save as draft');
        });
        if (continueBtn) {
            continueBtn.click();
            await sleep(2500);
            return true;
        }
        return false;
    }

    async function fillPartA1(s) {
        logStatus(`[Part-A1] Filling Basic Profile & Attendance for ${s.name}...`);
        // 1. Admission Number
        let admVal = String(s.admission_no || '').trim();
        if (/^S[0-9A-Z]{6,16}$/i.test(admVal) || admVal === (s.login_id || '') || admVal === (s.apaar_id || '')) {
            admVal = '';
        }
        const admContainer = Array.from(document.querySelectorAll('label')).find(l => l.innerText.includes('School Admission Number'))?.closest('div');
        const admInp = admContainer?.querySelector('input');
        if (admInp && admVal) setNativeValue(admInp, admVal);

        // 2. Mother tongue
        const mtContainer = Array.from(document.querySelectorAll('label')).find(l => l.innerText.includes('Mother Tongue'))?.closest('div');
        const mtInp = mtContainer?.querySelector('input');
        if (mtInp) setNativeValue(mtInp, s.mother_tongue || 'Kashmiri');

        // 3. Parent Name
        const pInp = document.querySelector('input[placeholder="Enter Father Name"], input[placeholder*="Father"]');
        if (pInp) setNativeValue(pInp, s.parent_name || 'Parent');

        // 4. Monthly Working Days (12 months: Apr - Mar)
        const monthKeys = ['apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec', 'jan', 'feb', 'mar'];
        const defaultWork = [20, 22, 10, 20, 22, 21, 19, 20, 15, 15, 20, 18];
        const defaultPres = [19, 21, 10, 18, 20, 20, 18, 17, 14, 13, 19, 17];

        const r1Inputs = Array.from(document.querySelectorAll('tbody tr:nth-child(1) td input'));
        monthKeys.forEach((mKey, i) => {
            const val = (s['work_' + mKey] !== undefined && s['work_' + mKey] !== '') ? s['work_' + mKey] : defaultWork[i];
            if (r1Inputs[i]) setNativeValue(r1Inputs[i], val);
        });

        // 5. Monthly Present Days (12 months: Apr - Mar)
        const r2Inputs = Array.from(document.querySelectorAll('tbody tr:nth-child(2) td input'));
        monthKeys.forEach((mKey, i) => {
            const val = (s['pres_' + mKey] !== undefined && s['pres_' + mKey] !== '') ? s['pres_' + mKey] : defaultPres[i];
            if (r2Inputs[i]) setNativeValue(r2Inputs[i], val);
        });

        // 6. Absent times count
        const absTimesInp = Array.from(document.querySelectorAll('label')).find(l => l.innerText.includes('absent'))?.closest('div')?.querySelector('input');
        if (absTimesInp) setNativeValue(absTimesInp, s.absent_count || '16');

        // 7. Absence frequency / reason
        const absInp = document.querySelector('input[placeholder="Write here"]:last-of-type, textarea');
        if (absInp) setNativeValue(absInp, s.absence_reason || 'Brief seasonal viral illness');

        // 8. Interests checkbox
        const interestTarget = (s.interest || 'reading').toLowerCase();
        const cbs = Array.from(document.querySelectorAll('input[type="checkbox"]'));
        const matchingCb = cbs.find(c => {
            const label = (c.closest('label')?.innerText || '').toLowerCase();
            return label.includes(interestTarget);
        }) || cbs[0];
        if (matchingCb && !matchingCb.checked) matchingCb.click();

        await sleep(800);
    }

    async function fillPartA2(s) {
        logStatus(`[Part-A2] Filling All About Me for ${s.name}...`);
        const orderedA2 = [
            s.live_with     || 'Family',
            s.stay_at       || 'Sugin, Budgam',
            s.free_time     || 'Reading books and drawing',
            s.do_well       || 'Learning new skills and actively participating',
            s.responsible   || 'most times',
            s.do_better     || s.could_improve || 'Time management in exams',
            s.care_others   || s.care_about || 'Helping peers with notes',
            s.feel_proud    || 'Solving challenging problems independently',
            s.friend_name   || s.student_name || 'Ayaan Ahmad',
            s.friend_age    || s.student_age || '12',
            s.acad_goal     || s.academic_goal || 'Score above 90% in Mathematics and Science',
            s.acad_goal_why || s.goal_importance || 'Builds solid foundation for pursuing higher studies in Engineering',
            s.acad_step1    || s.goal_step1 || 'Dedicate 45 minutes daily to numerical problem solving and formula revision',
            s.acad_step2    || s.goal_step2 || 'Clarify doubts with subject teachers immediately and practice weekly mock tests',
            s.pers_goal_why || 'Keeps mind alert, improves stamina, and enhances daily concentration',
            s.pers_step1    || 'Wake up at 6:00 AM every morning and do 30 minutes of yoga and jogging',
            s.pers_step2    || 'Read at least 15 pages of non-academic literature before bedtime',
            s.pers_goal     || s.personal_goal || 'Develop regular physical exercise and disciplined reading routine',
            s.sch_learn1    || s.learnt_school || 'Laws of reflection and refraction in science laboratory',
            s.sch_learn2    || 'Formulating and solving linear equations in single variable',
            s.sch_learn3    || 'Constitutional preamble and fundamental rights in Social Science',
            s.out_learn1    || s.learnt_outside || 'Organic kitchen gardening, composting, and soil preparation',
            s.out_learn2    || 'Bicycle maintenance, tire inflation, and chain lubrication',
            s.out_learn3    || 'Basic household budgeting and grocery calculation',
            s.help_with     || s.teacher_help || 'Hands-on science experiments and advanced mathematical puzzles',
            s.teacher_know  || 'I am enthusiastic about collaborative group projects and active discussions'
        ];

        const elements = Array.from(document.querySelectorAll('input:not([type="checkbox"]):not([type="radio"]), textarea, select'));
        orderedA2.forEach((val, idx) => {
            if (elements[idx + 1]) {
                setNativeValue(elements[idx + 1], val);
            } else if (elements[idx]) {
                setNativeValue(elements[idx], val);
            }
        });

        await sleep(800);
    }

    async function fillPartA3(s) {
        logStatus(`[Part-A3] Filling Ambition Card for ${s.name}...`);
        const orderedA3 = [
            s.ambition          || 'Doctor / Astrophysicist',
            s.achieve_how       || 'Conceptual understanding in Maths & Science and dedicated daily practice',
            s.skills_needed     || 'Critical Thinking, Problem Solving, Communication, Coding, Curiosity',
            s.focus_subjects    || 'Mathematics, Science, English, Computing',
            s.habits_i_will     || s.habits_build || s.i_will || 'Disciplined daily study, 30 min morning exercise, and curious questioning',
            s.mentor            || 'Bilal Ahmad Tali',
            s.mentor_help       || 'Recommending Olympiad reference books and laboratory guidance',
            s.learn_new         || 'Scientific problem solving and experimental techniques',
            s.feel_when_achieve || 'Proud, fulfilled, and motivated to help others',
            s.parents_feel      || 'Immensely joyful, honored, and proud'
        ];

        const elements = Array.from(document.querySelectorAll('input:not([type="checkbox"]):not([type="radio"]), textarea, select'));
        orderedA3.forEach((val, idx) => {
            if (elements[idx + 1]) {
                setNativeValue(elements[idx + 1], val);
            } else if (elements[idx]) {
                setNativeValue(elements[idx], val);
            }
        });

        await sleep(800);
    }

    async function fillPartA4(s) {
        logStatus(`[Part-A4] Filling Home Resources & Support for ${s.name}...`);
        const parentFeedback = s.parent_feedback || 'Provide quiet study space, supervise 1.5 hours of evening revision, monitor screen time, and discuss daily learnings';
        const ratings = [
            s.p_motivated || 'Yes',
            s.p_schedule || 'Yes',
            s.p_curriculum_diff || 'No',
            s.p_progress || 'Yes'
        ];

        const homeResMap = {
            'books':     String(s.res_books || '').toLowerCase() === 'yes',
            'news':      String(s.res_news || '').toLowerCase() === 'yes',
            'toy':       String(s.res_toys || '').toLowerCase() === 'yes',
            'phone':     String(s.res_phone || '').toLowerCase() === 'yes',
            'internet':  String(s.res_internet || '').toLowerCase() === 'yes',
            'radio':     String(s.res_radio || '').toLowerCase() === 'yes',
            'broadcast': String(s.res_broadcast || '').toLowerCase() === 'yes',
            't.v':       String(s.res_tv || '').toLowerCase() === 'yes',
            'cwd':       String(s.res_cwd || '').toLowerCase() === 'yes'
        };
        if (s.res_books === undefined && s.home_resources) {
            const hr = s.home_resources.toLowerCase();
            for (const k in homeResMap) homeResMap[k] = hr.includes(k);
        }

        const suppMap = {
            'language':   String(s.supp_lang || '').toLowerCase() === 'yes',
            'mathemat':   String(s.supp_math || '').toLowerCase() === 'yes',
            'science':    String(s.supp_sci || '').toLowerCase() === 'yes',
            'social':     String(s.supp_soc || '').toLowerCase() === 'yes',
            'self-belief':String(s.supp_self_belief || '').toLowerCase() === 'yes',
            'social skil':String(s.supp_social_skills || '').toLowerCase() === 'yes',
            'emotion':    String(s.supp_emotions || '').toLowerCase() === 'yes',
            'study skil': String(s.supp_study_skills || '').toLowerCase() === 'yes',
            'digital':    String(s.supp_skill_guidance || '').toLowerCase() === 'yes',
            'any other':  String(s.supp_other || '').toLowerCase() === 'yes'
        };
        if (s.supp_math === undefined && s.child_needs_support) {
            const cs = s.child_needs_support.toLowerCase();
            for (const k in suppMap) suppMap[k] = cs.includes(k.slice(0, 4));
        }

        // 1. Tick Home Resources Checkboxes
        document.querySelectorAll('.childrens-resources li').forEach(li => {
            const lbl = (li.querySelector('label')?.innerText || '').toLowerCase();
            const cb = li.querySelector('input[type="checkbox"]');
            if (cb && lbl) {
                let shouldCheck = false;
                for (const key in homeResMap) {
                    if (lbl.includes(key)) { shouldCheck = homeResMap[key]; break; }
                }
                if (shouldCheck !== cb.checked) {
                    cb.click();
                    cb.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }
        });

        // Other home resources text
        const otherHomeInput = document.querySelector('.children-specific-box input');
        if (otherHomeInput && s.res_other) setNativeValue(otherHomeInput, s.res_other);

        // 2. Select radio smileys for 'Understanding of my Child' (Q1 - Q4)
        const table = document.querySelector('.hpc-table-part-b table, table');
        if (table) {
            const rows = Array.from(table.querySelectorAll('tbody tr')).filter(r => r.querySelectorAll('.appropriate-option-select li').length > 0);
            rows.forEach((r, idx) => {
                const targetRating = (ratings[idx] || 'Yes').toLowerCase();
                const lis = Array.from(r.querySelectorAll('.appropriate-option-select li'));
                const targetLi = lis.find(li => (li.innerText || '').trim().toLowerCase() === targetRating)
                              || lis.find(li => (li.innerText || '').trim().toLowerCase() === 'yes')
                              || lis[0];
                if (targetLi) targetLi.click();
            });
        }

        // 3. Child Support Needs Checkboxes
        document.querySelectorAll('.need-children-support-check input[type="checkbox"]').forEach(cb => {
            const p = cb.closest('label')?.querySelector('p') || cb.closest('li')?.querySelector('p');
            const txt = (p?.innerText || '').toLowerCase();
            if (txt) {
                let shouldCheck = false;
                for (const key in suppMap) {
                    if (txt.includes(key)) { shouldCheck = suppMap[key]; break; }
                }
                if (shouldCheck !== cb.checked) {
                    cb.click();
                    cb.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }
        });

        // 4. Fill textareas
        document.querySelectorAll('textarea').forEach(ta => {
            setNativeValue(ta, parentFeedback);
        });

        await sleep(800);
    }

    async function fillPartB(s) {
        logStatus(`[Part-B] Filling Holistic Activities for ${s.name}...`);
        const actSubject = s.act_subject || 'Science';
        const actTitle = s.act_title || `${actSubject} Practical Project`;
        const actLevel = (s.act_level || 'proficient').toLowerCase();
        const actAwr = (s.act_rubric_awareness || actLevel).toLowerCase();
        const actSen = (s.act_rubric_sensitivity || actLevel).toLowerCase();
        const actCre = (s.act_rubric_creativity || actLevel).toLowerCase();
        const actDesc = s.act_desc || 'Constructed scale models of planetary orbits, computed relative ratios, and demonstrated to class';
        const actLearnt = s.act_learnt || 'Learned celestial orbital distances, 3D scale geometry, and public presentation skills';
        const actInteresting = s.act_interesting || 'Fabricating rotating planetary models using recycled materials and LED lighting';
        const actPractice = s.act_practice || 'Time management during hands-on model assembly and fine scale calibrations';
        const actPeerName = s.act_peer_name || 'Ayaan Ahmad';
        const actPeerFeedback = s.act_peer_feedback || 'Very supportive and collaborative teammate; explained difficult astronomical concepts clearly';
        const actSupportPlan = s.act_support_plan || 'Encourage participation in inter-school science competitions, provide advanced telescope observation sessions, and recommend for student mentorship roles';

        // 1. If activity cards already exist on student card, click matching button
        const actButtons = Array.from(document.querySelectorAll('.hpc-forms button')).filter(b => /^\d+\s*\n/.test(b.innerText.trim()));
        if (actButtons.length > 0) {
            const target = actButtons.find(b => b.innerText.toLowerCase().includes(actSubject.slice(0, 5).toLowerCase())) || actButtons[0];
            if (target) target.click();
            await sleep(500);
        }

        // 2. Click "Add Activity" if present
        const addBtn = Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('Add Activity'));
        if (addBtn) {
            addBtn.click();
            await sleep(600);
            const modal = document.querySelector('.quick-add-activity, .modal');
            if (modal) {
                const contBtn = Array.from(modal.querySelectorAll('button')).find(b => b.innerText.trim() === 'Continue');
                if (contBtn) contBtn.click();
                await sleep(800);
            }
        }

        // Tab 1: Activity Name
        const nameInp = document.querySelector('.acivity-name-updates input');
        if (nameInp) setNativeValue(nameInp, actTitle);
        document.querySelectorAll('input[name^="activity_"]').forEach((cb, i) => {
            if (i < 2 && !cb.checked) cb.click();
        });

        // Tab 2: Subject & Competency
        const tab2 = document.querySelectorAll('.activate-tab-pills div[role="button"]')[1];
        if (tab2) {
            tab2.click();
            await sleep(500);
        }
        const wrapper = document.querySelector('.scroller-cg-domain .custom-dropdown-wrapper');
        if (wrapper) wrapper.querySelector('.custom-dropdown-header')?.click();
        await sleep(400);

        const items = Array.from(document.querySelectorAll('.scroller-cg-domain .custom-dropdown-item, .custom-dropdown-item'));
        const targetDomain = items.find(el => el.innerText.trim().toLowerCase().startsWith(actSubject.slice(0, 5).toLowerCase()));
        if (targetDomain) targetDomain.click();
        await sleep(600);

        // Check first competency
        const cpts = document.querySelectorAll('.scroller-container input[name="cpt"]');
        if (cpts.length > 0 && !cpts[0].checked) {
            cpts[0].click();
            cpts[0].dispatchEvent(new Event('change', { bubbles: true }));
        }

        // React Fiber state update for instant complete activity payload
        const btn = document.querySelector('.hpc-finish-activity-btn');
        if (btn) {
            let f = btn[Object.keys(btn).find(k => k.startsWith('__reactFiber'))];
            while (f && f.type?.name !== 'S') f = f.return;
            let cur = f ? f.memoizedState : null;
            while (cur) {
                if (cur.memoizedState && typeof cur.memoizedState === 'object' && cur.memoizedState.cpt) {
                    const prev = cur.memoizedState;
                    const next = {
                        ...prev,
                        activity: prev.activity && prev.activity.length ? prev.activity : [1, 2],
                        any_other: ['', '', ''],
                        ast: [1, 2, 3],
                        bar: [9],
                        awr: ['Demonstrates good awareness', 'Demonstrates strong awareness', 'Demonstrates exceptional awareness', actAwr],
                        sen: ['Shows sensitivity', 'Shows great sensitivity', 'Shows excellent sensitivity', actSen],
                        cre: ['Displays creative thinking', 'Displays innovative creativity', 'Displays high creativity', actCre],
                        desc: [actDesc, 'Achieved all learning objectives successfully.'],
                        ml: [actLearnt, 'Developed practical confidence.', 'Collaborated actively.', actInteresting],
                        slr: ['Student actively engaged throughout.', actPractice, 'Completed assignments joyfully.'],
                        spt: [actSupportPlan, 'Continue encouragement.'],
                        tf: ['2', '2', '2'],
                        mpa: [0, 1],
                        mps: [0, 1],
                        mpc: [0, 1],
                        ppa: [0],
                        pps: [0],
                        ppc: [0],
                        pf: [actPeerName, '8', 'A', actPeerFeedback, 'Cooperative and helpful', 'Positive']
                    };
                    if (cur.queue && typeof cur.queue.dispatch === 'function') {
                        cur.queue.dispatch(next);
                    }
                    break;
                }
                cur = cur.next;
            }
        }

        await sleep(800);

        // Finish Activity
        const finishBtn = document.querySelector('.hpc-finish-activity-btn');
        if (finishBtn && !finishBtn.disabled) {
            finishBtn.click();
            await sleep(1000);
            const modal = document.querySelector('.modal, .fade.show, [role="dialog"]');
            if (modal) {
                const mFinishBtn = Array.from(modal.querySelectorAll('button')).find(b => b.innerText.trim() === 'Finish');
                if (mFinishBtn) mFinishBtn.click();
            }
        }

        await sleep(1000);
    }

    async function fillPartC(s) {
        logStatus(`[Part-C] Filling Curricular Progress Levels for ${s.name}...`);
        const radioGroups = {};
        document.querySelectorAll('input[type="radio"]').forEach(r => {
            const name = r.name || 'group';
            if (!radioGroups[name]) radioGroups[name] = [];
            radioGroups[name].push(r);
        });
        Object.values(radioGroups).forEach(group => {
            if (group.length > 0 && !group.some(r => r.checked)) {
                const prof = group.find(r => r.value === 'proficient' || r.value === '2' || r.value === 'yes') || group[0];
                prof.click();
                prof.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });

        await sleep(800);
    }

    async function fillHpcCredit(s) {
        logStatus(`[HPC-Credit] Filling Credit Marks and Submitting for ${s.name}...`);
        const marksList = [];
        const m = s.marks || {};
        const keys = ['l1', 'l2', 'l3', 'm', 'sci', 'soc', 'art', 'pe'];
        keys.forEach(k => {
            marksList.push(String((m[k] && m[k].max) || s[k + '_max'] || '50'));
            marksList.push(String((m[k] && m[k].obt) || s[k + '_obt'] || '42'));
        });

        const teacherRemarks = s.remarks || 'Consistent performer with excellent curiosity and strong analytical reasoning';

        const table = document.querySelectorAll('table')[1] || document.querySelector('table');
        if (table) {
            const marksInputs = Array.from(table.querySelectorAll('input[type="text"], input[type="number"]'));
            marksInputs.forEach((inp, i) => {
                const val = marksList[i] || (i % 2 === 0 ? '50' : '42');
                setNativeValue(inp, val);
            });
        }

        // Check declaration checkbox
        const decCb = document.querySelector('input[type="checkbox"]');
        if (decCb && !decCb.checked) {
            decCb.click();
            decCb.dispatchEvent(new Event('change', { bubbles: true }));
        }

        // Teacher Remarks
        const remTa = document.querySelector('textarea');
        if (remTa) setNativeValue(remTa, teacherRemarks);

        await sleep(800);

        // Click Mark as Complete
        const markCompleteBtn = Array.from(document.querySelectorAll('button')).find(b => b.innerText.trim() === 'Mark as Complete');
        if (markCompleteBtn && !markCompleteBtn.disabled) {
            markCompleteBtn.click();
            await sleep(1500);

            // Confirm modal
            const modal = document.querySelector('.modal, .swal2-popup, [role="dialog"], .fade.show');
            if (modal) {
                const cb = Array.from(modal.querySelectorAll('button')).find(b => /yes|confirm|proceed|ok|complete/i.test(b.innerText.trim()));
                if (cb) cb.click();
                await sleep(2000);
            }
        }
    }

    async function handleFormPartStep(s) {
        if (!s) {
            try {
                const raw = localStorage.getItem('hpc_teacher_active_student');
                if (raw) s = JSON.parse(raw);
            } catch(e) {}
        }
        if (!s) return false;

        const url = location.href.toLowerCase();

        if (url.includes('/part-a1') || (!url.includes('/part-') && document.querySelector('tbody tr:nth-child(1) td input'))) {
            await fillPartA1(s);
            await clickContinueBtn();
            return true;
        }
        if (url.includes('/part-a2')) {
            await fillPartA2(s);
            await clickContinueBtn();
            return true;
        }
        if (url.includes('/part-a3')) {
            await fillPartA3(s);
            await clickContinueBtn();
            return true;
        }
        if (url.includes('/part-a4')) {
            await fillPartA4(s);
            await clickContinueBtn();
            return true;
        }
        if (url.includes('/part-b')) {
            await fillPartB(s);
            await clickContinueBtn();
            return true;
        }
        if (url.includes('/part-c')) {
            await fillPartC(s);
            await clickContinueBtn();
            return true;
        }
        if (url.includes('/hpc-credit')) {
            await fillHpcCredit(s);
            logStatus(`🎉 Evaluated & submitted ${s.name} successfully!`);
            await reportTeacherProgress(teacherCurrentIndex + 1, teacherQueue.length, s.name, s.login_id, 'completed', 'HPC assessment and credit marks submitted successfully');

            // Advance index
            teacherCurrentIndex++;
            localStorage.setItem('hpc_teacher_index', String(teacherCurrentIndex));
            localStorage.removeItem('hpc_teacher_active_student');

            if (teacherCurrentIndex >= teacherQueue.length) {
                isTeacherRunning = false;
                localStorage.setItem('hpc_teacher_running', 'false');
                alert(`🎉 All ${teacherQueue.length} student evaluations completed successfully!`);
                updateWidgetContent();
                return false;
            }

            // Navigate back to stage list for next student
            const nextStudent = teacherQueue[teacherCurrentIndex];
            const stageUrl = getStageUrlForGrade(nextStudent.grade);
            logStatus(`Navigating to ${stageUrl} for next student: ${nextStudent.name}...`);
            await sleep(1500);
            window.location.href = stageUrl;
            return false;
        }

        return false;
    }

    async function processNextTeacherStudent() {
        if (!isTeacherRunning) return;
        if (teacherCurrentIndex >= teacherQueue.length) {
            isTeacherRunning = false;
            localStorage.setItem('hpc_teacher_running', 'false');
            logStatus('🎉 All student evaluations completed!');
            updateWidgetContent();
            return;
        }

        const s = teacherQueue[teacherCurrentIndex];
        logStatus(`[${teacherCurrentIndex + 1}/${teacherQueue.length}] Locating ${s.name} (${s.login_id})...`);

        // Ensure we are on the right stage page for this student
        const expectedStageUrl = getStageUrlForGrade(s.grade);
        const currPath = location.pathname.toLowerCase();
        const expectedSlug = expectedStageUrl.split('/').pop().toLowerCase();

        if (!currPath.includes(expectedSlug)) {
            logStatus(`Switching stage to ${expectedStageUrl}...`);
            await sleep(1000);
            window.location.href = expectedStageUrl;
            return;
        }

        // Wait up to 5 seconds for table to render
        let rows = [];
        for (let i = 0; i < 10; i++) {
            rows = Array.from(document.querySelectorAll('tbody tr'));
            if (rows.length > 0) break;
            await sleep(500);
        }

        if (rows.length === 0) {
            logStatus('⚠️ No student table rows found. Waiting...');
            await sleep(2000);
            return;
        }

        const loginId = (s.login_id || '').trim();
        const name = (s.name || '').trim().toLowerCase();

        const targetRow = rows.find(tr => {
            const cells = Array.from(tr.querySelectorAll('td'));
            const idCell = (cells[1] ? cells[1].innerText : '').trim();
            const trText = tr.innerText.toLowerCase();
            if (loginId && (idCell === loginId || trText.includes(loginId.toLowerCase()))) return true;
            if (name && trText.includes(name)) return true;
            return false;
        });

        if (!targetRow) {
            logStatus(`⚠️ Student ${s.name} (${loginId}) not found on current page table.`);
            const nextPgBtn = document.querySelector('.pagination .next, button[aria-label="Next"], .page-item:not(.disabled) .page-link[aria-label*="Next"]');
            if (nextPgBtn) {
                logStatus('Checking next table page...');
                nextPgBtn.click();
                await sleep(2000);
                processNextTeacherStudent();
                return;
            }

            logStatus(`⚠️ Could not locate ${s.name}. Skipping to next student...`);
            await reportTeacherProgress(teacherCurrentIndex + 1, teacherQueue.length, s.name, s.login_id, 'error', 'Student not found in stage table');
            teacherCurrentIndex++;
            localStorage.setItem('hpc_teacher_index', String(teacherCurrentIndex));
            updateWidgetContent();
            await sleep(2000);
            processNextTeacherStudent();
            return;
        }

        // Check status in table
        const cells = Array.from(targetRow.querySelectorAll('td'));
        const statusText = (cells[3] ? cells[3].innerText : targetRow.innerText).trim();
        if (statusText.toLowerCase().includes('completed')) {
            logStatus(`Student ${s.name} already Completed on portal. Skipping.`);
            await reportTeacherProgress(teacherCurrentIndex + 1, teacherQueue.length, s.name, s.login_id, 'skipped', 'Status is ' + statusText + ' on portal (left untouched)');
            teacherCurrentIndex++;
            localStorage.setItem('hpc_teacher_index', String(teacherCurrentIndex));
            updateWidgetContent();
            await sleep(1000);
            processNextTeacherStudent();
            return;
        }

        // Open Action Dropdown
        const dropdownHeader = targetRow.querySelector('.custom-dropdown-header');
        if (dropdownHeader) {
            dropdownHeader.click();
            await sleep(500);

            const items = Array.from(document.querySelectorAll('.custom-dropdown-item'));
            const actionItem = items.find(it => /open|edit/i.test(it.innerText.trim()));
            if (actionItem) {
                logStatus(`Opening evaluation form for ${s.name}...`);
                localStorage.setItem('hpc_teacher_active_student', JSON.stringify(s));
                actionItem.click();
                await sleep(3000);
                return;
            }
        }

        logStatus(`⚠️ Could not click Open/Edit for ${s.name}. Please open manually.`);
    }

    async function startTeacherEvaluation() {
        if (!teacherQueue.length) {
            await loadTeacherQueue(true);
            if (!teacherQueue.length) {
                alert('No student evaluations loaded. Please upload the filled Excel template in Step 4 on your dashboard.');
                return;
            }
        }

        if (teacherCurrentIndex >= teacherQueue.length) {
            teacherCurrentIndex = 0;
            localStorage.setItem('hpc_teacher_index', '0');
        }

        isTeacherRunning = true;
        localStorage.setItem('hpc_teacher_running', 'true');
        logStatus(`Starting teacher evaluation (${teacherCurrentIndex + 1}/${teacherQueue.length})...`);
        updateWidgetContent();

        const url = location.href.toLowerCase();
        if (url.includes('/part-') || url.includes('/hpc-credit')) {
            await handleFormPartStep();
        } else if (url.includes('-stage')) {
            await processNextTeacherStudent();
        } else {
            const nextStudent = teacherQueue[teacherCurrentIndex];
            const stageUrl = getStageUrlForGrade(nextStudent.grade);
            logStatus(`Navigating to ${stageUrl}...`);
            window.location.href = stageUrl;
        }
    }

    function pauseTeacherEvaluation() {
        isTeacherRunning = false;
        localStorage.setItem('hpc_teacher_running', 'false');
        logStatus('Teacher evaluation paused.');
        updateWidgetContent();
    }

    // ═════════════════════════════════════════════════════════════════════════
    // INITIALIZATION & DUAL-MODE AUTO-RESUME
    // ═════════════════════════════════════════════════════════════════════════

    async function init() {
        await autoDetectActiveSchool();
        CURRENT_MODE = detectCurrentMode();

        if (CURRENT_MODE === 'teacher') {
            await loadTeacherQueue();
            const savedTeacherIdx = localStorage.getItem('hpc_teacher_index');
            if (savedTeacherIdx) teacherCurrentIndex = parseInt(savedTeacherIdx, 10) || 0;
            isTeacherRunning = localStorage.getItem('hpc_teacher_running') === 'true';
        } else {
            await loadQueue();
            const savedIdx = localStorage.getItem('hpc_student_index');
            if (savedIdx) currentIndex = parseInt(savedIdx, 10) || 0;
        }

        injectWidget();

        // Mode-specific auto-resume
        if (CURRENT_MODE === 'teacher' && isTeacherRunning) {
            const url = location.href.toLowerCase();
            if (url.includes('/part-') || url.includes('/hpc-credit')) {
                await sleep(1500);
                await handleFormPartStep();
            } else if (url.includes('-stage')) {
                await sleep(1500);
                await processNextTeacherStudent();
            }
        } else if (CURRENT_MODE === 'school') {
            const wasRunning = localStorage.getItem('hpc_submission_running') === 'true';
            if (wasRunning && location.pathname.includes('/students/add') && currentIndex > 0) {
                const resume = confirm(
                    `HPC Assist was running and has processed ${currentIndex} student(s).\n` +
                    `Resume from student ${currentIndex + 1}?\n\n` +
                    `Click Cancel to reset the counter to Student 1.`
                );
                if (resume) {
                    isRunning = true;
                    updateWidgetContent();
                    await sleep(1500);
                    runAutomationLoop();
                } else {
                    currentIndex = 0;
                    localStorage.setItem('hpc_student_index', '0');
                    localStorage.setItem('hpc_submission_running', 'false');
                    updateWidgetContent();
                }
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
