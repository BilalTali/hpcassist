// HPC Assist PARAKH Portal Student Auto-Submission Script
(function () {
    console.log('[HPC Assist] PARAKH HPC Automation script loaded on', location.href);

    const SERVER_URL = (window.__HPC_CONFIG && window.__HPC_CONFIG.server_url) ? window.__HPC_CONFIG.server_url : 'https://ctetmonktest.fun';
    const SCHOOL_ID  = (window.__HPC_CONFIG && window.__HPC_CONFIG.school_id) ? window.__HPC_CONFIG.school_id : null;

    let studentsQueue = [];
    let isRunning = false;
    let currentIndex = 0;

    function setNativeValue(element, value) {
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
    }

    function parseDate(dobStr) {
        if (!dobStr) return null;
        dobStr = String(dobStr).trim();
        const parts = dobStr.split(/[\/\-\.]/);
        if (parts.length === 3) {
            if (parts[0].length === 4) {
                // YYYY-MM-DD
                return { year: parts[0], month: parseInt(parts[1], 10) - 1, day: parseInt(parts[2], 10) };
            } else {
                // DD/MM/YYYY
                return { year: parts[2], month: parseInt(parts[1], 10) - 1, day: parseInt(parts[0], 10) };
            }
        }
        return null;
    }

    async function sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    // Load queue from localStorage or Server API
    async function loadQueue() {
        // 1. Try local storage cache
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

        // 2. Fetch from HPC Assist server
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

    // Inject Floating Automation Widget
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

        updateWidgetContent(widget);
        document.body.appendChild(widget);
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
                <span style="font-size: 10px; background: rgba(16, 185, 129, 0.2); color: #34d399; padding: 2px 8px; border-radius: 12px; font-weight: 600;">
                    ${isRunning ? 'RUNNING' : 'READY'}
                </span>
            </div>
            
            <div style="font-size: 12px; color: #94a3b8; margin-bottom: 12px; line-height: 1.4;">
                ${total > 0 ? `<strong>${total}</strong> students queued for portal submission.` : 'No student queue found. Upload or sync in HPC Assist.'}
            </div>

            ${total > 0 ? `
                <div style="background: rgba(255, 255, 255, 0.08); border-radius: 8px; height: 6px; overflow: hidden; margin-bottom: 12px;">
                    <div style="background: #10b981; height: 100%; width: ${progressPct}%; transition: width 0.3s ease;"></div>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 11px; color: #cbd5e1; margin-bottom: 12px;">
                    <span id="hpc-widget-status">${isRunning ? `Submitting ${currentIndex + 1} of ${total}...` : `${currentIndex}/${total} completed`}</span>
                    <span>${progressPct}%</span>
                </div>
            ` : ''}

            <div style="display: flex; gap: 8px;">
                ${isRunning ? `
                    <button id="hpc-pause-btn" style="flex: 1; padding: 8px 12px; background: #e11d48; color: white; border: none; border-radius: 8px; font-weight: 600; font-size: 12px; cursor: pointer;">
                        ⏸ Pause
                    </button>
                ` : `
                    <button id="hpc-start-btn" ${total === 0 ? 'disabled' : ''} style="flex: 1; padding: 8px 12px; background: #2563eb; color: white; border: none; border-radius: 8px; font-weight: 600; font-size: 12px; cursor: pointer; opacity: ${total === 0 ? '0.5' : '1'};">
                        ▶ ${currentIndex > 0 ? 'Resume' : 'Start Auto-Submit'}
                    </button>
                `}
                <button id="hpc-refresh-btn" style="padding: 8px 12px; background: rgba(255, 255, 255, 0.1); color: #e2e8f0; border: none; border-radius: 8px; font-weight: 600; font-size: 12px; cursor: pointer;">
                    🔄 Sync
                </button>
            </div>
        `;

        document.getElementById('hpc-start-btn')?.addEventListener('click', startAutomation);
        document.getElementById('hpc-pause-btn')?.addEventListener('click', pauseAutomation);
        document.getElementById('hpc-refresh-btn')?.addEventListener('click', async () => {
            await loadQueue();
            updateWidgetContent();
        });
    }

    async function startAutomation() {
        if (!studentsQueue.length) {
            alert('No student queue loaded. Please sync students in HPC Assist.');
            return;
        }

        isRunning = true;
        localStorage.setItem('hpc_submission_running', 'true');
        updateWidgetContent();

        // Check if on Add Student form
        if (!location.pathname.includes('/students/add')) {
            window.location.href = 'https://hpc.parakh.ncert.gov.in/en/students/add';
            return;
        }

        processCurrentStudent();
    }

    function pauseAutomation() {
        isRunning = false;
        localStorage.setItem('hpc_submission_running', 'false');
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
        const statusEl = document.getElementById('hpc-widget-status');
        const stName = student.name || `${student.first_name || ''} ${student.last_name || ''}`.trim() || 'Student';
        if (statusEl) statusEl.textContent = `Submitting (${currentIndex + 1}/${studentsQueue.length}): ${stName}...`;

        // Check if form is ready
        const writeInputs = document.querySelectorAll('input[placeholder="Write here"]');
        if (!writeInputs || writeInputs.length < 2) {
            // Wait for form to appear or navigate to add form
            if (!location.pathname.includes('/students/add')) {
                window.location.href = 'https://hpc.parakh.ncert.gov.in/en/students/add';
                return;
            }
            await sleep(1000);
            processCurrentStudent();
            return;
        }

        try {
            // 1. First & Last Name
            const firstName = student.first_name || student.name?.split(' ')?.[0] || 'Student';
            const lastName  = student.last_name || (student.name?.split(' ')?.slice(1)?.join(' ')) || '';
            setNativeValue(writeInputs[0], firstName);
            await sleep(150);
            if (writeInputs[1]) {
                setNativeValue(writeInputs[1], lastName);
                await sleep(150);
            }

            // 2. Date of Birth (React Datepicker)
            const dob = parseDate(student.dob);
            if (dob) {
                const selectInputs = document.querySelectorAll('input[placeholder="Select"]');
                if (selectInputs.length > 0) {
                    selectInputs[0].click();
                    await sleep(400);

                    const yearSelect = document.querySelector('.react-datepicker__year-select');
                    if (yearSelect) {
                        yearSelect.value = dob.year;
                        yearSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                    await sleep(200);

                    const monthSelect = document.querySelector('.react-datepicker__month-select');
                    if (monthSelect) {
                        monthSelect.value = String(dob.month);
                        monthSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                    await sleep(250);

                    const days = Array.from(document.querySelectorAll('.react-datepicker__day:not(.react-datepicker__day--outside-month)'));
                    const dayEl = days.find(d => d.innerText.trim() === String(dob.day));
                    if (dayEl) dayEl.click();
                    await sleep(300);
                }
            }

            // 3. Email
            const emailInput = document.querySelector('input[type="email"]') || writeInputs[2];
            if (emailInput && student.email) {
                setNativeValue(emailInput, student.email);
                await sleep(150);
            }

            // 4. Grade (.custom-dropdown-wrapper)
            const rawGrade = String(student.grade || 'Grade 6').toLowerCase().trim();
            const dropdownHeaders = document.querySelectorAll('.custom-dropdown-header');
            if (dropdownHeaders.length > 0) {
                dropdownHeaders[0].click();
                await sleep(400);

                const items = Array.from(document.querySelectorAll('.custom-dropdown-item'));
                const item = items.find(it => {
                    const txt = it.innerText.toLowerCase().trim();
                    return txt.includes(rawGrade) || rawGrade.includes(txt);
                });
                if (item) item.click();
                await sleep(300);
            }

            // 5. Section (.custom-dropdown-wrapper)
            const rawSection = String(student.section || 'A').toUpperCase().trim();
            if (dropdownHeaders.length > 1) {
                dropdownHeaders[1].click();
                await sleep(400);

                const items = Array.from(document.querySelectorAll('.custom-dropdown-item'));
                const item = items.find(it => {
                    const txt = it.innerText.toUpperCase().trim();
                    return txt === rawSection || txt === `SECTION ${rawSection}`;
                });
                if (item) item.click();
                await sleep(300);
            }

            // 6. APAAR ID (12 individual character boxes)
            const apaarId = String(student.apaar_id || '').replace(/\D/g, '');
            if (apaarId.length === 12) {
                const apaarBoxes = document.querySelectorAll('input[maxlength="1"]');
                if (apaarBoxes.length >= 12) {
                    for (let b = 0; b < 12; b++) {
                        setNativeValue(apaarBoxes[b], apaarId[b]);
                    }
                    await sleep(200);
                }
            }

            // 7. Click Save / Submit
            await sleep(500);
            const buttons = Array.from(document.querySelectorAll('button, input[type="submit"]'));
            const saveBtn = buttons.find(b => {
                const txt = (b.innerText || b.value || '').toLowerCase().trim();
                return (txt.includes('save') || txt.includes('submit')) && !txt.includes('another');
            });

            if (saveBtn) {
                saveBtn.click();
            }

            // Report progress back to server
            await reportProgress(currentIndex + 1, studentsQueue.length, stName, 'success', `Saved ${stName}`);

            currentIndex++;
            localStorage.setItem('hpc_student_index', String(currentIndex));
            updateWidgetContent();

            // Wait for response, then reset or proceed
            await sleep(2500);

            // Re-navigate or trigger next
            if (isRunning) {
                window.location.href = 'https://hpc.parakh.ncert.gov.in/en/students/add';
            }

        } catch(err) {
            console.error('[HPC Assist] Error submitting student:', err);
            await reportProgress(currentIndex + 1, studentsQueue.length, stName, 'error', err.message);
            currentIndex++;
            localStorage.setItem('hpc_student_index', String(currentIndex));
            updateWidgetContent();
            await sleep(2000);
            if (isRunning) {
                window.location.href = 'https://hpc.parakh.ncert.gov.in/en/students/add';
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
