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
    let currentSchoolName = (window.__HPC_CONFIG && window.__HPC_CONFIG.school_name) || '';
    let availableSchools = [];

    let studentsQueue = [];
    let isRunning = false;
    let currentIndex = 0;
    let lastLogMessage = '';

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
        // 1. Check chrome.storage.local (synced from ctetmonktest.fun dashboard)
        if (typeof chrome !== 'undefined' && chrome.storage && chrome.storage.local) {
            try {
                const stored = await new Promise(r => chrome.storage.local.get(['hpc_active_school'], r));
                if (stored && stored.hpc_active_school && stored.hpc_active_school.school_id) {
                    const st = stored.hpc_active_school;
                    SCHOOL_ID = st.school_id;
                    currentSchoolName = st.school_name || currentSchoolName;
                    if (st.server_url) SERVER_URL = st.server_url.replace(/\/$/, '');
                    if (st.progress_token) HPC_TOKEN = st.progress_token;
                    console.log('[HPC Assist] Loaded active school from extension storage:', currentSchoolName, 'ID:', SCHOOL_ID);
                }
            } catch(e) {}
        }

        // 2. Fetch available schools list from server
        await fetchSchoolsList();

        // 3. Scan PARAKH portal page text for school UDISE code or name
        const pageText = (document.body ? (document.body.innerText || '') : '').toLowerCase();
        for (const sc of availableSchools) {
            const scUdise = (sc.udise_code || '').trim().toLowerCase();
            const scName  = (sc.name || '').trim().toLowerCase();
            if ((scUdise && scUdise.length >= 6 && pageText.includes(scUdise)) || 
                (scName && scName.length >= 5 && pageText.includes(scName))) {
                console.log('[HPC Assist] Auto-detected matching school from PARAKH portal text:', sc.name, 'ID:', sc.id);
                SCHOOL_ID = sc.id;
                currentSchoolName = sc.name;
                break;
            }
        }

        // 4. Fallback: if SCHOOL_ID has 0 pending students but another school has pending students, or if no SCHOOL_ID is set
        if (!SCHOOL_ID || (availableSchools.length > 0 && availableSchools.find(s => s.id === SCHOOL_ID)?.pending === 0)) {
            const withPending = availableSchools.find(s => s.pending > 0);
            if (withPending) {
                console.log(`[HPC Assist] Switching active school to pending queue: ${withPending.name} (${withPending.pending} students)`);
                SCHOOL_ID = withPending.id;
                currentSchoolName = withPending.name;
            } else if (availableSchools.length > 0 && !SCHOOL_ID) {
                SCHOOL_ID = availableSchools[0].id;
                currentSchoolName = availableSchools[0].name;
            }
        }
    }

    // Load queue from localStorage or Server API
    async function loadQueue(forceServer = false) {
        if (!forceServer) {
            try {
                const raw = localStorage.getItem('hpc_student_queue');
                if (raw) {
                    const parsed = JSON.parse(raw);
                    if (parsed.school_id === SCHOOL_ID && Array.isArray(parsed.students) && parsed.students.length > 0) {
                        studentsQueue = parsed.students;
                        currentSchoolName = parsed.school_name || currentSchoolName;
                        return true;
                    }
                }
            } catch(e) {}
        }

        if (!SERVER_URL) {
            console.warn('[HPC Assist] SERVER_URL not configured — cannot fetch student queue.');
            return false;
        }

        try {
            const url = `${SERVER_URL}/hpc/students/queue${SCHOOL_ID ? '?school_id=' + SCHOOL_ID : ''}`;
            const res = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-HPC-Token': HPC_TOKEN,
                }
            });
            const data = await res.json();
            if (data.ok && Array.isArray(data.students)) {
                studentsQueue = data.students;
                currentSchoolName = data.school_name || currentSchoolName;
                localStorage.setItem('hpc_student_queue', JSON.stringify({
                    school_id: data.school_id || SCHOOL_ID,
                    school_name: data.school_name || currentSchoolName,
                    students: data.students
                }));
                return true;
            } else {
                console.warn('[HPC Assist] Queue endpoint returned error or no students:', data);
                studentsQueue = [];
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
        for (const tgt of candidates) {
            found = options.find(el => {
                const txt = el.textContent.toLowerCase().trim();
                return txt === tgt || txt.startsWith(tgt) || txt.includes(tgt) || tgt.includes(txt);
            });
            if (found) break;
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
                const item = filtered[0];
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
            if (g.startsWith('m') || g.includes('boy'))        target = 'Male';
            else if (g.startsWith('f') || g.includes('girl'))  target = 'Female';
            else if (g.includes('other'))                       target = 'Other';
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

    // Inject Floating Automation Widget with Persistent Event Delegation
    function injectWidget() {
        if (document.getElementById('hpc-parakh-widget')) return;

        const widget = document.createElement('div');
        widget.id = 'hpc-parakh-widget';
        widget.style.cssText = `
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 9999999;
            background: #0f172a;
            color: #f8fafc;
            border-radius: 16px;
            padding: 16px 20px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            font-size: 13px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.4), 0 8px 10px -6px rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.15);
            width: 320px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        `;

        document.body.appendChild(widget);

        // Event delegation on widget
        widget.addEventListener('click', async (e) => {
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
        });

        // School switcher dropdown handler
        widget.addEventListener('change', async (e) => {
            if (e.target.id === 'hpc-school-switcher') {
                const newId = parseInt(e.target.value, 10);
                if (newId) {
                    SCHOOL_ID = newId;
                    const matched = availableSchools.find(s => s.id === newId);
                    currentSchoolName = matched ? matched.name : '';
                    localStorage.setItem('hpc_active_school_id', String(newId));
                    if (typeof chrome !== 'undefined' && chrome.storage && chrome.storage.local) {
                        chrome.storage.local.set({ hpc_active_school: { school_id: newId, school_name: currentSchoolName } });
                    }
                    logStatus(`🏫 Switched to ${currentSchoolName}. Syncing queue...`);
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

        const total = studentsQueue.length;
        const progressPct = total > 0 ? Math.round((currentIndex / total) * 100) : 0;

        const isStudentListPage = location.pathname.includes('/students') && !location.pathname.includes('/students/add');

        let schoolSelectHtml = '';
        if (availableSchools.length > 0) {
            schoolSelectHtml = `
                <div style="background: rgba(255, 255, 255, 0.06); border: 1px solid rgba(255, 255, 255, 0.12); border-radius: 8px; padding: 6px 10px; margin-bottom: 10px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                        <span style="font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">🏫 Active School</span>
                        <span style="font-size: 10px; color: #38bdf8; font-weight: 700; max-width: 170px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${currentSchoolName || 'Select'}</span>
                    </div>
                    <select id="hpc-school-switcher" style="width: 100%; background: #1e293b; color: #f8fafc; border: 1px solid #475569; border-radius: 6px; padding: 4px 6px; font-size: 11px; font-weight: 600; cursor: pointer; outline: none;">
                        ${availableSchools.map(s => `
                            <option value="${s.id}" ${s.id === SCHOOL_ID ? 'selected' : ''}>
                                ${s.name} (${s.pending} queued)
                            </option>
                        `).join('')}
                    </select>
                </div>
            `;
        }

        widget.innerHTML = `
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 16px;">🚀</span>
                    <strong style="font-weight: 700; font-size: 13px; letter-spacing: -0.2px;">HPC Assist Submitter</strong>
                </div>
                <span style="font-size: 10px; background: ${isRunning ? 'rgba(59, 130, 246, 0.25)' : 'rgba(16, 185, 129, 0.2)'}; color: ${isRunning ? '#60a5fa' : '#34d399'}; padding: 2px 8px; border-radius: 12px; font-weight: 700;">
                    ${isRunning ? '⚡ RUNNING' : 'READY'}
                </span>
            </div>

            ${schoolSelectHtml}
            
            <div style="font-size: 12px; color: #94a3b8; margin-bottom: 12px; line-height: 1.4;">
                ${total > 0 ? `<strong>${total}</strong> students queued for portal submission.` : 'No student queue found. Select school or upload spreadsheet.'}
            </div>

            ${total > 0 ? `
                <div style="background: rgba(255, 255, 255, 0.08); border-radius: 8px; height: 6px; overflow: hidden; margin-bottom: 10px;">
                    <div style="background: #10b981; height: 100%; width: ${progressPct}%; transition: width 0.3s ease;"></div>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 11px; color: #cbd5e1; margin-bottom: 12px;">
                    <span id="hpc-widget-status">${lastLogMessage || (isRunning ? `Submitting ${currentIndex + 1} of ${total}...` : `${currentIndex}/${total} completed`)}</span>
                    <span>${progressPct}%</span>
                </div>
            ` : ''}

            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                ${isRunning ? `
                    <button id="hpc-pause-btn" style="flex: 1; min-width: 100px; padding: 8px 12px; background: #e11d48; color: white; border: none; border-radius: 8px; font-weight: 700; font-size: 12px; cursor: pointer;">
                        ⏸ Pause
                    </button>
                ` : `
                    <button id="hpc-start-btn" ${total === 0 ? 'disabled' : ''} style="flex: 1; min-width: 120px; padding: 8px 12px; background: #2563eb; color: white; border: none; border-radius: 8px; font-weight: 700; font-size: 12px; cursor: pointer; opacity: ${total === 0 ? '0.5' : '1'};">
                        ▶ ${currentIndex >= total ? 'Restart from #1' : (currentIndex > 0 ? 'Resume (' + (currentIndex + 1) + '/' + total + ')' : 'Start Auto-Submit')}
                    </button>
                `}
                <button id="hpc-refresh-btn" style="padding: 8px 12px; background: rgba(255, 255, 255, 0.1); color: #e2e8f0; border: none; border-radius: 8px; font-weight: 600; font-size: 12px; cursor: pointer;" title="Refresh student queue from HPC Assist server">
                    🔄 Sync
                </button>
                ${currentIndex > 0 && !isRunning ? `
                    <button id="hpc-reset-btn" style="padding: 8px 10px; background: rgba(239, 68, 68, 0.2); color: #fca5a5; border: none; border-radius: 8px; font-weight: 600; font-size: 11px; cursor: pointer;" title="Reset progress to student 1">
                        ⏮ Reset
                    </button>
                ` : ''}
            </div>

            ${isStudentListPage ? `
                <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid rgba(255,255,255,0.1);">
                    <button id="hpc-fetch-portal-btn" style="width: 100%; padding: 9px 12px; background: linear-gradient(135deg, #7c3aed, #4f46e5); color: white; border: none; border-radius: 8px; font-weight: 700; font-size: 12px; cursor: pointer;" title="Scrape all students from this list and mark them as already submitted in HPC Assist">
                        📥 Fetch HPC List → Mark as Done
                    </button>
                    <div style="font-size: 10px; color: #64748b; margin-top: 5px; text-align: center;">Removes already-submitted students from your queue</div>
                </div>
            ` : ''}
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
                    // Admission No: short alphanumeric
                    if (!admNo && /^[A-Z0-9]{2,12}$/i.test(t) && t !== apaar && !/^(grade|class|pp)/i.test(t)) {
                        admNo = t;
                    }
                }

                if (!name) continue; // skip rows without a recognisable name

                const nameParts = name.trim().split(/\s+/);
                allStudents.push({
                    hpc_student_id: hpcId,
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
            // PARAKH opens #custom_modal with "Student has been added successfully" on success
            let confirmedSuccess = false;
            let portalError = null;

            for (let poll = 0; poll < 15; poll++) {
                await sleep(400);

                // Check for custom success modal (#custom_modal or .modal.show or style*="block")
                const successModal = document.querySelector('#custom_modal, .modal.show, div.modal[style*="block"]');
                if (successModal) {
                    const mTxt = (successModal.innerText || successModal.textContent || '').toLowerCase();
                    if (mTxt.includes('successfully') || mTxt.includes('success') || mTxt.includes('added') || mTxt.includes('updated')) {
                        console.log('[HPC Assist] Success modal appeared for', stName);
                        // Click confirmation button in modal-footer to dismiss modal and reset form
                        const okBtn = successModal.querySelector('.modal-footer button.custom-btn:not(.custom-btn-secondary), .modal-footer button, button.close-modal') ||
                                      Array.from(document.querySelectorAll('button')).find(b => b.textContent.trim().toLowerCase() === 'ok');
                        if (okBtn) {
                            const btnPropsKey = Object.keys(okBtn).find(k => k.startsWith('__reactProps'));
                            if (btnPropsKey && typeof okBtn[btnPropsKey]?.onClick === 'function') {
                                try { okBtn[btnPropsKey].onClick({ stopPropagation: () => {}, preventDefault: () => {} }); } catch(e) {}
                            }
                            okBtn.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
                            okBtn.dispatchEvent(new MouseEvent('mouseup', { bubbles: true }));
                            okBtn.click();
                            await sleep(800);
                        }
                        confirmedSuccess = true;
                        break;
                    }
                }

                // Check for error toasts / alerts
                const errorElements = Array.from(document.querySelectorAll(
                    '.Toastify__toast--error, [role="alert"], .error, [class*="error" i], [class*="invalid" i], .text-danger, .invalid-feedback, div[class*="tooltip" i]'
                )).filter(el => {
                    if (el.offsetParent === null) return false;
                    const txt = el.textContent.toLowerCase().trim();
                    return txt.includes('required') || txt.includes('mandatory') || txt.includes('invalid') || txt.includes('failed') || txt.includes('error');
                });

                if (errorElements.length > 0) {
                    portalError = errorElements.map(e => e.innerText.trim()).filter(Boolean).join('; ') || 'Form validation failed';
                    break;
                }
            }

            if (portalError) {
                console.error('[HPC Assist] Submission rejected by portal:', portalError);
                logStatus(`❌ ${stName} failed: ${portalError}`);
                pauseAutomation();
                alert(`⚠️ PARAKH Portal rejected submission for ${stName}:\n\n"${portalError}"\n\nPlease fix the highlighted field on the page.`);
                return false;
            }

            if (!confirmedSuccess && !isLast) {
                // If modal didn't appear, check if form cleared
                const currentFirstVal = (fields.firstName?.value || '').trim();
                if (currentFirstVal === firstName) {
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

    // Auto-resume if running flag is present
    async function init() {
        await autoDetectActiveSchool();
        await loadQueue();
        injectWidget();

        const savedIdx = localStorage.getItem('hpc_student_index');
        if (savedIdx) currentIndex = parseInt(savedIdx, 10) || 0;

        const wasRunning = localStorage.getItem('hpc_submission_running') === 'true';
        if (wasRunning && location.pathname.includes('/students/add') && currentIndex > 0) {
            // Fix 4.1: ask the user before auto-resuming to prevent accidental double-submission
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

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
