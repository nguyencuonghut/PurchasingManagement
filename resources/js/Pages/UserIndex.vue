<template>
    <Head>
        <title>Người dùng</title>
    </Head>

    <div class="card">
        <Toolbar v-if="can.create_user || can.delete_user || can.import_user || can.export_user" class="mb-6">
            <template #start>
                <Button v-if="can.create_user" label="Thêm" icon="pi pi-plus" class="mr-2" @click="openNew" />
                <Button v-if="can.delete_user" label="Xóa" icon="pi pi-trash" severity="danger" outlined @click="confirmDeleteSelected" :disabled="!selectedUsers || !selectedUsers.length" />
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
            <Column field="department_id" header="Phòng ban" sortable style="min-width: 12rem">
                <template #body="{ data }">
                    {{ getDepartmentName(data.department_id) }}
                </template>
                <template #filter="{ filterModel }">
                    <Select v-model="filterModel.value" :options="departments" optionLabel="label" optionValue="value" placeholder="Chọn phòng ban" showClear />
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
            <Column header="Vai trò" field="role_id" sortable :filterMenuStyle="{ width: '14rem' }" style="min-width: 12rem">
                <template #body="{ data }">
                    <Tag :value="getRoleName(data.role_id)" :severity="getRoleSeverity(getRoleName(data.role_id))" />
                </template>
                <template #filter="{ filterModel }">
                    <Select v-model="filterModel.value" :options="roles" optionLabel="label" optionValue="value" placeholder="Chọn" showClear>
                        <template #option="slotProps">
                            <Tag :value="slotProps.option.label" :severity="getRoleSeverity(slotProps.option.label)" />
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
            <Column v-if="can.update_user || can.delete_user" :exportable="false" style="min-width: 12rem">
                <template #body="slotProps">
                    <Button icon="pi pi-pencil" outlined rounded class="mr-2" @click="editUser(slotProps.data)" />
                    <Button icon="pi pi-trash" outlined rounded severity="danger" @click="confirmDeleteUser(slotProps.data)" />
                </template>
            </Column>
        </DataTable>


        <Dialog v-model:visible="userDialog" :style="{ width: '450px' }" header="Chi tiết người dùng" :modal="true">
            <form @submit.prevent="saveUser">
                <div class="flex flex-col gap-6">
                    <div>
                        <label for="name" class="block font-bold mb-3 required-field">Tên</label>
                        <InputText id="name" v-model.trim="form.name" @blur="v$.name.$touch" :invalid="v$.name.$error || form.errors.name" fluid />
                        <small v-if="v$.name.$error" class="text-red-500">{{ v$.name.$errors[0].$message }}</small>
                        <small v-else-if="form.errors.name" class="text-red-500">{{ form.errors.name }}</small>
                    </div>
                    <div>
                        <label for="email" class="block font-bold mb-3 required-field">Email</label>
                        <InputText id="email" v-model.trim="form.email" @blur="v$.email.$touch" :invalid="v$.email.$error || form.errors.email" fluid />
                        <small v-if="v$.email.$error" class="text-red-500">{{ v$.email.$errors[0].$message }}</small>
                        <small v-else-if="form.errors.email" class="text-red-500">{{ form.errors.email }}</small>
                    </div>
                    <div v-if="isAddUser">
                        <label for="password" class="block font-bold mb-3 required-field">Mật khẩu</label>
                        <Password id="password" v-model.trim="form.password" @blur="v$.password.$touch" autocomplete="off" :invalid="v$.password.$error || form.errors.password" fluid toggleMask />
                        <small v-if="v$.password.$error" class="text-red-500">{{ v$.password.$errors[0].$message }}</small>
                        <small v-else-if="form.errors.password" class="text-red-500">{{ form.errors.password }}</small>
                    </div>
                    <div v-if="isAddUser">
                        <label for="password_confirmation" class="block font-bold mb-3 required-field">Xác nhận mật khẩu</label>
                        <Password id="password_confirmation" v-model.trim="form.password_confirmation" @blur="v$.password_confirmation.$touch" autocomplete="off" :invalid="v$.password_confirmation.$error || form.errors.password_confirmation" fluid toggleMask />
                        <small v-if="v$.password_confirmation.$error" class="text-red-500">{{ v$.password_confirmation.$errors[0].$message }}</small>
                        <small v-else-if="form.errors.password_confirmation" class="text-red-500">{{ form.errors.password_confirmation }}</small>
                    </div>
                    <div>
                        <label for="department" class="block font-bold mb-3 required-field">Phòng ban</label>
                        <Select id="department" v-model="form.department_id" @blur="v$.department_id.$touch" :options="departments" optionLabel="label" optionValue="value" class="w-full" placeholder="Chọn phòng ban" :invalid="v$.department_id.$error || form.errors.department_id" />
                        <small v-if="v$.department_id.$error" class="text-red-500">{{ v$.department_id.$errors[0].$message }}</small>
                        <small v-else-if="form.errors.department_id" class="text-red-500">{{ form.errors.department_id }}</small>
                    </div>
                    <div>
                        <label for="role" class="block font-bold mb-3 required-field">Vai trò</label>
                        <Select id="role" v-model="form.role_id" @blur="v$.role_id.$touch" :options="roles" optionLabel="label" optionValue="value" class="w-full" placeholder="Chọn vai trò" :invalid="v$.role_id.$error || form.errors.role_id" />
                        <small v-if="v$.role_id.$error" class="text-red-500">{{ v$.role_id.$errors[0].$message }}</small>
                        <small v-else-if="form.errors.role_id" class="text-red-500">{{ form.errors.role_id }}</small>
                    </div>
                    <div>
                        <span class="block font-bold mb-4 required-field">Trạng thái</span>
                        <div class="grid grid-cols-12 gap-4">
                            <div class="flex items-center gap-2 col-span-6">
                                <RadioButton id="status_on" v-model="form.status" value="On" name="status" @blur="v$.status.$touch" :invalid="v$.status.$error || form.errors.status"/>
                                <label for="status_on">ON</label>
                            </div>
                            <div class="flex items-center gap-2 col-span-6">
                                <RadioButton id="status_off" v-model="form.status" value="Off" name="status" @blur="v$.status.$touch" :invalid="v$.status.$error || form.errors.status"/>
                                <label for="status_off">OFF</label>
                            </div>
                        </div>
                        <small v-if="v$.status.$error" class="text-red-500">{{ v$.status.$errors[0].$message }}</small>
                        <small v-else-if="form.errors.status" class="text-red-500">{{ form.errors.status }}</small>
                    </div>
                </div>
            </form>
            <template #footer>
                <Button label="Hủy" icon="pi pi-times" text @click="hideDialog" />
                <Button label="Lưu" icon="pi pi-check" :disabled="v$.$invalid || form.processing" @click="saveUser" />
            </template>
        </Dialog>

        <Dialog v-model:visible="deleteUserDialog" :style="{ width: '450px' }" header="Confirm" :modal="true">
            <div class="flex items-center gap-4">
                <i class="pi pi-exclamation-triangle !text-3xl" />
                <span v-if="form.name"
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
    <Toast />
