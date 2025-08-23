<template>
  <div>
    <!-- Editable area for paste/drag image -->
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
        <p class="placeholder-text">{{ placeholderText || t('placeholder.paste_or_drop_image') }}</p>
      </div>
      <div v-else-if="imagePreviewSrc" class="paste-content-wrapper">
        <img :src="imagePreviewSrc" :alt="t('image_meta.preview_alt')" class="pasted-image-preview" />
      </div>
    </div>

    <!-- Current image info and remove button -->
    <div class="mt-3 flex items-center gap-3" v-if="imagePreviewSrc || existingImageUrl">
      <i class="pi pi-image text-xl"></i>
      <span class="font-medium">{{ t('image_meta.current') }}</span>
      <span v-if="currentFile && currentFile.size" class="text-color-secondary">{{ (currentFile.size / 1024).toFixed(2) }} KB ({{ currentFile.name }})</span>
      <span v-else-if="!currentFile && (existingImageUrl && !removed)">{{ t('image_meta.using_saved') }}</span>
      <Button :label="t('actions.delete')" icon="pi pi-trash" class="p-button-danger p-button-text p-button-sm ml-auto" @click="onRemove" />
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onUnmounted } from 'vue';
import Button from 'primevue/button';
import { useToast } from 'primevue/usetoast';
import { t } from '@/i18n/messages';
import { fileFromPasteEvent, fileFromDropEvent, objectUrl, ALLOWED_IMAGE_MIME_SET, MAX_IMAGE_BYTES } from '@/utils/file';

const props = defineProps({
  modelValue: { type: [File, String, Object, null], default: null },
  existingImageUrl: { type: [String, null], default: null },
  maxSize: { type: Number, default: MAX_IMAGE_BYTES },
  placeholderText: { type: String, default: '' },
  removed: { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue', 'remove']);

const toast = useToast();

const pasteAreaRef = ref(null);
const imagePreviewSrc = ref(null);
const isContentEditable = ref(true);
const showPlaceholder = ref(true);
const editorKey = ref(0);
const lastObjectUrl = ref(null);
const currentFile = ref(null);

function setPreviewFromFile(file) {
  if (lastObjectUrl.value) {
    try { URL.revokeObjectURL(lastObjectUrl.value); } catch (_) {}
    lastObjectUrl.value = null;
  }
  const url = objectUrl(file);
  lastObjectUrl.value = url;
  imagePreviewSrc.value = url;
}

function syncFromProps() {
  // Initialize preview from props
  if (props.modelValue instanceof File) {
    currentFile.value = props.modelValue;
    setPreviewFromFile(props.modelValue);
    showPlaceholder.value = false;
  } else if (typeof props.modelValue === 'string' && props.modelValue) {
    imagePreviewSrc.value = props.modelValue;
    showPlaceholder.value = false;
  } else if (props.existingImageUrl) {
    imagePreviewSrc.value = props.existingImageUrl;
    showPlaceholder.value = false;
  } else {
    imagePreviewSrc.value = null;
    showPlaceholder.value = true;
  }
}

watch(() => props.modelValue, syncFromProps, { immediate: true });
watch(() => props.existingImageUrl, syncFromProps);

function handleFocus() { showPlaceholder.value = false; isContentEditable.value = true; }
function handleBlur() {
  if (!imagePreviewSrc.value && (!pasteAreaRef.value || pasteAreaRef.value.innerText.trim() === '')) showPlaceholder.value = true;
}

function acceptImageFile(file) {
  if (!file || !file.type || !ALLOWED_IMAGE_MIME_SET.has(file.type)) {
    toast.add({ severity: 'warn', summary: t('common.warn'), detail: t('image.only_accept'), life: 2500 });
    return false;
  }
  if (file.size > props.maxSize) {
    toast.add({ severity: 'warn', summary: t('common.warn'), detail: t('image.too_large_10mb'), life: 3000 });
    return false;
  }
  return true;
}

function handlePaste(e) {
  e.preventDefault();
  showPlaceholder.value = false;
  const file = fileFromPasteEvent(e);
  if (!file) {
    toast.add({ severity: 'warn', summary: t('common.warn'), detail: t('paste.no_image'), life: 2500 });
    return;
  }
  if (!acceptImageFile(file)) return;
  currentFile.value = file;
  setPreviewFromFile(file);
  emit('update:modelValue', file);
}

function handleDrop(e) {
  const file = fileFromDropEvent(e);
  if (!acceptImageFile(file)) return;
  currentFile.value = file;
  setPreviewFromFile(file);
  emit('update:modelValue', file);
}

function onRemove() {
  if (!window.confirm(t('confirm.delete_image'))) return;
  // clear preview and signal removal
  emit('remove');
  currentFile.value = null;
  imagePreviewSrc.value = null;
  showPlaceholder.value = true;
  editorKey.value++;
  if (lastObjectUrl.value) {
    try { URL.revokeObjectURL(lastObjectUrl.value); } catch (_) {}
    lastObjectUrl.value = null;
  }
}

onUnmounted(() => {
  if (lastObjectUrl.value) {
    try { URL.revokeObjectURL(lastObjectUrl.value); } catch (_) {}
    lastObjectUrl.value = null;
  }
});
</script>

<style scoped>
.paste-content-wrapper { display:flex; justify-content:center; align-items:center; min-height:140px; width:100%; text-align:center; color: var(--text-color-secondary); font-style: italic; background: var(--surface-100); border-radius: var(--border-radius); padding: 1rem; box-sizing: border-box; }
.pasted-image-preview { max-width: 100%; max-height: 100%; display:block; margin:auto; object-fit: contain; }
.p-editor-container { border: 1px solid var(--surface-300); border-radius: var(--border-radius); padding: 1rem; cursor: text; min-height: 150px; box-sizing: border-box; overflow: hidden; display:flex; justify-content:center; align-items:center; }
.p-editor-container.has-content { background-color: var(--surface-0); }
</style>
