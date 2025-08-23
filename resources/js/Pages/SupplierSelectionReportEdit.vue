<template>
  <Head title="Sửa BCLCNCC" />

  <div class="card max-w-4xl mx-auto">
    <h2 class="text-xl font-semibold mb-4">Chỉnh sửa báo cáo</h2>

    <form @submit.prevent="save" class="flex flex-col gap-6">
      <!-- Code -->
      <div>
        <label for="code" class="block font-bold mb-2 required-field">Mã</label>
        <InputText id="code" v-model="v$.code.$model" :invalid="submitted && v$.code.$invalid" fluid />
        <small v-if="submitted && v$.code.$invalid" class="text-red-500">{{ v$.code.$errors[0]?.$message }}</small>
      </div>

      <!-- Description -->
      <div>
        <label for="description" class="block font-bold mb-2 required-field">Mô tả</label>
        <InputText id="description" v-model.trim="v$.description.$model" :invalid="submitted && v$.description.$invalid" fluid />
        <small v-if="submitted && v$.description.$invalid" class="text-red-500">{{ v$.description.$errors[0]?.$message }}</small>
      </div>

      <!-- file_path - ảnh đính kèm chính -->
      <div>
        <label class="block font-bold mb-2 required-field">File báo cáo (ảnh)</label>

        <!-- Khu vực dán / kéo-thả ảnh -->
        <div
          :key="editorKey"
          ref="pasteAreaRef"
          :contenteditable="isContentEditable"
          class="p-inputtext p-component p-editor-container"
          :class="{ 'has-content': imagePreviewSrc || (!showPlaceholder && pasteAreaRef?.innerText?.trim() !== '') }"
          style="min-height: 150px; border: 1px solid var(--surface-300); padding: 1rem; cursor: text; overflow: hidden;"
          @paste="handlePaste"
          @drop.prevent="handleDrop"
          @dragover.prevent
          @focus="handleFocus"
          @blur="handleBlur"
        >
          <div v-if="!imagePreviewSrc && showPlaceholder" class="paste-content-wrapper">
              <p class="placeholder-text">
              Dán ảnh (Ctrl+V) hoặc kéo thả ảnh vào đây.
              </p>
          </div>
          <div v-else-if="imagePreviewSrc" class="paste-content-wrapper">
              <img :src="imagePreviewSrc" alt="Image Preview" class="pasted-image-preview" />
          </div>
        </div>


        <!-- Thông tin / nút xoá ảnh -->
        <div class="mt-3 flex items-center gap-3" v-if="imagePreviewSrc || existingImageUrl">
          <i class="pi pi-image text-xl"></i>
          <span class="font-medium">Ảnh hiện tại</span>
          <span v-if="imageFile" class="text-color-secondary">{{ (imageFile.size / 1024).toFixed(2) }} KB ({{ imageFile.name }})</span>
          <span v-else-if="!imageFile && (existingImageUrl && !form.file_path_removed)">Đang dùng ảnh đã lưu</span>
          <Button label="Xóa ảnh" icon="pi pi-times" class="p-button-danger p-button-text p-button-sm ml-auto" @click="removeImage" />
        </div>

        <small v-if="submitted && form.errors.file_path" class="text-red-500">{{ form.errors.file_path }}</small>
      </div>

      <!-- Quotation Files -->
      <div>
        <label class="block font-bold mb-2">File báo giá</label>

        <!-- Kéo thả / chọn file mới -->
        <div
          class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center cursor-pointer hover:border-blue-400 transition-colors"
          @drop.prevent="handleQuotationFilesDrop"
          @dragover.prevent
          @click="$refs.quotationFilesInput.click()"
        >
          <i class="pi pi-cloud-upload text-4xl text-gray-400 mb-2"></i>
          <p class="text-gray-600 mb-1">Kéo thả file báo giá vào đây hoặc click để chọn</p>
          <p class="text-sm text-gray-500">Hỗ trợ: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG</p>
          <input
            type="file"
            ref="quotationFilesInput"
            @change="handleQuotationFilesSelect"
            multiple
            accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
            class="hidden"
          />
        </div>

        <!-- Danh sách file báo giá hiện có -->
        <div v-if="existingQuotationFiles.length > 0" class="mt-4">
          <h4 class="font-semibold mb-2">File báo giá hiện có:</h4>
          <div class="space-y-2">
            <div v-for="file in existingQuotationFiles" :key="file.id" class="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
              <div class="flex items-center space-x-3">
                <i :class="getFileIcon(file.file_type)" class="text-xl text-blue-600"></i>
                <div>
                  <a :href="file.file_url" target="_blank" class="font-medium text-sm text-blue-600 hover:underline">{{ file.file_name }}</a>
                  <p class="text-xs text-gray-500">{{ file.file_size_formatted }}</p>
                </div>
              </div>
              <Button icon="pi pi-trash" class="p-button-text p-button-danger p-button-sm" @click="markQuotationFileDeleted(file.id)" />
            </div>
          </div>
        </div>

        <!-- Danh sách file báo giá mới upload -->
        <div v-if="uploadedQuotationFiles.length > 0" class="mt-4">
          <h4 class="font-semibold mb-2">File mới thêm:</h4>
          <div class="space-y-2">
            <div v-for="(file, index) in uploadedQuotationFiles" :key="index" class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
              <div class="flex items-center space-x-3">
                <i :class="getFileIcon(file.type)" class="text-xl"></i>
                <div>
                  <p class="font-medium text-sm">{{ file.name }}</p>
                  <p class="text-xs text-gray-500">{{ formatFileSize(file.size) }}</p>
                </div>
              </div>
              <Button icon="pi pi-times" class="p-button-text p-button-danger p-button-sm" @click="removeNewQuotationFile(index)" />
            </div>
          </div>
        </div>

        <small v-if="submitted && form.errors.quotation_files" class="text-red-500">{{ form.errors.quotation_files }}</small>
      </div>

      <div class="flex justify-end gap-2">
        <Button type="button" label="Quay lại" icon="pi pi-arrow-left" outlined @click="goBack" />
        <Button type="submit" label="Lưu" icon="pi pi-check" :disabled="form.processing" />
      </div>
    </form>
  </div>
