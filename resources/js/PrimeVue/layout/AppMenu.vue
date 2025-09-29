<script setup>
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

import AppMenuItem from './AppMenuItem.vue';

const page = usePage();
const userRole = computed(() => page.props.auth?.user?.role);

const model = computed(() => {
    const base = [
        {
            label: 'Home',
            items: [
                { label: 'Dashboard', icon: 'pi pi-fw pi-home', to: '/' },
                { label: 'BCLCNCC', icon: 'pi pi-fw pi-file-check', to: '/supplier_selection_reports' },
                { label: 'Hồ sơ', icon: 'pi pi-fw pi-id-card', to: '/profile' }
            ]
        }
    ];
    if (userRole.value === 'Quản trị') {
        base.push({
            label: 'HỆ THỐNG',
            items: [
                { label: 'Vai trò', icon: 'pi pi-fw pi-user-edit', to: '/roles' },
                { label: 'Phòng ban', icon: 'pi pi-fw pi-sitemap', to: '/departments' },
                { label: 'Người dùng', icon: 'pi pi-fw pi-users', to: '/users' },
            ]
        });
    }
    return base;
});
</script>

<template>
    <ul class="layout-menu">
        <template v-for="(item, i) in model" :key="item">
            <app-menu-item v-if="!item.separator" :item="item" :index="i"></app-menu-item>
            <li v-if="item.separator" class="menu-separator"></li>
        </template>
    </ul>
</template>

<style lang="scss" scoped></style>