</template>

<script setup>
import { Head, router, useForm } from '@inertiajs/vue3'; // Đổi useForm từ Precognition sang @inertiajs/vue3
import { ref, computed, watch } from 'vue'; // Import computed
import { FilterMatchMode, FilterOperator } from '@primevue/core/api';
import { useToast } from 'primevue/usetoast';
import { usePage } from '@inertiajs/vue3'

// Import Vuelidate
import { useVuelidate } from '@vuelidate/core';
import { required, email, minLength, sameAs, helpers } from '@vuelidate/validators';

// PrimeVue Components (nếu bạn dùng auto-import thì không cần liệt kê hết)
import Toolbar from 'primevue/toolbar';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password'; // Thêm Password
import RadioButton from 'primevue/radiobutton';
import Select from 'primevue/select'; // Đổi từ Dropdown thành Select nếu cần
import Toast from 'primevue/toast';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import Tag from 'primevue/tag'; // Thêm Tag
import FileUpload from 'primevue/fileupload';

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
    users: Object,
    can: Object,
});

const userDialog = ref(false);
const deleteUserDialog = ref(false);
const deleteUsersDialog = ref(false);

const form = useForm({
    id: null,
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role_id: '',
    department_id: '',
    status: '',
});

const submitted = ref(false);
const isAddUser = ref(false);

// Định nghĩa rules cho Vuelidate
const rules = computed(() => {
    // Rules cơ bản áp dụng cho cả tạo và cập nhật
    const baseRules = {
        name: {
            required: helpers.withMessage('Tên không được để trống.', required),
        },
        email: {
            required: helpers.withMessage('Email không được để trống.', required),
            email: helpers.withMessage('Email sai định dạng.', email),
        },
        role_id: {
            required: helpers.withMessage('Vai trò không được để trống.', required),
        },
        department_id: {
            required: helpers.withMessage('Phòng ban không được để trống.', required),
        },
        status: {
            required: helpers.withMessage('Trạng thái không được để trống.', required),
        },
    };

    // Thêm rules cho password chỉ khi là "Add new User"
    if (isAddUser.value) {
        baseRules.password = {
            required: helpers.withMessage('Mật khẩu không được để trống.', required),
            minLength: helpers.withMessage('Mật khẩu phải dài ít nhất 6 ký tự.', minLength(6)),
        };
        baseRules.password_confirmation = {
            required: helpers.withMessage('Xác nhận mật khẩu không được để trống.', required),
            sameAs: helpers.withMessage('Mật khẩu không khớp.', sameAs(form.password)),
        };
    } else {
        // Đặt password và password_confirmation là optional khi không phải thêm mới
        // hoặc khi không có giá trị để tránh validation lỗi khi edit
        baseRules.password = {};
        baseRules.password_confirmation = {};
    }

    return baseRules;
});

// Khởi tạo Vuelidate
const v$ = useVuelidate(rules, form);

const openNew = () => {
    isAddUser.value = true;
    form.reset(); // Reset form data
    v$.value.$reset(); // Reset Vuelidate state
    submitted.value = false;
    userDialog.value = true;
};

