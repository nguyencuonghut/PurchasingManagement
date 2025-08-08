<template>
  <Head>
    <title>Chi tiết BCLCNCC</title>
  </Head>
  <div class="card">
    <h2 class="font-bold text-xl mb-4">Chi tiết báo cáo lựa chọn nhà cung cấp</h2>
    <div class="mb-4">
      <div><b>Mã:</b> {{ report.code }}</div>
      <div><b>Mô tả:</b> {{ report.description }}</div>
      <div><b>Trạng thái:</b> <Tag :value="report.status" :severity="getStatusSeverity(report.status)" /></div>
      <div><b>Người tạo:</b> {{ report.creator_name }} ({{ formatDate(report.created_at) }})</div>
      <div v-if="'pending' !== report.manager_approved_result"><b>Trưởng phòng Thu Mua:</b>
        <div class="flex ml-6 gap-4">
          <div style="width: 50%"><b>Người duyệt: </b>{{ report.manager_name }}</div>
          <div style="width: 50%"><b>Thời gian: </b>{{ formatDate(report.manager_approved_at) }}</div>
        </div>
        <div class="flex ml-6 gap-4">
          <div style="width: 50%"><b>Kết quả: </b><Tag :value="report.manager_approved_result" :severity="getResultsSeverity(report.manager_approved_result)" /></div>
          <div v-if="report.manager_approved_notes" style="width: 50%"><b>Ghi chú: </b>{{ report.manager_approved_notes }}</div>
        </div>
      </div>
      <div v-if="'pending' !== report.auditor_audited_result"><b>Nhân viên Kiểm Soát:</b>
        <div class="flex ml-6 gap-4">
          <div style="width: 50%"><b>Người duyệt: </b>{{ report.auditor_name }}</div>
          <div style="width: 50%"><b>Thời gian: </b>{{ formatDate(report.auditor_audited_at) }}</div>
        </div>
        <div class="flex ml-6 gap-4">
          <div style="width: 50%"><b>Kết quả: </b><Tag :value="report.auditor_audited_result" :severity="getResultsSeverity(report.auditor_audited_result)" /></div>
          <div v-if="report.auditor_audited_notes" style="width: 50%"><b>Ghi chú: </b>{{ report.auditor_audited_notes }}</div>
        </div>
      </div>
      <div v-if="'pending' !== report.director_approved_result"><b>Giám đốc:</b>
        <div class="flex ml-6 gap-4">
          <div style="width: 50%"><b>Người duyệt: </b>{{ report.director_name }}</div>
          <div style="width: 50%"><b>Thời gian: </b>{{ formatDate(report.director_approved_at) }}</div>
        </div>
        <div class="flex ml-6 gap-4">
          <div style="width: 50%"><b>Kết quả: </b><Tag :value="report.director_approved_result" :severity="getResultsSeverity(report.director_approved_result)" /></div>
          <div v-if="report.director_approved_notes" style="width: 50%"><b>Ghi chú: </b>{{ report.director_approved_notes }}</div>
        </div>
      </div>
      <div v-if="report.image_url">
        <b>File đính kèm:</b>
        <img :src="report.image_url" alt="Ảnh đính kèm" style="max-width: 100%; max-height: 100%; display: block; margin-top: 8px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); cursor: pointer;" @click="openImageModal(report.image_url)" />
        <Dialog v-model:visible="imageModalVisible" maximizable :style="{ width: '80vw', height: '80vh' }" header="Xem ảnh đính kèm" :modal="true" class="image-modal">
          <div class="image-modal-content">
            <img v-if="currentImageSrc" :src="currentImageSrc" alt="Full size image" ref="imageRef" style="max-width: 100%; max-height: 100%; display: block; object-fit: contain; margin: auto;" />
            <p v-else>Không có ảnh để hiển thị.</p>
          </div>
          <template #footer>
              <div class="flex gap-2 justify-end">
                <Button
                    label="Xem full màn hình"
                    icon="pi pi-external-link"
                    @click="openFullscreen"
                    class="p-button-outlined"
                    v-if="currentImageSrc"
                />
                <Button label="Đóng" icon="pi pi-times" @click="imageModalVisible = false" />
              </div>
          </template>
        </Dialog>
      </div>
      <div v-else>
        <b>File đính kèm:</b> Không có file
      </div>
    </div>
    <div v-if="canManagerApprove
        && (report.status === 'pending_manager_approval' || report.status === 'manager_approved' || report.status === 'rejected')
        && report.auditor_audited_result === 'pending'">
      <Button label="Duyệt phiếu" icon="pi pi-check" @click="openManagerModal" class="w-full" />
    </div>
    <div v-else-if="canAudit
        && (report.status === 'manager_approved' || report.status === 'auditor_approved' || report.status === 'rejected')
        && report.director_approved_result === 'pending'">
      <Button label="Kiểm tra phiếu" icon="pi pi-search" @click="openAuditorModal" class="w-full" />
    </div>
    <div v-if="canDirectorApprove
        && (report.status === 'auditor_approved' || report.status === 'director_approved' || report.status === 'rejected')">
      <Button label="Duyệt phiếu" icon="pi pi-check" @click="openDirectorModal" class="w-full" />
    </div>
  </div>
  <Toast />

  <!-- Manager Approval Modal -->
  <Dialog v-model:visible="managerModalVisible" :modal="true" header="Trưởng phòng Thu Mua duyệt phiếu" :style="{ width: '500px' }">
    <div class="flex flex-col gap-4">
      <div>
        <label class="block mb-2 font-semibold">Kết quả duyệt</label>
        <Select v-model="manager_approved_result" :options="['approved', 'rejected']" placeholder="Chọn kết quả" class="w-full" />
      </div>
      <div>
        <label class="block mb-2 font-semibold">Ghi chú</label>
        <textarea v-model="manager_approved_notes" placeholder="Nhập ghi chú (tuỳ chọn)" rows="4" class="w-full p-2 border rounded resize-vertical" />
      </div>
    </div>
    <template #footer>
      <div class="flex gap-2 justify-end">
        <Button label="Hủy" icon="pi pi-times" @click="closeManagerModal" class="p-button-text" />
        <Button label="Gửi" icon="pi pi-check" @click="submitManagerApprove" :disabled="managerProcessing" :loading="managerProcessing" />
      </div>
    </template>
  </Dialog>

  <!-- Auditor Review Modal -->
  <Dialog v-model:visible="auditorModalVisible" :modal="true" header="Nhân viên Kiểm Soát review phiếu" :style="{ width: '500px' }">
    <div class="flex flex-col gap-4">
      <div>
        <label class="block mb-2 font-semibold">Kết quả kiểm tra</label>
        <Select v-model="auditor_audited_result" :options="['approved', 'rejected']" placeholder="Chọn kết quả" class="w-full" />
      </div>
      <div>
        <label class="block mb-2 font-semibold">Ghi chú</label>
        <textarea v-model="auditor_audited_notes" placeholder="Nhập ghi chú (tuỳ chọn)" rows="4" class="w-full p-2 border rounded resize-vertical" />
      </div>
    </div>
    <template #footer>
      <div class="flex gap-2 justify-end">
        <Button label="Hủy" icon="pi pi-times" @click="closeAuditorModal" class="p-button-text" />
        <Button label="Gửi" icon="pi pi-check" @click="submitAuditorAudit" :disabled="processing" :loading="processing" />
      </div>
    </template>
  </Dialog>

  <!-- Director Approval Modal -->
  <Dialog v-model:visible="directorModalVisible" :modal="true" header="Giám đốc duyệt phiếu" :style="{ width: '500px' }">
    <div class="flex flex-col gap-4">
      <div>
        <label class="block mb-2 font-semibold">Kết quả duyệt</label>
        <Select v-model="director_approved_result" :options="['approved', 'rejected']" placeholder="Chọn kết quả" class="w-full" />
      </div>
      <div>
        <label class="block mb-2 font-semibold">Ghi chú</label>
        <textarea v-model="director_approved_notes" placeholder="Nhập ghi chú (tuỳ chọn)" rows="4" class="w-full p-2 border rounded resize-vertical" />
      </div>
    </div>
    <template #footer>
      <div class="flex gap-2 justify-end">
        <Button label="Hủy" icon="pi pi-times" @click="closeDirectorModal" class="p-button-text" />
        <Button label="Gửi" icon="pi pi-check" @click="submitDirectorApprove" :disabled="directorProcessing" :loading="directorProcessing" />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import Dialog from 'primevue/dialog';
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

