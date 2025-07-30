<template>
    <Head>
        <title>Người dùng</title>
    </Head>

    <div class="card">
        <Toolbar  v-if="can.create_user || can.delete_user || can.import_user || can.export_user" class="mb-6">
            <template #start>
                <Button v-if="can.create_user" label="New" icon="pi pi-plus" class="mr-2" @click="openNew" />
                <Button v-if="can.delete_user" label="Delete" icon="pi pi-trash" severity="danger" outlined @click="confirmDeleteSelected" :disabled="!selectedUsers || !selectedUsers.length" />
            </template>

            <template #end>
                <FileUpload v-if="can.import_user" mode="basic" accept="image/*" :maxFileSize="1000000" label="Import" customUpload chooseLabel="Import" class="mr-2" auto :chooseButtonProps="{ severity: 'secondary' }" />
                <Button v-if="can.export_user" label="Export" icon="pi pi-upload" severity="secondary" @click="exportCSV($event)" />
            </template>
        </Toolbar>

        <DataTable ref="dt" v-model:filters="filters" v-model:selection="selectedUsers" :value="users" paginator :rows="10" dataKey="id" filterDisplay="menu"
            :globalFilterFields="['name', 'email', 'role', 'status']">
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
            <template #empty> Không tìm thấy người dùng. </template>
            <Column selectionMode="multiple" headerStyle="width: 3rem"></Column>
            <Column field="name" header="Tên" sortable style="min-width: 14rem">
                <template #body="{ data }">
                    {{ data.name }}
                </template>
                <template #filter="{ filterModel }">
                    <InputText v-model="filterModel.value" type="text" placeholder="Tìm theo tên" />
                </template>
            </Column>
            <Column field="email" header="Email" sortable style="min-width: 14rem">
                <template #body="{ data }">
                    {{ data.email }}
                </template>
                <template #filter="{ filterModel }">
                    <InputText v-model="filterModel.value" type="text" placeholder="Tìm theo email" />
                </template>
            </Column>
            <Column header="Vai trò" field="role" sortable :filterMenuStyle="{ width: '14rem' }" style="min-width: 12rem">
                <template #body="{ data }">
                    <Tag :value="data.role" :severity="getRoleSeverity(data.role)" />
                </template>
                <template #filter="{ filterModel }">
                    <Select v-model="filterModel.value" :options="roles" placeholder="Chọn" showClear>
                        <template #option="slotProps">
                            <Tag :value="slotProps.option" :severity="getRoleSeverity(slotProps.option)" />
                        </template>
                    </Select>
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
            <Column  v-if="can.update_user || can.delete_user" :exportable="false" style="min-width: 12rem">
                <template #body="slotProps">
                    <Button icon="pi pi-pencil" outlined rounded class="mr-2" @click="editUser(slotProps.data)" />
                    <Button icon="pi pi-trash" outlined rounded severity="danger" @click="confirmDeleteUser(slotProps.data)" />
                </template>
            </Column>
        </DataTable>


        <Dialog v-model:visible="userDialog" :style="{ width: '450px' }" header="Chi tiết người dùng" :modal="true">
            <div class="flex flex-col gap-6">
                <div>
                    <label for="name" class="block font-bold mb-3 required-field">Tên</label>
                    <InputText id="name" v-model="form.name" @change="form.validate('name')" autofocus :invalid="submitted && !form.name" fluid />
                    <small v-if="form.invalid('name')" class="text-red-500">{{ form.errors.name }}</small>
                </div>
                <div>
                    <label for="email" class="block font-bold mb-3 required-field">Email</label>
                    <InputText id="email" v-model.trim="form.email" @change="form.validate('email')" autofocus :invalid="submitted && !form.email" fluid />
                    <small v-if="form.invalid('email')" class="text-red-500">{{ form.errors.email }}</small>
                </div>
                <div v-if="isAddUser">
                    <label for="password" class="block font-bold mb-3 required-field">Mật khẩu</label>
                    <Password id="password" v-model.trim="form.password" @change="form.validate('password')" autocomplete="off" autofocus :invalid="submitted && !form.password" fluid />
                    <small v-if="form.invalid('password')" class="text-red-500">{{ form.errors.password }}</small>
                </div>
                <div v-if="isAddUser">
                    <label for="password_confirmation" class="block font-bold mb-3 required-field">Xác nhận mật khẩu</label>
                    <Password id="password_confirmation" v-model.trim="form.password_confirmation" @change="form.validate('password_confirmation')" autocomplete="off" autofocus :invalid="submitted && !form.password_confirmation" fluid />
                    <small v-if="form.invalid('password_confirmation')" class="text-red-500">{{ form.errors.password_confirmation }}</small>
                </div>
                <div>
                    <label for="role" class="block font-bold mb-3 required-field">Vai trò</label>
                    <Select v-model="form.role" @change="form.validate('role')" :options="roles" class="w-full" placeholder="Chọn vai trò" />
                    <small v-if="form.invalid('role')" class="text-red-500">{{ form.errors.role }}</small>
                </div>
                <div>
                    <span class="block font-bold mb-4 required-field">Trạng thái</span>
                    <div class="grid grid-cols-12 gap-4">
                        <div class="flex items-center gap-2 col-span-6">
                            <RadioButton id="status_on" v-model="form.status" value="On" name="status"/>
                            <label for="status_on">ON</label>
                        </div>
                        <div class="flex items-center gap-2 col-span-6">
                            <RadioButton id="status_off" v-model="form.status" value="Off" name="status"/>
                            <label for="status_off">OFF</label>
                        </div>
                    </div>
                    <small v-if="form.invalid('status')" class="text-red-500">{{ form.errors.status }}</small>
                </div>
            </div>

            <template #footer>
                <Button label="Hủy" icon="pi pi-times" text @click="hideDialog" />
                <Button label="Lưu" icon="pi pi-check" :disabled="form.hasErrors" @click="saveUser" />
            </template>
        </Dialog>

        <Dialog v-model:visible="deleteUserDialog" :style="{ width: '450px' }" header="Confirm" :modal="true">
            <div class="flex items-center gap-4">
                <i class="pi pi-exclamation-triangle !text-3xl" />
                <span v-if="form"
                    >Bạn chắc chắn muốn xóa <b>{{ form.name }}</b
                    >?</span
                >
            </div>
            <template #footer>
                <Button label="Không" icon="pi pi-times" text @click="deleteUserDialog = false" />
                <Button label="Có" icon="pi pi-check" @click="deleteUser" />
            </template>
        </Dialog>

        <Dialog v-model:visible="deleteUsersDialog" :style="{ width: '450px' }" header="Confirm" :modal="true">
            <div class="flex items-center gap-4">
                <i class="pi pi-exclamation-triangle !text-3xl" />
                <span v-if="form">Bạn chắc chắn muốn xóa những người đã chọn?</span>
            </div>
            <template #footer>
                <Button label="Không" icon="pi pi-times" text @click="deleteUsersDialog = false" />
                <Button label="Có" icon="pi pi-check" text @click="deleteSelectedUsers" />
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
import { router } from '@inertiajs/vue3';
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
    users: Object,
    can: Object,
});

