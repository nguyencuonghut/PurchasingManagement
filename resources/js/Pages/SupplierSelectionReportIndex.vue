<template>
    <Head>
        <title>BCLCNCC</title>
    </Head>

    <div class="card">
        <Toolbar  v-if="can.create_report || can.delete_report || can.import_report || can.export_report" class="mb-6">
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
                    <InputText v-model="filterModel.value" type="text" placeholder="Tìm theo mô tả" />
                </template>
            </Column>
            <Column field="file_path" header="File đính kèm" sortable style="min-width: 14rem">
                <template #body="{ data }">
                    {{ data.file_path }}
                </template>
                <template #filter="{ filterModel }">
                    <InputText v-model="filterModel.value" type="text" placeholder="Tìm theo file đính kèm" />
                </template>
            </Column>
            <Column  v-if="can.update_report || can.delete_report" :exportable="false" style="min-width: 12rem">
                <template #body="slotProps">
                    <Button icon="pi pi-pencil" outlined rounded class="mr-2" @click="editReport(slotProps.data)" />
                    <Button icon="pi pi-trash" outlined rounded severity="danger" @click="confirmDeleteReport(slotProps.data)" />
                </template>
            </Column>
        </DataTable>


        <Dialog v-model:visible="reportDialog" :style="{ width: '450px' }" header="Chi tiết BCLCNCC" :modal="true">
            <div class="flex flex-col gap-6">
                <div>
                    <label for="code" class="block font-bold mb-3 required-field">Mã</label>
                    <InputText id="code" v-model="form.code" @change="form.validate('code')" autofocus :invalid="submitted && !form.code" fluid />
                    <small v-if="form.invalid('code')" class="text-red-500">{{ form.errors.code }}</small>
                </div>
                <div>
                    <label for="description" class="block font-bold mb-3 required-field">Mô tả</label>
                    <InputText id="description" v-model.trim="form.description" @change="form.validate('description')" autofocus :invalid="submitted && !form.description" fluid />
                    <small v-if="form.invalid('description')" class="text-red-500">{{ form.errors.description }}</small>
                </div>
            </div>

            <template #footer>
                <Button label="Hủy" icon="pi pi-times" text @click="hideDialog" />
                <Button label="Lưu" icon="pi pi-check" :disabled="form.hasErrors" @click="saveReport" />
            </template>
        </Dialog>

        <Dialog v-model:visible="deleteReportDialog" :style="{ width: '450px' }" header="Confirm" :modal="true">
            <div class="flex items-center gap-4">
                <i class="pi pi-exclamation-triangle !text-3xl" />
                <span v-if="form"
                    >Bạn chắc chắn muốn xóa <b>{{ form.code }}</b
                    >?</span
                >
            </div>
            <template #footer>
                <Button label="Không" icon="pi pi-times" text @click="deleteReportDialog = false" />
                <Button label="Có" icon="pi pi-check" @click="deleteReport" />
            </template>
        </Dialog>
    </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import { FilterMatchMode, FilterOperator } from '@primevue/core/api';
import { useForm } from 'laravel-precognition-vue-inertia';
import { useToast } from 'primevue/usetoast';
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

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
const form = useForm('post', '/supplier_selection_reports', {
    id: '',
    code: '',
    description: '',
    file_path: '',
});
const submitted = ref(false);
const isAddReport = ref(false);

const openNew = () => {
    form.reset();
    submitted.value = false;
    reportDialog.value = true;
    isAddReport.value = true;
};

const hideDialog = () => {
    reportDialog.value = false;
    submitted.value = false;
};

const saveReport = () => {
    submitted.value = true;

    // Add new Report
    if (isAddReport.value) {
        // Creat new Report
        form.submit({
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                reportDialog.value = false;
                toast.add({severity:'success', summary: 'Thành công', detail: message, life: 3000});
            },
            onError: () => {
                toast.add({severity: 'error', summary: 'Lỗi', detail: message, life: 3000});
            },
        });
    } else {
        // Edit this Report
        form.put(`supplier_selection_reports/${form.id}`, {
            onSuccess: () => {
                form.reset();
                reportDialog.value = false;
                toast.add({severity: 'success', summary: 'Thành công', detail: message, life: 3000});
            },
            onError: () => {
                toast.add({severity: 'error', summary: 'Lỗi', detail: message, life: 3000});
            },
        });

    }
};
const editReport = (report) => {
    setReport(report);
    reportDialog.value = true;
    isAddReport.value = false;
};
const confirmDeleteReport = (report) => {
    setReport(report);
    deleteReportDialog.value = true;
};

const deleteReport = () => {
    form.delete(`supplier_selection_reports/${form.id}`, {
        onSuccess: () => {
            form.reset();
            deleteReportDialog.value = false;
            toast.add({severity:'success', summary: 'Successful', detail: message, life: 3000});
        },
        onError: () => {
            deleteReportDialog.value = false;
            toast.add({severity:'error', summary: 'Failed', detail: message, life: 3000});
        },
    });
};

const setReport = (report) => {
    form.id = report.id;
    form.code = report.code;
    form.description = report.description;
    form.file_path = report.file_path;
}

const selectedReports = ref();
const filters = ref();

const initFilters = () => {
    filters.value = {
        global: { value: null, matchMode: FilterMatchMode.CONTAINS },
        code: { operator: FilterOperator.AND, constraints: [{ value: null, matchMode: FilterMatchMode.STARTS_WITH }] },
        description: { operator: FilterOperator.AND, constraints: [{ value: null, matchMode: FilterMatchMode.STARTS_WITH }] },
        file_path: { operator: FilterOperator.AND, constraints: [{ value: null, matchMode: FilterMatchMode.STARTS_WITH }] },
    };
};

initFilters();

const clearFilter = () => {
    initFilters();
};
</script>