import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Tag from 'primevue/tag';
import Select from 'primevue/select';
import Button from 'primevue/button';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';

const page = usePage();
const toast = useToast();
const report = computed(() => page.props.report);
const user = computed(() => page.props.auth.user);
const canAudit = computed(() => user.value.role === 'Nhân viên Kiểm Soát');
const canManagerApprove = computed(() => user.value.role === 'Trưởng phòng Thu Mua');
const canDirectorApprove = computed(() => user.value.role === 'Giám đốc');
const manager_approved_result = ref('');
const manager_approved_notes = ref('');
const managerProcessing = ref(false);
const managerModalVisible = ref(false);

const openManagerModal = () => {
  managerModalVisible.value = true;
  // Reset form values when opening modal
  manager_approved_result.value = '';
  manager_approved_notes.value = '';
};

const closeManagerModal = () => {
  managerModalVisible.value = false;
};

const submitManagerApprove = () => {
  if (!manager_approved_result.value) {
    toast.add({severity: 'error', summary: 'Lỗi', detail: 'Vui lòng chọn trạng thái duyệt.', life: 3000});
    return;
  }
  managerProcessing.value = true;
  router.post(`/supplier_selection_reports/${report.value.id}/manager-approve`, {
    manager_approved_result: manager_approved_result.value,
    manager_approved_notes: manager_approved_notes.value
  }, {
    preserveScroll: true,
    onSuccess: () => {
      toast.add({severity: 'success', summary: 'Thành công', detail: 'Đã duyệt phiếu!', life: 3000});
      managerProcessing.value = false;
      closeManagerModal();
    },
    onError: (errors) => {
      console.error('Lỗi khi duyệt phiếu:', errors);
      toast.add({severity: 'error', summary: 'Lỗi', detail: 'Duyệt phiếu thất bại.', life: 3000});
      managerProcessing.value = false;
    }
  });
};
const auditor_audited_result = ref('');
const auditor_audited_notes = ref('');
const processing = ref(false);
const auditorModalVisible = ref(false);

