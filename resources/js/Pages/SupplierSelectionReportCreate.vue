<template>
    <Head>
        <title>Tạo BCLCNCC</title>
    </Head>

    <div class="card">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-xl font-semibold">Tạo Báo Cáo Lựa Chọn Nhà Cung Cấp</h2>
            <div class="flex gap-2">
                <Link href="/supplier_selection_reports" class="p-button p-component p-button-outlined">
                    <span class="p-button-icon p-button-icon-left pi pi-arrow-left"></span>
                    <span class="p-button-label">Quay lại</span>
                </Link>
                <Button :disabled="form.processing" class="p-button p-component" @click="saveReport">
                    <span class="p-button-icon p-button-icon-left pi pi-check"></span>
                    <span class="p-button-label">Lưu</span>
                </Button>
            </div>
        </div>

        <form @submit.prevent="saveReport">
            <div class="flex flex-col gap-6">
                <div>
                    <label for="code" class="block font-bold mb-3 required-field">Mã</label>
                    <InputText id="code" v-model="form.code" @blur="v$.code.$touch" :invalid="v$.code.$error || form.errors.code" fluid />
                    <small v-if="v$.code.$error" class="text-red-500">{{ v$.code.$errors[0].$message }}</small>
                    <small v-else-if="form.errors.code" class="text-red-500">{{ form.errors.code }}</small>
                </div>

                <div>
                    <label for="description" class="block font-bold mb-3 required-field">Mô tả</label>
                    <InputText id="description" v-model.trim="form.description" @blur="v$.description.$touch" :invalid="v$.description.$error || form.errors.description" fluid />
                    <small v-if="v$.description.$error" class="text-red-500">{{ v$.description.$errors[0].$message }}</small>
                    <small v-else-if="form.errors.description" class="text-red-500">{{ form.errors.description }}</small>
                </div>

                <div>
                    <label for="file_path" class="block font-bold mb-3 required-field">File báo cáo</label>

                    <div class="integrated-paste-input">
                        <div
                            ref="pasteAreaRef"
                            :contenteditable="isContentEditable"
                            class="p-inputtext p-component p-editor-container"
                            :class="{ 'has-content': imagePreviewSrc || (!showPlaceholder && pasteAreaRef?.innerText.trim() !== '') }"
                            style="min-height: 150px; border: 1px solid var(--surface-300); padding: 1rem; cursor: text; overflow: hidden;"
                            @paste="handlePaste"
                            @drop.prevent="handleDrop"
                            @focus="handleFocus"
                            @blur="handleBlur"
                        >
                            <div v-if="!imagePreviewSrc && showPlaceholder" class="paste-content-wrapper">
                                <p class="placeholder-text">
                                    Dán ảnh (Ctrl+V) hoặc kéo thả file ảnh vào đây.
                                    <br />
                                    (Nếu dán bảng Excel/Word, sẽ có tùy chọn chuyển thành ảnh.)
                                </p>
                            </div>
                            <div v-else-if="imagePreviewSrc" class="paste-content-wrapper">
                                <img :src="imagePreviewSrc" alt="Image Preview" class="pasted-image-preview" />
                            </div>
                        </div>

                        <div v-if="imagePreviewSrc" class="mt-3 flex align-items-center gap-2">
                            <i class="pi pi-image text-xl"></i>
                            <span class="font-medium">Ảnh hiện tại</span>
                            <span class="text-color-secondary" v-if="imageFile">
                                {{ (imageFile.size / 1024).toFixed(2) }} KB
                                <template v-if="imageFile.name"> ({{ imageFile.name }})</template>
                            </span>
                            <Button label="Xóa ảnh" icon="pi pi-times" class="p-button-danger p-button-text p-button-sm ml-auto" @click="clearImage(true)" />
                        </div>
                        <div v-else-if="imageFile" class="mt-3 flex align-items-center gap-2">
                            <i class="pi pi-spinner pi-spin text-xl"></i>
                            <span class="font-medium">Đang xử lý ảnh...</span>
                        </div>
                    </div>
                    <small v-if="form.errors.file_path" class="text-red-500">{{ form.errors.file_path }}</small>
                </div>

                <!-- Quotation Files Upload Section -->
                <div>
                    <label class="block font-bold mb-3">File báo giá</label>

                    <!-- File Upload Area -->
                    <div
                        class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center cursor-pointer hover:border-blue-400 transition-colors"
                        @drop.prevent="handleQuotationFilesDrop"
                        @dragover.prevent
                        @click="quotationFilesInput?.click()"
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

                    <!-- Uploaded Files List -->
                    <div v-if="uploadedQuotationFiles.length" class="mt-4">
                        <h4 class="text-sm font-semibold mb-2">File đã upload</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                            <div v-for="(file, index) in uploadedQuotationFiles" :key="index" class="p-3 border rounded flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <i :class="getFileIcon(file.type)"></i>
                                    <div class="flex flex-col">
                                        <span class="font-medium">{{ file.name }}</span>
                                        <small class="text-gray-500">{{ formatFileSize(file.size) }}</small>
                                    </div>
                                </div>
                                <Button icon="pi pi-times" text severity="danger" @click="removeQuotationFile(index)" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex justify-end gap-2">
                    <Link href="/supplier_selection_reports" class="p-button p-component p-button-text">
                        <span class="p-button-icon p-button-icon-left pi pi-times"></span>
                        <span class="p-button-label">Hủy</span>
                    </Link>
                    <Button type="submit" label="Lưu" icon="pi pi-check" :disabled="form.processing" />
                </div>
            </div>
        </form>
    </div>
    <Toast />
