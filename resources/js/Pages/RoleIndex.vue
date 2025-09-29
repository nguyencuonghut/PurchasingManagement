<template>
    <Head>
        <title>Vai trò</title>
    </Head>
    <div class="card">
        <Toolbar v-if="can.create_role || can.delete_role" class="mb-6">
            <template #start>
                <Button v-if="can.create_role" label="Thêm" icon="pi pi-plus" class="mr-2" @click="openNew" />
                <Button v-if="can.delete_role" label="Xóa" icon="pi pi-trash" severity="danger" outlined @click="confirmDeleteSelected" :disabled="!selectedRoles || !selectedRoles.length" />
            </template>
        </Toolbar>
        <DataTable ref="dt" v-model:filters="filters" v-model:selection="selectedRoles" :value="roles" paginator :rows="10" dataKey="id" filterDisplay="menu"
            :globalFilterFields="['name']">
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
            <template #empty> Không tìm thấy vai trò. </template>
            <Column selectionMode="multiple" headerStyle="width: 3rem"></Column>
            <Column field="name" header="Tên" sortable style="min-width: 14rem">
                <template #body="{ data }">
                    {{ data.name }}
                </template>
                <template #filter="{ filterModel }">
                    <InputText v-model="filterModel.value" type="text" placeholder="Tìm theo tên" />
                </template>
            </Column>
            <Column v-if="can.update_role || can.delete_role" :exportable="false" style="min-width: 12rem">
                <template #body="slotProps">
                    <Button icon="pi pi-pencil" outlined rounded class="mr-2" @click="editRole(slotProps.data)" />
                    <Button icon="pi pi-trash" outlined rounded severity="danger" @click="confirmDeleteRole(slotProps.data)" />
                </template>
            </Column>
        </DataTable>
        <Dialog v-model:visible="roleDialog" :style="{ width: '450px' }" header="Chi tiết vai trò" :modal="true">
            <form @submit.prevent="saveRole">
                <div class="flex flex-col gap-6">
                    <div>
                        <label for="name" class="block font-bold mb-3 required-field">Tên</label>
                        <InputText id="name" v-model.trim="form.name" @blur="v$.name.$touch" :invalid="v$.name.$error || form.errors.name" fluid />
                        <small v-if="v$.name.$error" class="text-red-500">{{ v$.name.$errors[0].$message }}</small>
                        <small v-else-if="form.errors.name" class="text-red-500">{{ form.errors.name }}</small>
                    </div>
                </div>
            </form>
            <template #footer>
                <Button label="Hủy" icon="pi pi-times" text @click="hideDialog" />
                <Button label="Lưu" icon="pi pi-check" :disabled="v$.$invalid || form.processing" @click="saveRole" />
            </template>
        </Dialog>
        <Dialog v-model:visible="deleteRoleDialog" :style="{ width: '450px' }" header="Confirm" :modal="true">
            <div class="flex items-center gap-4">
                <i class="pi pi-exclamation-triangle !text-3xl" />
                <span v-if="form.name">Bạn chắc chắn muốn xóa <b>{{ form.name }}</b>?</span>
            </div>
            <template #footer>
                <Button label="Không" icon="pi pi-times" text @click="deleteRoleDialog = false" />
                <Button label="Có" icon="pi pi-check" @click="deleteRole" />
            </template>
        </Dialog>
        <Dialog v-model:visible="deleteRolesDialog" :style="{ width: '450px' }" header="Confirm" :modal="true">
            <div class="flex items-center gap-4">
                <i class="pi pi-exclamation-triangle !text-3xl" />
                <span v-if="form">Bạn chắc chắn muốn xóa những vai trò đã chọn?</span>
            </div>
            <template #footer>
                <Button label="Không" icon="pi pi-times" text @click="deleteRolesDialog = false" />
                <Button label="Có" icon="pi pi-check" text @click="deleteSelectedRoles" />
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
import { usePage } from '@inertiajs/vue3'
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
    roles: Object,
    can: Object,
});

const roleDialog = ref(false);
const deleteRoleDialog = ref(false);
const deleteRolesDialog = ref(false);

const form = useForm({
    id: null,
    name: '',
});

const submitted = ref(false);
const isAddRole = ref(false);

const rules = computed(() => {
    return {
        name: {
            required: helpers.withMessage('Tên không được để trống.', required),
        },
    };
});

const v$ = useVuelidate(rules, form);

const openNew = () => {
    isAddRole.value = true;
    form.reset();
    v$.value.$reset();
    submitted.value = false;
    roleDialog.value = true;
};

const hideDialog = () => {
    roleDialog.value = false;
    submitted.value = false;
    form.clearErrors();
    v$.value.$reset();
};

const saveRole = async () => {
    submitted.value = true;
    const isFormValid = await v$.value.$validate();
    if (!isFormValid) {
        toast.add({severity: 'error', summary: 'Lỗi Validation', detail: 'Vui lòng kiểm tra lại các trường có lỗi.', life: 3000});
        return;
    }
    if (isAddRole.value) {
        form.post('/roles', {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                v$.value.$reset();
                roleDialog.value = false;
            },
            onError: (errors) => {
                console.error("Lỗi khi tạo vai trò:", errors);
            },
        });
    } else {
        form.put(`/roles/${form.id}`, {
            onSuccess: () => {
                form.reset();
                v$.value.$reset();
                roleDialog.value = false;
            },
            onError: (errors) => {
                console.error("Lỗi khi cập nhật vai trò:", errors);
            },
        });
    }
};

const editRole = (role) => {
    isAddRole.value = false;
    setRole(role);
    form.clearErrors();
    v$.value.$reset();
    submitted.value = false;
    roleDialog.value = true;
};

const confirmDeleteRole = (role) => {
    setRole(role);
    deleteRoleDialog.value = true;
};

const deleteRole = () => {
    form.delete(`/roles/${form.id}`, {
        onSuccess: () => {
            form.reset();
            deleteRoleDialog.value = false;
        },
        onError: (errors) => {
            console.error("Lỗi khi xóa vai trò:", errors);
            deleteRoleDialog.value = false;
        },
    });
};

const setRole = (role) => {
    form.id = role.id;
    form.name = role.name;
};

const confirmDeleteSelected = () => {
    deleteRolesDialog.value = true;
};

const deleteSelectedRoles = () => {
    router.post('roles/bulkDelete', { roles: selectedRoles.value }, {
        onSuccess: () => {
            deleteRolesDialog.value = false;
            selectedRoles.value = null;
        },
        onError: (errors) => {
            console.error("Lỗi khi xóa nhiều vai trò:", errors);
            deleteRolesDialog.value = false;
            selectedRoles.value = null;
        },
    });
};

const selectedRoles = ref();
const filters = ref();
const statuses = ref(['On', 'Off']);

const initFilters = () => {
    filters.value = {
        global: { value: null, matchMode: FilterMatchMode.CONTAINS },
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
