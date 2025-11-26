<template>
  <Head>
    <title>Chi tiết BCLCNCC</title>
  </Head>
  <div class="card">
    <div class="flex justify-end items-center mb-4">
      <Button type="button" label="Quay lại" icon="pi pi-arrow-left" outlined @click="goBack" />
    </div>
    <!-- Tabs with two tabs -->
    <Tabs value="0">
      <TabList>
        <Tab value="0">Chi tiết</Tab>
        <Tab value="1">Đề nghị/BOQ</Tab>
        <Tab value="2">Báo giá</Tab>
        <Tab value="3">Nhật ký</Tab>
      </TabList>
      <TabPanels>
        <TabPanel value="0">
          <div class="mb-4">
            <div>
            <div class="flex gap-4">
              <div style="width: 50%">
                <b>Mã:</b> {{ report.code }}
                <Tag v-if="report.is_urgent" value="KHẨN CẤP" severity="danger" class="ml-2" style="font-size: 0.75rem;" />
              </div>
              <div style="width: 50%" class="ml-6"><b>Mô tả:</b> {{ report.description }}</div>
            </div>
            </div>
            <div class="flex gap-4">
              <div style="width: 50%"><b>Trạng thái:</b> <Tag :value="getStatusLabel(report.status)" :severity="getStatusSeverity(report.status)" /></div>
              <div style="width: 50%" class="ml-6"><b>Người tạo:</b> {{ report.creator_name }} ({{ report.formatted_created_at }})</div>
            </div>
            <div v-if="'pending' !== report.manager_approved_result" class="mt-2"><b>Trưởng phòng:</b>
              <div class="flex ml-6 gap-4">
                <div style="width: 50%"><b>Người duyệt: </b>{{ report.manager_name }}</div>
                <div style="width: 50%"><b>Thời gian: </b>{{ report.formatted_manager_approved_at }}</div>
              </div>
              <div class="flex ml-6 gap-4">
                <div style="width: 50%"><b>Kết quả: </b><Tag :value="getResultLabel(report.manager_approved_result)" :severity="getResultsSeverity(report.manager_approved_result)" /></div>
                <div v-if="report.manager_approved_notes" style="width: 50%"><b>Ghi chú: </b>{{ report.manager_approved_notes }}</div>
              </div>
            </div>
            <div v-if="'pending' !== report.auditor_audited_result" class="mt-2"><b>Nhân viên Kiểm Soát:</b>
              <div class="flex ml-6 gap-4">
                <div style="width: 50%"><b>Người duyệt: </b>{{ report.auditor_name }}</div>
                <div style="width: 50%"><b>Thời gian: </b>{{ report.formatted_auditor_audited_at }}</div>
              </div>
              <div class="flex ml-6 gap-4">
                <div style="width: 50%"><b>Kết quả: </b><Tag :value="getResultLabel(report.auditor_audited_result)" :severity="getResultsSeverity(report.auditor_audited_result)" /></div>
                <div v-if="report.auditor_audited_notes" style="width: 50%"><b>Ghi chú: </b>{{ report.auditor_audited_notes }}</div>
              </div>
            </div>
            <div v-if="'pending' !== report.director_approved_result" class="mt-2"><b>Giám đốc:</b>
              <div class="flex ml-6 gap-4">
                <div style="width: 50%"><b>Người duyệt: </b>{{ report.director_name }}</div>
                <div style="width: 50%"><b>Thời gian: </b>{{ report.formatted_director_approved_at }}</div>
              </div>
              <div class="flex ml-6 gap-4">
                <div style="width: 50%"><b>Kết quả: </b><Tag :value="getResultLabel(report.director_approved_result)" :severity="getResultsSeverity(report.director_approved_result)" /></div>
                <div v-if="report.director_approved_notes" style="width: 50%"><b>Ghi chú: </b>{{ report.director_approved_notes }}</div>
              </div>
            </div>
            <div v-if="report.image_url" class="mt-2">
              <b>File đính kèm:</b>
              <div class="image-container" style="background-color: #ffffff; padding: 8px; border-radius: 8px; margin-top: 8px;">
                <img :src="report.image_url" alt="Ảnh đính kèm" style="max-width: 100%; max-height: 100%; display: block; border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); cursor: pointer;" @click="openImageModal(report.image_url)" />
              </div>
              <Dialog v-model:visible="imageModalVisible" maximizable :style="{ width: '80vw', height: '80vh' }" header="Xem ảnh đính kèm" :modal="true" class="image-modal">
                <div class="image-modal-content" style="background-color: #ffffff;">
                  <img v-if="currentImageSrc" :src="currentImageSrc" alt="Full size image" ref="imageRef" style="max-width: 100%; max-height: 100%; display: block; object-fit: contain; margin: auto;" />
                  <p v-else>Không có ảnh để hiển thị.</p>
                </div>
                <template #footer>
                    <div class="flex gap-2 justify-end">
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
              && report.auditor_audited_result === 'pending'
              && report.manager_id === user?.id">
            <Button label="Duyệt phiếu" icon="pi pi-check" @click="openManagerModal" class="w-full" />
          </div>
          <div v-else-if="canAudit
              && (report.status === 'manager_approved' || report.status === 'auditor_approved' || report.status === 'rejected')
              && report.director_approved_result === 'pending'">
            <Button label="Kiểm tra phiếu" icon="pi pi-search" @click="openAuditorModal" class="w-full" />
          </div>
          <div v-if="canDirectorApprove
              && (report.status === 'pending_director_approval' || report.status === 'director_approved' || report.status === 'rejected')
              && report.director_id === user?.id">
            <Button label="Duyệt phiếu" icon="pi pi-check" @click="openDirectorModal" class="w-full" />
          </div>

          <div v-if="report.status === 'rejected'
                    && !report.child_report
                    && (user?.role === 'Nhân viên mua hàng' || user?.role === 'Trưởng phòng')"
            class="mt-4 justify-end flex"
          >
            <Button
                label="Tạo phiếu mới từ phiếu bị từ chối"
                icon="pi pi-plus"
                class="p-button-success"
                @click="createNewFromRejected"
            />
          </div>
        </TabPanel>

        <TabPanel value="1">
          <div class="p-4">
            <DataTable ref="dtProposal" v-model:filters="filtersProposal" :value="report.proposal_files && report.proposal_files.data ? report.proposal_files.data : []" paginator :rows="10" dataKey="id" filterDisplay="menu"
                :globalFilterFields="['file_name']">
              <template #header>
                <div class="flex justify-between">
                  <Button type="button" icon="pi pi-filter-slash" label="Clear" outlined @click="clearProposalFilter()" />
                  <IconField>
                    <InputIcon>
                      <i class="pi pi-search" />
                    </InputIcon>
                    <InputText v-model="filtersProposal['global'].value" placeholder="Tìm kiếm file đề nghị/BOQ" />
                  </IconField>
                </div>
              </template>
              <template #empty> Không có file đề nghị/BOQ nào. </template>
              <Column header="STT" style="width: 60px">
                <template #body="slotProps">
                  {{ slotProps.index + 1 }}
                </template>
              </Column>
              <Column field="file_name" header="File đề nghị/BOQ" sortable style="min-width: 14rem">
                <template #body="{ data }">
                  <a :href="data.file_url" target="_blank" class="text-primary hover:underline" style="cursor:pointer">{{ data.file_name }}</a>
                </template>
                <template #filter="{ filterModel }">
                  <InputText v-model="filterModel.value" type="text" placeholder="Tìm theo tên file" />
                </template>
              </Column>
              <Column field="file_size_formatted" header="Dung lượng" style="min-width: 8rem" sortable>
                <template #body="{ data }">
                  {{ data.file_size_formatted }}
                </template>
              </Column>
            </DataTable>
          </div>
        </TabPanel>

        <TabPanel value="2">
          <div class="p-4">
            <DataTable ref="dtQuotation" v-model:filters="filtersQuotation" :value="report.quotation_files && report.quotation_files.data ? report.quotation_files.data : []" paginator :rows="10" dataKey="id" filterDisplay="menu"
                :globalFilterFields="['file_name']">
              <template #header>
                <div class="flex justify-between">
                  <Button type="button" icon="pi pi-filter-slash" label="Clear" outlined @click="clearQuotationFilter()" />
                  <IconField>
                    <InputIcon>
                      <i class="pi pi-search" />
                    </InputIcon>
                    <InputText v-model="filtersQuotation['global'].value" placeholder="Tìm kiếm file báo giá" />
                  </IconField>
                </div>
              </template>
              <template #empty> Không có file báo giá nào. </template>
              <Column header="STT" style="width: 60px">
                <template #body="slotProps">
                  {{ slotProps.index + 1 }}
                </template>
              </Column>
              <Column field="file_name" header="File báo giá" sortable style="min-width: 14rem">
                <template #body="{ data }">
                  <a :href="data.file_url" target="_blank" class="text-primary hover:underline" style="cursor:pointer">{{ data.file_name }}</a>
                </template>
                <template #filter="{ filterModel }">
                  <InputText v-model="filterModel.value" type="text" placeholder="Tìm theo tên file" />
                </template>
              </Column>
              <Column field="file_size_formatted" header="Dung lượng" style="min-width: 8rem" sortable>
                <template #body="{ data }">
                  {{ data.file_size_formatted }}
                </template>
              </Column>
            </DataTable>
          </div>
        </TabPanel>

        <TabPanel value="3">
          <div class="p-4">
            <DataTable :value="activityLogs" paginator :rows="10" dataKey="id" :rowClass="rowClass">
              <template #empty> Chưa có nhật ký nào. </template>
              <Column field="created_at" header="Thời gian" sortable style="min-width: 12rem" />
              <Column field="action" header="Hành động" sortable style="min-width: 12rem">
                <template #body="{ data }">
                  <div class="flex items-center gap-2">
                    {{ formatAction(data.action) }}
                    <Tag v-if="data.from_ancestor" :value="`Lần ${data.ancestor_attempt}`" severity="info" class="text-xs" />
                  </div>
                </template>
              </Column>
              <Column field="user" header="Người thực hiện" style="min-width: 12rem" />
              <Column field="user_role" header="Vai trò" style="min-width: 10rem" />
              <Column header="Chi tiết" style="min-width: 20rem">
                <template #body="{ data }">
                  <div v-if="data.from_ancestor || formatDetails(data)">
                    <p v-if="data.from_ancestor" class="text-sm text-blue-600 font-semibold mb-1">
                      📋 Từ phiếu: {{ data.ancestor_code }}
                    </p>
                    <span v-if="formatDetails(data)">{{ formatDetails(data) }}</span>
                  </div>
                  <span v-else class="text-gray-400">—</span>
                </template>
              </Column>
            </DataTable>
          </div>
        </TabPanel>
      </TabPanels>
    </Tabs>
  </div>
  <Toast />

  <!-- Manager Approval Modal -->
  <Dialog v-model:visible="managerModalVisible" :modal="true" header="Trưởng phòng duyệt phiếu" :style="{ width: '500px' }">
    <div class="flex flex-col gap-4">
      <div>
        <label class="block mb-2 font-semibold">Kết quả duyệt</label>
        <Select v-model="manager_approved_result" :options="[{ label: 'Đồng ý', value: 'approved' }, { label: 'Từ chối', value: 'rejected' }]" optionLabel="label" optionValue="value" placeholder="Chọn kết quả" class="w-full" />
      </div>
      <div v-if="manager_approved_result === 'approved'" class="flex items-center gap-2 p-3 bg-orange-50 border border-orange-200 rounded">
        <Checkbox v-model="is_urgent" binary inputId="is_urgent" />
        <label for="is_urgent" class="font-semibold text-orange-700 cursor-pointer">
          Báo cáo khẩn cấp (bỏ qua Kiểm Soát Nội Bộ)
        </label>
      </div>
      <div v-if="is_urgent && manager_approved_result === 'approved'">
        <label class="block mb-2 font-semibold text-orange-700">Chọn Giám đốc duyệt <span class="text-red-500">*</span></label>
        <Select v-model="director_id" :options="directors" optionLabel="name" optionValue="id" placeholder="Chọn Giám đốc" class="w-full" />
        <small class="text-orange-600">* Bắt buộc chọn Giám đốc cho phiếu khẩn cấp</small>
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
        <Select v-model="auditor_audited_result" :options="[{ label: 'Đồng ý', value: 'approved' }, { label: 'Từ chối', value: 'rejected' }]" optionLabel="label" optionValue="value" placeholder="Chọn kết quả" class="w-full" />
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
        <Select v-model="director_approved_result" :options="[{ label: 'Đồng ý', value: 'approved' }, { label: 'Từ chối', value: 'rejected' }]" optionLabel="label" optionValue="value" placeholder="Chọn kết quả" class="w-full" />
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
// Đã có import { ref, computed } from 'vue' phía dưới, không cần lặp lại
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import InputText from 'primevue/inputtext';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import { FilterMatchMode } from '@primevue/core/api';