</template>

<script setup>
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, computed, toRefs, onUnmounted } from 'vue';
import { useVuelidate } from '@vuelidate/core';
import { required, maxLength, helpers } from '@vuelidate/validators';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import { useToast } from 'primevue/usetoast';
import { getFileIcon, formatFileSize, isAllowedMimeOrExt, fileFromPasteEvent, fileFromDropEvent, objectUrl } from '@/utils/file';
import { t } from '@/i18n/messages';


const props = defineProps({
  report: { type: Object, required: true },
});

const toast = useToast();

// ----- Form state
const form = useForm({
  code: props.report.code || '',
  description: props.report.description || '',
  file_path: props.report.image_url || null, // có thể là URL, base64, File hoặc null
  quotation_files: [], // File[] mới
  deleted_quotation_file_ids: [], // id[] file báo giá cũ đánh dấu xóa
  file_path_removed: false, // user click Xóa ảnh
});

// Lấy flash message (tuỳ app của bạn là flash hay auth.flash)
const page = usePage();
const flashMessage = computed(() =>
  page.props?.flash?.message ?? page.props?.auth?.flash?.message ?? ''
);

const editorKey = ref(0);

const { code, description } = toRefs(form);

// ----- Vuelidate
const rules = computed(() => ({
  code: {
    required: helpers.withMessage('Mã báo cáo không được để trống.', required),
    maxLength: helpers.withMessage('Mã báo cáo không được vượt quá 255 ký tự.', maxLength(255)),
  },
  description: {
    required: helpers.withMessage('Mô tả không được để trống.', required),
    maxLength: helpers.withMessage('Mô tả không được vượt quá 1000 ký tự.', maxLength(1000)),
  },
}));
const v$ = useVuelidate(rules, { code, description });
const submitted = ref(false);

// ----- Ảnh file_path (paste/drag)
const pasteAreaRef = ref(null);
const imagePreviewSrc = ref(props.report.image_url || null);
const existingImageUrl = computed(() => (form.file_path && typeof form.file_path === 'string' && !form.file_path.startsWith('data:image')) ? form.file_path : props.report.image_url || null);
const imageFile = ref(null);
const showPlaceholder = ref(!imagePreviewSrc.value);
const isContentEditable = ref(true);
const lastObjectUrl = ref(null);

const MAX_IMAGE_SIZE = 10 * 1024 * 1024; // 10MB
const MAX_DOC_SIZE = 20 * 1024 * 1024;   // 20MB

function setPreviewFromFile(file) {
  // Revoke previous object URL if any
  if (lastObjectUrl.value) {
    try { URL.revokeObjectURL(lastObjectUrl.value); } catch (e) { /* ignore */ }
    lastObjectUrl.value = null;
  }
  const url = objectUrl(file);
  lastObjectUrl.value = url;
  imagePreviewSrc.value = url;
}

