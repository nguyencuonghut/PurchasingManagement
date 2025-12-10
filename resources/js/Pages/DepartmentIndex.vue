<template>
    <Head>
        <title>Phòng ban</title>
    </Head>
    <div class="card">
        <Toolbar v-if="can.create_department || can.delete_department" class="mb-6">
            <template #start>
                <Button v-if="can.create_department" label="Thêm" icon="pi pi-plus" class="mr-2" @click="openNew" />
                <Button v-if="can.delete_department" label="Xóa" icon="pi pi-trash" severity="danger" outlined @click="confirmDeleteSelected" :disabled="!selectedDepartments || !selectedDepartments.length" />
            </template>
        </Toolbar>
        <DataTable ref="dt" v-model:filters="filters" v-model:selection="selectedDepartments" :value="departments" paginator :rows="10" dataKey="id" filterDisplay="menu"
            :globalFilterFields="['code', 'name']">
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
            <template #empty> Không tìm thấy phòng ban. </template>
            <Column selectionMode="multiple" headerStyle="width: 3rem"></Column>
            <Column field="code" header="Mã" sortable style="min-width: 10rem">
                <template #body="{ data }">
                    {{ data.code }}
                </template>
                <template #filter="{ filterModel }">
                    <InputText v-model="filterModel.value" type="text" placeholder="Tìm theo mã" />
                </template>
            </Column>
            <Column field="name" header="Tên" sortable style="min-width: 14rem">
                <template #body="{ data }">
                    {{ data.name }}
                </template>
                <template #filter="{ filterModel }">
                    <InputText v-model="filterModel.value" type="text" placeholder="Tìm theo tên" />
                </template>
            </Column>
            <Column v-if="can.update_department || can.delete_department" :exportable="false" style="min-width: 12rem">
                <template #body="slotProps">
                    <Button icon="pi pi-pencil" outlined rounded class="mr-2" @click="editDepartment(slotProps.data)" />
                    <Button icon="pi pi-trash" outlined rounded severity="danger" @click="confirmDeleteDepartment(slotProps.data)" />
                </template>
            </Column>
        </DataTable>
        <Dialog v-model:visible="departmentDialog" :style="{ width: '450px' }" header="Chi tiết phòng ban" :modal="true">
            <form @submit.prevent="saveDepartment">
                <div class="flex flex-col gap-6">
                    <div>
                        <label for="code" class="block font-bold mb-3 required-field">Mã</label>
                        <InputText id="code" v-model="form.code" @blur="v$.code.$touch" :invalid="v$.code.$error || form.errors.code" fluid />
                        <small v-if="v$.code.$error" class="text-red-500">{{ v$.code.$errors[0].$message }}</small>
                        <small v-else-if="form.errors.code" class="text-red-500">{{ form.errors.code }}</small>
                    </div>
                    <div>
                        <label for="name" class="block font-bold mb-3 required-field">Tên</label>
                        <InputText id="name" v-model="form.name" @blur="v$.name.$touch" :invalid="v$.name.$error || form.errors.name" fluid />
                        <small v-if="v$.name.$error" class="text-red-500">{{ v$.name.$errors[0].$message }}</small>
                        <small v-else-if="form.errors.name" class="text-red-500">{{ form.errors.name }}</small>
                    </div>
                </div>
            </form>
            <template #footer>
                <Button label="Hủy" icon="pi pi-times" text @click="hideDialog" />
                <Button label="Lưu" icon="pi pi-check" :disabled="v$.$invalid || form.processing" @click="saveDepartment" />
            </template>
        </Dialog>
        <Dialog v-model:visible="deleteDepartmentDialog" :style="{ width: '450px' }" header="Confirm" :modal="true">
            <div class="flex items-center gap-4">
                <i class="pi pi-exclamation-triangle !text-3xl" />
                <span v-if="form.name">Bạn chắc chắn muốn xóa <b>{{ form.name }}</b>?</span>
            </div>
            <template #footer>
                <Button label="Không" icon="pi pi-times" text @click="deleteDepartmentDialog = false" />
                <Button label="Có" icon="pi pi-check" @click="deleteDepartment" />
            </template>
        </Dialog>
        <Dialog v-model:visible="deleteDepartmentsDialog" :style="{ width: '450px' }" header="Confirm" :modal="true">
            <div class="flex items-center gap-4">
                <i class="pi pi-exclamation-triangle !text-3xl" />
                <span v-if="form">Bạn chắc chắn muốn xóa những phòng ban đã chọn?</span>
            </div>
            <template #footer>
                <Button label="Không" icon="pi pi-times" text @click="deleteDepartmentsDialog = false" />
                <Button label="Có" icon="pi pi-check" text @click="deleteSelectedDepartments" />
            </template>
        </Dialog>
    </div>
    <Toast />
