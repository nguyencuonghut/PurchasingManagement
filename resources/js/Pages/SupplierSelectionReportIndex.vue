<template>
    <Head>
        <title>BCLCNCC</title>
    </Head>

    <div class="card">
        <Toolbar v-if="can.create_report || can.delete_report || can.import_report || can.export_report" class="mb-6">
            <template #start>
                <Button v-if="can.create_report" label="New" icon="pi pi-plus" class="mr-2" @click="openNew" />
            </template>
            <template #end>
                <Button v-if="can.export_report" label="Export" icon="pi pi-upload" severity="secondary" @click="exportCSV($event)" />
            </template>
        </Toolbar>

        <DataTable
            ref="dt"
            v-model:filters="filters"
            v-model:selection="selectedReports"
            :value="reports && reports.data ? reports.data : []"
            paginator
            :rows="10"
            dataKey="id"
            filterDisplay="menu"
            :globalFilterFields="['code', 'description', 'image_url', 'formatted_created_at']"
            :sortField="'created_at'"
            :sortOrder="-1"
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

            <Column field="creator_name" header="Người tạo" sortable style="min-width: 12rem">
                <template #body="{ data }">
                    {{ data.creator_name || 'N/A' }}
                </template>
            </Column>

            <Column field="formatted_created_at" sortField="created_at" header="Ngày tạo" sortable style="min-width: 14rem">
                <template #body="{ data }">
                    {{ data.formatted_created_at || 'N/A' }}
                </template>
                <template #filter="{ filterModel }">
                    <InputText v-model="filterModel.value" type="text" placeholder="Tìm theo ngày/giờ (dd/mm/yyyy hoặc HH:mm)" />
                </template>
            </Column>

            <Column header="Trạng thái" field="status" sortable :filterMenuStyle="{ width: '14rem' }" style="min-width: 12rem">
                <template #body="{ data }">
                    <Tag :value="statusToVietnameseFn(data.status)" :severity="getStatusSeverity(data.status)" />
                </template>
                <template #filter="{ filterModel }">
                    <Select v-model="filterModel.value" :options="statuses" placeholder="Chọn" showClear>
                        <template #option="slotProps">
                            <Tag :value="statusToVietnameseFn(slotProps.option)" :severity="getStatusSeverity(slotProps.option)" />
                        </template>
                    </Select>
                </template>
            </Column>

            <Column :exportable="false" style="min-width: 15rem">
                <template #body="slotProps">
                    <!-- Gửi duyệt tới Trưởng phòng -->
                    <!-- Gửi duyệt tới Trưởng phòng -->
                    <Button
                        v-if="($page.props.auth.user.id === slotProps.data.creator_id) && (slotProps.data.status === 'draft' || slotProps.data.status === 'pending_manager_approval')"
                        icon="pi pi-send"
                        outlined
                        rounded
                        severity="warn"
                        @click="requestManagerToApprove(slotProps.data)"
                        class="mr-2"
                    />

                    <Button
                        v-if="$page.props.auth.user.role === 'Nhân viên Kiểm Soát' && (slotProps.data.status === 'auditor_approved' || slotProps.data.status === 'pending_director_approval')"
                        icon="pi pi-send"
                        outlined
                        rounded
                        severity="info"
                        @click="requestDirectorToApprove(slotProps.data)"
                        class="mr-2"
                    />

                    <!-- Sửa: điều hướng sang trang edit -->
                    <Button
                        v-if="canEdit(slotProps.data)"
                        icon="pi pi-pencil"
                        outlined
                        rounded
                        class="mr-2"
                        @click="openEdit(slotProps.data)"
                    />

                    <!-- Xóa -->
                    <Button
                        v-if="canDelete(slotProps.data)"
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

        <Dialog v-model:visible="sendApproveDialog" :style="{ width: '450px' }" header="Trưởng phòng duyệt" :modal="true">
            <div class="flex flex-col gap-4">
                <label for="manager" class="block font-bold">Chọn Trưởng phòng duyệt</label>
                <Select id="manager" v-model="selectedManagerId" :options="managers" optionLabel="name" optionValue="id" placeholder="Chọn Trưởng phòng" class="w-full" />
            </div>
            <template #footer>
                <Button label="Hủy" icon="pi pi-times" text @click="sendApproveDialog = false" />
                <Button label="Gửi" icon="pi pi-check" @click="submitSendToManager" :disabled="!selectedManagerId" />
            </template>
        </Dialog>

        <Dialog v-model:visible="directorApproveDialog" :style="{ width: '450px' }" header="Giám đốc duyệt" :modal="true">
            <div class="flex flex-col gap-4">
                <label for="director" class="block font-bold">Chọn Giám đốc duyệt</label>
                <Select id="director" v-model="selectedDirectorId" :options="directors" optionLabel="name" optionValue="id" placeholder="Chọn Giám đốc" class="w-full" />
            </div>
            <template #footer>
                <Button label="Hủy" icon="pi pi-times" text @click="directorApproveDialog = false" />
                <Button label="Gửi" icon="pi pi-check" @click="submitSendToDirector" :disabled="!selectedDirectorId" />
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
import { Roles, Statuses, getStatusSeverity as statusSeverity, statusToVietnamese } from '@/utils/constants';

