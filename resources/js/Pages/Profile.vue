<template>
    <Head>
        <title>Thông tin cá nhân</title>
    </Head>
    <div class="profile-page profile-flex-container p-0">
      <!-- Left: User Info Card -->
      <div class="profile-left">
        <Card class="user-info-card text-center p-4">
          <template #title>Thông tin cá nhân</template>
          <template #content>
            <h2>{{ user.name }}</h2>
            <p class="text-gray-500 mb-2">{{ user.role }}</p>
            <p class="mb-2"><i class="pi pi-envelope mr-2" />{{ user.email }}</p>
            <Button label="Đổi mật khẩu" icon="pi pi-key" severity="info" class="mt-3 w-full" @click="changePassword = true" />
          </template>
        </Card>
      </div>
      <!-- Right: Recent Activities Card -->
      <div class="profile-right">
        <Card class="p-4">
          <template #title>Hoạt động gần đây</template>
          <template #content>
            <DataTable :value="activityLogs" :rows="7" paginator>
              <Column field="created_at" header="Thời gian">
                <template #body="{ data }">
                  {{ formatDate(data.created_at) }}
                </template>
              </Column>
              <Column field="action" header="Hành động">
                <template #body="{ data }">
                  <span v-if="data.subject_id && reportCodesById[data.subject_id]">
                    <a :href="`/supplier_selection_reports/${data.subject_id}`" class="text-primary hover:underline mr-1">{{ reportCodesById[data.subject_id] }}</a>
                  </span>
                  {{'· ' + getActionVi(data.action) }}
                </template>
              </Column>
              <Column field="properties" header="Chi tiết">
                <template #body="{ data }">
                  <span v-if="data.properties && data.properties.notes">{{ data.properties.notes }}</span>
                  <span v-else>-</span>
                </template>
              </Column>
            </DataTable>
          </template>
        </Card>
      </div>
      <!-- Change Password Modal -->
      <Dialog v-model:visible="changePassword" header="Đổi mật khẩu" :modal="true" :closable="true" :style="{ width: '98vw', maxWidth: '520px' }">
        <div class="p-fluid">
          <div class="field">
            <label>Mật khẩu hiện tại</label>
            <Password v-model="passwordForm.current_password" toggleMask class="w-full" inputClass="w-full" />
            <small v-if="passwordForm.errors.current_password" class="text-red-500">{{ passwordForm.errors.current_password }}</small>
          </div>
          <div class="field">
            <label>Mật khẩu mới</label>
            <Password v-model="passwordForm.new_password" toggleMask class="w-full" inputClass="w-full" />
            <small v-if="passwordForm.errors.new_password" class="text-red-500">{{ passwordForm.errors.new_password }}</small>
          </div>
          <div class="field">
            <label>Nhập lại mật khẩu mới</label>
            <Password v-model="passwordForm.new_password_confirmation" toggleMask class="w-full" inputClass="w-full" />
            <small v-if="passwordForm.errors.new_password_confirmation" class="text-red-500">{{ passwordForm.errors.new_password_confirmation }}</small>
          </div>
          <Button label="Cập nhật" icon="pi pi-check" class="mt-2 w-full" @click="updatePassword" :loading="passwordForm.processing" />
        </div>
      </Dialog>
    </div>
</template>

<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Button from 'primevue/button';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Dialog from 'primevue/dialog';
import Password from 'primevue/password';
import Card from 'primevue/card';

import { usePage } from '@inertiajs/vue3';
const page = usePage();
const user = computed(() => page.props.user ?? {});
const activityLogs = computed(() => page.props.activityLogs ?? []);
const reportCodesById = computed(() => page.props.reportCodesById ?? {});

const changePassword = ref(false);
const passwordForm = useForm({
  current_password: '',
  new_password: '',
  new_password_confirmation: '',
});

function updatePassword() {
  // Clear previous errors before submitting
  passwordForm.clearErrors();
  passwordForm.post('/change-password', {
    onSuccess: () => {
      changePassword.value = false;
      passwordForm.reset();
    },
    onError: (errors) => {
      if (errors && errors.message) {
        alert(errors.message);
      } else {
        console.log('Có lỗi xảy ra, vui lòng thử lại.');
      }
    }
  });
}
function formatDate(dateString) {
  if (!dateString) return '';
  const date = new Date(dateString);
  const day = String(date.getDate()).padStart(2, '0');
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const year = date.getFullYear();
  const hours = String(date.getHours()).padStart(2, '0');
  const minutes = String(date.getMinutes()).padStart(2, '0');
  return `${day}/${month}/${year} ${hours}:${minutes}`;
}

// Map action to Vietnamese (chuẩn theo BE)
const actionViMap = {
  'created': 'Tạo báo cáo',
  'updated': 'Cập nhật báo cáo',
  'deleted': 'Xóa báo cáo',
  'submitted_to_manager': 'Gửi Trưởng Phòng duyệt',
  'manager_approved': 'Trưởng Phòng duyệt',
  'manager_rejected': 'Trưởng Phòng từ chối',
  'auditor_approved': 'Kiểm Soát duyệt',
  'auditor_rejected': 'Kiểm Soát từ chối',
  'submitted_to_director': 'Gửi Giám Đốc duyệt',
  'director_approved': 'Giám Đốc duyệt',
  'director_rejected': 'Giám Đốc từ chối',
};
function getActionVi(action) {
  return actionViMap[action] || action;
}

</script>

<style scoped>
.profile-page {
  width: 100%;
  margin: 0;
  padding: 0;
}
.profile-flex-container {
  display: flex;
  flex-direction: row;
  gap: 1.5rem;
}
.profile-left {
  flex: 0 0 400px;
  max-width: 480px;
  min-width: 320px;
}
.profile-right {
  flex: 1 1 0%;
  min-width: 0;
}
.user-info-card {
  min-height: 220px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}
@media (max-width: 900px) {
  .profile-flex-container {
    flex-direction: column;
    gap: 1rem;
  }
  .profile-left, .profile-right {
    max-width: 100%;
    min-width: 0;
    flex: 1 1 100%;
  }
  .p-dialog {
    width: 98vw !important;
    max-width: 98vw !important;
  }
}
@media (max-width: 600px) {
  .card {
    padding: 0.5rem !important;
  }
  .profile-page {
    padding: 0.5rem !important;
  }
  .p-dialog {
    width: 100vw !important;
    max-width: 100vw !important;
    margin: 0 !important;
  }
  .p-dialog .p-dialog-content {
    padding: 1rem 0.5rem !important;
  }
  .field label, .field input, .field .p-password {
    width: 100% !important;
    min-width: 0;
    box-sizing: border-box;
  }
}
</style>