</template>

<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { ref, nextTick, computed } from 'vue'
import { useToast } from 'primevue/usetoast'
import { useVuelidate } from '@vuelidate/core'
import { required, maxLength, helpers } from '@vuelidate/validators'

import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Toast from 'primevue/toast'

import html2canvas from 'html2canvas'

const toast = useToast()

// Form
const form = useForm({
    code: '',
    description: '',
    file_path: null,
    quotation_files: [],
})

// Validation
const rules = computed(() => ({
    code: {
        required: helpers.withMessage('Mã báo cáo không được để trống.', required),
        maxLength: helpers.withMessage('Mã báo cáo không được vượt quá 255 ký tự.', maxLength(255)),
    },
    description: {
        required: helpers.withMessage('Mô tả không được để trống.', required),
        maxLength: helpers.withMessage('Mô tả không được vượt quá 1000 ký tự.', maxLength(1000)),
    },
}))
const v$ = useVuelidate(rules, form)

// Image paste/drag state
const pasteAreaRef = ref(null)
const imagePreviewSrc = ref(null)
const imageFile = ref(null)
const showPlaceholder = ref(true)
const isContentEditable = ref(true)

// Quotation files
const uploadedQuotationFiles = ref([])
const quotationFilesInput = ref(null)

// Image helpers
const clearImage = (clearAll = false) => {
    imagePreviewSrc.value = null
    imageFile.value = null
    showPlaceholder.value = true
    if (pasteAreaRef.value) pasteAreaRef.value.innerHTML = ''
    if (clearAll) form.file_path = null
}

const handlePaste = async (event) => {
    event.preventDefault()
    showPlaceholder.value = false

    const items = (event.clipboardData || event.originalEvent.clipboardData).items
    let imageFound = false

    for (const item of items) {
        if (item.type.indexOf('image') !== -1) {
            imageFound = true
            const file = item.getAsFile()
            imageFile.value = file
            const reader = new FileReader()
            reader.onload = (e) => {
                imagePreviewSrc.value = e.target.result
                form.file_path = e.target.result
            }
            reader.readAsDataURL(file)
            break
        } else if (item.type === 'text/html') {
            const html = await new Promise(resolve => item.getAsString(resolve))
            if (html.includes('<table') || html.includes('<img')) {
                convertHtmlToImage(html)
                imageFound = true
                break
            }
        }
    }

    if (!imageFound) {
        const text = (event.clipboardData || window.clipboardData).getData('text')
        if (pasteAreaRef.value) {
            const tempDiv = document.createElement('div')
            tempDiv.innerText = text
            pasteAreaRef.value.innerHTML = ''
            pasteAreaRef.value.appendChild(tempDiv)
        }
        showPlaceholder.value = !text.trim()
        imagePreviewSrc.value = null
        imageFile.value = null
        form.file_path = null
    }
}

const handleDrop = (event) => {
    event.preventDefault()
    showPlaceholder.value = false

    const file = event.dataTransfer.files[0]
    if (file && file.type.startsWith('image/')) {
        imageFile.value = file
        const reader = new FileReader()
        reader.onload = (e) => {
            imagePreviewSrc.value = e.target.result
            form.file_path = e.target.result
        }
        reader.readAsDataURL(file)
    } else {
        toast.add({severity: 'warn', summary: 'Cảnh báo', detail: 'Chỉ chấp nhận file ảnh.', life: 3000})
        showPlaceholder.value = true
    }
}

const convertHtmlToImage = (html) => {
    const tempDiv = document.createElement('div')
    tempDiv.style.position = 'absolute'
    tempDiv.style.left = '-9999px'
    tempDiv.style.width = 'fit-content'
    tempDiv.style.padding = '10px'
    tempDiv.innerHTML = html
    document.body.appendChild(tempDiv)

    nextTick(() => {
        html2canvas(tempDiv, { scale: 2, useCORS: true, logging: false })
            .then(canvas => {
                const imgData = canvas.toDataURL('image/png')
                imagePreviewSrc.value = imgData
                form.file_path = imgData
                imageFile.value = new File([dataURLtoBlob(imgData)], 'pasted_content.png', { type: 'image/png' })
                document.body.removeChild(tempDiv)
            })
            .catch(error => {
                console.error('Error converting HTML to image:', error)
                toast.add({severity: 'error', summary: 'Lỗi', detail: 'Không thể chuyển đổi nội dung dán thành ảnh.', life: 3000})
                showPlaceholder.value = true
                imagePreviewSrc.value = null
                form.file_path = null
                document.body.removeChild(tempDiv)
            })
    })
}

