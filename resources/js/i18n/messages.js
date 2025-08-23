// Simple i18n helper. Extend as needed.
const vi = {
  common: {
    warn: 'Cảnh báo',
    success: 'Thành công',
    error: 'Lỗi',
  },
  paste: {
    no_image: 'Không có ảnh nào được dán.',
  },
  image: {
    only_accept: 'Chỉ chấp nhận file ảnh.',
    too_large_10mb: 'Ảnh vượt quá dung lượng tối đa 10MB.',
  },
  files: {
    some_invalid: 'Một số file không được hỗ trợ và đã bị bỏ qua.',
  },
  update: {
    success: 'Cập nhật thành công',
    error_generic: 'Có lỗi xảy ra khi cập nhật.',
  },
  validation: {
    image_required: 'Vui lòng đính kèm ảnh báo cáo trước khi lưu.',
  },
};

export function t(key) {
  const parts = String(key).split('.');
  let cur = vi;
  for (const p of parts) {
    if (cur && Object.prototype.hasOwnProperty.call(cur, p)) cur = cur[p];
    else return key; // fallback to key
  }
  return typeof cur === 'string' ? cur : key;
}

export default { t };