</template>

<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { FilterMatchMode, FilterOperator } from '@primevue/core/api';
import { useToast } from 'primevue/usetoast';
import { usePage } from '@inertiajs/vue3';
import { useVuelidate } from '@vuelidate/core';
import { required, helpers } from '@vuelidate/validators';
import Toolbar from 'primevue/toolbar';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Toast from 'primevue/toast';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';

const toast = useToast();
const dt = ref();
const page = usePage();
const flash = computed(() => page.props.auth.flash);

watch(flash, (val) => {
  if (val?.message) {
    toast.add({
      severity: val.type === 'error' ? 'error' : 'success',
      summary: val.type === 'error' ? 'Lỗi' : 'Thành công',
      detail: val.message,
      life: 3000
    });
  }
});

defineProps({
    errors: {
        type: Object,
    },
    departments: Object,
    can: Object,
});

const departmentDialog = ref(false);
const deleteDepartmentDialog = ref(false);
const deleteDepartmentsDialog = ref(false);

const form = useForm({
    id: null,
    code: '',
    name: '',
});

const submitted = ref(false);
const isAddDepartment = ref(false);

const rules = computed(() => {
    return {
        code: {
            required: helpers.withMessage('Mã không được để trống.', required),
        },
        name: {
            required: helpers.withMessage('Tên không được để trống.', required),
        },
    };
});

const v$ = useVuelidate(rules, form);

const openNew = () => {
    isAddDepartment.value = true;
    form.reset();
    v$.value.$reset();
    submitted.value = false;
    departmentDialog.value = true;
};

const hideDialog = () => {
    departmentDialog.value = false;
    submitted.value = false;
    form.clearErrors();
    v$.value.$reset();
};

const saveDepartment = async () => {
    submitted.value = true;

    // Trim values before validation
    form.code = form.code?.trim() || '';
    form.name = form.name?.trim() || '';

    const isFormValid = await v$.value.$validate();
    if (!isFormValid) {
        toast.add({severity: 'error', summary: 'Lỗi Validation', detail: 'Vui lòng kiểm tra lại các trường có lỗi.', life: 3000});
        return;
    }
    if (isAddDepartment.value) {
        form.post('/departments', {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                v$.value.$reset();
                departmentDialog.value = false;
            },
            onError: (errors) => {
                console.error("Lỗi khi tạo phòng ban:", errors);
            },
        });
    } else {
        form.put(`/departments/${form.id}`, {
            onSuccess: () => {
                form.reset();
                v$.value.$reset();
                departmentDialog.value = false;
            },
            onError: (errors) => {
                console.error("Lỗi khi cập nhật phòng ban:", errors);
            },
        });
    }
};

const editDepartment = (department) => {
    isAddDepartment.value = false;
    setDepartment(department);
    form.clearErrors();
    v$.value.$reset();
    submitted.value = false;
    departmentDialog.value = true;
};

const confirmDeleteDepartment = (department) => {
    setDepartment(department);
    deleteDepartmentDialog.value = true;
};

const deleteDepartment = () => {
    form.delete(`/departments/${form.id}`, {
        onSuccess: () => {
            form.reset();
            deleteDepartmentDialog.value = false;
        },
        onError: (errors) => {
            console.error("Lỗi khi xóa phòng ban:", errors);
            deleteDepartmentDialog.value = false;
        },
    });
};

const setDepartment = (department) => {
    form.id = department.id;
    form.code = department.code;
    form.name = department.name;
};

const confirmDeleteSelected = () => {
    deleteDepartmentsDialog.value = true;
};

const deleteSelectedDepartments = () => {
    router.post('departments/bulkDelete', { departments: selectedDepartments.value }, {
        onSuccess: () => {
            deleteDepartmentsDialog.value = false;
            selectedDepartments.value = null;
        },
        onError: (errors) => {
            console.error("Lỗi khi xóa nhiều phòng ban:", errors);
            deleteDepartmentsDialog.value = false;
            selectedDepartments.value = null;
        },
    });
};

const selectedDepartments = ref();
const filters = ref();

const initFilters = () => {
    filters.value = {
        global: { value: null, matchMode: FilterMatchMode.CONTAINS },
        code: { operator: FilterOperator.AND, constraints: [{ value: null, matchMode: FilterMatchMode.STARTS_WITH }] },
        name: { operator: FilterOperator.AND, constraints: [{ value: null, matchMode: FilterMatchMode.STARTS_WITH }] },
    };
};

initFilters();

const clearFilter = () => {
    initFilters();
};
</script>

<style scoped>
.required-field::after {
    content: ' *';
    color: red;
    margin-left: 2px;
}
</style>
