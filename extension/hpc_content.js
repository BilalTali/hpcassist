// HPC Assist PARAKH Portal Student Auto-Submission Script
(function () {
    console.log('[HPC Assist] PARAKH HPC Automation script loaded on', location.href);

    const SERVER_URL = (window.__HPC_CONFIG && window.__HPC_CONFIG.server_url) ? window.__HPC_CONFIG.server_url : 'https://ctetmonktest.fun';
    const SCHOOL_ID  = (window.__HPC_CONFIG && window.__HPC_CONFIG.school_id) ? window.__HPC_CONFIG.school_id : null;

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

    // Load queue from localStorage or Server API
    async function loadQueue(forceServer = false) {
        if (!forceServer) {
            try {
                const raw = localStorage.getItem('hpc_student_queue');
                if (raw) {
                    const parsed = JSON.parse(raw);
                    if (Array.isArray(parsed.students) && parsed.students.length > 0) {
                        studentsQueue = parsed.students;
                        return;
                    }
                }
            } catch(e) {}
        }

        try {
            const url = `${SERVER_URL}/hpc/students/queue${SCHOOL_ID ? '?school_id=' + SCHOOL_ID : ''}`;
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            if (data.ok && Array.isArray(data.students)) {
                studentsQueue = data.students;
                localStorage.setItem('hpc_student_queue', JSON.stringify({
                    school_id: data.school_id,
                    school_name: data.school_name,
                    students: data.students
                }));
            }
        } catch(err) {
            console.warn('[HPC Assist] Could not fetch student queue from server:', err);
        }
    }

    async function reportProgress(current, total, name, status, message, done = false) {
        try {
            await fetch(`${SERVER_URL}/hpc/run/progress`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({
                    school_id: SCHOOL_ID,
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
        const lbl = labels.find(l => l.textContent.toLowerCase().includes(labelKeyword.toLowerCase()));
        if (!lbl) return null;
        const wrapper = lbl.closest('.custom-input-label') || lbl.parentElement;
        return wrapper ? wrapper.querySelector('input:not([type="hidden"]):not([maxlength="1"])') : null;
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
        const writeInputs = Array.from(document.querySelectorAll('input[placeholder*="Write here" i], input[placeholder*="write here" i]'));
        const allTextInputs = Array.from(document.querySelectorAll('input:not([type="hidden"]):not([type="button"]):not([type="submit"]):not([maxlength="1"])'))
            .filter(i => !i.disabled && (i.type === 'text' || i.type === 'email' || !i.type));

        const firstName = getFieldByLabel('first') || writeInputs[0] || allTextInputs[0] || null;
        const lastName  = getFieldByLabel('last') || writeInputs[1] || allTextInputs[1] || null;
        const email     = getFieldByLabel('email') || writeInputs[2] || (document.querySelector('input[type="email"]') || allTextInputs[2] || null);
        const admission = getFieldByLabel('admission') || writeInputs[3] || allTextInputs[3] || null;

        // Date of Birth input (React Datepicker wrapped in .custom-input-label)
        const dobLabel = Array.from(document.querySelectorAll('.custom-input-label label, label')).find(l => {
            const t = l.textContent.toLowerCase();
            return t.includes('birth') || t.includes('d-o-b') || t.includes('dob') || t.includes('जन्म');
        });
        let dob = dobLabel?.closest('.custom-input-label')?.querySelector('input') ||
                  document.querySelector('.react-datepicker__input-container input') ||
                  document.querySelector('input[placeholder*="Select" i], input[type="date"]');

        if (!dob || dob === firstName || dob === lastName) {
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
        let target = 'Female';
        if (student.gender) {
            const g = String(student.gender).toLowerCase().trim();
            if (g.startsWith('m') || g.includes('boy')) target = 'Male';
            else if (g.startsWith('f') || g.includes('girl')) target = 'Female';
            else if (g.includes('other')) target = 'Other';
        } else {
            const name = (student.name || `${student.first_name || ''} ${student.last_name || ''}`).toLowerCase();
            const femaleNames = ['jan', 'akhter', 'begum', 'bano', 'fatima', 'zehra', 'kousar', 'maryam', 'aabroo', 'aaliya', 'adeeba', 'hafsa', 'insha', 'rabia', 'toiba', 'zaira', 'afreen', 'asma', 'nimrah', 'jannat', 'misbah', 'safoora', 'tahira', 'zenab', 'shazia', 'ayaz', 'ayat', 'muntaha', 'abreena', 'bilkeesa', 'bisma', 'farhat', 'mehnaz', 'ronak', 'rubeena', 'rukhsana', 'saimah', 'simran', 'tasleema', 'towseer', 'uzma'];
            target = femaleNames.some(fn => name.includes(fn)) ? 'Female' : 'Male';
        }

        logStatus(`Setting Gender: ${target}...`);
        await selectDropdownOption('gender', 0, target, [
            target.toLowerCase(),
            target.toUpperCase(),
            target === 'Female' ? 'Girl' : (target === 'Male' ? 'Boy' : 'Other')
        ]);
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
                await loadQueue(true);
                currentIndex = 0;
                localStorage.setItem('hpc_student_index', '0');
                updateWidgetContent();
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
        });

        updateWidgetContent(widget);
    }

    function updateWidgetContent(widget) {
        if (!widget) widget = document.getElementById('hpc-parakh-widget');
        if (!widget) return;

        const total = studentsQueue.length;
        const progressPct = total > 0 ? Math.round((currentIndex / total) * 100) : 0;

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
            
            <div style="font-size: 12px; color: #94a3b8; margin-bottom: 12px; line-height: 1.4;">
                ${total > 0 ? `<strong>${total}</strong> students queued for portal submission.` : 'No student queue found. Upload spreadsheet in HPC Assist.'}
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
        `;
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

        await processCurrentStudent();
    }

    function pauseAutomation() {
        isRunning = false;
        localStorage.setItem('hpc_submission_running', 'false');
        logStatus('Paused.');
        updateWidgetContent();
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

    function findAdmissionInput() {
        // Priority 1: input with maxlength="12" (exclusive to school admission number on PARAKH student add form)
        const byMaxLen = Array.from(document.querySelectorAll('input[maxlength="12"]:not([type="hidden"])'))
            .find(i => !i.disabled && (i.type === 'text' || !i.type));
        if (byMaxLen) return byMaxLen;

        // Priority 2: input inside a .custom-input-label containing 'admission' or 'admn' or 'प्रवेश' or 'दाखिला'
        const wrappers = Array.from(document.querySelectorAll('.custom-input-label'));
        const admWrapper = wrappers.find(w => {
            const t = w.textContent.toLowerCase();
            return t.includes('admission') || t.includes('admn') || t.includes('प्रवेश') || t.includes('दाखिला');
        });
        if (admWrapper) {
            const inp = admWrapper.querySelector('input:not([type="hidden"]):not([maxlength="1"])');
            if (inp) return inp;
        }

        // Priority 3: by label
        const labels = Array.from(document.querySelectorAll('.custom-input-label label, label'));
        const admLabel = labels.find(l => {
            const t = l.textContent.toLowerCase();
            return t.includes('admission') || t.includes('admn') || t.includes('प्रवेश') || t.includes('दाखिला');
        });
        if (admLabel) {
            const wrapper = admLabel.closest('.custom-input-label') || admLabel.parentElement;
            const inp = wrapper?.querySelector('input:not([type="hidden"]):not([maxlength="1"])');
            if (inp) return inp;
        }

        // Priority 4: 4th "Write here" input (0: First, 1: Last, 2: Email, 3: Admission)
        const writeInputs = Array.from(document.querySelectorAll('input[placeholder*="Write here" i], input[placeholder*="write here" i], input[placeholder*="लिखें" i]'));
        if (writeInputs.length >= 4) return writeInputs[3];

        // Priority 5: 4th text input on page
        const textInputs = Array.from(document.querySelectorAll('input:not([type="hidden"]):not([type="button"]):not([type="submit"]):not([maxlength="1"])'))
            .filter(i => !i.disabled && (i.type === 'text' || !i.type));
        if (textInputs.length >= 4) return textInputs[3];

        return null;
    }

    async function fillAdmissionNumber(student) {
        const admInput = findAdmissionInput();
        const admVal = String(
            student.admission_no ?? 
            student.pen_code ?? 
            student.admissionNo ?? 
            student.admnNumber ?? 
            student.pen ?? 
            student.roll_no ?? 
            student.rollNo ?? 
            ''
        ).trim().replace(/[^a-zA-Z0-9]/g, '').slice(0, 12);

        if (!admInput) {
            console.warn('[HPC Assist] Could not find School Admission Number input');
            return;
        }

        if (!admVal) {
            console.log('[HPC Assist] Student has no admission number/PEN. Leaving blank.');
            return;
        }

        logStatus(`Setting Admission No: ${admVal}...`);
        admInput.focus();

        // 1. Set DOM value via HTMLInputElement prototype setter
        const proto = Object.getPrototypeOf(admInput);
        const nativeSetter = Object.getOwnPropertyDescriptor(proto, 'value')?.set ||
                             Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value')?.set;
        if (nativeSetter) {
            nativeSetter.call(admInput, admVal);
        } else {
            admInput.value = admVal;
        }

        // 2. Clear React internal _valueTracker so React recognizes value change
        if (admInput._valueTracker) {
            admInput._valueTracker.setValue('');
        }

        // 3. Dispatch standard input & change events
        admInput.dispatchEvent(new Event('input', { bubbles: true }));
        admInput.dispatchEvent(new Event('change', { bubbles: true }));

        // 4. Directly invoke React's internal onChange handler if present on fiber/props
        const propsKey = Object.keys(admInput).find(k => k.startsWith('__reactProps') || k.startsWith('__reactEventHandlers'));
        if (propsKey && typeof admInput[propsKey]?.onChange === 'function') {
            try {
                admInput[propsKey].onChange({
                    target: { value: admVal },
                    currentTarget: { value: admVal },
                    preventDefault: () => {},
                    stopPropagation: () => {}
                });
            } catch(e) {
                try {
                    admInput[propsKey].onChange({
                        target: admInput,
                        currentTarget: admInput,
                        preventDefault: () => {},
                        stopPropagation: () => {}
                    });
                } catch(e2) {}
            }
        }

        // 5. Blur to finalize
        admInput.dispatchEvent(new Event('blur', { bubbles: true }));
        await sleep(150);
    }

    async function processCurrentStudent() {
        if (!isRunning) return;

        if (currentIndex >= studentsQueue.length) {
            isRunning = false;
            localStorage.setItem('hpc_submission_running', 'false');
            await reportProgress(studentsQueue.length, studentsQueue.length, '', 'completed', 'All students submitted successfully!', true);
            alert(`🎉 All ${studentsQueue.length} students have been successfully submitted to PARAKH HPC portal!`);
            updateWidgetContent();
            return;
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
            if (isRunning) processCurrentStudent();
            return;
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
            await fillGender(student);
            await sleep(200);

            // 4. Email
            if (fields.email && student.email && fields.email !== fields.firstName) {
                setNativeValue(fields.email, student.email);
                await sleep(150);
            }

            // 5. School Admission Number
            await fillAdmissionNumber(student);

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
            const expectedAdm = String(student.admission_no ?? student.pen_code ?? student.admissionNo ?? student.admnNumber ?? '').trim().replace(/[^a-zA-Z0-9]/g, '').slice(0, 12);
            if (expectedAdm) {
                const curAdmInput = findAdmissionInput();
                if (curAdmInput && curAdmInput.value !== expectedAdm) {
                    console.log('[HPC Assist] Re-applying Admission Number after dropdowns:', expectedAdm);
                    await fillAdmissionNumber(student);
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
                return;
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
                return;
            }

            if (!confirmedSuccess && !isLast) {
                // If modal didn't appear, check if form cleared
                const currentFirstVal = (fields.firstName?.value || '').trim();
                if (currentFirstVal === firstName) {
                    console.warn('[HPC Assist] Form did not clear and no success modal appeared.');
                    logStatus(`⚠️ ${stName} submission unconfirmed.`);
                    pauseAutomation();
                    alert(`⚠️ The portal did not confirm saving ${stName}.\n\nPlease check the form and click Save manually.`);
                    return;
                }
            }

            // Submission SUCCESS!
            logStatus(`✅ Saved [${currentIndex + 1}/${studentsQueue.length}] ${stName}!`);
            await reportProgress(currentIndex + 1, studentsQueue.length, stName, 'success', `Saved ${stName}`);

            currentIndex++;
            localStorage.setItem('hpc_student_index', String(currentIndex));
            updateWidgetContent();

            await sleep(1500);

            if (isRunning) {
                if (currentIndex >= studentsQueue.length) {
                    isRunning = false;
                    localStorage.setItem('hpc_submission_running', 'false');
                    logStatus(`🎉 All ${studentsQueue.length} students completed!`);
                    updateWidgetContent();
                    alert(`🎉 All ${studentsQueue.length} students have been successfully submitted to PARAKH HPC portal!`);
                    return;
                }

                if (!location.pathname.includes('/students/add')) {
                    window.location.href = 'https://hpc.parakh.ncert.gov.in/en/students/add';
                    return;
                }

                await processCurrentStudent();
            }

        } catch(err) {
            console.error('[HPC Assist] Error submitting student:', err);
            logStatus(`⚠️ Error on ${stName}: ${err.message}`);
            pauseAutomation();
            alert(`⚠️ Error submitting ${stName}: ${err.message}`);
        }
    }

    // Auto-resume if running flag is present
    async function init() {
        await loadQueue();
        injectWidget();

        const savedIdx = localStorage.getItem('hpc_student_index');
        if (savedIdx) currentIndex = parseInt(savedIdx, 10) || 0;

        const wasRunning = localStorage.getItem('hpc_submission_running') === 'true';
        if (wasRunning && location.pathname.includes('/students/add')) {
            isRunning = true;
            updateWidgetContent();
            await sleep(1500);
            processCurrentStudent();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
