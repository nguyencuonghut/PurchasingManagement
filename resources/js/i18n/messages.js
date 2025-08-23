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
    code_required: 'Mã báo cáo không được để trống.',
    code_max_255: 'Mã báo cáo không được vượt quá 255 ký tự.',
    description_required: 'Mô tả không được để trống.',
    description_max_1000: 'Mô tả không được vượt quá 1000 ký tự.',
  },
  head: {
    edit_title: 'Sửa BCLCNCC',
  },
  page: {
    edit_heading: 'Chỉnh sửa báo cáo',
  },
  form: {
    code_label: 'Mã',
    description_label: 'Mô tả',
    image_label: 'File báo cáo (ảnh)',
  },
  placeholder: {
    paste_or_drop_image: 'Dán ảnh (Ctrl+V) hoặc kéo thả ảnh vào đây.',
  },
  image_meta: {
    current: 'Ảnh hiện tại',
    using_saved: 'Đang dùng ảnh đã lưu',
    preview_alt: 'Xem trước ảnh',
  },
  actions: {
    delete_image: 'Xóa ảnh',
    back: 'Quay lại',
    save: 'Lưu',
  },
  quotation: {
    title: 'File báo giá',
    drop_hint: 'Kéo thả file báo giá vào đây hoặc click để chọn',
    supported: 'Hỗ trợ: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG',
    existing_title: 'File báo giá hiện có:',
    new_title: 'File mới thêm:',
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
