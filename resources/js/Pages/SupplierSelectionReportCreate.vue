<template>
  <Head title="Tạo BCLCNCC" />

  <div class="card max-w-4xl mx-auto">
    <h2 class="text-xl font-semibold mb-4">Tạo báo cáo lựa chọn nhà cung cấp</h2>

    <form @submit.prevent="save" class="flex flex-col gap-6">
      <div v-if="parentReport" class="mb-4 p-3 border-l-4 border-yellow-400 bg-yellow-50 rounded">
        <b>Phiếu cha bị từ chối:</b>
        <div>Mã: {{ parentReport.code }}</div>
        <div>Mô tả: {{ parentReport.description }}</div>
        <div>Admin Thu Mua: {{ parentReport.admin_thu_mua_name }}</div>
        <div> parent_report_id: {{ form.parent_report_id }}</div>
          <!-- Hidden input để truyền parent_report_id xuống backend -->
          <input type="hidden" name="parent_report_id" v-model="form.parent_report_id" />
      </div>

      <!-- Code is auto-generated, no input field -->


      <!-- Description -->
      <div>
        <label for="description" class="block font-bold mb-2 required-field">Mô tả</label>
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
        <label class="block font-bold mb-2 required-field">Ảnh báo cáo</label>

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

        <div class="mt-3 flex items-center gap-3" v-if="imagePreviewSrc">
          <i class="pi pi-image text-xl"></i>
          <span class="font-medium">Ảnh đã chọn</span>
          <span v-if="imageFile" class="text-color-secondary">{{ (imageFile.size / 1024).toFixed(2) }} KB ({{ imageFile.name }})</span>
          <Button label="Xóa ảnh" icon="pi pi-times" class="p-button-danger p-button-text p-button-sm ml-auto" @click="removeImage" />
        </div>

        <small v-if="submitted && form.errors.file_path" class="text-red-500">{{ form.errors.file_path }}</small>
      </div>


      <!-- Quotation Files -->
      <div>
        <label class="block font-bold mb-2 required-field">File báo giá</label>
        <div
          class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center cursor-pointer hover:border-blue-400 transition-colors"
          @drop.prevent="handleQuotationFilesDrop"
          @dragover.prevent
          @click="$refs.quotationFilesInput.click()"
        >
          <i class="pi pi-cloud-upload text-4xl text-green-400 mb-2"></i>
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

      <!-- Proposal/BOQ Files -->
      <div>
        <label class="block font-bold mb-2 required-field">File đề nghị/BOQ</label>
        <div
          class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center cursor-pointer hover:border-green-400 transition-colors"
          @drop.prevent="handleProposalFilesDrop"
          @dragover.prevent
          @click="$refs.proposalFilesInput.click()"
        >
          <i class="pi pi-cloud-upload text-4xl text-green-400 mb-2"></i>
          <p class="text-gray-600 mb-1">Kéo thả file đề nghị/BOQ vào đây hoặc click để chọn</p>
          <p class="text-sm text-gray-500">Hỗ trợ: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG</p>
          <input
            type="file"
            ref="proposalFilesInput"
            @change="handleProposalFilesSelect"
            multiple
            accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
            class="hidden"
          />
        </div>
        <div v-if="uploadedProposalFiles.length > 0" class="mt-4">
          <h4 class="font-semibold mb-2">File mới thêm:</h4>
          <div class="space-y-2">
            <div v-for="(file, index) in uploadedProposalFiles" :key="index" class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
              <div class="flex items-center space-x-3">
                <i :class="getFileIcon(file.type)" class="text-xl"></i>
                <div>
                  <p class="font-medium text-sm">{{ file.name }}</p>
                  <p class="text-xs text-gray-500">{{ formatFileSize(file.size) }}</p>
                </div>
              </div>
              <Button icon="pi pi-times" class="p-button-text p-button-danger p-button-sm" @click="removeNewProposalFile(index)" />
            </div>
          </div>
        </div>
        <small v-if="submitted && form.errors.proposal_files" class="text-red-500">{{ form.errors.proposal_files }}</small>
      </div>

      <div class="flex justify-end gap-2">
        <Button type="button" label="Quay lại" icon="pi pi-arrow-left" outlined @click="goBack" />
        <Button label="Lưu" icon="pi pi-check" :disabled="v$.$invalid || form.processing" @click="save" />
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
import { useToast } from 'primevue/usetoast';

const toast = useToast();

// ----- Form state
const page = usePage();
const adminThuMuaUsers = computed(() => page.props.admin_thu_mua_users || []);
const form = useForm({
  description: '',
  admin_thu_mua_id: '',
  file_path: null,           // có thể là URL, base64, File hoặc null
  quotation_files: [],       // File[] mới
  proposal_files: [],        // File[] mới
  parent_report_id: '',      // ID phiếu cha bị từ chối (nếu có)
});

watch(
  () => usePage().props.auth.flash,
  (val) => {
    if (val && val.message) {
      toast.add({
        severity: val.type === 'error' ? 'error' : 'success',
        summary: val.type === 'error' ? 'Lỗi' : 'Thành công',
        detail: val.message,
        life: 3000
      });
    }
  },
  { immediate: true }
);

