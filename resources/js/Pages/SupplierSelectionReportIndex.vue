<template>
    <Head>
        <title>BCLCNCC</title>
    </Head>

    <div class="card">
        <Toolbar v-if="can.create_report || can.delete_report || can.import_report || can.export_report" class="mb-6">
            <template #start>
                <Button v-if="can.create_report" label="New" icon="pi pi-plus" class="mr-2" @click="openNew" />
            </template>
        </Toolbar>

        <DataTable ref="dt" v-model:filters="filters" v-model:selection="selectedReports" :value="reports" paginator :rows="10" dataKey="id" filterDisplay="menu"
            :globalFilterFields="['code', 'description', 'file_path']">
            <template #header>
                <div class="flex justify-between">
                    <Button type="button" icon="pi pi-filter-slash" label="Clear" outlined @click="clearFilter()" />
                    <IconField>
                        <InputIcon>
                            <i class="pi pi-search" />
                        </InputIcon>
                        <InputText v-model="filters['global'].value" placeholder="Tìm kiếm" />
                    </IconField>
                </div>
            </template>
            <template #empty> Không tìm thấy báo cáo. </template>
            <Column selectionMode="multiple" headerStyle="width: 3rem"></Column>
            <Column field="code" header="Mã" sortable style="min-width: 14rem">
                <template #body="{ data }">
                    {{ data.code }}
                </template>
                <template #filter="{ filterModel }">
                    <InputText v-model="filterModel.value" type="text" placeholder="Tìm theo mã" />
                </template>
            </Column>
            <Column field="description" header="Mô tả" sortable style="min-width: 14rem">
                <template #body="{ data }">
                    {{ data.description }}
                </template>
                <template #filter="{ filterModel }">
                    {{ data.description }}
                </template>
            </Column>
            <Column header="File đính kèm" style="min-width: 14rem">
                <template #body="{ data }">
                    <a v-if="data.image_url" href="#" @click.prevent="openImageModal(data.image_url)" class="file-link">
                        <i class="pi pi-image mr-2"></i>Xem ảnh
                    </a>
                    <span v-else>Không có file</span>
                </template>
                <template #filter="{ filterModel }">
                    <InputText v-model="filterModel.value" type="text" placeholder="Tìm theo file đính kèm" />
                </template>
            </Column>

            <Column v-if="can.update_report || can.delete_report" :exportable="false" style="min-width: 12rem">
                <template #body="slotProps">
                    <Button
                        v-if="can.update_report && ($page.props.auth.user.role === 'Quản trị' || $page.props.auth.user.id === slotProps.data.user_id)"
                        icon="pi pi-pencil" outlined rounded class="mr-2" @click="editReport(slotProps.data)" />
                    <Button
                        v-if="can.delete_report && $page.props.auth.user.role === 'Quản trị'"
                        icon="pi pi-trash" outlined rounded severity="danger" @click="confirmDeleteReport(slotProps.data)" />
                </template>
            </Column>
        </DataTable>


        <Dialog v-model:visible="reportDialog" :style="{ width: '450px' }" header="Chi tiết BCLCNCC" :modal="true">
            {{ form.code }}
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
                        <label for="file_path" class="block font-bold mb-3 required-field">File</label>

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
                                <span class="text-color-secondary" v-else-if="!imageFile && form.file_path && !form.file_path.startsWith('data:image')">
                                    (Đã lưu trên server)
                                </span>
                                <Button
                                    label="Xóa ảnh"
                                    icon="pi times"
                                    class="p-button-danger p-button-text p-button-sm ml-auto"
                                    @click="clearImage(true)" />
                            </div>
                            <div v-else-if="imageFile" class="mt-3 flex align-items-center gap-2">
                                <i class="pi pi-spinner pi-spin text-xl"></i>
                                <span class="font-medium">Đang xử lý ảnh...</span>
                            </div>
                        </div>
                        <small v-if="form.errors.file_path" class="text-red-500">{{ form.errors.file_path }}</small>
                    </div>
                </div>
            </form>
            <template #footer>
                <Button label="Hủy" icon="pi pi-times" text @click="hideDialog" />
                <Button type="submit" label="Lưu" icon="pi pi-check" @click="saveReport" :disabled="form.processing" />
            </template>
        </Dialog>

        <Dialog v-model:visible="imageModalVisible" :style="{ width: '80vw', height: '80vh' }" header="Xem ảnh đính kèm" :modal="true" class="image-modal">
            <div class="image-modal-content">
                <img v-if="currentImageSrc" :src="currentImageSrc" alt="Full size image" class="full-size-image" />
                <p v-else>Không có ảnh để hiển thị.</p>
            </div>
            <template #footer>
                <Button label="Đóng" icon="pi pi-times" @click="imageModalVisible = false" />
            </template>
        </Dialog>

        <Dialog v-model:visible="deleteReportDialog" :style="{ width: '450px' }" header="Xác nhận xóa" :modal="true">
            <div class="confirmation-content">
                <i class="pi pi-exclamation-triangle mr-3" style="font-size: 2rem" />
                <span v-if="form.code">Bạn có chắc chắn muốn xóa báo cáo <b>{{ form.code }}</b>?</span>
            </div>
            <template #footer>
                <Button label="Không" icon="pi pi-times" text @click="deleteReportDialog = false" />
                <Button label="Có" icon="pi pi-check" severity="danger" @click="deleteReport" />
            </template>
        </Dialog>
    </div>
    <Toast />
