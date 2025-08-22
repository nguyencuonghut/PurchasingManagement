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

        <DataTable
            ref="dt"
            v-model:filters="filters"
            v-model:selection="selectedReports"
            :value="reports"
            paginator
            :rows="10"
            dataKey="id"
            filterDisplay="menu"
            :globalFilterFields="['code', 'description', 'file_path']"
        >
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

            <Column field="id" header="Id" style="width: 20%; height: 44px"></Column>

            <Column field="code" header="Mã" sortable style="min-width: 14rem">
                <template #body="{ data }">
                    <a :href="`/supplier_selection_reports/${data.id}`" class="text-primary hover:underline" style="cursor:pointer">
                        {{ data.code }}
                    </a>
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
                    <InputText v-model="filterModel.value" type="text" placeholder="Tìm theo mô tả" />
                </template>
            </Column>

            <Column field="file_path" header="File đính kèm" style="min-width: 14rem">
                <template #body="{ data }">
                    <a v-if="data.image_url" href="#" @click.prevent="openImageModal(data.image_url)" class="file-link">
                        <i class="pi pi-image mr-2"></i>Xem ảnh
                    </a>
                    <span v-else>Không có file</span>
                </template>
            </Column>

            <Column header="Trạng thái" field="status" sortable :filterMenuStyle="{ width: '14rem' }" style="min-width: 12rem">
                <template #body="{ data }">
                    <Tag :value="data.status" :severity="getStatusSeverity(data.status)" />
                </template>
                <template #filter="{ filterModel }">
                    <Select v-model="filterModel.value" :options="statuses" placeholder="Chọn" showClear>
                        <template #option="slotProps">
                            <Tag :value="slotProps.option" :severity="getStatusSeverity(slotProps.option)" />
                        </template>
                    </Select>
                </template>
            </Column>

            <Column header="Báo giá" field="quotation_files_count" sortable style="min-width: 8rem">
                <template #body="{ data }">
                    <span class="font-medium">{{ data.quotation_files_count ?? 0 }}</span>
                </template>
            </Column>

            <Column v-if="can.update_report || can.delete_report" :exportable="false" style="min-width: 15rem">
                <template #body="slotProps">
                    <!-- Gửi duyệt -->
                    <Button
                        v-if="slotProps.data.status === 'draft' && $page.props.auth.user.id === slotProps.data.creator_id"
                        icon="pi pi-send"
                        outlined
                        rounded
                        severity="warn"
                        @click="requestManagerToApprove(slotProps.data)"
                        class="mr-2"
                    />

                    <!-- Sửa: điều hướng sang trang edit -->
                    <Button
                        v-if="can.update_report && ($page.props.auth.user.role === 'Quản trị' || ($page.props.auth.user.id === slotProps.data.creator_id && slotProps.data.status === 'draft' || ($page.props.auth.user.id === slotProps.data.creator_id && 'Trưởng phòng Thu Mua' == $page.props.auth.user.role && slotProps.data.status === 'manager_approved')))"
                        icon="pi pi-pencil"
                        outlined
                        rounded
                        class="mr-2"
                        @click="openEdit(slotProps.data)"
                    />

                    <!-- Xóa -->
                    <Button
                        v-if="can.delete_report && ($page.props.auth.user.role === 'Quản trị' || ($page.props.auth.user.id === slotProps.data.creator_id && slotProps.data.status === 'draft' || ($page.props.auth.user.id === slotProps.data.creator_id && 'Trưởng phòng Thu Mua' == $page.props.auth.user.role && slotProps.data.status === 'manager_approved')))"
                        icon="pi pi-trash"
                        outlined
                        rounded
                        severity="danger"
                        @click="confirmDeleteReport(slotProps.data)"
                    />
                </template>
            </Column>
        </DataTable>

        <!-- Modal xem ảnh -->
        <Dialog v-model:visible="imageModalVisible" maximizable :style="{ width: '80vw', height: '80vh' }" header="Xem ảnh đính kèm" :modal="true" class="image-modal">
            <div class="image-modal-content">
                <img v-if="currentImageSrc" :src="currentImageSrc" alt="Full size image" class="full-size-image" ref="imageRef" />
                <p v-else>Không có ảnh để hiển thị.</p>
            </div>
            <template #footer>
                <Button
                    label="Xem full màn hình"
                    icon="pi pi-external-link"
                    @click="openFullscreen"
                    class="p-button-outlined"
                    v-if="currentImageSrc"
                />
                <Button label="Đóng" icon="pi pi-times" @click="imageModalVisible = false" />
            </template>
        </Dialog>

        <!-- Modal xác nhận xóa -->
        <Dialog v-model:visible="deleteReportDialog" :style="{ width: '450px' }" header="Xác nhận xóa" :modal="true">
            <div class="confirmation-content">
                <i class="pi pi-exclamation-triangle mr-3" style="font-size: 2rem" />
                <span v-if="selectedReportCode">Bạn có chắc chắn muốn xóa báo cáo <b>{{ selectedReportCode }}</b>?</span>
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
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { FilterMatchMode, FilterOperator } from '@primevue/core/api';
import { useToast } from 'primevue/usetoast';
import { usePage } from '@inertiajs/vue3';

