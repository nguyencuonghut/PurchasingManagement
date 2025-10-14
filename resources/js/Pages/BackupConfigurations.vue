<template>
    <div class="min-h-screen bg-gray-50">
        <Head title="Auto Backup Configuration" />

        <!-- Header -->
        <div class="bg-white shadow">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="py-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">Auto Backup</h1>
                            <p class="mt-2 text-sm text-gray-600">Cấu hình backup tự động giống SqlBak</p>
                        </div>
                        <button
                            @click="showCreateModal = true"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium flex items-center gap-2"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            Tạo Backup Mới
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-2 bg-green-100 rounded-lg">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Hoạt động</p>
                            <p class="text-2xl font-semibold text-gray-900">{{ activeConfigs }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-2 bg-blue-100 rounded-lg">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Tổng Backup</p>
                            <p class="text-2xl font-semibold text-gray-900">{{ totalBackups }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-2 bg-yellow-100 rounded-lg">
                            <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Backup Cuối</p>
                            <p class="text-sm font-semibold text-gray-900">{{ lastBackupTime }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-2 bg-purple-100 rounded-lg">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Google Drive</p>
                            <p class="text-sm font-semibold" :class="googleDriveConnected ? 'text-green-600' : 'text-red-600'">
                                {{ googleDriveConnected ? 'Đã kết nối' : 'Chưa kết nối' }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Backup Configurations -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-medium text-gray-900">Cấu hình Backup</h2>
                </div>

                <div v-if="configurations.length === 0" class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M34 40h10v-4a6 6 0 00-10.712-3.714M34 40H14m20 0v-4a9.971 9.971 0 00-.712-3.714M14 40H4v-4a6 6 0 0110.713-3.714M14 40v-4c0-1.313.253-2.566.713-3.714m0 0A9.971 9.971 0 0124 24a9.971 9.971 0 018.287 4.286" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">Chưa có cấu hình backup</h3>
                    <p class="mt-1 text-sm text-gray-500">Bắt đầu bằng cách tạo cấu hình backup đầu tiên.</p>
                    <div class="mt-6">
                        <button
                            @click="showCreateModal = true"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium"
                        >
                            Tạo Backup Đầu Tiên
                        </button>
                    </div>
                </div>

                <div v-else class="divide-y divide-gray-200">
                    <div v-for="config in configurations" :key="config.id" class="p-6">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-3">
                                    <h3 class="text-lg font-medium text-gray-900">{{ config.name }}</h3>
                                    <span
                                        :class="config.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                    >
                                        {{ config.is_active ? 'Hoạt động' : 'Tạm dừng' }}
                                    </span>
                                    <span
                                        v-if="config.google_drive_enabled"
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800"
                                    >
                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
                                        </svg>
                                        Google Drive
                                    </span>
                                </div>
                                <p class="mt-1 text-sm text-gray-600">{{ config.schedule_description }}</p>
                                <div class="mt-2 flex items-center gap-4 text-sm text-gray-500">
                                    <span>Lần cuối: {{ formatDate(config.last_run_at) }}</span>
                                    <span>Tiếp theo: {{ formatDate(config.next_run_at) }}</span>
                                    <span>Email: {{ config.notification_emails.length }} người nhận</span>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <button
                                    @click="runBackup(config)"
                                    :disabled="runningBackup === config.id"
                                    class="bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white px-3 py-1 rounded text-sm font-medium"
                                >
                                    <svg v-if="runningBackup === config.id" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    {{ runningBackup === config.id ? 'Đang chạy...' : 'Chạy ngay' }}
                                </button>
                                <button
                                    @click="editConfig(config)"
                                    class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm font-medium"
                                >
                                    Sửa
                                </button>
                                <button
                                    @click="toggleConfig(config)"
                                    class="bg-gray-600 hover:bg-gray-700 text-white px-3 py-1 rounded text-sm font-medium"
                                >
                                    {{ config.is_active ? 'Tạm dừng' : 'Kích hoạt' }}
                                </button>
                            </div>
                        </div>

                        <!-- Recent Logs -->
                        <div v-if="config.logs && config.logs.length > 0" class="mt-4">
                            <h4 class="text-sm font-medium text-gray-900 mb-2">Lịch sử gần đây</h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                                <div
                                    v-for="log in config.logs.slice(0, 3)"
                                    :key="log.id"
                                    class="flex items-center gap-2 p-2 bg-gray-50 rounded text-sm"
                                >
                                    <div
                                        :class="{
                                            'w-2 h-2 rounded-full': true,
                                            'bg-green-500': log.status === 'success',
                                            'bg-red-500': log.status === 'failed',
                                            'bg-blue-500': log.status === 'running'
                                        }"
                                    ></div>
                                    <span class="text-gray-600">{{ formatDate(log.started_at) }}</span>
                                    <span v-if="log.formatted_file_size" class="text-gray-500">{{ log.formatted_file_size }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create/Edit Backup Modal -->
        <BackupConfigModal
            :show="showCreateModal || showEditModal"
            :config="editingConfig"
            @close="closeModal"
            @saved="handleConfigSaved"
        />

        <!-- Google Drive Folder Picker Modal -->
        <GoogleDriveFolderPicker 
            :show="showFolderPicker"
            @close="showFolderPicker = false"
            @selected="handleFolderSelected"
        />
    </div>
</template><script setup>
import { ref, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import BackupConfigModal from '@/Components/BackupConfigModal.vue'
import GoogleDriveFolderPicker from '@/Components/GoogleDriveFolderPicker.vue'
import { useForm } from '@inertiajs/vue3'

// Props
const props = defineProps({
    configurations: {
        type: Array,
        default: () => []
    }
})

// Reactive data
const showCreateModal = ref(false)
const showEditModal = ref(false)
const showFolderPicker = ref(false)
const editingConfig = ref(null)
const runningBackup = ref(null)
const googleDriveConnected = ref(false)

// Computed properties
const activeConfigs = computed(() =>
    props.configurations.filter(config => config.is_active).length
)

const totalBackups = computed(() =>
    props.configurations.reduce((total, config) => total + (config.logs?.length || 0), 0)
)

const lastBackupTime = computed(() => {
    const allLogs = props.configurations.flatMap(config => config.logs || [])
    if (allLogs.length === 0) return 'Chưa có'

    const latestLog = allLogs.sort((a, b) => new Date(b.started_at) - new Date(a.started_at))[0]
    return formatDate(latestLog.started_at)
})

// Methods
const formatDate = (dateString) => {
    if (!dateString) return 'Chưa có'
    try {
        const date = new Date(dateString)
        if (isNaN(date.getTime())) return 'Không hợp lệ'

        return date.toLocaleDateString('vi-VN', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        })
    } catch {
        return 'Không hợp lệ'
    }
}

const closeModal = () => {
    showCreateModal.value = false
    showEditModal.value = false
    editingConfig.value = null
}

const editConfig = (config) => {
    editingConfig.value = config
    showEditModal.value = true
}

const handleConfigSaved = () => {
    closeModal()
    // Reload page hoặc refetch data
    window.location.reload()
}

const runBackup = async (config) => {
    runningBackup.value = config.id

    try {
        const form = useForm({})
        await form.post(`/backup/configurations/${config.id}/run`)

        // Show success message
        alert('Backup đã được thực hiện thành công!')

    } catch (error) {
        console.error('Backup failed:', error)
        alert('Có lỗi xảy ra khi thực hiện backup!')
    } finally {
        runningBackup.value = null
    }
}

const toggleConfig = async (config) => {
    try {
        const form = useForm({
            is_active: !config.is_active
        })
        await form.patch(`/backup/configurations/${config.id}`)

        config.is_active = !config.is_active

    } catch (error) {
        console.error('Toggle config failed:', error)
    }
}

const handleFolderSelected = (folder) => {
    showFolderPicker.value = false
    // Handle folder selection
    console.log('Selected folder:', folder)
}

// Mounted
onMounted(() => {
    // Check Google Drive connection status
    checkGoogleDriveConnection()
})

const checkGoogleDriveConnection = () => {
    // This would be an API call to check connection
    googleDriveConnected.value = false
}
</script>
