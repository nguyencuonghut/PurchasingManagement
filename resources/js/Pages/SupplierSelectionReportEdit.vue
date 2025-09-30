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

      <!-- Admin Thu Mua -->
      <div>
        <label for="admin_thu_mua_id" class="block font-bold mb-2 required-field">Admin Thu Mua</label>
        <Select
          id="admin_thu_mua_id"
          v-model="form.admin_thu_mua_id"
          :options="adminThuMuaUsers"
          optionLabel="name"
          optionValue="id"
          placeholder="Chọn Admin Thu Mua"
          class="w-full"
        >
          <template #option="slotProps">
            {{ slotProps.option.name }} ({{ slotProps.option.email }})
          </template>
        </Select>
        <small v-if="submitted && !form.admin_thu_mua_id" class="text-red-500">Vui lòng chọn Admin Thu Mua</small>
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
          existing-title="File báo giá hiện có:"
          @delete-existing="markQuotationFileDeleted"
          @invalid-files="onInvalidQuotationFiles"
          @oversize-files="onOversizeQuotationFiles"
        />

        <small v-if="submitted && form.errors.quotation_files" class="text-red-500">{{ form.errors.quotation_files }}</small>
      </div>

      <!-- Proposal/BOQ Files -->
      <div>
        <label class="block font-bold mb-2">File đề nghị/BOQ</label>
        <QuotationFilesUploadList
          v-model="uploadedProposalFiles"
          :existing-files="existingProposalFiles"
          :max-size="MAX_DOC_SIZE"
          existing-title="File đề nghị/BOQ hiện có:"
          @delete-existing="markProposalFileDeleted"
          @invalid-files="onInvalidProposalFiles"
          @oversize-files="onOversizeProposalFiles"
        />
        <small v-if="submitted && form.errors.proposal_files" class="text-red-500">{{ form.errors.proposal_files }}</small>
      </div>

      <div class="flex justify-end gap-2">
        <Button type="button" :label="t('actions.back')" icon="pi pi-arrow-left" outlined @click="goBack" />
        <Button type="submit" :label="saveButtonLabel" icon="pi pi-check" :disabled="form.processing" />
      </div>

      <div v-if="form.progress" class="mt-3">
        <ProgressBar :value="form.progress.percentage" />
        <div class="mt-1 text-right text-sm text-gray-600">{{ form.progress.percentage }}%</div>
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
import Select from 'primevue/select';
import ProgressBar from 'primevue/progressbar';
import { useToast } from 'primevue/usetoast';
import DragDropImageUpload from '@/components/DragDropImageUpload.vue';
import QuotationFilesUploadList from '@/components/QuotationFilesUploadList.vue';
import { t } from '@/i18n/messages';


const props = defineProps({
  report: { type: Object, required: true },
  admin_thu_mua_users: { type: Array, default: () => [] },
});
const adminThuMuaUsers = computed(() => props.admin_thu_mua_users || []);

const toast = useToast();

// ----- Form state
const form = useForm({
  code: props.report.code || '',
  description: props.report.description || '',
  admin_thu_mua_id: props.report.admin_thu_mua_id !== undefined && props.report.admin_thu_mua_id !== null && props.report.admin_thu_mua_id !== ''
    ? Number(props.report.admin_thu_mua_id)
    : '',
  file_path: props.report.image_url || null, // có thể là URL, base64, File hoặc null
  quotation_files: [], // File[] mới
  deleted_quotation_file_ids: [], // id[] file báo giá cũ đánh dấu xóa
  file_path_removed: false, // user click Xóa ảnh
  proposal_files: [], // File[] mới
  deleted_proposal_file_ids: [], // id[] file đề nghị/BOQ cũ đánh dấu xóa
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
  admin_thu_mua_id: {
    required: helpers.withMessage('Vui lòng chọn Admin Thu Mua.', required),
  },
}));
const v$ = useVuelidate(rules, form);
const submitted = ref(false);
const saveButtonLabel = computed(() => {
  const p = form.progress?.percentage;
  return typeof p === 'number' ? `${t('actions.saving')} ${p}%` : t('actions.save');
});

// ----- Ảnh file_path
const existingImageUrl = computed(() => (form.file_path && typeof form.file_path === 'string' && !form.file_path.startsWith('data:image')) ? form.file_path : props.report.image_url || null);

const MAX_IMAGE_SIZE = 10 * 1024 * 1024; // 10MB
const MAX_DOC_SIZE = 20 * 1024 * 1024;   // 20MB

function onImageRemove() {
  form.file_path_removed = true;
  form.file_path = null;
}

