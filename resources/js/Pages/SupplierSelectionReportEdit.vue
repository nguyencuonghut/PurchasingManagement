<template>
  <Head :title="t('head.edit_title')" />

  <div class="card max-w-4xl mx-auto">
    <h2 class="text-xl font-semibold mb-4">{{ t('page.edit_heading') }}</h2>

    <form @submit.prevent="save" class="flex flex-col gap-6">
      <!-- Code -->
      <div>
        <label for="code" class="block font-bold mb-2 required-field">{{ t('form.code_label') }}</label>
        <InputText id="code" v-model="v$.code.$model" :invalid="submitted && v$.code.$invalid" fluid />
        <small v-if="submitted && v$.code.$invalid" class="text-red-500">{{ v$.code.$errors[0]?.$message }}</small>
      </div>

      <!-- Description -->
      <div>
        <label for="description" class="block font-bold mb-2 required-field">{{ t('form.description_label') }}</label>
        <InputText id="description" v-model.trim="v$.description.$model" :invalid="submitted && v$.description.$invalid" fluid />
        <small v-if="submitted && v$.description.$invalid" class="text-red-500">{{ v$.description.$errors[0]?.$message }}</small>
      </div>

      <!-- file_path - ảnh đính kèm chính -->
      <div>
        <label class="block font-bold mb-2 required-field">{{ t('form.image_label') }}</label>

        <DragDropImageUpload
          v-model="form.file_path"
          :existing-image-url="existingImageUrl"
          :max-size="MAX_IMAGE_SIZE"
          :removed="form.file_path_removed"
          @remove="onImageRemove"
        />

        <small v-if="submitted && form.errors.file_path" class="text-red-500">{{ form.errors.file_path }}</small>
      </div>

      <!-- Quotation Files -->
      <div>
        <label class="block font-bold mb-2">{{ t('quotation.title') }}</label>

        <QuotationFilesUploadList
          v-model="uploadedQuotationFiles"
          :existing-files="existingQuotationFiles"
          :max-size="MAX_DOC_SIZE"
          @delete-existing="markQuotationFileDeleted"
        />

        <small v-if="submitted && form.errors.quotation_files" class="text-red-500">{{ form.errors.quotation_files }}</small>
      </div>

      <div class="flex justify-end gap-2">
        <Button type="button" :label="t('actions.back')" icon="pi pi-arrow-left" outlined @click="goBack" />
        <Button type="submit" :label="t('actions.save')" icon="pi pi-check" :disabled="form.processing" />
      </div>
    </form>
  </div>
</template>

<script setup>
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, computed, toRefs, watch } from 'vue';
import { useVuelidate } from '@vuelidate/core';
import { required, maxLength, helpers } from '@vuelidate/validators';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import { useToast } from 'primevue/usetoast';
import DragDropImageUpload from '@/components/DragDropImageUpload.vue';
import QuotationFilesUploadList from '@/components/QuotationFilesUploadList.vue';
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
    required: helpers.withMessage(t('validation.code_required'), required),
    maxLength: helpers.withMessage(t('validation.code_max_255'), maxLength(255)),
  },
  description: {
    required: helpers.withMessage(t('validation.description_required'), required),
    maxLength: helpers.withMessage(t('validation.description_max_1000'), maxLength(1000)),
  },
}));
const v$ = useVuelidate(rules, { code, description });
const submitted = ref(false);

// ----- Ảnh file_path
const existingImageUrl = computed(() => (form.file_path && typeof form.file_path === 'string' && !form.file_path.startsWith('data:image')) ? form.file_path : props.report.image_url || null);

const MAX_IMAGE_SIZE = 10 * 1024 * 1024; // 10MB
const MAX_DOC_SIZE = 20 * 1024 * 1024;   // 20MB

function onImageRemove() {
  form.file_path_removed = true;
  form.file_path = null;
}

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
    t('update.error_generic')
  );
}


// removal handled via onImageRemove and child component


// ----- Quotation files
const existingQuotationFiles = ref(props.report.quotation_files || []);
const uploadedQuotationFiles = ref([]);
watch(uploadedQuotationFiles, (val) => {
  form.quotation_files = Array.isArray(val) ? [...val] : [];
});
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
    toast.add({ severity: 'error', summary: t('common.error'), detail: t('validation.image_required'), life: 3500 });
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