const openAuditorModal = () => {
  auditorModalVisible.value = true;
  // Reset form values when opening modal
  auditor_audited_result.value = '';
  auditor_audited_notes.value = '';
};

const closeAuditorModal = () => {
  auditorModalVisible.value = false;
};

const director_approved_result = ref('');
const director_approved_notes = ref('');
const directorProcessing = ref(false);
const directorModalVisible = ref(false);

const openDirectorModal = () => {
  directorModalVisible.value = true;
  director_approved_result.value = '';
  director_approved_notes.value = '';
};

const closeDirectorModal = () => {
  directorModalVisible.value = false;
};

const submitDirectorApprove = () => {
  if (!director_approved_result.value) {
    toast.add({severity: 'error', summary: 'Lỗi', detail: 'Vui lòng chọn trạng thái duyệt.', life: 3000});
    return;
  }
  directorProcessing.value = true;
  router.post(`/supplier_selection_reports/${report.value.id}/director-approve`, {
    director_approved_result: director_approved_result.value,
    director_approved_notes: director_approved_notes.value,
    director_id: user.value.id,
    director_approved_at: new Date().toISOString()
  }, {
    preserveScroll: true,
    onSuccess: () => {
      toast.add({severity: 'success', summary: 'Thành công', detail: 'Đã duyệt phiếu!', life: 3000});
      directorProcessing.value = false;
      closeDirectorModal();
    },
    onError: (errors) => {
      console.error('Lỗi khi duyệt phiếu:', errors);
      toast.add({severity: 'error', summary: 'Lỗi', detail: 'Duyệt phiếu thất bại.', life: 3000});
      directorProcessing.value = false;
    }
  });
};

const getResultsSeverity = (result) => {
    switch (result) {
        case 'rejected':
            return 'danger';

        case 'approved':
            return 'success';
        default:
            return 'info';
    }
};

const formatDate = (dateString) => {
    if (!dateString) return '';

    const date = new Date(dateString);
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');

    return `${day}/${month}/${year} ${hours}:${minutes}`;
};

const getStatusSeverity = (status) => {
    switch (status) {
        case 'draft':
            return 'secondary';

        case 'pending_manager_approval':
            return 'warn';

        case 'manager_approved':
        case 'auditor_approved':
            return 'info';

        case 'director_approved':
            return 'success';

        case 'rejected':
            return 'danger';
        default:
            return 'info';
    }
};

const submitAuditorAudit = () => {
  if (!auditor_audited_result.value) {
    toast.add({severity: 'error', summary: 'Lỗi', detail: 'Vui lòng chọn trạng thái.', life: 3000});
    return;
  }
  processing.value = true;
  router.post(`/supplier_selection_reports/${report.value.id}/auditor-audit`, {
    auditor_audited_result: auditor_audited_result.value,
    auditor_audited_notes: auditor_audited_notes.value
  }, {
    preserveScroll: true,
    onSuccess: () => {
      toast.add({severity: 'success', summary: 'Thành công', detail: 'Đã duyệt thành công!', life: 3000});
      processing.value = false;
      closeAuditorModal();
    },
    onError: (errors) => {
      console.error('Lỗi khi duyệt:', errors);
      toast.add({severity: 'error', summary: 'Lỗi', detail: 'Lỗi xảy ra trong quá trình duyệt.', life: 3000});
      processing.value = false;
    }
  });
};
</script>