onUnmounted(() => {
  if (lastObjectUrl.value) {
    try { URL.revokeObjectURL(lastObjectUrl.value); } catch (e) { /* ignore */ }
    lastObjectUrl.value = null;
  }
});


// Helper: lấy lỗi đầu (string hoặc array đều OK)
function firstErr(val) {
  if (Array.isArray(val)) return val[0] || null;
  if (typeof val === 'string') return val || null;
  return null;
}
function pickServerError(errors, fallback) {
  return (
    firstErr(errors?.file_path) ||
    firstErr(errors?.quotation_files) ||
    firstErr(errors?.code) ||
    firstErr(errors?.description) ||
    fallback ||
    'Có lỗi xảy ra khi cập nhật.'
  );
}


function handleFocus() { showPlaceholder.value = false; isContentEditable.value = true; }
function handleBlur() { if (!imagePreviewSrc.value && (!pasteAreaRef.value || pasteAreaRef.value.innerText.trim() === '')) showPlaceholder.value = true; }

function handlePaste(e) {
  e.preventDefault();
  showPlaceholder.value = false;
  const file = fileFromPasteEvent(e);
  if (!file) {
    toast.add({ severity: 'warn', summary: t('common.warn'), detail: t('paste.no_image'), life: 2500 });
    return;
  }
  if (!file.type || !file.type.startsWith('image/')) {
    toast.add({ severity: 'warn', summary: t('common.warn'), detail: t('image.only_accept'), life: 2500 });
    return;
  }
  if (file.size > MAX_IMAGE_SIZE) {
    toast.add({ severity: 'warn', summary: t('common.warn'), detail: t('image.too_large_10mb'), life: 3000 });
    return;
  }
  imageFile.value = file;
  setPreviewFromFile(file);
  form.file_path = file;
  form.file_path_removed = false;
}

function handleDrop(e) {
  const file = fileFromDropEvent(e);
  if (!file || !file.type || !file.type.startsWith('image/')) {
    toast.add({ severity: 'warn', summary: t('common.warn'), detail: t('image.only_accept'), life: 2500 });
    return;
  }
  if (file.size > MAX_IMAGE_SIZE) {
    toast.add({ severity: 'warn', summary: t('common.warn'), detail: t('image.too_large_10mb'), life: 3000 });
    return;
  }
  imageFile.value = file;
  setPreviewFromFile(file);
  form.file_path = file;
  form.file_path_removed = false;
}

function removeImage() {
  form.file_path_removed = true;
  form.file_path = null;

  imagePreviewSrc.value = null;
  showPlaceholder.value = true;
  isContentEditable.value = true;

  if (lastObjectUrl.value) {
    try { URL.revokeObjectURL(lastObjectUrl.value); } catch (e) { /* ignore */ }
    lastObjectUrl.value = null;
  }

  // ⚠️ Không đụng innerHTML nữa. Remount vùng editor để Vue tự quản lý DOM.
  editorKey.value++;
}


// ----- Quotation files
const existingQuotationFiles = ref(props.report.quotation_files || []);
const uploadedQuotationFiles = ref([]);

function handleQuotationFilesDrop(e) {
  addQuotationFiles(Array.from(e.dataTransfer.files || []));
}
function handleQuotationFilesSelect(e) {
  addQuotationFiles(Array.from(e.target.files || []));
}
function addQuotationFiles(files) {
  const valid = files.filter((f) => isAllowedMimeOrExt(f) && (f.size || 0) <= MAX_DOC_SIZE);
  if (valid.length !== files.length) toast.add({ severity: 'warn', summary: t('common.warn'), detail: t('files.some_invalid'), life: 2500 });
  uploadedQuotationFiles.value.push(...valid);
  form.quotation_files = [...uploadedQuotationFiles.value];
}
function removeNewQuotationFile(index) {
  uploadedQuotationFiles.value.splice(index, 1);
  form.quotation_files = [...uploadedQuotationFiles.value];
}
function markQuotationFileDeleted(id) {
  if (!form.deleted_quotation_file_ids.includes(id)) form.deleted_quotation_file_ids.push(id);
  existingQuotationFiles.value = existingQuotationFiles.value.filter(f => f.id !== id);
}

// getFileIcon, formatFileSize được import từ '@/utils/file'

function goBack() { router.visit('/supplier_selection_reports'); }

