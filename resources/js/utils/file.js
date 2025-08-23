// Shared file utilities (JavaScript)

// Map MIME type to PrimeIcons + color classes
export function getFileIcon(mime) {
  const map = {
    'application/pdf': 'pi pi-file-pdf text-red-500',
    'application/msword': 'pi pi-file-word text-blue-500',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'pi pi-file-word text-blue-500',
    'application/vnd.ms-excel': 'pi pi-file-excel text-green-500',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'pi pi-file-excel text-green-500',
    'image/jpeg': 'pi pi-image text-purple-500',
    'image/jpg': 'pi pi-image text-purple-500',
    'image/png': 'pi pi-image text-purple-500',
  };
  return map[mime] || 'pi pi-file';
}

// Pretty file size
export function formatFileSize(bytes) {
  if (!bytes) return '0 Bytes';
  const k = 1024;
  const sizes = ['Bytes', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return `${parseFloat((bytes / Math.pow(k, i)).toFixed(2))} ${sizes[i]}`;
}

// FE constants to mirror BE config/uploads.php
export const ALLOWED_QUOTATION_MIME_SET = new Set([
  'application/pdf',
  'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  'application/vnd.ms-excel',
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  'image/jpeg',
  'image/jpg',
  'image/png',
]);
export const ALLOWED_QUOTATION_EXTS = new Set(['.pdf', '.doc', '.docx', '.xls', '.xlsx', '.jpg', '.jpeg', '.png']);
export const ALLOWED_IMAGE_MIME_SET = new Set(['image/jpeg', 'image/jpg', 'image/png']);
export const MAX_IMAGE_BYTES = 10 * 1024 * 1024; // 10MB
export const MAX_QUOTATION_BYTES = 20 * 1024 * 1024; // 20MB

export function isAllowedMimeOrExt(file) {
  if (!file) return false;
  if (file.type && ALLOWED_QUOTATION_MIME_SET.has(file.type)) return true;
  const name = (file.name || '').toLowerCase();
  return Array.from(ALLOWED_QUOTATION_EXTS).some((ext) => name.endsWith(ext));
}

export function buildAcceptFromExts(extSet) {
  return Array.from(extSet).join(',');
}

// Extract first image file from paste event
export function fileFromPasteEvent(e) {
  const items = (e.clipboardData || e.originalEvent?.clipboardData)?.items || [];
  for (const item of items) {
    if (item.type && item.type.indexOf('image') !== -1) {
      const file = item.getAsFile && item.getAsFile();
      if (file) return file;
    }
  }
  return null;
}

// Extract first file from drop event
export function fileFromDropEvent(e) {
  const file = e?.dataTransfer?.files?.[0] || null;
  return file || null;
}

// Create an object URL for preview
export function objectUrl(file) {
  if (!file) return null;
  return URL.createObjectURL(file);
}
