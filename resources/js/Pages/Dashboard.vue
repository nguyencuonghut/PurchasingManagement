<template>
  <Head>
    <title>Dashboard</title>
  </Head>
  <div class="dashboard">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
  <Card v-for="stat in stats" :key="stat.label" class="text-center">
        <template #title>
          <i :class="stat.icon" class="text-2xl mb-2"></i>
          <div class="font-bold text-lg mt-2">{{ stat.value }}</div>
        </template>
        <template #content>
          <div class="text-gray-600">{{ stat.label }}</div>
        </template>
      </Card>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
      <Card>
        <template #title>Trạng thái phiếu (Biểu đồ)</template>
        <template #content>
          <Chart type="pie" :data="statusChartData" :options="chartOptions" />
        </template>
      </Card>
      <Card>
        <template #title>Số lượng phiếu theo tháng</template>
        <template #content>
          <Chart type="bar" :data="monthlyChartData" :options="chartOptions" />
        </template>
      </Card>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
      <Card>
        <template #title>Phiếu gần đây</template>
        <template #content>
          <DataTable :value="recentReports" :rows="5" paginator :rowsPerPageOptions="[5,10]">
            <Column field="code" header="Mã">
              <template #body="{ data }">
                <a :href="`/supplier_selection_reports/${data.id}`" class="text-primary hover:underline" style="cursor:pointer">{{ data.code }}</a>
              </template>
            </Column>
            <Column field="description" header="Mô tả" />
            <Column field="status" header="Trạng thái">
              <template #body="{ data }">
                <Tag :value="data.status" :severity="getStatusSeverity(data.status)" />
              </template>
            </Column>
            <Column field="created_at" header="Ngày tạo">
              <template #body="{ data }">
                {{ formatDate(data.created_at) }}
              </template>
            </Column>
          </DataTable>
        </template>
      </Card>
      <Card>
        <template #title>Phiếu cần xử lý</template>
        <template #content>
          <DataTable :value="pendingReports" :rows="5" paginator :rowsPerPageOptions="[5,10]">
            <Column field="code" header="Mã">
              <template #body="{ data }">
                <a :href="`/supplier_selection_reports/${data.id}`" class="text-primary hover:underline" style="cursor:pointer">{{ data.code }}</a>
              </template>
            </Column>
            <Column field="description" header="Mô tả" />
            <Column field="status" header="Trạng thái">
              <template #body="{ data }">
                <Tag :value="data.status" :severity="getStatusSeverity(data.status)" />
              </template>
            </Column>
            <Column field="created_at" header="Ngày tạo">
              <template #body="{ data }">
                {{ formatDate(data.created_at) }}
              </template>
            </Column>
          </DataTable>
        </template>
      </Card>
    </div>
    <div class="mb-6">
      <Card>
        <template #title>Thông báo</template>
        <template #content>
          <ul>
            <li v-for="notify in notifications" :key="notify.id" class="mb-2">
              <span class="font-semibold">{{ notify.title }}</span>: {{ notify.content }}
            </li>
          </ul>
        </template>
      </Card>
    </div>
  </div>
</template>

<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import Card from 'primevue/card';
import Chart from 'primevue/chart';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';

import Tag from 'primevue/tag';

// Copy getStatusSeverity logic from SupplierSelectionReportIndex.vue
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



// Format ngày dd/mm/yyyy H:i
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

const page = usePage();
const stats = computed(() => page.props.stats ?? []);
const statusChartData = computed(() => page.props.statusChartData ?? {});
const monthlyChartData = computed(() => page.props.monthlyChartData ?? {});
const recentReports = computed(() => page.props.recentReports ?? []);
const pendingReports = computed(() => page.props.pendingReports ?? []);
const notifications = computed(() => page.props.notifications ?? []);
const chartOptions = {
  plugins: { legend: { position: 'bottom' } },
  responsive: true,
};
</script>

<style scoped>
  .dashboard {
    padding: 2rem;
  }
</style>