const dataURLtoBlob = (dataurl) => {
    let arr = dataurl.split(','), mime = arr[0].match(/:(.*?);/)[1], bstr = atob(arr[1]), n = bstr.length, u8arr = new Uint8Array(n)
    while (n--) { u8arr[n] = bstr.charCodeAt(n) }
    return new Blob([u8arr], { type: mime })
}

const handleFocus = () => {
    showPlaceholder.value = false
    isContentEditable.value = true
}

const handleBlur = () => {
    if (!imagePreviewSrc.value && (!pasteAreaRef.value || pasteAreaRef.value.innerText.trim() === '')) {
        showPlaceholder.value = true
    }
}

// Quotation files handlers
const handleQuotationFilesDrop = (event) => {
    const files = Array.from(event.dataTransfer.files)
    addQuotationFiles(files)
}

const handleQuotationFilesSelect = (event) => {
    const files = Array.from(event.target.files)
    addQuotationFiles(files)
}

const addQuotationFiles = (files) => {
    const validFiles = files.filter(file => {
        const validTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'image/jpeg', 'image/jpg', 'image/png'
        ]
        return validTypes.includes(file.type)
    })

    if (validFiles.length !== files.length) {
        toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Một số file không được hỗ trợ và đã bị bỏ qua.', life: 3000 })
    }

    uploadedQuotationFiles.value.push(...validFiles)
    form.quotation_files = [...uploadedQuotationFiles.value]
}

const removeQuotationFile = (index) => {
    uploadedQuotationFiles.value.splice(index, 1)
    form.quotation_files = [...uploadedQuotationFiles.value]
}

const getFileIcon = (fileType) => {
    const iconMap = {
        'application/pdf': 'pi pi-file-pdf text-red-500',
        'application/msword': 'pi pi-file-word text-blue-500',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'pi pi-file-word text-blue-500',
        'application/vnd.ms-excel': 'pi pi-file-excel text-green-500',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'pi pi-file-excel text-green-500',
        'image/jpeg': 'pi pi-image text-purple-500',
        'image/jpg': 'pi pi-image text-purple-500',
        'image/png': 'pi pi-image text-purple-500',
    }
    return iconMap[fileType] || 'pi pi-file'
}

const formatFileSize = (bytes) => {
    if (bytes === 0) return '0 Bytes'
    const k = 1024
    const sizes = ['Bytes', 'KB', 'MB', 'GB']
    const i = Math.floor(Math.log(bytes) / Math.log(k))
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

// Save
const saveReport = async () => {
    const isFormValid = await v$.value.$validate()
    if (!isFormValid) {
        toast.add({severity: 'error', summary: 'Lỗi Validation', detail: 'Vui lòng kiểm tra lại các trường có lỗi.', life: 3000})
        return
    }

    const formData = new FormData()
    formData.append('code', form.code)
    formData.append('description', form.description)

    if (form.file_path) {
        if (typeof form.file_path === 'string' && form.file_path.startsWith('data:image')) {
            const blob = dataURLtoBlob(form.file_path)
            formData.append('file_path', blob, 'report_image.png')
        } else if (form.file_path instanceof File) {
            formData.append('file_path', form.file_path)
        }
    }

    // quotation files
    uploadedQuotationFiles.value.forEach((file) => {
        formData.append('quotation_files[]', file)
    })

    form.post('/supplier_selection_reports', {
        data: formData,
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset()
            v$.value.$reset()
            toast.add({severity:'success', summary: 'Thành công', detail: 'Báo cáo đã được tạo thành công!', life: 3000})
            clearImage()
            uploadedQuotationFiles.value = []
            router.visit('/supplier_selection_reports')
        },
        onError: () => {
            toast.add({severity: 'error', summary: 'Lỗi', detail: 'Có lỗi xảy ra khi tạo báo cáo.', life: 3000})
        },
    })
}
</script>

<style scoped>
.required-field::after { content: ' *'; color: red; margin-left: 2px; }

.paste-content-wrapper { display: flex; justify-content: center; align-items: center; min-height: 140px; width: 100%; text-align: center; color: var(--text-color-secondary); font-style: italic; background-color: var(--surface-100); border-radius: var(--border-radius); padding: 1rem; box-sizing: border-box; }

.paste-content-wrapper .placeholder-text { line-height: 1.5; color: var(--text-color-secondary); font-style: italic; }

.pasted-image-preview { max-width: 100%; max-height: 100%; display: block; margin: auto; object-fit: contain; }

.p-editor-container { border: 1px solid var(--surface-300); border-radius: var(--border-radius); padding: 1rem; cursor: text; min-height: 150px; box-sizing: border-box; overflow: hidden; display: flex; justify-content: center; align-items: center; }

.p-editor-container.has-content { background-color: var(--surface-0); }
</style>
