<template>
    <div>
        <Head title="Auto Backup Configuration" />

        <!-- Header -->
        <div class="p-6">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-3xl font-bold">Auto Backup</h1>
                    <p class="mt-2 text-sm text-muted-color">Cấu hình backup tự động giống SqlBak</p>
                </div>
                <Button
                    @click="showCreateModal = true"
                    icon="pi pi-plus"
                    label="Tạo Backup Mới"
                    class="p-button-primary"
                />
            </div>
        </div>

        <!-- Content -->
        <div class="p-6">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <Card>
                    <template #content>
                        <div class="flex items-center">
                            <div class="p-2 bg-green-100 rounded-lg">
                                <i class="pi pi-check-circle text-green-600 text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-muted-color">Hoạt động</p>
                                <p class="text-2xl font-semibold">{{ activeConfigs }}</p>
                            </div>
                        </div>
                    </template>
                </Card>

                <Card>
                    <template #content>
                        <div class="flex items-center">
                            <div class="p-2 bg-blue-100 rounded-lg">
                                <i class="pi pi-cloud-download text-blue-600 text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-muted-color">Tổng Backup</p>
                                <p class="text-2xl font-semibold">{{ totalBackups }}</p>
                            </div>
                        </div>
                    </template>
                </Card>

                <Card>
                    <template #content>
                        <div class="flex items-center">
                            <div class="p-2 bg-yellow-100 rounded-lg">
                                <i class="pi pi-clock text-yellow-600 text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-muted-color">Backup Cuối</p>
                                <p class="text-sm font-semibold">{{ lastBackupTime }}</p>
                            </div>
                        </div>
                    </template>
                </Card>

                <Card>
                    <template #content>
                        <div class="flex items-center">
                            <div class="p-2 bg-purple-100 rounded-lg">
                                <i class="pi pi-google text-purple-600 text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-muted-color">Google Drive</p>
                                <p class="text-sm font-semibold" :class="googleDriveConnected ? 'text-green-600' : 'text-red-600'">
                                    {{ googleDriveConnected ? 'Đã kết nối' : 'Chưa kết nối' }}
                                </p>
                            </div>
                        </div>
                    </template>
                </Card>
            </div>

            <!-- Backup Configurations -->
            <Card>
                <template #title>
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-medium">Cấu hình Backup</h2>
                    </div>
                </template>
                <template #content>
                    <div v-if="configurations.length === 0" class="text-center py-12">
                        <i class="pi pi-users text-6xl text-muted-color mb-4"></i>
                        <h3 class="mt-2 text-sm font-medium">Chưa có cấu hình backup</h3>
                        <p class="mt-1 text-sm text-muted-color">Bắt đầu bằng cách tạo cấu hình backup đầu tiên.</p>
                        <div class="mt-6">
                            <Button
                                @click="showCreateModal = true"
                                label="Tạo Backup Đầu Tiên"
                                class="p-button-primary"
                            />
                        </div>
                    </div>

                    <div v-else class="space-y-6">
                        <div v-for="config in configurations" :key="config.id" class="border-b border-surface-border pb-6 last:border-b-0">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center gap-3 mb-2">
                                        <h3 class="text-lg font-medium">{{ config.name }}</h3>
                                        <Tag
                                            :value="config.is_active ? 'Hoạt động' : 'Tạm dừng'"
                                            :severity="config.is_active ? 'success' : 'danger'"
                                        />
                                        <Tag
                                            v-if="config.google_drive_enabled"
                                            value="Google Drive"
                                            severity="info"
                                            icon="pi pi-google"
                                        />
                                    </div>
                                    <p class="text-sm text-muted-color mb-2">{{ config.schedule_description }}</p>
                                    <div class="flex items-center gap-4 text-sm text-muted-color">
                                        <span>Lần cuối: {{ formatDate(config.last_run_at) }}</span>
                                        <span>Tiếp theo: {{ formatDate(config.next_run_at) }}</span>
                                        <span>Email: {{ config.notification_emails.length }} người nhận</span>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <Button
                                        @click="runBackup(config)"
                                        :disabled="runningBackup === config.id"
                                        :loading="runningBackup === config.id"
                                        label="Chạy ngay"
                                        icon="pi pi-play"
                                        class="p-button-success p-button-sm"
                                    />
                                    <Button
                                        @click="editConfig(config)"
                                        label="Sửa"
                                        icon="pi pi-pencil"
                                        class="p-button-primary p-button-sm"
                                    />
                                    <Button
                                        @click="toggleConfig(config)"
                                        :label="config.is_active ? 'Tạm dừng' : 'Kích hoạt'"
                                        :icon="config.is_active ? 'pi pi-pause' : 'pi pi-play'"
                                        class="p-button-secondary p-button-sm"
                                    />
                                </div>
                            </div>

                            <!-- Recent Logs -->
                            <div v-if="config.logs && config.logs.length > 0" class="mt-4">
                                <h4 class="text-sm font-medium mb-2">Lịch sử gần đây</h4>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                                    <div
                                        v-for="log in config.logs.slice(0, 3)"
                                        :key="log.id"
                                        class="flex items-center gap-2 p-2 surface-ground border border-surface-border rounded text-sm"
                                    >
                                        <div
                                            :class="{
                                                'w-2 h-2 rounded-full': true,
                                                'bg-green-500': log.status === 'success',
                                                'bg-red-500': log.status === 'failed',
                                                'bg-blue-500': log.status === 'running'
                                            }"
                                        ></div>
                                        <span class="text-muted-color">{{ formatDate(log.started_at) }}</span>
                                        <span v-if="log.formatted_file_size" class="text-muted-color">{{ log.formatted_file_size }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </Card>
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
import { useForm } from '@inertiajs/vue3'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Tag from 'primevue/tag'
import BackupConfigModal from '@/Components/BackupConfigModal.vue'
import GoogleDriveFolderPicker from '@/Components/GoogleDriveFolderPicker.vue'

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