</template>

<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, watch, nextTick, computed } from 'vue';
import { FilterMatchMode } from '@primevue/core/api';
import { useToast } from 'primevue/usetoast';
import { usePage } from '@inertiajs/vue3';

// Import Vuelidate
import { useVuelidate } from '@vuelidate/core';
import { required, maxLength, helpers } from '@vuelidate/validators';

import Toolbar from 'primevue/toolbar';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import RadioButton from 'primevue/radiobutton';
import Toast from 'primevue/toast';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';

import html2canvas from 'html2canvas';

const toast = useToast();
const dt = ref();
const page = usePage();
const message = computed(() => page.props.auth.flash.message);

defineProps({
    errors: {
        type: Object,
    },
    reports: Object,
    can: Object,
});

const reportDialog = ref(false);
const deleteReportDialog = ref(false);
const selectedReports = ref([]);

const selectedReportId = ref(null);

const form = useForm({
    code: '',
    description: '',
    file_path: null,
});

// Định nghĩa rules cho Vuelidate
const rules = computed(() => {
    return {
        code: {
            required: helpers.withMessage('Mã báo cáo không được để trống.', required),
            maxLength: helpers.withMessage('Mã báo cáo không được vượt quá 255 ký tự.', maxLength(255)),
            // Rule unique sẽ được xử lý hoàn toàn ở backend để tránh phức tạp frontend
        },
        description: {
            required: helpers.withMessage('Mô tả không được để trống.', required),
            maxLength: helpers.withMessage('Mô tả không được vượt quá 1000 ký tự.', maxLength(1000)),
        },
        // File path: rule "required" sẽ được kiểm soát bởi Laravel dựa trên tình huống
        // Frontend chỉ cần kiểm tra sự tồn tại nếu bạn muốn
        // file_path: { required: helpers.withMessage('File đính kèm không được để trống.', required) },
    };
});

// Khởi tạo Vuelidate
const v$ = useVuelidate(rules, form);

const submitted = ref(false);
const isAddReport = ref(false);


// START: Image Modal & Paste/Drop File Logic
const pasteAreaRef = ref(null);
const imagePreviewSrc = ref(null);
const imageFile = ref(null);
const showPlaceholder = ref(true);
const isContentEditable = ref(true);

const imageModalVisible = ref(false); // Keep this one
const currentImageSrc = ref(null);

const openImageModal = (imageUrl) => {
    currentImageSrc.value = imageUrl;
    imageModalVisible.value = true;
};

const clearImage = (clearAll = false) => {
    imagePreviewSrc.value = null;
    imageFile.value = null;
    showPlaceholder.value = true;
    if (pasteAreaRef.value) {
        pasteAreaRef.value.innerHTML = '';
    }
    if (clearAll) {
        form.file_path = null;
    }
};

const handlePaste = async (event) => {
    event.preventDefault();
    showPlaceholder.value = false;

    const items = (event.clipboardData || event.originalEvent.clipboardData).items;
    let imageFound = false;

    for (const item of items) {
        if (item.type.indexOf('image') !== -1) {
            imageFound = true;
            const file = item.getAsFile();
            imageFile.value = file;
            const reader = new FileReader();
            reader.onload = (e) => {
                imagePreviewSrc.value = e.target.result;
                form.file_path = e.target.result; // Lưu Base64 vào form.file_path
            };
            reader.readAsDataURL(file);
            break;
        } else if (item.type === 'text/html') {
            const html = await new Promise(resolve => item.getAsString(resolve));
            if (html.includes('<table') || html.includes('<img')) {
                // Nếu là nội dung HTML có thể là bảng hoặc hình ảnh, chuyển đổi sang ảnh
                convertHtmlToImage(html);
                imageFound = true;
                break;
            }
        }
    }

    if (!imageFound) {
        // Fallback: Nếu không tìm thấy ảnh và không phải HTML, dán nội dung dưới dạng text
        const text = (event.clipboardData || window.clipboardData).getData('text');
        if (pasteAreaRef.value) {
            const tempDiv = document.createElement('div');
            tempDiv.innerText = text; // Dán dưới dạng text thuần
            pasteAreaRef.value.innerHTML = ''; // Clear existing content
            pasteAreaRef.value.appendChild(tempDiv);
        }
        showPlaceholder.value = !text.trim();
        imagePreviewSrc.value = null;
        imageFile.value = null;
        form.file_path = null;
    }
};


