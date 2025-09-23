<template>
  <Head>
    <title>Dashboard</title>
  </Head>
  <div class="dashboard">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
  <Card v-for="stat in stats" :key="stat.label" class="text-center">
        <template #title>
          <i :class="stat.icon" class="text-2xl mb-2 text-blue-500"></i>
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
      <div class="h-96 p-4">
        <Chart type="pie" :data="statusChartData" :options="pieChartOptions" style="height:100%;width:100%;" />
      </div>
        </template>
      </Card>
      <Card>
        <template #title>Số lượng phiếu theo tháng</template>
        <template #content>
      <div class="h-96 p-4">
        <Chart type="bar" :data="monthlyChartData" :options="barchartOptions" style="height:100%;width:100%;" />
      </div>
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
    <div v-if="notifications.length" class="mb-6">
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


// Tối ưu hóa getStatusSeverity bằng object mapping
const statusSeverityMap = {
  draft: 'secondary',
  pending_manager_approval: 'warn',
  manager_approved: 'info',
  auditor_approved: 'info',
  director_approved: 'success',
  rejected: 'danger',
};
const getStatusSeverity = (status) => statusSeverityMap[status] || 'info';



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
// const chartOptions = {
//   plugins: { legend: { position: 'bottom' } },
//   responsive: true,
// };
const barchartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'bottom',
      labels: {
        usePointStyle: true,
        padding: 20,
        font: {
          size: 12
        }
      }
    },
    tooltip: {
      mode: 'index',
      intersect: false,
      backgroundColor: 'rgba(0, 0, 0, 0.8)',
      titleColor: '#fff',
      bodyColor: '#fff',
      borderColor: 'rgba(255, 255, 255, 0.1)',
      borderWidth: 1
    }
  },
  scales: {
    x: {
      grid: {
        display: false
      },
      ticks: {
        font: {
          size: 11
        }
      }
    },
    y: {
      beginAtZero: true,
      grid: {
        display: false
      },
      ticks: {
        stepSize: 1,
        font: {
          size: 11
        }
      }
    }
  },
  interaction: {
    mode: 'nearest',
    axis: 'x',
    intersect: false
  },
  elements: {
    line: {
      tension: 0.4
    },
    point: {
      radius: 4,
      hoverRadius: 6
    }
  }
}

const pieChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'bottom'
    }
  }
}
</script>

<style scoped>
  .dashboard {
    padding: 2rem;
  }
</style>