const userDialog = ref(false);
const deleteUserDialog = ref(false);
const deleteUsersDialog = ref(false);
const form = useForm('post', '/users', {
    id: '',
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: '',
    status: '',
});
const submitted = ref(false);
const isAddUser = ref(false);

const openNew = () => {
    form.reset();
    submitted.value = false;
    userDialog.value = true;
    isAddUser.value = true;
};

const hideDialog = () => {
    userDialog.value = false;
    submitted.value = false;
};

const saveUser = () => {
    submitted.value = true;

    // Add new User
    if (isAddUser.value) {
        // Creat new User
        form.submit({
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                userDialog.value = false;
                toast.add({severity:'success', summary: 'Thành công', detail: message, life: 3000});
            },
            onError: () => {
                toast.add({severity: 'error', summary: 'Lỗi', detail: message, life: 3000});
            },
        });
    } else {
        // Edit this User
        form.put(`users/${form.id}`, {
            onSuccess: () => {
                form.reset();
                userDialog.value = false;
                toast.add({severity: 'success', summary: 'Thành công', detail: message, life: 3000});
            },
            onError: () => {
                toast.add({severity: 'error', summary: 'Lỗi', detail: message, life: 3000});
            },
        });

    }
};
const editUser = (usr) => {
    setUser(usr);
    userDialog.value = true;
    isAddUser.value = false;
};
const confirmDeleteUser = (usr) => {
    setUser(usr);
    deleteUserDialog.value = true;
};

const deleteUser = () => {
    form.delete(`users/${form.id}`, {
        onSuccess: () => {
            form.reset();
            deleteUserDialog.value = false;
            toast.add({severity:'success', summary: 'Successful', detail: message, life: 3000});
        },
        onError: () => {
            deleteUserDialog.value = false;
            toast.add({severity:'error', summary: 'Failed', detail: message, life: 3000});
        },
    });
};

const setUser = (usr) => {
    form.id = usr.id;
    form.name = usr.name;
    form.email = usr.email;
    form.role = usr.role;
    form.status = usr.status;
}

const exportCSV = () => {
    dt.value.exportCSV();
};
const confirmDeleteSelected = () => {
    deleteUsersDialog.value = true;
};

const deleteSelectedUsers = () => {
    router.post('users/bulkDelete', {users : selectedUsers.value }, {
        onSuccess: () => {
            deleteUsersDialog.value = false;
            selectedUsers.value = null;
            toast.add({severity:'success', summary: 'Successful', detail: message, life: 3000});
        },
        onError: () => {
            deleteUsersDialog.value = false;
            selectedUsers.value = null;
            toast.add({severity:'error', summary: 'Failed', detail: message, life: 3000});
        },
    });
};

const selectedUsers = ref();
const filters = ref();
const roles = ref(['Quản trị', 'Người dùng']);
const statuses = ref(['On', 'Off']);

const initFilters = () => {
    filters.value = {
        global: { value: null, matchMode: FilterMatchMode.CONTAINS },
        name: { operator: FilterOperator.AND, constraints: [{ value: null, matchMode: FilterMatchMode.STARTS_WITH }] },
        email: { operator: FilterOperator.AND, constraints: [{ value: null, matchMode: FilterMatchMode.STARTS_WITH }] },
        role: { operator: FilterOperator.OR, constraints: [{ value: null, matchMode: FilterMatchMode.EQUALS }] },
        status: { operator: FilterOperator.OR, constraints: [{ value: null, matchMode: FilterMatchMode.EQUALS }] },
    };
};

initFilters();

const clearFilter = () => {
    initFilters();
};

const getStatusSeverity = (status) => {
    switch (status) {
        case 'On':
            return 'success';

        case 'Off':
            return 'danger';
    }
};

const getRoleSeverity = (status) => {
    switch (status) {
        case 'Người quản trị':
            return 'success';

        case 'Người dùng':
            return 'info';
    }
};
</script>
