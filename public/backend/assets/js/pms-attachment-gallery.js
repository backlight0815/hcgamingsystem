(() => {
  const dialog = document.querySelector('.pm-gallery-dialog');
  if (!dialog) return;
  const stage = dialog.querySelector('[data-gallery-stage]');
  const images = [...document.querySelectorAll('.pm-gallery-open[data-kind="image"]')].filter((button) => button.classList.contains('pm-gallery-visual'));
  let activeImage = -1;
  let zoomed = false;
  const show = (button) => {
    if (!button) return;
    stage.replaceChildren();
    dialog.querySelector('[data-gallery-name]').textContent = button.dataset.name;
    dialog.querySelector('[data-gallery-download]').href = button.dataset.download;
    const kind = button.dataset.kind;
    let viewer;
    if (kind === 'image') {
      viewer = document.createElement('img');
      viewer.alt = button.dataset.name;
      activeImage = images.findIndex((image) => image.dataset.preview === button.dataset.preview);
    } else if (kind === 'pdf') {
      viewer = document.createElement('iframe');
      viewer.title = `PDF preview: ${button.dataset.name}`;
      activeImage = -1;
    } else {
      viewer = document.createElement('video');
      viewer.controls = true;
      activeImage = -1;
    }
    viewer.src = button.dataset.preview;
    viewer.addEventListener('error', () => { stage.textContent = 'Preview unavailable. Use Download to open this file.'; });
    stage.append(viewer);
    zoomed = false;
    stage.classList.remove('zoomed');
    dialog.querySelector('[data-gallery-zoom]').hidden = kind !== 'image';
    dialog.querySelector('.pm-gallery-nav').hidden = kind !== 'image' || images.length < 2;
    if (!dialog.open) dialog.showModal();
  };
  document.querySelectorAll('.pm-gallery-open').forEach((button) => button.addEventListener('click', () => show(button)));
  dialog.querySelector('[data-gallery-close]').addEventListener('click', () => dialog.close());
  dialog.querySelector('[data-gallery-zoom]').addEventListener('click', () => { zoomed = !zoomed; stage.classList.toggle('zoomed', zoomed); });
  dialog.querySelector('[data-gallery-prev]').addEventListener('click', () => show(images[(activeImage - 1 + images.length) % images.length]));
  dialog.querySelector('[data-gallery-next]').addEventListener('click', () => show(images[(activeImage + 1) % images.length]));
  dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
  dialog.addEventListener('keydown', (event) => {
    if (activeImage < 0 || images.length < 2) return;
    if (event.key === 'ArrowLeft') show(images[(activeImage - 1 + images.length) % images.length]);
    if (event.key === 'ArrowRight') show(images[(activeImage + 1) % images.length]);
  });
  document.querySelectorAll('.pm-gallery-copy').forEach((button) => button.addEventListener('click', async () => {
    try { await navigator.clipboard.writeText(button.dataset.url); button.textContent = 'Copied'; setTimeout(() => { button.textContent = 'Copy link'; }, 2000); }
    catch (_) { window.prompt('Copy attachment link', button.dataset.url); }
  }));
  document.querySelectorAll('.pm-gallery-upload').forEach((form) => {
    const input = form.querySelector('input[type=file]');
    const drop = form.querySelector('.pm-gallery-drop');
    const progress = form.querySelector('progress');
    const error = form.querySelector('.pm-gallery-error');
    ['dragenter', 'dragover'].forEach((name) => drop.addEventListener(name, (event) => { event.preventDefault(); drop.classList.add('dragging'); }));
    ['dragleave', 'drop'].forEach((name) => drop.addEventListener(name, (event) => { event.preventDefault(); drop.classList.remove('dragging'); }));
    drop.addEventListener('drop', (event) => { input.files = event.dataTransfer.files; drop.firstChild.textContent = `${input.files.length} file(s) selected or `; });
    form.addEventListener('submit', (event) => {
      if (!form.reportValidity()) return;
      event.preventDefault();
      error.hidden = true;
      const button = form.querySelector('button[type=submit], button.pm-button');
      button.disabled = true;
      progress.hidden = false;
      const xhr = new XMLHttpRequest();
      xhr.open('POST', form.action);
      xhr.setRequestHeader('Accept', 'application/json');
      xhr.upload.onprogress = (update) => { if (update.lengthComputable) progress.value = Math.round(update.loaded / update.total * 100); };
      xhr.onload = () => {
        if (xhr.status >= 200 && xhr.status < 300) { window.location.reload(); return; }
        let message = 'Upload failed. Please check the selected files and try again.';
        try { const body = JSON.parse(xhr.responseText); message = Object.values(body.errors || {}).flat().join(' ') || body.message || message; } catch (_) {}
        error.textContent = message; error.hidden = false; button.disabled = false; progress.hidden = true;
      };
      xhr.onerror = () => { error.textContent = 'Network error while uploading. Please retry.'; error.hidden = false; button.disabled = false; progress.hidden = true; };
      xhr.send(new FormData(form));
    });
  });
})();
