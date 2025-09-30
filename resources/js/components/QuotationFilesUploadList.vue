<template>
  <div>
    <!-- Dropzone / click-to-select -->
    <div
      class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center cursor-pointer hover:border-blue-400 transition-colors"
      @drop.prevent="onDrop"
      @dragover.prevent
      @click="$refs.fileInput.click()"
    >
      <i class="pi pi-cloud-upload text-4xl text-gray-400 mb-2"></i>
      <p class="text-gray-600 mb-1">{{ t('quotation.drop_hint') }}</p>
      <p class="text-sm text-gray-500">{{ t('quotation.supported') }}</p>
      <input
        type="file"
        ref="fileInput"
        @change="onSelect"
        multiple
        :accept="accept"
        class="hidden"
      />
    </div>

    <!-- Existing files -->
    <div v-if="existingFiles.length > 0" class="mt-4">
      <h4 class="font-semibold mb-2">{{ existingTitle || t('quotation.existing_title') }}</h4>
      <div class="space-y-2">
        <div v-for="file in existingFiles" :key="file.id" class="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
          <div class="flex items-center space-x-3">
            <i :class="getFileIcon(file.file_type)" class="text-xl text-blue-600"></i>
            <div>
              <a :href="file.file_url" target="_blank" class="font-medium text-sm text-blue-600 hover:underline">{{ file.file_name }}</a>
              <div class="mt-1 flex items-center gap-2">
                <span class="px-2 py-0.5 text-xs rounded bg-gray-200 text-gray-700 uppercase">{{ extFromExisting(file) }}</span>
                <span class="px-2 py-0.5 text-xs rounded bg-gray-200 text-gray-700">{{ file.file_size_formatted }}</span>
              </div>
            </div>
          </div>
          <Button :label="t('actions.delete')" icon="pi pi-trash" class="p-button-text p-button-danger p-button-sm" @click="confirmDeleteExisting(file.id)" />
        </div>
      </div>
    </div>

    <!-- Newly uploaded files -->
    <div v-if="modelValue.length > 0" class="mt-4">
      <h4 class="font-semibold mb-2">{{ t('quotation.new_title') }}</h4>
      <div class="space-y-2">
        <div v-for="(file, index) in modelValue" :key="index" class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
          <div class="flex items-center space-x-3">
            <i :class="getFileIcon(file.type)" class="text-xl"></i>
            <div>
              <p class="font-medium text-sm">{{ file.name }}</p>
              <div class="mt-1 flex items-center gap-2">
                <span class="px-2 py-0.5 text-xs rounded bg-gray-200 text-gray-700 uppercase">{{ extFromNew(file) }}</span>
                <span class="px-2 py-0.5 text-xs rounded bg-gray-200 text-gray-700">{{ formatFileSize(file.size) }}</span>
              </div>
            </div>
          </div>
          <Button :label="t('actions.delete')" icon="pi pi-trash" class="p-button-text p-button-danger p-button-sm" @click="confirmRemoveNew(index)" />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import Button from 'primevue/button';
import { t } from '@/i18n/messages';
import { getFileIcon, formatFileSize, isAllowedMimeOrExt, ALLOWED_QUOTATION_EXTS, MAX_QUOTATION_BYTES, buildAcceptFromExts } from '@/utils/file';

const props = defineProps({
  modelValue: { type: Array, default: () => [] }, // File[]
  existingFiles: { type: Array, default: () => [] }, // [{id, file_url, file_name, file_type, file_size_formatted}]
  maxSize: { type: Number, default: MAX_QUOTATION_BYTES },
  accept: { type: String, default: buildAcceptFromExts(ALLOWED_QUOTATION_EXTS) },
  existingTitle: { type: String, default: '' },
});
const emit = defineEmits(['update:modelValue', 'delete-existing', 'invalid-files', 'oversize-files']);

const fileInput = ref(null);

function addFiles(files) {
  const valid = [];
  let oversize = 0;
  let unsupported = 0;
  for (const f of files) {
    const allowed = isAllowedMimeOrExt(f);
    const sizeOk = (f.size || 0) <= props.maxSize;
    if (allowed && sizeOk) valid.push(f);
    else if (allowed && !sizeOk) oversize++;
    else if (!allowed) unsupported++;
  }
  if (oversize > 0) emit('oversize-files', oversize);
  if (unsupported > 0) emit('invalid-files', unsupported);
  const updated = [...props.modelValue, ...valid];
  emit('update:modelValue', updated);
}

function onDrop(e) {
  addFiles(Array.from(e.dataTransfer.files || []));
}
function onSelect(e) {
  addFiles(Array.from(e.target.files || []));
  if (fileInput.value) fileInput.value.value = '';
}
function removeNew(index) {
  const updated = [...props.modelValue];
  updated.splice(index, 1);
  emit('update:modelValue', updated);
}

function extFromExisting(file) {
  const name = file?.file_name || '';
  const ext = name.includes('.') ? name.split('.').pop() : '';
  return ext || (file?.file_type?.split('/')?.pop() || '').toUpperCase();
}

function extFromNew(file) {
  const name = file?.name || '';
  const ext = name.includes('.') ? name.split('.').pop() : '';
  return ext || (file?.type?.split('/')?.pop() || '').toUpperCase();
}

function confirmDeleteExisting(id) {
  if (!window.confirm(t('confirm.delete_file'))) return;
  emit('delete-existing', id);
}

function confirmRemoveNew(index) {
  if (!window.confirm(t('confirm.delete_file'))) return;
  removeNew(index);
}
</script>
