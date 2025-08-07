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
      <div v-if="report.status == 'pm_approved'"><b>Trưởng phòng mua đã duyệt:</b>
        <div class="flex ml-6 gap-4">
          <div style="width: 50%"><b>Kết quả: </b><Tag :value="report.pm_approver_status" :severity="getStatusSeverity(report.pm_approver_status)" /></div>
          <div v-if="report.pm_approver_notes" style="width: 50%"><b>Ghi chú: </b>{{ report.pm_approver_notes }}</div>
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
      <div v-if="report.review_note">
        <b>Ghi chú review:</b> {{ report.review_note }}
      </div>
    </div>
    <div v-if="canManagerReview && report.status === 'pending_pm_approval'">
      <h3 class="font-bold mb-2">Trưởng phòng Thu Mua duyệt phiếu</h3>
      <form @submit.prevent="submitManagerReview" class="flex flex-col gap-4">
        <div>
          <Select v-model="pm_approver_status" :options="['approved', 'rejected']" placeholder="Chọn trạng thái" class="w-full" />
        </div>
        <div>
          <textarea v-model="pm_approver_notes" placeholder="Ghi chú (tuỳ chọn)" rows="3" class="w-full p-2 border rounded resize-vertical" />
        </div>
        <div>
          <Button type="submit" label="Duyệt phiếu" :disabled="managerProcessing" class="w-full" />
        </div>
      </form>
    </div>
    <div v-else-if="canReview && report.status === 'manager_approved'">
      <h3 class="font-bold mb-2">Nhân viên Kiểm Soát review</h3>
      <form @submit.prevent="submitReview" class="flex flex-col gap-4">
        <div>
          <Select v-model="reviewer_status" :options="['approved', 'rejected']" placeholder="Chọn trạng thái" class="w-full" />
        </div>
        <div>
          <textarea v-model="reviewer_notes" placeholder="Ghi chú (tuỳ chọn)" rows="3" class="w-full p-2 border rounded resize-vertical" />
        </div>
        <div>
          <Button type="submit" label="Gửi review" :disabled="processing" class="w-full" />
        </div>
      </form>
    </div>
  </div>
  <Toast />
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
const canReview = computed(() => user.value.role === 'Nhân viên Kiểm Soát');
const canManagerReview = computed(() => user.value.role === 'Trưởng phòng Thu Mua');
const pm_approver_status = ref('');
const pm_approver_notes = ref('');
const managerProcessing = ref(false);
const submitManagerReview = () => {
  if (!pm_approver_status.value) {
    toast.add({severity: 'error', summary: 'Lỗi', detail: 'Vui lòng chọn trạng thái duyệt.', life: 3000});
    return;
  }
  managerProcessing.value = true;
  router.post(`/supplier_selection_reports/${report.value.id}/manager-review`, {
    pm_approver_status: pm_approver_status.value,
    pm_approver_notes: pm_approver_notes.value
  }, {
    preserveScroll: true,
    onSuccess: () => {
      toast.add({severity: 'success', summary: 'Thành công', detail: 'Đã duyệt phiếu!', life: 3000});
      managerProcessing.value = false;
    },
    onError: (errors) => {
      console.error('Lỗi khi duyệt phiếu:', errors);
      toast.add({severity: 'error', summary: 'Lỗi', detail: 'Duyệt phiếu thất bại.', life: 3000});
      managerProcessing.value = false;
    }
  });
};
const reviewer_status = ref('');
const reviewer_notes = ref('');
const processing = ref(false);

const getStatusSeverity = (status) => {
    switch (status) {
        case 'pending_review':
        case 'pending_pm_approval':
        case 'pending_director_approval':
            return 'warn';

        case 'pm_approved':
        case 'reviewed':
        case 'director_approved':
            return 'success';

        case 'rejected':
            return 'danger';
        default:
            return 'info';
    }
};

const submitReview = () => {
  if (!reviewer_status.value) {
    toast.add({severity: 'error', summary: 'Lỗi', detail: 'Vui lòng chọn trạng thái review.', life: 3000});
    return;
  }
  processing.value = true;
  router.post(`/supplier_selection_reports/${report.value.id}/review`, {
    reviewer_status: reviewer_status.value,
    reviewer_notes: reviewer_notes.value
  }, {
    preserveScroll: true,
    onSuccess: () => {
      toast.add({severity: 'success', summary: 'Thành công', detail: 'Đã gửi review!', life: 3000});
      processing.value = false;
    },
    onError: (errors) => {
      console.error('Lỗi khi gửi review:', errors);
      toast.add({severity: 'error', summary: 'Lỗi', detail: 'Gửi review thất bại.', life: 3000});
      processing.value = false;
    }
  });
};
</script>