const handleDrop = (event) => {
    event.preventDefault();
    showPlaceholder.value = false;

    const file = event.dataTransfer.files[0];
    if (file && file.type.startsWith('image/')) {
        imageFile.value = file;
        const reader = new FileReader();
        reader.onload = (e) => {
            imagePreviewSrc.value = e.target.result;
            form.file_path = e.target.result; // Lưu Base64 vào form.file_path
        };
        reader.readAsDataURL(file);
    } else {
        toast.add({severity: 'warn', summary: 'Cảnh báo', detail: 'Chỉ chấp nhận file ảnh.', life: 3000});
        showPlaceholder.value = true;
    }
};

const convertHtmlToImage = (html) => {
    const tempDiv = document.createElement('div');
    tempDiv.style.position = 'absolute';
    tempDiv.style.left = '-9999px'; // Đặt ra ngoài màn hình
    tempDiv.style.width = 'fit-content'; // Đảm bảo div co giãn theo nội dung
    tempDiv.style.padding = '10px'; // Thêm padding để ảnh không bị cắt sát
    tempDiv.innerHTML = html;
    document.body.appendChild(tempDiv);

    // Sử dụng nextTick để đảm bảo DOM đã render xong
    nextTick(() => {
        html2canvas(tempDiv, {
            scale: 2, // Tăng scale để ảnh nét hơn
            useCORS: true, // Quan trọng nếu có ảnh từ các nguồn khác
            logging: false // Tắt logging của html2canvas
        }).then(canvas => {
            const imgData = canvas.toDataURL('image/png');
            imagePreviewSrc.value = imgData;
            form.file_path = imgData;
            imageFile.value = new File([dataURLtoBlob(imgData)], 'pasted_content.png', { type: 'image/png' });

            document.body.removeChild(tempDiv); // Xóa div tạm thời
        }).catch(error => {
            console.error('Error converting HTML to image:', error);
            toast.add({severity: 'error', summary: 'Lỗi', detail: 'Không thể chuyển đổi nội dung dán thành ảnh.', life: 3000});
            showPlaceholder.value = true;
            imagePreviewSrc.value = null;
            form.file_path = null;
            document.body.removeChild(tempDiv);
        });
    });
};

const dataURLtoBlob = (dataurl) => {
    let arr = dataurl.split(','), mime = arr[0].match(/:(.*?);/)[1],
        bstr = atob(arr[1]), n = bstr.length, u8arr = new Uint8Array(n);
    while(n--){
        u8arr[n] = bstr.charCodeAt(n);
    }
    return new Blob([u8arr], {type:mime});
}

const handleFocus = () => {
    showPlaceholder.value = false;
    isContentEditable.value = true;
};

const handleBlur = () => {
    if (!imagePreviewSrc.value && (!pasteAreaRef.value || pasteAreaRef.value.innerText.trim() === '')) {
        showPlaceholder.value = true;
    }
};
// END: Image Modal & Paste/Drop File Logic


// START: Dialog & Form Logic
const openNew = () => {
    isAddReport.value = true;
    form.reset();
    v$.value.$reset(); // Reset trạng thái validation của Vuelidate
    selectedReportId.value = null;
    clearImage();
    submitted.value = false;
    reportDialog.value = true;
};

const hideDialog = () => {
    reportDialog.value = false;
    submitted.value = false;
    form.clearErrors(); // Xóa lỗi Inertia
    v$.value.$reset(); // Reset trạng thái validation của Vuelidate
    clearImage();
    selectedReportId.value = null;
};

