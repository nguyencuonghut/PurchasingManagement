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

// Check allowed by MIME type or extension
const allowedMimes = new Set([
  'application/pdf',
  'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  'application/vnd.ms-excel',
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  'image/jpeg',
  'image/jpg',
  'image/png',
]);
const allowedExts = new Set(['.pdf', '.doc', '.docx', '.xls', '.xlsx', '.jpg', '.jpeg', '.png']);

export function isAllowedMimeOrExt(file) {
  if (!file) return false;
  if (file.type && allowedMimes.has(file.type)) return true;
  const name = (file.name || '').toLowerCase();
  return Array.from(allowedExts).some((ext) => name.endsWith(ext));
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