// Xử lý cho phiếu tạo từ phiếu bị rejected
const initData = computed(() => page.props.init_data || {});
const parentReport = computed(() => page.props.parent_report || null);
watch(
  () => initData.value,
  (val) => {
    if (val) {
      if (val.description) form.description = val.description;
      if (val.adm_id) form.admin_thu_mua_id = val.adm_id;
      if (val.parent_report_id) form.parent_report_id = val.parent_report_id;
    }
  },
  { immediate: true }
);

// ----- Form actions
const editorKey = ref(0);

const { description } = toRefs(form);

// ----- Vuelidate
const rules = computed(() => ({
  description: {
    required: helpers.withMessage('Mô tả không được để trống.', required),
    maxLength: helpers.withMessage('Mô tả không được vượt quá 255 ký tự.', maxLength(255)),
  },
  description: {
    required: helpers.withMessage('Mô tả không được để trống.', required),
    maxLength: helpers.withMessage('Mô tả không được vượt quá 1000 ký tự.', maxLength(1000)),
  },
  file_path: {
    required: helpers.withMessage('Ảnh báo cáo bắt buộc.', required),
  },
  quotation_files: {
    required: helpers.withMessage('File báo giá bắt buộc.', value => Array.isArray(value) && value.length > 0),
  },
  admin_thu_mua_id: {
    required: helpers.withMessage('Vui lòng chọn Admin Thu Mua.', required),
  },
}));
const v$ = useVuelidate(rules, form);
const submitted = ref(false);

// ----- Ảnh file_path (paste/drag)
const pasteAreaRef = ref(null);
const imagePreviewSrc = ref(null);
const imageFile = ref(null);
const showPlaceholder = ref(true);
const isContentEditable = ref(true);

function handleFocus() { showPlaceholder.value = false; isContentEditable.value = true; }
function handleBlur() { if (!imagePreviewSrc.value && (!pasteAreaRef.value || pasteAreaRef.value.innerText.trim() === '')) showPlaceholder.value = true; }

function handlePaste(e) {
  e.preventDefault(); showPlaceholder.value = false;
  const items = (e.clipboardData || e.originalEvent?.clipboardData)?.items || [];
  for (const item of items) {
    if (item.type.indexOf('image') !== -1) {
      const file = item.getAsFile();
      imageFile.value = file;
      const reader = new FileReader();
      reader.onload = (ev) => { imagePreviewSrc.value = ev.target.result; form.file_path = ev.target.result; };
      reader.readAsDataURL(file);
      return;
    }
  }
}

function handleDrop(e) {
  const file = e.dataTransfer.files?.[0];
  if (!file || !file.type.startsWith('image/')) { toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Chỉ chấp nhận file ảnh.', life: 2500 }); return; }
  imageFile.value = file;
  const reader = new FileReader();
  reader.onload = (ev) => { imagePreviewSrc.value = ev.target.result; form.file_path = ev.target.result; };
  reader.readAsDataURL(file);
}

function removeImage() {
  form.file_path = null;
  imagePreviewSrc.value = null;
  showPlaceholder.value = true;
  isContentEditable.value = true;
  editorKey.value++;
}


// ----- Proposal/BOQ files
const uploadedProposalFiles = ref([]);
function handleProposalFilesDrop(e) { addProposalFiles(Array.from(e.dataTransfer.files || [])); }
function handleProposalFilesSelect(e) { addProposalFiles(Array.from(e.target.files || [])); }
function addProposalFiles(files) {
  const allowedTypes = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'image/jpeg', 'image/jpg', 'image/png'
  ];
  const allowedExtensions = ['.pdf', '.doc', '.docx', '.xls', '.xlsx', '.jpg', '.jpeg', '.png'];
  const valid = [];
  const invalid = [];
  files.forEach(f => {
    let ok = false;
    const ext = f.name && f.name.lastIndexOf('.') !== -1 ? f.name.slice(f.name.lastIndexOf('.')).toLowerCase() : '';
    if (allowedTypes.includes(f.type)) {
      ok = true;
    } else if (
      (f.type === 'application/octet-stream' || f.type === '' || f.type.startsWith('application/'))
      && allowedExtensions.includes(ext)
    ) {
      ok = true;
    }
    if (ok) {
      valid.push(f);
    } else {
      invalid.push(f);
    }
  });
  if (invalid.length > 0) {
    console.warn('[ProposalFiles] Các file bị loại:', invalid.map(f => f.name));
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Một số file không được hỗ trợ và đã bị bỏ qua.', life: 2500 });
  }
  uploadedProposalFiles.value.push(...valid);
  form.proposal_files = [...uploadedProposalFiles.value];
}
function removeNewProposalFile(index) {
  uploadedProposalFiles.value.splice(index, 1);
  form.proposal_files = [...uploadedProposalFiles.value];
}

// ----- Quotation files
const uploadedQuotationFiles = ref([]);

