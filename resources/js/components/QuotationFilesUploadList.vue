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
      <h4 class="font-semibold mb-2">{{ t('quotation.existing_title') }}</h4>
      <div class="space-y-2">
        <div v-for="file in existingFiles" :key="file.id" class="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
          <div class="flex items-center space-x-3">
            <i :class="getFileIcon(file.file_type)" class="text-xl text-blue-600"></i>
            <div>
              <a :href="file.file_url" target="_blank" class="font-medium text-sm text-blue-600 hover:underline">{{ file.file_name }}</a>
              <p class="text-xs text-gray-500">{{ file.file_size_formatted }}</p>
            </div>
          </div>
          <Button icon="pi pi-trash" class="p-button-text p-button-danger p-button-sm" @click="$emit('delete-existing', file.id)" />
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
              <p class="text-xs text-gray-500">{{ formatFileSize(file.size) }}</p>
            </div>
          </div>
          <Button icon="pi pi-times" class="p-button-text p-button-danger p-button-sm" @click="removeNew(index)" />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import Button from 'primevue/button';
import { t } from '@/i18n/messages';
import { getFileIcon, formatFileSize, isAllowedMimeOrExt } from '@/utils/file';

const props = defineProps({
  modelValue: { type: Array, default: () => [] }, // File[]
  existingFiles: { type: Array, default: () => [] }, // [{id, file_url, file_name, file_type, file_size_formatted}]
  maxSize: { type: Number, default: 20 * 1024 * 1024 },
  accept: { type: String, default: '.pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png' },
});
const emit = defineEmits(['update:modelValue', 'delete-existing', 'invalid-files']);

const fileInput = ref(null);

function addFiles(files) {
  const before = files.length;
  const valid = files.filter((f) => isAllowedMimeOrExt(f) && (f.size || 0) <= props.maxSize);
  const invalidCount = before - valid.length;
  if (invalidCount > 0) emit('invalid-files', invalidCount);
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
</script>
