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

    // Comprehensive form field detector
    function findFormFields() {
        const fields = {
            firstName: null,
            lastName: null,
            dob: null,
            email: null,
            gradeTrigger: null,
            sectionTrigger: null,
            apaarBoxes: []
        };

        const allLabels = Array.from(document.querySelectorAll('label, div, span, p'));
        function getControlForLabel(matchText) {
            const l = allLabels.find(el => {
                const txt = (el.innerText || '').toLowerCase().trim();
                return txt.startsWith(matchText.toLowerCase()) && el.children.length === 0;
            }) || allLabels.find(el => {
                const txt = (el.innerText || '').toLowerCase().trim();
                return txt.includes(matchText.toLowerCase()) && el.children.length <= 1;
            });
            if (!l) return null;

            const forId = l.getAttribute('for');
            if (forId) {
                const el = document.getElementById(forId);
                if (el) return el;
            }

            let parent = l.parentElement;
            for (let i = 0; i < 4 && parent; i++) {
                const control = parent.querySelector('input, select, .custom-dropdown-header, [role="combobox"]');
                if (control && control !== l) return control;
                parent = parent.parentElement;
            }
            return null;
        }

        fields.firstName = getControlForLabel('First Name');
        fields.lastName  = getControlForLabel('Last Name');
        fields.dob       = getControlForLabel('Date of Birth');
        fields.email     = getControlForLabel('Email');
        fields.gradeTrigger   = getControlForLabel('Grade');
        fields.sectionTrigger = getControlForLabel('Section');

        // Fallbacks
        const allInputs = Array.from(document.querySelectorAll('input:not([type="hidden"]):not([type="button"]):not([type="submit"])'));
        const textInputs = allInputs.filter(inp => {
            const t = (inp.getAttribute('type') || 'text').toLowerCase();
            return t === 'text' || t === 'email';
        });

        if (!fields.firstName && textInputs.length >= 1) fields.firstName = textInputs[0];
        if (!fields.lastName && textInputs.length >= 2) fields.lastName = textInputs[1];
        if (!fields.email) {
            fields.email = document.querySelector('input[type="email"]') || (textInputs.length >= 4 ? textInputs[3] : textInputs[2]);
        }

        if (!fields.dob) {
            fields.dob = allInputs.find(inp => (inp.getAttribute('placeholder') || '').toLowerCase().includes('select'));
        }

        const dds = Array.from(document.querySelectorAll('.custom-dropdown-header, [role="combobox"]'));
        if (!fields.gradeTrigger && dds.length > 0) fields.gradeTrigger = dds[0];
        if (!fields.sectionTrigger && dds.length > 1) fields.sectionTrigger = dds[1];

        fields.apaarBoxes = allInputs.filter(inp => inp.getAttribute('maxlength') === '1');

        return fields;
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

        // Event delegation on widget: guaranteed to catch all clicks!
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
            if (student.dob) {
                const dobObj = parseDate(student.dob);
                if (fields.dob) {
                    // Try direct native fill
                    if (dobObj) {
                        setNativeValue(fields.dob, dobObj.formatted);
                    } else {
                        setNativeValue(fields.dob, student.dob);
                    }
                    await sleep(100);

                    // Click to trigger datepicker calendar
                    fields.dob.click();
                    await sleep(350);

                    if (dobObj) {
                        const yearSelect = document.querySelector('.react-datepicker__year-select');
                        if (yearSelect) {
                            yearSelect.value = dobObj.year;
                            yearSelect.dispatchEvent(new Event('change', { bubbles: true }));
                            await sleep(150);
                        }

                        const monthSelect = document.querySelector('.react-datepicker__month-select');
                        if (monthSelect) {
                            monthSelect.value = String(dobObj.month);
                            monthSelect.dispatchEvent(new Event('change', { bubbles: true }));
                            await sleep(150);
                        }

                        const days = Array.from(document.querySelectorAll('.react-datepicker__day:not(.react-datepicker__day--outside-month)'));
                        const dayEl = days.find(d => d.innerText.trim() === String(dobObj.day));
                        if (dayEl) {
                            dayEl.click();
                            await sleep(250);
                        }
                    }
                }
            }

            // 3. Email
            if (fields.email && student.email) {
                setNativeValue(fields.email, student.email);
                await sleep(150);
            }

            // 4. Grade Dropdown
            const rawGrade = String(student.grade || 'Grade 1').toLowerCase().trim();
            if (fields.gradeTrigger) {
                fields.gradeTrigger.click();
                await sleep(350);

                const gradeOptions = Array.from(document.querySelectorAll('.custom-dropdown-item, [role="option"], li, div[class*="option"], div[class*="item"]'));
                const matchedGrade = gradeOptions.find(it => {
                    const txt = (it.innerText || '').toLowerCase().trim();
                    return txt === rawGrade || txt.includes(rawGrade) || rawGrade.includes(txt);
                });
                if (matchedGrade) {
                    matchedGrade.click();
                    await sleep(250);
                }
            }

            // 5. Section Dropdown
            const rawSection = String(student.section || 'A').toUpperCase().trim();
            if (fields.sectionTrigger) {
                fields.sectionTrigger.click();
                await sleep(350);

                const sectionOptions = Array.from(document.querySelectorAll('.custom-dropdown-item, [role="option"], li, div[class*="option"], div[class*="item"]'));
                const matchedSection = sectionOptions.find(it => {
                    const txt = (it.innerText || '').toUpperCase().trim();
                    return txt === rawSection || txt === `SECTION ${rawSection}` || txt.includes(`SECTION ${rawSection}`);
                });
                if (matchedSection) {
                    matchedSection.click();
                    await sleep(250);
                }
            }

            // 6. APAAR ID (12 character boxes)
            const apaarId = String(student.apaar_id || '').replace(/\D/g, '');
            if (apaarId.length === 12) {
                const boxes = fields.apaarBoxes.length >= 12 ? fields.apaarBoxes : Array.from(document.querySelectorAll('input[maxlength="1"]')).slice(0, 12);
                if (boxes.length === 12) {
                    for (let b = 0; b < 12; b++) {
                        setNativeValue(boxes[b], apaarId[b]);
                        await sleep(50);
                    }
                }
            }

            // 7. Click Save Button (prefer "Save & Add Another" for intermediate students)
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

            if (targetBtn) {
                logStatus(`Submitting ${stName}...`);
                targetBtn.click();
            } else {
                logStatus(`Form filled for ${stName}. Click Save manually.`);
            }

            // Report progress back to server
            await reportProgress(currentIndex + 1, studentsQueue.length, stName, 'success', `Saved ${stName}`);

            currentIndex++;
            localStorage.setItem('hpc_student_index', String(currentIndex));
            updateWidgetContent();

            await sleep(2500);

            // Re-navigate or proceed
            if (isRunning) {
                if (currentIndex >= studentsQueue.length) {
                    isRunning = false;
                    localStorage.setItem('hpc_submission_running', 'false');
                    logStatus(`🎉 All ${studentsQueue.length} students completed!`);
                    updateWidgetContent();
                    alert(`🎉 All ${studentsQueue.length} students have been successfully submitted to PARAKH HPC portal!`);
                    return;
                }

                // If page navigated away, re-navigate to add student form
                if (!location.pathname.includes('/students/add')) {
                    window.location.href = 'https://hpc.parakh.ncert.gov.in/en/students/add';
                    return;
                }

                // If still on /students/add (Save & Add Another), proceed directly to next student
                await processCurrentStudent();
            }

        } catch(err) {
            console.error('[HPC Assist] Error submitting student:', err);
            logStatus(`⚠️ Error on ${stName}: ${err.message}`);
            await reportProgress(currentIndex + 1, studentsQueue.length, stName, 'error', err.message);
            currentIndex++;
            localStorage.setItem('hpc_student_index', String(currentIndex));
            updateWidgetContent();
            await sleep(2500);
            if (isRunning) {
                if (currentIndex >= studentsQueue.length) {
                    isRunning = false;
                    localStorage.setItem('hpc_submission_running', 'false');
                    updateWidgetContent();
                    return;
                }
                if (!location.pathname.includes('/students/add')) {
                    window.location.href = 'https://hpc.parakh.ncert.gov.in/en/students/add';
                    return;
                }
                await processCurrentStudent();
            }
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