const dtQuotation = ref();
const filtersQuotation = ref({
  global: { value: null, matchMode: FilterMatchMode.CONTAINS },
  file_name: { value: null, matchMode: FilterMatchMode.CONTAINS },
});
const clearQuotationFilter = () => {
  filtersQuotation.value = {
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
    file_name: { value: null, matchMode: FilterMatchMode.CONTAINS },
  };
};


const dtProposal = ref();
const filtersProposal = ref({
  global: { value: null, matchMode: FilterMatchMode.CONTAINS },
  file_name: { value: null, matchMode: FilterMatchMode.CONTAINS },
});
const clearProposalFilter = () => {
  filtersProposal.value = {
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
    file_name: { value: null, matchMode: FilterMatchMode.CONTAINS },
  };
};

const formatAction = (action) => {
  switch (action) {
    case 'created': return 'Tạo mới';
    case 'updated': return 'Cập nhật';
    case 'deleted': return 'Xóa';
    case 'submitted_to_manager': return 'Gửi duyệt Trưởng phòng';
    case 'manager_approved': return 'Trưởng phòng duyệt';
    case 'manager_rejected': return 'Trưởng phòng từ chối';
    case 'skipped_manager': return 'Bỏ qua Trưởng phòng';
    case 'auditor_approved': return 'Kiểm Soát duyệt';
    case 'auditor_rejected': return 'Kiểm Soát từ chối';
    case 'skipped_auditor': return 'Bỏ qua Kiểm Soát (Khẩn cấp)';
    case 'submitted_to_director': return 'Gửi duyệt Giám đốc';
    case 'director_approved': return 'Giám đốc duyệt';
    case 'director_rejected': return 'Giám đốc từ chối';
    default: return action;
  }
};

