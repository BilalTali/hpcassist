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

    // Precision scoped field finder by label text
    function findFieldByLabel(labelMatch) {
        const norm = labelMatch.toLowerCase().trim();

        // 1. Direct name/id attribute matching
        const cleanKey = norm.replace(/[^a-z0-9]/g, '');
        let el = document.querySelector(`[name*="${cleanKey}" i], [id*="${cleanKey}" i]`);
        if (el && !el.disabled && el.type !== 'hidden') return el;

        // 2. Find label elements whose text closely matches
        const candidateLabels = Array.from(document.querySelectorAll('label, div, span, p, h6, h5')).filter(l => {
            if (l.children.length > 2) return false;
            const txt = l.textContent.trim().toLowerCase();
            return (txt.startsWith(norm) || (txt.includes(norm) && txt.length <= norm.length + 15));
        });

        for (const lbl of candidateLabels) {
            // Check 'for' attribute
            const forId = lbl.getAttribute('for') || lbl.getAttribute('htmlFor');
            if (forId) {
                const target = document.getElementById(forId);
                if (target) return target;
            }

            // Check siblings
            let sib = lbl.nextElementSibling;
            while (sib) {
                if (sib.matches('input:not([type="hidden"]), select, [role="combobox"], button')) {
                    return sib;
                }
                const inner = sib.querySelector('input:not([type="hidden"]), select, [role="combobox"], .custom-dropdown-header, button');
                if (inner) return inner;
                sib = sib.nextElementSibling;
            }

            // Check immediate parent column wrapper (<div class="col...">)
            let parent = lbl.parentElement;
            for (let d = 0; d < 3 && parent; d++) {
                if (parent.tagName === 'FORM' || parent.id === 'root') break;
                const controls = Array.from(parent.querySelectorAll('input:not([type="hidden"]), select, [role="combobox"], .custom-dropdown-header'));
                if (controls.length === 1) {
                    return controls[0];
                }
                if (controls.length > 1 && controls.length <= 3) {
                    const found = controls.find(c => c !== lbl && !lbl.contains(c));
                    if (found) return found;
                }
                parent = parent.parentElement;
            }
        }
        return null;
    }

    // Precision form field detector
    function findFormFields() {
        const fields = {
            firstName: null,
            lastName: null,
            dob: null,
            genderTrigger: null,
            email: null,
            admissionNo: null,
            gradeTrigger: null,
            sectionTrigger: null,
            apaarBoxes: []
        };

        fields.firstName     = findFieldByLabel('First Name');
        fields.lastName      = findFieldByLabel('Last Name');
        fields.dob           = findFieldByLabel('Date of Birth') || findFieldByLabel('DOB');
        fields.genderTrigger = findFieldByLabel('Gender');
        fields.email         = findFieldByLabel('Email');
        fields.admissionNo   = findFieldByLabel('School Admission Number') || findFieldByLabel('Admission');
        fields.gradeTrigger  = findFieldByLabel('Grade');
        fields.sectionTrigger= findFieldByLabel('Section');

        // Positional fallbacks based on exact PARAKH layout
        const allInputs = Array.from(document.querySelectorAll('input:not([type="hidden"]):not([type="button"]):not([type="submit"])'));
        const textInputs = allInputs.filter(inp => inp.getAttribute('maxlength') !== '1' && !inp.disabled);

        if (!fields.firstName && textInputs.length >= 1) fields.firstName = textInputs[0];
        if (!fields.lastName && textInputs.length >= 2) fields.lastName = textInputs[1];

        // Ensure Email is NEVER the same element as First Name or Last Name
        if (!fields.email || fields.email === fields.firstName || fields.email === fields.lastName) {
            fields.email = document.querySelector('input[type="email"]') ||
                           textInputs.find(inp => inp !== fields.firstName && inp !== fields.lastName);
        }

        // DOB fallback
        if (!fields.dob) {
            fields.dob = allInputs.find(inp => {
                const ph = (inp.getAttribute('placeholder') || '').toLowerCase();
                return ph.includes('select') || ph.includes('dd/mm') || ph.includes('date');
            });
        }

        // Dropdowns fallback (Gender, Grade, Section)
        const dropdowns = Array.from(document.querySelectorAll('.custom-dropdown-header, [role="combobox"], select, div[class*="dropdown-toggle"], button[class*="dropdown"]'))
            .filter(dd => dd.offsetParent !== null);

        if (!fields.genderTrigger && dropdowns.length >= 1) fields.genderTrigger = dropdowns[0];
        if (!fields.gradeTrigger && dropdowns.length >= 2) fields.gradeTrigger = dropdowns[1];
        if (!fields.sectionTrigger && dropdowns.length >= 3) fields.sectionTrigger = dropdowns[2];

        // APAAR ID (12 single-character input boxes)
        fields.apaarBoxes = allInputs.filter(inp => inp.getAttribute('maxlength') === '1');

        return fields;
    }

    async function selectDropdownOption(trigger, targetValue, fallbacks = []) {
        if (!trigger) return false;

        // Native select
        if (trigger.tagName === 'SELECT') {
            const opt = Array.from(trigger.options).find(o => {
                const txt = o.text.toLowerCase().trim();
                const val = o.value.toLowerCase().trim();
                const tgt = targetValue.toLowerCase().trim();
                return txt === tgt || txt.includes(tgt) || val === tgt;
            });
            if (opt) {
                trigger.value = opt.value;
                trigger.dispatchEvent(new Event('change', { bubbles: true }));
                return true;
            }
        }

        // Custom Dropdown: Click trigger
        trigger.click();
        await sleep(350);

        const candidates = [targetValue, ...fallbacks].map(s => String(s).toLowerCase().trim());
        const options = Array.from(document.querySelectorAll(
            '.custom-dropdown-item, [role="option"], li, div[class*="option" i], div[class*="item" i], span[class*="option" i], p[class*="option" i], button[class*="option" i]'
        )).filter(el => el.offsetParent !== null);

        for (const tgt of candidates) {
            const found = options.find(el => {
                const txt = el.textContent.toLowerCase().trim();
                return txt === tgt || txt.startsWith(tgt) || txt.includes(tgt);
            });
            if (found) {
                found.click();
                await sleep(250);
                return true;
            }
        }

        // Click outside if not matched to close
        document.body.click();
        await sleep(150);
        return false;
    }

    async function fillGender(trigger, student) {
        if (!trigger) {
            console.warn('[HPC Assist] Gender trigger not found');
            return;
        }

        let target = 'Female';
        if (student.gender) {
            const g = String(student.gender).toLowerCase().trim();
            if (g.startsWith('m') || g.includes('boy')) target = 'Male';
            else if (g.startsWith('f') || g.includes('girl')) target = 'Female';
        } else {
            const name = (student.name || `${student.first_name || ''} ${student.last_name || ''}`).toLowerCase();
            const femaleNames = ['jan', 'akhter', 'begum', 'bano', 'fatima', 'zehra', 'kousar', 'maryam', 'aabroo', 'aaliya', 'adeeba', 'hafsa', 'insha', 'rabia', 'toiba', 'zaira', 'afreen', 'asma', 'nimrah', 'jannat', 'misbah', 'safoora', 'tahira', 'zenab', 'shazia', 'ayaz', 'ayat', 'muntaha', 'abreena', 'bilkeesa', 'bisma', 'farhat', 'mehnaz', 'ronak', 'rubeena', 'rukhsana', 'saimah', 'simran', 'tasleema', 'towseer', 'uzma'];
            target = femaleNames.some(fn => name.includes(fn)) ? 'Female' : 'Male';
        }

        logStatus(`Setting Gender: ${target}...`);
        await selectDropdownOption(trigger, target, [target.toLowerCase(), target === 'Female' ? 'Girl' : 'Boy']);
    }

    async function fillDob(dobField, dobStr) {
        if (!dobField || !dobStr) return;
        const dobObj = parseDate(dobStr);
        if (!dobObj) return;

        logStatus(`Setting DOB: ${dobObj.formatted}...`);
        
        dobField.removeAttribute('readonly');
        setNativeValue(dobField, dobObj.formatted);
        await sleep(150);

        // Open calendar
        dobField.click();
        await sleep(350);

        const yr = document.querySelector('.react-datepicker__year-select, select[class*="year" i]');
        if (yr) {
            yr.value = dobObj.year;
            yr.dispatchEvent(new Event('change', { bubbles: true }));
            await sleep(150);
        }

        const mo = document.querySelector('.react-datepicker__month-select, select[class*="month" i]');
        if (mo) {
            mo.value = String(dobObj.month);
            mo.dispatchEvent(new Event('change', { bubbles: true }));
            await sleep(150);
        }

        const days = Array.from(document.querySelectorAll('.react-datepicker__day:not(.react-datepicker__day--outside-month), [class*="day"]:not([class*="outside"])'));
        const dayEl = days.find(d => d.textContent.trim() === String(dobObj.day));
        if (dayEl) {
            dayEl.click();
            await sleep(250);
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
                await sleep(150);
            }

            // 3. Gender (MANDATORY FIELD)
            if (fields.genderTrigger) {
                await fillGender(fields.genderTrigger, student);
                await sleep(150);
            }

            // 4. Email
            if (fields.email && student.email && fields.email !== fields.firstName) {
                setNativeValue(fields.email, student.email);
                await sleep(150);
            }

            // 5. Grade Dropdown
            const rawGrade = String(student.grade || 'Grade 1').toLowerCase().trim();
            if (fields.gradeTrigger) {
                logStatus(`Setting Grade: ${student.grade || 'Grade 1'}...`);
                await selectDropdownOption(fields.gradeTrigger, rawGrade, [rawGrade, 'Grade 1', 'Balvatika 1']);
                await sleep(150);
            }

            // 6. Section Dropdown
            const rawSection = String(student.section || 'A').toUpperCase().trim();
            if (fields.sectionTrigger) {
                logStatus(`Setting Section: ${rawSection}...`);
                await selectDropdownOption(fields.sectionTrigger, rawSection, [`SECTION ${rawSection}`, rawSection, 'A']);
                await sleep(150);
            }

            // 7. APAAR ID (12 character boxes)
            const apaarId = String(student.apaar_id || '').replace(/\D/g, '');
            if (apaarId.length === 12) {
                const boxes = fields.apaarBoxes.length >= 12 ? fields.apaarBoxes : Array.from(document.querySelectorAll('input[maxlength="1"]')).slice(0, 12);
                if (boxes.length === 12) {
                    for (let b = 0; b < 12; b++) {
                        setNativeValue(boxes[b], apaarId[b]);
                        await sleep(40);
                    }
                }
            }

            // 8. Submit via Save / Save & Add Another
            await sleep(500);
            const buttons = Array.from(document.querySelectorAll('button, input[type="submit"], [role="button"], a[class*="btn"], div[class*="btn"]'));
            const isLast = (currentIndex >= studentsQueue.length - 1);
            let targetBtn = null;

            if (!isLast) {
                targetBtn = buttons.find(b => {
                    const txt = (b.innerText || b.value || '').toLowerCase().trim();
                    return txt.includes('save & add another') || txt.includes('save and add another');
                });
            }

            if (!targetBtn) {
                targetBtn = buttons.find(b => {
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

            // WAIT AND STRICTLY VERIFY: Did PARAKH accept the submission?
            await sleep(2200);

            // Check for validation errors on page
            const errorElements = Array.from(document.querySelectorAll(
                '[role="alert"], .error, [class*="error" i], [class*="invalid" i], .text-danger, .invalid-feedback, div[class*="tooltip" i]'
            )).filter(el => {
                if (el.offsetParent === null) return false;
                const txt = el.textContent.toLowerCase().trim();
                return txt.includes('required') || txt.includes('mandatory') || txt.includes('invalid') || txt.includes('failed');
            });

            if (errorElements.length > 0) {
                const errText = errorElements.map(e => e.innerText.trim()).filter(Boolean).join('; ') || 'Form validation failed';
                console.error('[HPC Assist] Submission rejected by portal:', errText);
                logStatus(`❌ ${stName} failed: ${errText}`);
                
                // STOP IMMEDIATELY! Do NOT mark as saved! Do NOT increment count!
                pauseAutomation();
                alert(`⚠️ PARAKH Portal rejected submission for ${stName}:\n\n"${errText}"\n\nPlease fix the highlighted field on the page.`);
                return;
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
