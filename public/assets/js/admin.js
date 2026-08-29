const source = document.querySelector('[data-slug-source]');
const target = document.querySelector('[data-slug-target]');

function slugify(value) {
  return value
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

if (source && target) {
  let edited = Boolean(target.value);
  target.addEventListener('input', () => { edited = true; });
  source.addEventListener('input', () => {
    if (!edited) target.value = slugify(source.value);
  });
}

document.querySelectorAll('[data-copy-url]').forEach((button) => {
  button.addEventListener('click', async () => {
    await navigator.clipboard.writeText(button.dataset.copyUrl);
    const original = button.textContent;
    button.textContent = 'Copiado';
    setTimeout(() => { button.textContent = original; }, 1200);
  });
});

function insertAtCursor(textarea, value) {
  const start = textarea.selectionStart || 0;
  const end = textarea.selectionEnd || 0;
  textarea.value = `${textarea.value.slice(0, start)}${value}${textarea.value.slice(end)}`;
  textarea.focus();
  textarea.selectionStart = start + value.length;
  textarea.selectionEnd = start + value.length;
  textarea.dispatchEvent(new Event('input', { bubbles: true }));
}

let activeEditor = null;

document.querySelectorAll('[data-content-editor]').forEach((textarea) => {
  const rememberEditor = () => {
    activeEditor = textarea;
  };
  textarea.addEventListener('focus', rememberEditor);
  textarea.addEventListener('click', rememberEditor);
  textarea.addEventListener('keyup', rememberEditor);
});

document.querySelectorAll('[data-select-media]').forEach((button) => {
  button.addEventListener('click', () => {
    const input = document.querySelector('[data-featured-image]');
    const preview = document.querySelector('[data-media-preview]');
    if (input) input.value = button.dataset.selectMedia;
    if (preview) {
      preview.src = button.dataset.selectMedia;
      preview.hidden = false;
    }
  });
});

document.querySelectorAll('[data-insert-media]').forEach((button) => {
  button.addEventListener('click', () => {
    const editor = activeEditor || document.querySelector('[data-content-editor]');
    if (!editor) return;
    insertAtCursor(editor, `![${button.dataset.mediaName || 'Imagen'}](${button.dataset.insertMedia})`);
  });
});

document.querySelectorAll('[data-totp-qr]').forEach((root) => {
  const target = root.querySelector('[data-totp-qr-canvas]');
  if (!target || typeof window.qrcode !== 'function') return;
  const qr = window.qrcode(0, 'M');
  qr.addData(root.dataset.totpQr);
  qr.make();
  target.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 0, scalable: true });
});

document.querySelectorAll('[data-copy-recovery]').forEach((button) => {
  button.addEventListener('click', async () => {
    await navigator.clipboard.writeText(button.dataset.copyRecovery || '');
    button.textContent = 'Códigos copiados';
  });
});