const fieldLabels = {
  code: 'Mã',
  description: 'Mô tả',
  status: 'Trạng thái',
  manager_approved_result: 'KQ Trưởng phòng',
  manager_approved_notes: 'Ghi chú Trưởng phòng',
  auditor_audited_result: 'KQ Kiểm Soát',
  auditor_audited_notes: 'Ghi chú Kiểm Soát',
  director_approved_result: 'KQ Giám đốc',
  director_approved_notes: 'Ghi chú Giám đốc',
};

const formatDetails = (row) => {
  const p = row?.properties || {};
  if (p.notes) return `Ghi chú: ${p.notes}`;
  if (row.action === 'submitted_to_manager' && p.manager_name) return `Gửi duyệt Trưởng phòng: ${p.manager_name}`;
  if (row.action === 'submitted_to_director' && p.director_name) return `Gửi duyệt Giám đốc: ${p.director_name}`;
  if (row.action === 'deleted') return `Đã xóa bản ghi ${p.code ? `(#${p.code})` : (p.id ? `(#${p.id})` : '')}`.trim();
  if (p.changed && typeof p.changed === 'object') {
    const keys = Object.keys(p.changed);
    if (keys.length === 0) return null;
    const names = keys.map(k => fieldLabels[k] || k);
    return `Thay đổi: ${names.join(', ')}`;
  }
  return null;
};

// Row class để highlight logs từ phiếu cha/ông
const rowClass = (data) => {
  return data.from_ancestor ? 'bg-blue-50' : '';
};
import Dialog from 'primevue/dialog';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import TabPanels from 'primevue/tabpanels';
import TabPanel from 'primevue/tabpanel';
const imageModalVisible = ref(false);
const currentImageSrc = ref(null);
const imageRef = ref(null);
const openImageModal = (imageUrl) => {
  currentImageSrc.value = imageUrl;
  imageModalVisible.value = true;
};


import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Tag from 'primevue/tag';
import Select from 'primevue/select';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';

const page = usePage();
const toast = useToast();
const report = computed(() => page.props.report);
const activityLogs = computed(() => page.props.activity_logs ?? []);
const directors = computed(() => page.props.directors ?? []);
const user = computed(() => page.props.auth.user);
const canAudit = computed(() => user.value.role === 'Nhân viên Kiểm Soát');
const canManagerApprove = computed(() => user.value.role === 'Trưởng phòng');
const canDirectorApprove = computed(() => user.value.role === 'Giám đốc');
const manager_approved_result = ref('');
const manager_approved_notes = ref('');
const is_urgent = ref(false);
const director_id = ref(null);
const managerProcessing = ref(false);
const managerModalVisible = ref(false);

const openManagerModal = () => {
  managerModalVisible.value = true;
  // Reset form values when opening modal
  manager_approved_result.value = '';
  manager_approved_notes.value = '';
  is_urgent.value = false;
  director_id.value = null;
};

const closeManagerModal = () => {
  managerModalVisible.value = false;
};

const submitManagerApprove = () => {
  if (!manager_approved_result.value) {
    toast.add({severity: 'error', summary: 'Lỗi', detail: 'Vui lòng chọn trạng thái duyệt.', life: 3000});
    return;
  }
  if (is_urgent.value && !director_id.value) {
    toast.add({severity: 'error', summary: 'Lỗi', detail: 'Vui lòng chọn Giám đốc cho phiếu khẩn cấp.', life: 3000});
    return;
  }
  managerProcessing.value = true;
  router.post(`/supplier_selection_reports/${report.value.id}/manager-approve`, {
    manager_approved_result: manager_approved_result.value,
    manager_approved_notes: manager_approved_notes.value,
    is_urgent: is_urgent.value,
    director_id: director_id.value
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


// Chuyển đổi trạng thái sang tiếng Việt
const getStatusLabel = (status) => {
  switch (status) {
    case 'draft':
      return 'Nháp';
    case 'pending_manager_approval':
      return 'Chờ trưởng phòng duyệt';
    case 'manager_approved':
      return 'Trưởng phòng đã duyệt';
    case 'auditor_approved':
      return 'Kiểm soát đã duyệt';
    case 'pending_director_approval':
      return 'Chờ giám đốc duyệt';
    case 'director_approved':
      return 'Giám đốc đã duyệt';
    case 'rejected':
      return 'Từ chối';
    default:
      return status;
  }
};

// Chuyển đổi kết quả duyệt sang tiếng Việt
const getResultLabel = (result) => {
  switch (result) {
    case 'approved':
      return 'Đồng ý';
    case 'rejected':
      return 'Từ chối';
    case 'pending':
      return 'Chờ duyệt';
    default:
      return result;
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

const createNewFromRejected = () => {
  router.get('/supplier_selection_reports/create', {
    parent_report_id: report.value.id
  });
};

const goBack = () => {
  router.visit('/supplier_selection_reports', { method: 'get' });
};
</script>

<style scoped>
.image-container {
  background-color: #ffffff !important;
  border: 1px solid #e5e7eb;
}

.image-modal-content {
  background-color: #ffffff !important;
  border-radius: 4px;
  overflow: hidden;
}

/* Ensure dark mode compatibility */
:deep(.p-dialog-content) {
  background-color: #ffffff !important;
}
</style>