// If user selects a new File, clear removal flag
watch(() => form.file_path, (val) => {
  if (val instanceof File) {
    form.file_path_removed = false;
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
    t('update.error_generic')
  );
}


// removal handled via onImageRemove and child component


// ----- Quotation files
// ----- Proposal/BOQ files
const existingProposalFiles = ref(
  normalizeFiles(props.report.proposal_files) || normalizeFiles(props.report.proposalFiles)
);
const uploadedProposalFiles = ref([]);
watch(uploadedProposalFiles, (val) => {
  form.proposal_files = Array.isArray(val) ? [...val] : [];
});
const deletedProposalFileIds = ref([]);
function markProposalFileDeleted(id) {
  if (!deletedProposalFileIds.value.includes(id)) deletedProposalFileIds.value.push(id);
  form.deleted_proposal_file_ids = [...deletedProposalFileIds.value];
  existingProposalFiles.value = existingProposalFiles.value.filter(f => f.id !== id);
}
function onInvalidProposalFiles() {
  toast.add({ severity: 'warn', summary: t('common.warn'), detail: 'Một số file đề nghị/BOQ không hợp lệ.', life: 2500 });
}
function onOversizeProposalFiles() {
  toast.add({ severity: 'warn', summary: t('common.warn'), detail: 'File đề nghị/BOQ vượt quá 20MB.', life: 3000 });
}
function normalizeFiles(input) {
  if (Array.isArray(input)) return input;
  if (input && Array.isArray(input.data)) return input.data;
  return [];
}
const existingQuotationFiles = ref(
  normalizeFiles(props.report.quotation_files) || normalizeFiles(props.report.quotationFiles)
);
const uploadedQuotationFiles = ref([]);
watch(uploadedQuotationFiles, (val) => {
  form.quotation_files = Array.isArray(val) ? [...val] : [];
});
function markQuotationFileDeleted(id) {
  if (!form.deleted_quotation_file_ids.includes(id)) form.deleted_quotation_file_ids.push(id);
  existingQuotationFiles.value = existingQuotationFiles.value.filter(f => f.id !== id);
}

function onInvalidQuotationFiles() {
  toast.add({ severity: 'warn', summary: t('common.warn'), detail: t('files.some_invalid'), life: 2500 });
}

function onOversizeQuotationFiles() {
  toast.add({ severity: 'warn', summary: t('common.warn'), detail: t('files.too_large_20mb'), life: 3000 });
}

// getFileIcon, formatFileSize được import từ '@/utils/file'

function goBack() { router.visit('/supplier_selection_reports'); }

// ----- Save
async function save() {
  submitted.value = true;
  form.clearErrors();

  const ok = await v$.value.$validate();
  if (!ok) return

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
    form.file_path instanceof File || (form.quotation_files?.length || 0) > 0 || (form.proposal_files?.length || 0) > 0;

  const hasDeletedExisting = (form.deleted_quotation_file_ids?.length || 0) > 0 || (form.deleted_proposal_file_ids?.length || 0) > 0;

  const needsMultipart = hasUploadedFile || wantRemoveImage || hasDeletedExisting;

  if (needsMultipart) {
    // 🔁 Dùng POST + _method=PUT để PHP/Laravel parse multipart ổn định
    form
      .transform((data) => {
        const out = {
          _method: 'PUT',                         // 👈 quan trọng
          code: String(data.code ?? ''),
          description: String(data.description ?? ''),
          admin_thu_mua_id: data.admin_thu_mua_id,
          file_path_removed: !!data.file_path_removed,
          deleted_quotation_file_ids: Array.isArray(data.deleted_quotation_file_ids)
            ? data.deleted_quotation_file_ids
            : [],
          quotation_files: Array.isArray(data.quotation_files) ? data.quotation_files : [],
          deleted_proposal_file_ids: Array.isArray(data.deleted_proposal_file_ids)
            ? data.deleted_proposal_file_ids
            : [],
          proposal_files: Array.isArray(data.proposal_files) ? data.proposal_files : [],
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
        admin_thu_mua_id: data.admin_thu_mua_id,
        description: String(data.description ?? ''),
        file_path: typeof data.file_path === 'string' ? data.file_path : '',
        deleted_quotation_file_ids: Array.isArray(data.deleted_quotation_file_ids)
          ? data.deleted_quotation_file_ids
          : [],
        deleted_proposal_file_ids: Array.isArray(data.deleted_proposal_file_ids)
          ? data.deleted_proposal_file_ids
          : [],
        proposal_files: Array.isArray(data.proposal_files) ? data.proposal_files : [],
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