const saveReport = async () => {

    submitted.value = true;
    const isFormValid = await v$.value.$validate(); // Chạy validation frontend

    if (!isFormValid) {
        toast.add({severity: 'error', summary: 'Lỗi Validation', detail: 'Vui lòng kiểm tra lại các trường có lỗi.', life: 3000});
        return; // Dừng lại nếu frontend validation thất bại
    }

    // Nếu frontend validation pass, tiếp tục gửi đến backend
    if (isAddReport.value) {
        form.post('/supplier_selection_reports', {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                v$.value.$reset();
                reportDialog.value = false;
                toast.add({severity:'success', summary: 'Thành công', detail: message.value, life: 3000});
                clearImage();
                selectedReportId.value = null;
            },
            onError: (errors) => {
                // InertiaJS tự động điền errors vào form.errors
                toast.add({severity: 'error', summary: 'Lỗi', detail: message.value || 'Có lỗi xảy ra khi tạo báo cáo.', life: 3000});
            },
        });
    } else { // Logic CẬP NHẬT
        if (selectedReportId.value) {
            console.log('Dữ liệu form trước khi PUT:', form.data()); // Log 9 - Hiển thị dữ liệu form sẽ được gửi
            form.put(`/supplier_selection_reports/${selectedReportId.value}`, {
                onSuccess: () => {
                    form.reset();
                    v$.value.$reset();
                    reportDialog.value = false;
                    toast.add({severity:'success', summary: 'Thành công', detail: message.value, life: 3000});
                    clearImage();
                    selectedReportId.value = null;
                },
                onError: (errors) => {
                    toast.add({severity: 'error', summary: 'Lỗi', detail: message.value || 'Có lỗi xảy ra khi cập nhật.', life: 3000});
                },
            });
        } else {
            toast.add({severity: 'error', summary: 'Lỗi', detail: 'Không tìm thấy ID báo cáo để cập nhật.', life: 3000});
        }
    }
};

const editReport = (report) => {
    isAddReport.value = false;
    selectedReportId.value = report.id;

    // Properly populate form with existing data
    form.code = report.code || '';
    form.description = report.description || '';
    form.file_path = report.file_path || null;

    form.clearErrors(); // Xóa lỗi Inertia cũ
    v$.value.$reset(); // Reset trạng thái validation của Vuelidate

    // Cập nhật preview ảnh nếu có
    if (report.image_url) {
        imagePreviewSrc.value = report.image_url;
        showPlaceholder.value = false;
    } else {
        imagePreviewSrc.value = null;
        showPlaceholder.value = true;
    }
    imageFile.value = null;
    if (pasteAreaRef.value) {
        pasteAreaRef.value.innerHTML = '';
    }

    submitted.value = false;
    reportDialog.value = true;
};

const confirmDeleteReport = (report) => {
    selectedReportId.value = report.id;
    form.code = report.code;
    deleteReportDialog.value = true;
};

const deleteReport = () => {
    if (selectedReportId.value) {
        router.delete(`/supplier_selection_reports/${selectedReportId.value}`, {
            onSuccess: () => {
                deleteReportDialog.value = false;
                toast.add({severity:'success', summary: 'Thành công', detail: message.value, life: 3000});
                form.reset();
                selectedReportId.value = null;
            },
            onError: (errors) => {
                console.error("Lỗi khi xóa báo cáo:", errors);
                toast.add({severity: 'error', summary: 'Lỗi', detail: message.value || 'Có lỗi xảy ra khi xóa báo cáo.', life: 3000});
            },
        });
    } else {
        toast.add({severity: 'error', summary: 'Lỗi', detail: 'Không tìm thấy ID báo cáo để xóa.', life: 3000});
    }
};

const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
});

const clearFilter = () => {
    filters.value.global.value = null;
};
// END: Dialog & Form Logic
</script>

<style scoped>
.required-field::after {
    content: ' *';
    color: red;
    margin-left: 2px;
}

.paste-content-wrapper {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 140px; /* Adjust as needed */
    width: 100%;
    text-align: center;
    color: var(--text-color-secondary);
    font-style: italic;
    background-color: var(--surface-100); /* Light background for paste area */
    border-radius: var(--border-radius);
    padding: 1rem;
    box-sizing: border-box;
}

.paste-content-wrapper .placeholder-text {
    line-height: 1.5;
    color: var(--text-color-secondary);
    font-style: italic;
}

.pasted-image-preview {
    max-width: 100%;
    max-height: 100%;
    display: block;
    margin: auto;
    object-fit: contain;
}

.p-editor-container {
    /* Styles for the contenteditable div */
    border: 1px solid var(--surface-300);
    border-radius: var(--border-radius);
    padding: 1rem;
    cursor: text;
    min-height: 150px;
    box-sizing: border-box;
    overflow: hidden; /* Ensure image doesn't overflow */
    display: flex;
    justify-content: center;
    align-items: center;
}

.p-editor-container.has-content {
    background-color: var(--surface-0); /* White background when content is present */
}

.image-modal .p-dialog-content {
    display: flex;
    justify-content: center;
    align-items: center;
    overflow: hidden; /* Hide scrollbars if content overflows dialog */
}

.image-modal-content {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 100%;
    height: 100%;
    overflow: auto; /* Allow scroll if image is too large */
}

.image-modal .full-size-image {
    max-width: 100%;
    max-height: 100%;
    display: block;
    object-fit: contain; /* Ensure the image fits within the modal without cropping */
}
</style>
