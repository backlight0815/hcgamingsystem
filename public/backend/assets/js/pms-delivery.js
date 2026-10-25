(() => {
    const category = document.getElementById('pm-category');
    const project = document.getElementById('pm-project');
    const form = document.getElementById('pm-ticket-create');
    const qatAssignments = JSON.parse(form?.dataset.qatAssignments || '{}');
    const qaSelfProjects = JSON.parse(form?.dataset.qaSelfProjects || '[]').map(String);
    const serverErrors = JSON.parse(form?.dataset.serverErrors || '{}');
    const staticProjectLock = !!project?.disabled;
    let currentErrors = {};
    const fieldLabels = {
        classification_key: 'Ticket Type', project_id: 'Project', summary: 'Ticket Title',
        priority: 'Priority', developer_id: 'Assigned Developer', qa_id: 'Assigned QA',
        release_id: 'Release', cycle_id: 'Development Cycle', description: 'Description',
        business_justification: 'Business Justification', acceptance_criteria: 'Acceptance Criteria',
        current_behavior: 'Current Behavior', proposed_behavior: 'Proposed Behavior',
        detected_at: 'Detected At', business_impact: 'Business Impact',
        production_environment: 'Production Environment', defect_type: 'Defect Type',
        reproduction_steps: 'Steps to Reproduce', expected_result: 'Expected Result',
        actual_result: 'Actual Result', attachments: 'Attachments', tags: 'Tags',
        linked_requirement_key: 'Linked Requirement', linked_task_key: 'Linked Development Task',
        project_manager_id: 'Project Manager', creation_token: 'Ticket Creation Token'
    };
    const categoryRequirements = {
        REQ: ['description', 'business_justification', 'acceptance_criteria'],
        ENH: ['current_behavior', 'proposed_behavior', 'acceptance_criteria'],
        PRD: ['detected_at', 'business_impact', 'production_environment', 'actual_result'],
        QAT: ['project_id', 'reproduction_steps', 'expected_result', 'actual_result', 'defect_type'],
        TSK: ['description']
    };
    function field(name) {
        return [...(form?.querySelectorAll('[name]') || [])].find(el => el.name === name && !el.disabled && el.type !== 'hidden');
    }
    function requirements() {
        const conditional = categoryRequirements[category?.value] || [];
        const required = ['classification_key', 'summary',
            ...(conditional.includes('project_id') ? ['project_id'] : []), 'priority',
            ...conditional.filter(name => name !== 'project_id')];
        const projectId = project?.value || form?.querySelector('input[name="project_id"]')?.value || '';
        if (category?.value === 'QAT' && qaSelfProjects.includes(String(projectId))) required.push('developer_id');
        return required;
    }
    function updateRequired() {
        if (!form) return;
        const required = new Set(requirements());
        Object.keys(fieldLabels).forEach(name => {
            const control = field(name);
            if (!control) return;
            control.required = required.has(name);
            const label = control.closest('label');
            if (!label) return;
            let mark = label.querySelector('.pm-required-mark');
            if (required.has(name) && !mark) {
                mark = document.createElement('span'); mark.className = 'pm-required-mark';
                mark.textContent = ' *'; mark.setAttribute('aria-hidden', 'true');
                label.insertBefore(mark, label.firstElementChild);
            }
            if (mark) mark.hidden = !required.has(name);
        });
    }
    function renderErrors(errors, focus = false) {
        if (!form) return;
        currentErrors = { ...errors };
        form.querySelectorAll('.pm-field-error').forEach(item => item.remove());
        form.querySelectorAll('[aria-invalid="true"]').forEach(item => item.removeAttribute('aria-invalid'));
        const names = Object.keys(errors).sort((a, b) => {
            const order = ['classification_key', 'summary', 'project_id', 'priority', 'developer_id'];
            return (order.indexOf(a) < 0 ? 100 : order.indexOf(a)) -
                (order.indexOf(b) < 0 ? 100 : order.indexOf(b));
        });
        const summary = document.getElementById('pm-ticket-errors');
        summary.hidden = names.length === 0;
        if (!names.length) return;
        summary.textContent = `Please complete the following required fields: ${names.map(name => fieldLabels[name] || fieldLabels[name.split('.')[0]] || name.replaceAll('_', ' ')).join(', ')}.`;
        let first = null;
        names.forEach(name => {
            const control = field(name);
            if (!control) return;
            first ||= control;
            control.setAttribute('aria-invalid', 'true');
            const hint = document.createElement('small');
            hint.className = 'pm-field-error pm-ticket-inline-error';
            hint.setAttribute('role', 'alert');
            hint.textContent = Array.isArray(errors[name]) ? errors[name][0] : errors[name];
            control.insertAdjacentElement('afterend', hint);
        });
        if (focus) {
            (first || summary).scrollIntoView({ behavior: 'smooth', block: 'center' });
            (first || summary).focus({ preventScroll: true });
        }
    }

    function lock(select, locked, id) {
        if (!select) return;
        let hidden = select.parentElement.querySelector('input[data-qat-locked="' + id + '"]');
        if (locked && select.value) {
            select.disabled = true;
            select.setAttribute('aria-describedby', id + '-lock');
            if (!hidden) {
                hidden = document.createElement('input');
                hidden.type = 'hidden'; hidden.name = select.name; hidden.dataset.qatLocked = id;
                select.after(hidden);
            }
            hidden.value = select.value;
        } else {
            select.disabled = false;
            select.removeAttribute('aria-describedby');
            hidden?.remove();
        }
        const note = document.getElementById(id + '-lock');
        if (note) note.hidden = !locked;
    }
    function refresh() {
        const kind = category?.value;
        document.querySelectorAll('.pm-category-fields').forEach(group => {
            const visible = group.dataset.categories?.split(',').includes(kind);
            group.hidden = !visible;
            group.querySelectorAll('input,select,textarea').forEach(field => { field.disabled = !visible; });
        });
        if (project && !staticProjectLock) {
            const qatIds = Object.keys(qatAssignments);
            if (kind === 'QAT' && qatIds.length === 1) project.value = qatIds[0];
            if (kind === 'QAT' && project.value && !qatAssignments[project.value]) project.value = '';
            lock(project, kind === 'QAT' && qatIds.length === 1, 'pm-project');
        }
        if (category && project) {
            project.required = kind === 'QAT';
            project.options[0].disabled = kind === 'QAT';
        }
        const selectedProject = project?.value || document.querySelector('#pm-ticket-create input[name="project_id"]')?.value || '';
        const selfQa = kind === 'QAT' && qaSelfProjects.includes(String(selectedProject));
        const qaSelect = form?.querySelector('select[name="qa_id"]');
        const qaLabel = qaSelect?.closest('label');
        if (qaLabel) qaLabel.hidden = selfQa;
        if (qaSelect) qaSelect.disabled = selfQa;
        const selfQaDisplay = form?.querySelector('.pm-qat-self-qa');
        if (selfQaDisplay) selfQaDisplay.hidden = !selfQa;
        ['pm-release', 'pm-cycle'].forEach(id => {
            const select = document.getElementById(id);
            if (!select) return;
            lock(select, false, id);
            Array.from(select.options).forEach(option => {
                if (!option.value) return;
                option.hidden = option.dataset.project !== selectedProject;
                option.disabled = option.hidden;
            });
            if (select.selectedOptions[0]?.disabled) select.value = '';
        });
        const release = document.getElementById('pm-release');
        const cycle = document.getElementById('pm-cycle');
        const assignment = kind === 'QAT' ? qatAssignments[selectedProject] : null;
        if (release && assignment?.release?.id && !release.value) release.value = String(assignment.release.id);
        lock(release, !!assignment?.release?.locked && String(assignment.release.id) === release?.value, 'pm-release');
        if (release?.value && cycle) {
            const linkedCycle = release.selectedOptions[0]?.dataset.cycle;
            Array.from(cycle.options).forEach(option => {
                if (option.value && linkedCycle) {
                    option.hidden = option.disabled = option.value !== linkedCycle;
                }
            });
            if (cycle.selectedOptions[0]?.disabled) cycle.value = '';
            if (linkedCycle && Array.from(cycle.options).some(option => option.value === linkedCycle && !option.disabled)) cycle.value = linkedCycle;
        }
        if (cycle && !cycle.value && assignment?.cycle?.id) {
            const option = Array.from(cycle.options).find(item => item.value === String(assignment.cycle.id) && !item.disabled);
            if (option) cycle.value = option.value;
        }
        const cycleLocked = kind === 'QAT' && !!cycle?.value &&
            (!!assignment?.cycle?.locked && String(assignment.cycle.id) === cycle.value || !!release?.value && !!release.selectedOptions[0]?.dataset.cycle);
        lock(cycle, cycleLocked, 'pm-cycle');
        const globalCycle = document.getElementById('pm-global-cycle');
        if (globalCycle) {
            Array.from(globalCycle.options).forEach(option => {
                if (!option.value) return;
                option.hidden = !option.dataset.projects?.split(',').includes(selectedProject);
                option.disabled = option.hidden;
            });
            if (globalCycle.selectedOptions[0]?.disabled) globalCycle.value = '';
        }
        document.querySelectorAll('.pm-member-select').forEach(select => {
            Array.from(select.options).forEach(option => {
                if (!option.value) return;
                option.hidden = option.dataset.project !== selectedProject;
                option.disabled = option.hidden;
            });
            if (select.selectedOptions[0]?.disabled) select.value = '';
            if (select.dataset.assignmentType === 'manager') {
                select.disabled = project
                    ? project.selectedOptions[0]?.dataset.canManage !== '1'
                    : select.dataset.canManage !== '1';
                const search = select.closest('label')?.querySelector('.pm-assignee-search');
                if (search) search.disabled = select.disabled;
            }
        });
        updateRequired();
    }
    category?.addEventListener('change', refresh);
    project?.addEventListener('change', refresh);
    document.getElementById('pm-release')?.addEventListener('change', refresh);
    refresh();
    if (form && Object.keys(serverErrors).length) {
        renderErrors(serverErrors, true);
    }
    ['input', 'change'].forEach(type => form?.addEventListener(type, event => {
        const control = event.target;
        if (control.name && currentErrors[control.name] && String(control.value || '').trim()) {
            const next = { ...currentErrors };
            delete next[control.name];
            renderErrors(next);
        }
    }));

    const files = document.getElementById('pm-files');
    const zone = document.querySelector('.pm-dropzone');
    const list = document.getElementById('pm-file-list');
    function showFiles() {
        if (!files || !list) return;
        list.replaceChildren();
        Array.from(files.files).forEach((file, index) => {
            const li = document.createElement('li');
            const label = document.createElement('span');
            const extension = file.name.split('.').pop()?.toUpperCase() || 'FILE';
            label.textContent = `[${extension}] ${file.name} · ${(file.size / 1024).toFixed(1)} KB`;
            const remove = document.createElement('button');
            remove.type = 'button'; remove.className = 'pm-link'; remove.textContent = 'Remove';
            remove.addEventListener('click', () => {
                const next = new DataTransfer();
                Array.from(files.files).forEach((item, position) => { if (position !== index) next.items.add(item); });
                files.files = next.files; showFiles();
            });
            li.append(label, remove); list.append(li);
        });
    }
    files?.addEventListener('change', showFiles);
    zone?.addEventListener('dragover', event => { event.preventDefault(); zone.classList.add('dragover'); });
    zone?.addEventListener('dragleave', () => zone.classList.remove('dragover'));
    zone?.addEventListener('drop', event => {
        event.preventDefault(); zone.classList.remove('dragover');
        if (files && event.dataTransfer?.files) { files.files = event.dataTransfer.files; showFiles(); }
    });
    document.querySelectorAll('form[enctype="multipart/form-data"]').forEach(form => form.addEventListener('submit', event => {
        // Native navigation preserves validation errors and old input after a ticket upload.
        if (form.classList.contains('pm-gallery-upload')) return;
        if (form.id === 'pm-ticket-create') {
            const errors = {};
            requirements().forEach(name => {
                const control = field(name);
                if (control && !String(control.value || '').trim()) {
                    errors[name] = [name === 'project_id' ? 'Please select a project.'
                        : name === 'priority' ? 'Please select a priority.'
                            : `${fieldLabels[name]} is required.`];
                }
            });
            if (Object.keys(errors).length) {
                event.preventDefault(); renderErrors(errors, true); return;
            }
            renderErrors({});
            if (form.dataset.submitting === 'true') { event.preventDefault(); return; }
            form.dataset.submitting = 'true';
            const button = form.querySelector('button[type=submit],button:not([type])');
            if (button) { button.disabled = true; button.textContent = 'Creating ticket…'; }
            return;
        }
        const selectedFiles = Array.from(form.querySelectorAll('input[type=file]')).some(input => input.files?.length);
        if (!selectedFiles || !window.XMLHttpRequest || !window.FormData) return;
        event.preventDefault();
        const xhr = new XMLHttpRequest();
        const submit = form.querySelector('button[type=submit],button:not([type])');
        const progress = document.createElement('progress');
        progress.max = 100; progress.value = 0; progress.setAttribute('aria-label', 'Upload progress');
        const cancel = document.createElement('button');
        cancel.type = 'button'; cancel.className = 'pm-button secondary'; cancel.textContent = 'Cancel upload';
        cancel.addEventListener('click', () => { xhr.abort(); progress.remove(); cancel.remove(); submit.disabled = false; });
        submit.disabled = true; submit.after(progress, cancel);
        xhr.open('POST', form.action);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.upload.addEventListener('progress', e => { if (e.lengthComputable) progress.value = Math.round(e.loaded / e.total * 100); });
        xhr.addEventListener('load', () => {
            if (xhr.status >= 400) {
                progress.remove(); cancel.remove(); submit.disabled = false;
                let payload;
                try { payload = JSON.parse(xhr.responseText); } catch { payload = {}; }
                window.PmsFeedback?.show(payload.message ? payload : {
                    message: 'Upload was rejected. Review the file type and size, then try again.',
                });
                return;
            }
            window.location.assign(xhr.responseURL || form.action);
        });
        xhr.addEventListener('error', () => { progress.remove(); cancel.remove(); submit.disabled = false; window.PmsFeedback?.show({message: 'Upload failed. Please try again.'}); });
        xhr.send(new FormData(form));
    }));
    const dialog = document.getElementById('pm-preview-dialog');
    const content = document.getElementById('pm-preview-content');
    document.querySelectorAll('.pm-preview-trigger').forEach(button => button.addEventListener('click', () => {
        if (!dialog || !content) return;
        content.replaceChildren();
        document.getElementById('pm-preview-name').textContent = button.dataset.name || 'Attachment preview';
        let preview;
        if (button.dataset.type === 'image') {
            preview = document.createElement('img'); preview.alt = button.dataset.name || 'Attachment';
        } else if (button.dataset.type === 'video') {
            preview = document.createElement('video'); preview.controls = true;
        } else {
            preview = document.createElement('iframe'); preview.title = button.dataset.name || 'PDF preview';
        }
        preview.src = button.dataset.preview;
        content.append(preview); dialog.showModal();
    }));
    document.getElementById('pm-preview-close')?.addEventListener('click', () => dialog?.close());
    dialog?.addEventListener('close', () => content?.replaceChildren());
    const commentBody = document.querySelector('form[action$="/comments"] textarea[name="body"]');
    if (commentBody) {
        const toolbar = document.createElement('div');
        toolbar.className = 'pm-editor-toolbar';
        const actions = [
            ['Bold', '**', '**'], ['Italic', '*', '*'], ['List', '- ', ''],
            ['Numbered', '1. ', ''], ['Link', '[', '](https://)'],
            ['Code', '`', '`'], ['Quote', '> ', ''],
        ];
        actions.forEach(([label, before, after]) => {
            const button = document.createElement('button');
            button.type = 'button'; button.textContent = label;
            button.addEventListener('click', () => {
                const start = commentBody.selectionStart, end = commentBody.selectionEnd;
                const selected = commentBody.value.slice(start, end) || 'text';
                commentBody.setRangeText(before + selected + after, start, end, 'select');
                commentBody.focus();
            });
            toolbar.append(button);
        });
        commentBody.before(toolbar);
        commentBody.addEventListener('paste', event => {
            const input = commentBody.closest('form')?.querySelector('input[type=file]');
            if (!input || !event.clipboardData) return;
            const images = Array.from(event.clipboardData.items)
                .filter(item => item.kind === 'file' && item.type.startsWith('image/'))
                .map(item => item.getAsFile()).filter(Boolean);
            if (!images.length) return;
            const selected = new DataTransfer();
            Array.from(input.files).forEach(file => selected.items.add(file));
            images.forEach((image, index) => {
                const extension = {'image/jpeg': 'jpg', 'image/png': 'png', 'image/gif': 'gif', 'image/webp': 'webp'}[image.type] || 'png';
                selected.items.add(new File([image], `screenshot-${Date.now()}-${index}.${extension}`, {type: image.type}));
            });
            input.files = selected.files;
        });
    }
})();