import Toolbar from 'primevue/toolbar';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Toast from 'primevue/toast';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import Tag from 'primevue/tag';
import Select from 'primevue/select';

const toast = useToast();
const dt = ref();
const page = usePage();
const message = computed(() => page.props.auth.flash.message);

defineProps({
    errors: { type: Object },
    reports: Object,
    can: Object,
});

const statuses = ref([
    'draft',
    'pending_manager_approval',
    'manager_approved',
    'auditor_approved',
    'director_approved',
    'rejected'
]);

const getStatusSeverity = (status) => {
    switch (status) {
        case 'draft': return 'secondary';
        case 'pending_manager_approval': return 'warn';
        case 'manager_approved':
        case 'auditor_approved': return 'info';
        case 'director_approved': return 'success';
        case 'rejected': return 'danger';
        default: return 'info';
    }
};

// ==== Image view modal ====
const imageModalVisible = ref(false);
const currentImageSrc = ref(null);
const imageRef = ref(null);

const openImageModal = (imageUrl) => {
    currentImageSrc.value = imageUrl;
    imageModalVisible.value = true;
};

const openFullscreen = () => {
    if (imageRef.value && imageRef.value.requestFullscreen) {
        imageRef.value.requestFullscreen();
    } else if (imageRef.value && imageRef.value.webkitRequestFullscreen) {
        imageRef.value.webkitRequestFullscreen();
    } else if (imageRef.value && imageRef.value.msRequestFullscreen) {
        imageRef.value.msRequestFullscreen();
    }
};

// ==== Delete flow ====
const deleteReportDialog = ref(false);
const selectedReportId = ref(null);
const selectedReportCode = ref('');

const confirmDeleteReport = (report) => {
    selectedReportId.value = report.id;
    selectedReportCode.value = report.code;
    deleteReportDialog.value = true;
};

const deleteReport = () => {
    if (!selectedReportId.value) {
        toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không tìm thấy ID báo cáo để xóa.', life: 3000 });
        return;
    }

    router.delete(`/supplier_selection_reports/${selectedReportId.value}`, {
        onSuccess: () => {
            deleteReportDialog.value = false;
            toast.add({ severity:'success', summary: 'Thành công', detail: message.value, life: 3000 });
            selectedReportId.value = null;
            selectedReportCode.value = '';
        },
        onError: (errors) => {
            console.error("Lỗi khi xóa báo cáo:", errors);
            toast.add({ severity: 'error', summary: 'Lỗi', detail: message.value || 'Có lỗi xảy ra khi xóa báo cáo.', life: 3000 });
        },
    });
};

// ==== Approve flow ====
const requestManagerToApprove = (report) => {
    router.put(`/supplier_selection_reports/${report.id}/request-manager-to-approve`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({ severity:'success', summary: 'Thành công', detail: `Báo cáo ${report.code} đã được gửi duyệt.`, life: 3000 });
        },
        onError: (errors) => {
            console.error("Lỗi khi gửi yêu cầu duyệt báo cáo:", errors);
            toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Lỗi khi gửi yêu cầu duyệt báo cáo!', life: 3000 });
        },
    });
};

// ==== Navigation ====
const openNew = () => {
    router.get('/supplier_selection_reports/create');
};

const openEdit = (report) => {
    // Điều hướng sang trang edit (SupplierSelectionReportEdit.vue)
    router.get(`/supplier_selection_reports/${report.id}/edit`);
};

// ==== Filters ====
const filters = ref();
const initFilters = () => {
    filters.value = {
        global: { value: null, matchMode: FilterMatchMode.CONTAINS },
        code: { operator: FilterOperator.AND, constraints: [{ value: null, matchMode: FilterMatchMode.STARTS_WITH }] },
        description: { operator: FilterOperator.AND, constraints: [{ value: null, matchMode: FilterMatchMode.STARTS_WITH }] },
        status: { operator: FilterOperator.OR, constraints: [{ value: null, matchMode: FilterMatchMode.EQUALS }] },
    };
};
initFilters();

const selectedReports = ref([]);
const clearFilter = () => { initFilters(); };
</script>

<style scoped>
.required-field::after {
    content: ' *';
    color: red;
    margin-left: 2px;
}

/* Chỉ còn modal xem ảnh (modal edit đã bỏ) */
.image-modal .p-dialog-content {
    display: flex;
    justify-content: center;
    align-items: center;
    overflow: hidden;
}
.image-modal-content {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 100%;
    height: 100%;
    overflow: auto;
}
.image-modal .full-size-image {
    max-width: 100%;
    max-height: 100%;
    display: block;
    object-fit: contain;
}
</style>