function handleQuotationFilesDrop(e) { addQuotationFiles(Array.from(e.dataTransfer.files || [])); }
function handleQuotationFilesSelect(e) { addQuotationFiles(Array.from(e.target.files || [])); }
function addQuotationFiles(files) {
  const allowedTypes = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'image/jpeg', 'image/jpg', 'image/png'
  ];
  const allowedExtensions = ['.pdf', '.doc', '.docx', '.xls', '.xlsx', '.jpg', '.jpeg', '.png'];
  const valid = [];
  const invalid = [];
  files.forEach(f => {
    let ok = false;
    const ext = f.name && f.name.lastIndexOf('.') !== -1 ? f.name.slice(f.name.lastIndexOf('.')).toLowerCase() : '';
    // Chấp nhận nếu đúng mime type hoặc đúng extension
    if (allowedTypes.includes(f.type)) {
      ok = true;
    } else if (
      (f.type === 'application/octet-stream' || f.type === '' || f.type.startsWith('application/'))
      && allowedExtensions.includes(ext)
    ) {
      ok = true;
    }
    if (ok) {
      valid.push(f);
    } else {
      invalid.push(f);
    }
  });
  if (invalid.length > 0) {
    console.warn('[QuotationFiles] Các file bị loại:', invalid.map(f => f.name));
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Một số file không được hỗ trợ và đã bị bỏ qua.', life: 2500 });
  }
  uploadedQuotationFiles.value.push(...valid);
  form.quotation_files = [...uploadedQuotationFiles.value];
}
function removeNewQuotationFile(index) {
  uploadedQuotationFiles.value.splice(index, 1);
  form.quotation_files = [...uploadedQuotationFiles.value];
}

function getFileIcon(mime) {
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
function formatFileSize(bytes) {
  if (!bytes) return '0 Bytes';
  const k = 1024; const sizes = ['Bytes','KB','MB','GB']; const i = Math.floor(Math.log(bytes)/Math.log(k));
  return `${parseFloat((bytes / Math.pow(k,i)).toFixed(2))} ${sizes[i]}`;
}

function dataURLtoBlob(dataurl) {
  const arr = dataurl.split(','), mime = arr[0].match(/:(.*?);/)[1];
  const bstr = atob(arr[1]); const len = bstr.length; const u8 = new Uint8Array(len);
  for (let i=0;i<len;i++) u8[i] = bstr.charCodeAt(i);
  return new Blob([u8], { type: mime });
}

function goBack() { router.replace('/supplier_selection_reports'); }

// ----- Save
async function save() {
  submitted.value = true;
  form.clearErrors();

  // Validate other fields except code
  const ok = await v$.value.$validate();
  if (!ok) return;

  // Code will be generated by backend, do not send from frontend
  form.description = String(v$.value.description.$model ?? form.description ?? '');

  const hasNewInlineImage = typeof form.file_path === 'string' && form.file_path.startsWith('data:image');
  const hasUploadedQuotationFiles = (form.quotation_files?.length || 0) > 0;
  const hasUploadedProposalFiles = (form.proposal_files?.length || 0) > 0;
  const needsMultipart = hasNewInlineImage || hasUploadedQuotationFiles || hasUploadedProposalFiles;

  if (needsMultipart) {
    form
      .transform((data) => {
        const out = {
          description: String(data.description ?? ''),
          quotation_files: Array.isArray(data.quotation_files) ? data.quotation_files : [],
          proposal_files: Array.isArray(data.proposal_files) ? data.proposal_files : [],
          admin_thu_mua_id: data.admin_thu_mua_id || '',
          parent_report_id: data.parent_report_id || '',
        };
        if (typeof data.file_path === 'string' && data.file_path.startsWith('data:image')) {
          out.file_path = dataURLtoBlob(data.file_path);
        } else if (data.file_path instanceof File) {
          out.file_path = data.file_path;
        }
        return out;
      })
      .post('/supplier_selection_reports', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
          // Chỉ reset form nếu không có lỗi từ BE (flash type error)
          const flash = usePage().props.auth.flash;
          if (!flash || flash.type !== 'error') {
            form.reset();
          }
        },
        onError: (errors) => {
          form.errors = errors;
          submitted.value = true;
          console.group('[Create] server errors (multipart)'); console.log(errors); console.groupEnd();
        },
      });
  } else {
    form
      .transform((data) => ({
        description: String(data.description ?? ''),
        file_path: typeof data.file_path === 'string' ? data.file_path : '',
        admin_thu_mua_id: data.admin_thu_mua_id || '',
        parent_report_id: data.parent_report_id || '',
      }))
      .post('/supplier_selection_reports', {
        preserveScroll: true,
        onSuccess: () => {
          const flash = usePage().props.flash;
          if (!flash || flash.type !== 'error') {
            form.reset();
          }
        },
        onError: (errors) => {
          form.errors = errors;
          submitted.value = true;
          console.group('[Create] server errors (no multipart)'); console.log(errors); console.groupEnd();
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