// Đảm bảo statusToVietnamese khả dụng trong template
const statusToVietnameseFn = statusToVietnamese;

const toast = useToast();
const dt = ref();
const page = usePage();
const message = computed(() => page.props?.flash?.message ?? page.props?.auth?.flash?.message ?? '');

defineProps({
    errors: { type: Object },
    reports: { type: Object, default: () => ({ data: [] }) },
    can: Object,
    managers: Array,
    directors: Array,
});

const statuses = ref(Statuses);

const getStatusSeverity = (status) => statusSeverity(status);

const formatDateTime = (dateString) => {
    if (!dateString) return 'N/A';

    const date = new Date(dateString);
    // Use UTC methods to avoid timezone conversion
    const day = date.getUTCDate().toString().padStart(2, '0');
    const month = (date.getUTCMonth() + 1).toString().padStart(2, '0');
    const year = date.getUTCFullYear();
    const hours = date.getUTCHours().toString().padStart(2, '0');
    const minutes = date.getUTCMinutes().toString().padStart(2, '0');

    return `${day}/${month}/${year} ${hours}:${minutes}`;
};

const formatDateForSearch = (dateString) => {
    if (!dateString) return '';

    const date = new Date(dateString);
    // Use UTC methods to avoid timezone conversion
    const day = date.getUTCDate().toString().padStart(2, '0');
    const month = (date.getUTCMonth() + 1).toString().padStart(2, '0');
    const year = date.getUTCFullYear();

    return `${day}/${month}/${year}`;
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
const sendApproveDialog = ref(false);
const selectedManagerId = ref(null);
const reportToSend = ref(null);

const directorApproveDialog = ref(false);
const selectedDirectorId = ref(null);
const reportToDirector = ref(null);

const requestManagerToApprove = (report) => {
    reportToSend.value = report;
    selectedManagerId.value = null;
    sendApproveDialog.value = true;
};

const submitSendToManager = () => {
    if (!reportToSend.value) {
        toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không tìm thấy báo cáo để gửi.', life: 3000 });
        return;
    }
    if (!selectedManagerId.value) {
        toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Vui lòng chọn Trưởng phòng duyệt.', life: 3000 });
        return;
    }

    router.put(`/supplier_selection_reports/${reportToSend.value.id}/request-manager-to-approve`, { manager_id: selectedManagerId.value }, {
        preserveScroll: true,
        onSuccess: () => {
            sendApproveDialog.value = false;
            reportToSend.value = null;
            selectedManagerId.value = null;
            toast.add({ severity:'success', summary: 'Thành công', detail: 'Báo cáo đã được gửi duyệt.', life: 3000 });
        },
        onError: (errors) => {
            console.error("Lỗi khi gửi yêu cầu duyệt báo cáo:", errors);
            toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Lỗi khi gửi yêu cầu duyệt báo cáo!', life: 3000 });
        },
    });
};

const requestDirectorToApprove = (report) => {
    reportToDirector.value = report;
    selectedDirectorId.value = null;
    directorApproveDialog.value = true;
};

const submitSendToDirector = () => {
    if (!reportToDirector.value) {
        toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không tìm thấy báo cáo để gửi.', life: 3000 });
        return;
    }
    if (!selectedDirectorId.value) {
        toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Vui lòng chọn Giám đốc duyệt.', life: 3000 });
        return;
    }

    router.put(`/supplier_selection_reports/${reportToDirector.value.id}/request-director-to-approve`, { director_id: selectedDirectorId.value }, {
        preserveScroll: true,
        onSuccess: () => {
            directorApproveDialog.value = false;
            reportToDirector.value = null;
            selectedDirectorId.value = null;
            toast.add({ severity:'success', summary: 'Thành công', detail: 'Đã gửi yêu cầu duyệt tới Giám đốc.', life: 3000 });
        },
        onError: (errors) => {
            console.error("Lỗi khi gửi yêu cầu duyệt Giám đốc:", errors);
            toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Lỗi khi gửi yêu cầu duyệt Giám đốc!', life: 3000 });
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
        formatted_created_at: { operator: FilterOperator.AND, constraints: [{ value: null, matchMode: FilterMatchMode.CONTAINS }] },
        status: { operator: FilterOperator.OR, constraints: [{ value: null, matchMode: FilterMatchMode.EQUALS }] },
    };
};
initFilters();

const selectedReports = ref([]);
const clearFilter = () => { initFilters(); };

const exportCSV = () => {
    dt.value.exportCSV();
};

// ==== Permission helpers ====
const canEdit = (row) => {
    const u = page.props.auth.user;
    const can = page.props.can;
    if (!can?.update_report) return false;
    if (u.role === Roles.ADMIN) return true;
    const isCreator = u.id === row.creator_id;
    if (isCreator && row.status === 'draft') return true;
    if (isCreator && u.role === Roles.PM_MANAGER && row.status === 'manager_approved') return true;
    return false;
};

const canDelete = (row) => {
    const u = page.props.auth.user;
    const can = page.props.can;
    if (!can?.delete_report) return false;
    if (u.role === Roles.ADMIN) return true;
    const isCreator = u.id === row.creator_id;
    if (isCreator && row.status === 'draft') return true;
    if (isCreator && u.role === Roles.PM_MANAGER && row.status === 'manager_approved') return true;
    return false;
};
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
