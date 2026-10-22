import Swal from 'sweetalert2';

function buildMessageHtml(text) {
  if (!text) return '';
  const safeText = String(text).replace(/[&<>"']/g, (character) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  })[character]);
  return `<p class="siakad-alert-message">"${safeText}"</p>`;
}

function getBaseOptions() {
  return {
    customClass: {
      container: 'siakad-alert-container',
      popup: 'siakad-alert-popup',
      icon: 'siakad-alert-icon',
      title: 'siakad-alert-title',
      htmlContainer: 'siakad-alert-html',
      actions: 'siakad-alert-actions',
      confirmButton: 'siakad-alert-btn siakad-alert-btn--primary',
      cancelButton: 'siakad-alert-btn siakad-alert-btn--secondary',
      input: 'siakad-alert-input',
      inputLabel: 'siakad-alert-input-label',
      validationMessage: 'siakad-alert-validation-message',
    },
    buttonsStyling: false,
    backdrop: 'rgba(148, 148, 148, 0.72)',
  };
}

export async function confirmAction({
  title,
  text,
  icon = 'warning',
  confirmText = 'Ya',
  cancelText = 'Batal'
}) {
  const result = await Swal.fire({
    ...getBaseOptions(),
    title,
    html: buildMessageHtml(text),
    icon,
    showCancelButton: true,
    confirmButtonText: confirmText,
    cancelButtonText: cancelText,
  });
  return result.isConfirmed;
}

export function toastSuccess(title, text) {
  return Swal.fire({
    ...getBaseOptions(),
    icon: 'success',
    title,
    html: buildMessageHtml(text),
    confirmButtonText: 'OK',
  });
}

export function toastError(title, text) {
  return Swal.fire({
    ...getBaseOptions(),
    icon: 'error',
    title,
    html: buildMessageHtml(text),
    confirmButtonText: 'OK',
  });
}

export function toastValidation(title, text) {
  return Swal.fire({
    ...getBaseOptions(),
    icon: 'warning',
    title,
    html: buildMessageHtml(text),
    confirmButtonText: 'OK',
  });
}

export function showLoadingAlert(title = 'Memproses...') {
  return Swal.fire({
    ...getBaseOptions(),
    title,
    allowOutsideClick: false,
    didOpen: () => Swal.showLoading(),
  });
}

export function closeAlert() {
  Swal.close();
}