document.querySelectorAll('[data-download-recovery]').forEach((button) => {
  button.addEventListener('click', () => {
    const blob = new Blob([`${button.dataset.downloadRecovery || ''}\n`], { type: 'text/plain;charset=utf-8' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'isnomcms-recovery-codes.txt';
    link.click();
    URL.revokeObjectURL(link.href);
  });
});

function wrapSelection(textarea, before, after) {
  const start = textarea.selectionStart || 0;
  const end = textarea.selectionEnd || 0;
  const selected = textarea.value.slice(start, end) || 'texto';
  insertAtCursor(textarea, `${before}${selected}${after}`);
  textarea.selectionStart = start + before.length;
  textarea.selectionEnd = start + before.length + selected.length;
}

function prefixSelection(textarea, prefix) {
  const start = textarea.selectionStart || 0;
  const end = textarea.selectionEnd || 0;
  const selected = textarea.value.slice(start, end) || 'texto';
  const prefixed = selected.split('\n').map((line) => `${prefix}${line}`).join('\n');
  insertAtCursor(textarea, prefixed);
  textarea.selectionStart = start;
  textarea.selectionEnd = start + prefixed.length;
}

document.querySelectorAll('[data-editor-form]').forEach((form) => {
  const textarea = form.querySelector('[data-content-editor]');
  const preview = form.querySelector('[data-preview-body]');
  const status = form.querySelector('[data-preview-status]');
  const csrf = form.querySelector('input[name="_csrf"]');
  if (!textarea) return;

  form.querySelectorAll('[data-md-wrap]').forEach((button) => {
    button.addEventListener('click', () => {
      const [before, after] = button.dataset.mdWrap.split('|');
      wrapSelection(textarea, before, after);
    });
  });

  form.querySelectorAll('[data-md-prefix]').forEach((button) => {
    button.addEventListener('click', () => {
      prefixSelection(textarea, button.dataset.mdPrefix);
    });
  });

  const linkButton = form.querySelector('[data-md-link]');
  if (linkButton) {
    linkButton.addEventListener('click', () => {
      const url = window.prompt('URL');
      if (!url) return;
      const start = textarea.selectionStart || 0;
      const end = textarea.selectionEnd || 0;
      const selected = textarea.value.slice(start, end) || 'enlace';
      insertAtCursor(textarea, `[${selected}](${url})`);
    });
  }

  const imageButton = form.querySelector('[data-md-image]');
  if (imageButton) {
    imageButton.addEventListener('click', () => {
      const url = window.prompt('URL de imagen');
      if (!url) return;
      insertAtCursor(textarea, `![Imagen](${url})`);
    });
  }

  form.querySelectorAll('[data-md-embed]').forEach((button) => {
    button.addEventListener('click', () => {
      const url = window.prompt(button.dataset.mdEmbed === 'spotify' ? 'URL de Spotify' : 'URL de Apple Music');
      if (!url) return;
      insertAtCursor(textarea, `[${button.dataset.mdEmbed}:${url}]`);
    });
  });

  async function updatePreview() {
    if (!preview || !csrf) return;
    if (status) status.textContent = 'Actualizando...';
    const body = new URLSearchParams();
    body.set('_csrf', csrf.value);
    body.set('content', textarea.value);
    try {
      const response = await fetch(form.dataset.previewEndpoint, {
        method: 'POST',
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
        body,
      });
      const data = await response.json();
      if (!response.ok) throw new Error(data.error || 'No se pudo generar preview.');
      preview.innerHTML = data.html || '';
      if (status) status.textContent = 'Actualizado';
    } catch (error) {
      preview.innerHTML = '';
      if (status) status.textContent = error.message || 'Error';
    }
  }

  const previewButton = form.querySelector('[data-md-preview]');
  if (previewButton) previewButton.addEventListener('click', updatePreview);

  let timer;
  textarea.addEventListener('input', () => {
    if (!preview) return;
    if (status) status.textContent = 'Pendiente';
    clearTimeout(timer);
    timer = setTimeout(updatePreview, 700);
  });

  updatePreview();
});

document.querySelectorAll('[data-color-source]').forEach((picker) => {
  const text = picker.parentElement.querySelector('[data-color-target]');
  if (!text) return;
  picker.addEventListener('input', () => { text.value = picker.value; });
  text.addEventListener('input', () => {
    if (/^#[0-9a-f]{6}$/i.test(text.value)) picker.value = text.value;
  });
});

document.querySelectorAll('[data-list-editor]').forEach((editor) => {
  const body = editor.querySelector('[data-list-body]');
  const add = editor.querySelector('[data-add-row]');
  if (!body || !add) return;

  const bindRemove = (row) => {
    const button = row.querySelector('[data-remove-row]');
    if (!button) return;
    button.addEventListener('click', () => {
      if (body.querySelectorAll('[data-list-row]').length > 1) {
        row.remove();
        return;
      }
      row.querySelectorAll('input').forEach((input) => { input.value = ''; });
    });
  };

  body.querySelectorAll('[data-list-row]').forEach(bindRemove);

  add.addEventListener('click', () => {
    const row = document.createElement('div');
    row.className = 'link-row';
    row.dataset.listRow = '';

    const label = document.createElement('input');
    label.name = add.dataset.labelName;
    label.placeholder = add.dataset.labelPlaceholder || 'Etiqueta';

    const url = document.createElement('input');
    url.name = add.dataset.urlName;
    url.placeholder = add.dataset.urlPlaceholder || 'URL';

    const remove = document.createElement('button');
    remove.type = 'button';
    remove.dataset.removeRow = '';
    remove.textContent = 'Quitar';

    row.append(label, url, remove);
    body.append(row);
    bindRemove(row);
    label.focus();
  });
});

document.querySelectorAll('[data-podcast-episode-form]').forEach((form) => {
  const source = form.querySelector('[data-audio-source]');
  const local = form.querySelector('[data-audio-local]');
  const dropbox = form.querySelector('[data-audio-dropbox]');
  const toggle = () => {
    const isDropbox = source && source.value === 'dropbox';
    if (local) local.hidden = isDropbox;
    if (dropbox) dropbox.hidden = !isDropbox;
  };
  if (source) source.addEventListener('change', toggle);
  toggle();

  const validate = form.querySelector('[data-validate-dropbox]');
  const input = form.querySelector('[data-dropbox-url]');
  const status = form.querySelector('[data-dropbox-status]');
  const csrf = form.querySelector('input[name="_csrf"]');
  if (validate && input && status && csrf) validate.addEventListener('click', async () => {
    validate.disabled = true;
    status.textContent = 'Validando acceso, MIME y tamaño...';
    const body = new URLSearchParams({ _csrf: csrf.value, url: input.value });
    try {
      const response = await fetch('/admin/podcast/audio/validate', { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json' }, body });
      const result = await response.json();
      if (!response.ok || !result.ok) throw new Error(result.error || 'URL no válida.');
      status.textContent = `Audio válido: ${result.audio.mime_type}, ${result.audio.file_size} bytes. Apto para RSS.`;
    } catch (error) {
      status.textContent = error.message || 'No se pudo validar Dropbox.';
    } finally {
      validate.disabled = false;
    }
  });
});

document.querySelectorAll('[data-roles-matrix]').forEach((matrix) => {
  const save = matrix.querySelector('[data-role-save]');
  const activeLabel = matrix.querySelector('[data-active-role-label]');

  const selectRole = (role, label) => {
    if (!role || !document.getElementById(`role-form-${role}`)) return;
    matrix.dataset.activeRole = role;
    matrix.querySelectorAll('[data-role-column]').forEach((column) => {
      column.classList.toggle('is-selected', column.dataset.roleColumn === role);
    });
    if (save) save.setAttribute('form', `role-form-${role}`);
    if (activeLabel) activeLabel.textContent = label || role;
  };

  matrix.querySelectorAll('[data-select-role]').forEach((button) => {
    button.addEventListener('click', () => selectRole(button.dataset.selectRole, button.dataset.roleName));
  });

  matrix.querySelectorAll('[data-role-permission]').forEach((input) => {
    input.addEventListener('change', () => {
      const header = matrix.querySelector(`[data-select-role="${input.dataset.rolePermission}"]`);
      selectRole(input.dataset.rolePermission, header ? header.dataset.roleName : input.dataset.rolePermission);
    });
  });

  const setAll = (checked) => {
    const role = matrix.dataset.activeRole;
    matrix.querySelectorAll(`[data-role-permission="${role}"]`).forEach((input) => { input.checked = checked; });
  };
  const checkAll = matrix.querySelector('[data-role-check-all]');
  const clearAll = matrix.querySelector('[data-role-clear-all]');
  if (checkAll) checkAll.addEventListener('click', () => setAll(true));
  if (clearAll) clearAll.addEventListener('click', () => setAll(false));
});

const passkeySupported = window.PublicKeyCredential && navigator.credentials;

function base64UrlToBuffer(value) {
  const base64 = value.replace(/-/g, '+').replace(/_/g, '/');
  const padded = base64.padEnd(Math.ceil(base64.length / 4) * 4, '=');
  const binary = atob(padded);
  const bytes = new Uint8Array(binary.length);
  for (let i = 0; i < binary.length; i += 1) bytes[i] = binary.charCodeAt(i);
  return bytes.buffer;
}

function bufferToBase64Url(buffer) {
  const bytes = new Uint8Array(buffer);
  let binary = '';
  bytes.forEach((byte) => { binary += String.fromCharCode(byte); });
  return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
}

function prepareCreationOptions(options) {
  options.challenge = base64UrlToBuffer(options.challenge);
  options.user.id = base64UrlToBuffer(options.user.id);
  options.excludeCredentials = (options.excludeCredentials || []).map((credential) => ({
    ...credential,
    id: base64UrlToBuffer(credential.id),
  }));
  return options;
}

function prepareRequestOptions(options) {
  options.challenge = base64UrlToBuffer(options.challenge);
  options.allowCredentials = (options.allowCredentials || []).map((credential) => ({
    ...credential,
    id: base64UrlToBuffer(credential.id),
  }));
  return options;
}

function credentialToJson(credential) {
  const json = {
    id: credential.id,
    rawId: bufferToBase64Url(credential.rawId),
    type: credential.type,
    response: {},
  };

  if (credential.response.clientDataJSON) {
    json.response.clientDataJSON = bufferToBase64Url(credential.response.clientDataJSON);
  }
  if (credential.response.attestationObject) {
    json.response.attestationObject = bufferToBase64Url(credential.response.attestationObject);
  }
  if (credential.response.authenticatorData) {
    json.response.authenticatorData = bufferToBase64Url(credential.response.authenticatorData);
  }
  if (credential.response.signature) {
    json.response.signature = bufferToBase64Url(credential.response.signature);
  }
  if (credential.response.userHandle) {
    json.response.userHandle = bufferToBase64Url(credential.response.userHandle);
  }
  if (typeof credential.response.getTransports === 'function') {
    json.response.transports = credential.response.getTransports();
  }

  return json;
}

async function postJson(url, payload) {
  const response = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    credentials: 'same-origin',
    body: JSON.stringify(payload),
  });
  const data = await response.json();
  if (!response.ok) throw new Error(data.error || 'No se pudo completar la operación.');
  return data;
}

function setPasskeyMessage(root, message) {
  const box = root.querySelector('[data-passkey-message]');
  if (!box) return;
  box.hidden = false;
  box.textContent = message;
}

document.querySelectorAll('[data-passkey-login]').forEach((root) => {
  const button = root.querySelector('[data-passkey-login-button]');
  if (!button) return;
  if (!passkeySupported) {
    root.hidden = true;
    return;
  }

  button.addEventListener('click', async () => {
    button.disabled = true;
    try {
      const options = await postJson('/admin/passkeys/login/options', { _csrf: root.dataset.csrf });
      const credential = await navigator.credentials.get({ publicKey: prepareRequestOptions(options.publicKey) });
      const result = await postJson('/admin/passkeys/login/verify', {
        _csrf: root.dataset.csrf,
        credential: credentialToJson(credential),
      });
      window.location.href = result.redirect || '/admin';
    } catch (error) {
      setPasskeyMessage(root, error.message || 'No se pudo iniciar sesión con Passkey.');
      button.disabled = false;
    }
  });
});

document.querySelectorAll('[data-passkey-register]').forEach((root) => {
  const button = root.querySelector('[data-passkey-add]');
  const label = root.querySelector('[data-passkey-label]');
  if (!button) return;
  if (!passkeySupported) {
    button.disabled = true;
    setPasskeyMessage(root, 'Este navegador no tiene WebAuthn disponible.');
    return;
  }

  button.addEventListener('click', async () => {
    button.disabled = true;
    try {
      const options = await postJson('/admin/passkeys/register/options', { _csrf: root.dataset.csrf });
      const credential = await navigator.credentials.create({ publicKey: prepareCreationOptions(options.publicKey) });
      const result = await postJson('/admin/passkeys/register/verify', {
        _csrf: root.dataset.csrf,
        label: label ? label.value : '',
        credential: credentialToJson(credential),
      });
      window.location.href = result.redirect || '/admin/passkeys';
    } catch (error) {
      setPasskeyMessage(root, error.message || 'No se pudo registrar la passkey.');
      button.disabled = false;
    }
  });
});