// ----- Save
async function save() {
  submitted.value = true;
  form.clearErrors();

  const ok = await v$.value.$validate();
  if (!ok) return;

  form.code = String(v$.value.code.$model ?? form.code ?? '');
  form.description = String(v$.value.description.$model ?? form.description ?? '');

  // ngay sau khi đã validate v$ và đồng bộ form.code/description...
  const isNewImageFile   = form.file_path instanceof File;
  const wantRemoveImage  = !!form.file_path_removed;

  // ❗ Nếu user xóa ảnh mà KHÔNG đính kèm ảnh mới -> chặn tại FE
  if (wantRemoveImage && !isNewImageFile) {
    toast.add({ severity: 'error', summary: t('common.error'), detail: 'Vui lòng đính kèm ảnh báo cáo trước khi lưu.', life: 3500 });
    return;
  }

  const hasUploadedFile =
    form.file_path instanceof File || (form.quotation_files?.length || 0) > 0;

  const hasDeletedExisting = (form.deleted_quotation_file_ids?.length || 0) > 0;

  const needsMultipart = hasUploadedFile || wantRemoveImage || hasDeletedExisting;

  if (needsMultipart) {
    // 🔁 Dùng POST + _method=PUT để PHP/Laravel parse multipart ổn định
    form
      .transform((data) => {
        const out = {
          _method: 'PUT',                         // 👈 quan trọng
          code: String(data.code ?? ''),
          description: String(data.description ?? ''),
          file_path_removed: !!data.file_path_removed,
          deleted_quotation_file_ids: Array.isArray(data.deleted_quotation_file_ids)
            ? data.deleted_quotation_file_ids
            : [],
          quotation_files: Array.isArray(data.quotation_files) ? data.quotation_files : [],
        };

        // file_path 4 nhánh
        if (data.file_path_removed) {
          out.file_path = null;
        } else if (data.file_path instanceof File) {
          out.file_path = data.file_path;
        } else if (typeof data.file_path === 'string') {
          out.file_path = data.file_path; // URL ảnh cũ
        }
        return out;
      })
      .post(`/supplier_selection_reports/${props.report.id}`, {   // 👈 dùng post()
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
          toast.add({ severity: 'success', summary: t('common.success'), detail: flashMessage.value || t('update.success'), life: 2500 });
          router.visit('/supplier_selection_reports');
        },
        onError: (errors) => {
          console.group('[Save] server errors (multipart)'); console.log(errors); console.groupEnd();
          toast.add({ severity: 'error', summary: t('common.error'), detail: pickServerError(errors, flashMessage.value) || t('update.error_generic'), life: 4000 });
        },
      });
  } else {
    // giữ nguyên nhánh không multipart như trước (PUT thẳng)
    form
      .transform((data) => ({
        code: String(data.code ?? ''),
        description: String(data.description ?? ''),
        file_path: typeof data.file_path === 'string' ? data.file_path : '',
        // 👇 THÊM DÒNG NÀY
        deleted_quotation_file_ids: Array.isArray(data.deleted_quotation_file_ids)
        ? data.deleted_quotation_file_ids
        : [],
      }))
      .put(`/supplier_selection_reports/${props.report.id}`, {
        preserveScroll: true,
        onSuccess: () => {
          toast.add({ severity: 'success', summary: t('common.success'), detail: flashMessage.value || t('update.success'), life: 2500 });
          router.visit('/supplier_selection_reports');
        },
        onError: (errors) => {
          console.group('[Save] server errors (no multipart)'); console.log(errors); console.groupEnd();
          toast.add({ severity: 'error', summary: t('common.error'), detail: pickServerError(errors, flashMessage.value) || t('update.error_generic'), life: 4000 });
        },
      });
  }
}


</script>

<style scoped>
.required-field::after { content: ' *'; color: red; margin-left: 2px; }
.paste-content-wrapper { display:flex; justify-content:center; align-items:center; min-height:140px; width:100%; text-align:center; color: var(--text-color-secondary); font-style: italic; background: var(--surface-100); border-radius: var(--border-radius); padding: 1rem; box-sizing: border-box; }
.pasted-image-preview { max-width: 100%; max-height: 100%; display:block; margin:auto; object-fit: contain; }
.p-editor-container { border: 1px solid var(--surface-300); border-radius: var(--border-radius); padding: 1rem; cursor: text; min-height: 150px; box-sizing: border-box; overflow: hidden; display:flex; justify-content:center; align-items:center; }
.p-editor-container.has-content { background-color: var(--surface-0); }
</style>