const hideDialog = () => {
    userDialog.value = false;
    submitted.value = false;
    form.clearErrors(); // Xóa lỗi Inertia
    v$.value.$reset(); // Reset Vuelidate state
};

const saveUser = async () => {
    console.log("Form data before save:", form);
    submitted.value = true;
    const isFormValid = await v$.value.$validate(); // Chạy validation frontend

    if (!isFormValid) {
        toast.add({severity: 'error', summary: 'Lỗi Validation', detail: 'Vui lòng kiểm tra lại các trường có lỗi.', life: 3000});
        return; // Dừng lại nếu frontend validation thất bại
    }

    // Nếu frontend validation pass, tiếp tục gửi đến backend
    if (isAddUser.value) {
        form.post('/users', {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                v$.value.$reset(); // Reset Vuelidate
                userDialog.value = false;
            },
            onError: (errors) => {
                // InertiaJS tự động điền errors vào form.errors
                console.error("Lỗi khi tạo người dùng:", errors);
            },
        });
    } else {
        // Edit this User
        form.put(`/users/${form.id}`, {
            onSuccess: () => {
                form.reset();
                v$.value.$reset(); // Reset Vuelidate
                userDialog.value = false;
            },
            onError: (errors) => {
                // InertiaJS tự động điền errors vào form.errors
                console.error("Lỗi khi cập nhật người dùng:", errors);
            },
        });
    }
};

const editUser = (usr) => {
    isAddUser.value = false;
    setUser(usr);
    form.clearErrors(); // Xóa lỗi Inertia cũ
    v$.value.$reset(); // Reset trạng thái validation của Vuelidate
    submitted.value = false;
    userDialog.value = true;
};

const confirmDeleteUser = (usr) => {
    setUser(usr);
    deleteUserDialog.value = true;
};

const deleteUser = () => {
    form.delete(`/users/${form.id}`, {
        onSuccess: () => {
            form.reset();
            deleteUserDialog.value = false;
        },
        onError: (errors) => {
            console.error("Lỗi khi xóa người dùng:", errors);
            deleteUserDialog.value = false;
        },
    });
};

const setUser = (usr) => {
    form.id = usr.id;
    form.name = usr.name;
    form.email = usr.email;
    form.role_id = usr.role_id;
    form.department_id = usr.department_id;
    form.status = usr.status;
    form.password = '';
    form.password_confirmation = '';
};

const exportCSV = () => {
    dt.value.exportCSV();
};

const confirmDeleteSelected = () => {
    deleteUsersDialog.value = true;
};

const deleteSelectedUsers = () => {
    router.post('users/bulkDelete', { users: selectedUsers.value }, {
        onSuccess: () => {
            deleteUsersDialog.value = false;
            selectedUsers.value = null;
        },
        onError: (errors) => {
            console.error("Lỗi khi xóa nhiều người dùng:", errors);
            deleteUsersDialog.value = false;
            selectedUsers.value = null;
        },
    });
};

const selectedUsers = ref();
const filters = ref();
const roles = ref([]);
if (page.props && page.props.roles) {
    roles.value = page.props.roles.map(role => ({ label: role.name, value: role.id }));
}
function getRoleName(id) {
    if (!id) return '-';
    const found = roles.value.find(role => String(role.value) === String(id));
    return found ? found.label : '-';
}

const departments = ref([]);
if (page.props && page.props.departments) {
    departments.value = page.props.departments.map(dep => ({ label: dep.name, value: dep.id }));
}

function getDepartmentName(id) {
    if (!id) return '-';
    const found = departments.value.find(dep => String(dep.value) === String(id));
    return found ? found.label : '-';
}
const statuses = ref(['On', 'Off']);

const initFilters = () => {
    filters.value = {
        global: { value: null, matchMode: FilterMatchMode.CONTAINS },
        name: { operator: FilterOperator.AND, constraints: [{ value: null, matchMode: FilterMatchMode.STARTS_WITH }] },
        email: { operator: FilterOperator.AND, constraints: [{ value: null, matchMode: FilterMatchMode.STARTS_WITH }] },
        department_id: { operator: FilterOperator.OR, constraints: [{ value: null, matchMode: FilterMatchMode.EQUALS }] },
        role_id: { operator: FilterOperator.OR, constraints: [{ value: null, matchMode: FilterMatchMode.EQUALS }] },
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
        default:
            return null; // Handle default case if status is not 'On' or 'Off'
    }
};

// Map roles to severity levels
const roleSeverityMap = {
  'Quản trị': 'success',
  'Nhân viên Thu Mua': 'info',
  'Trưởng phòng Thu Mua': 'success',
  'Nhân viên Kiểm Soát': 'warn',
  'Giám đốc': 'danger',
  'Kế toán': 'secondary',
  'Admin Thu Mua': 'secondary',
};

function getRoleSeverity(role) {
  return roleSeverityMap[role] || 'secondary';
}
</script>

<style scoped>
.required-field::after {
    content: ' *';
    color: red;
    margin-left: 2px;
}
</style>
