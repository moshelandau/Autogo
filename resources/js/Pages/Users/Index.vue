<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({ users: Array, roles: Array, totalPermissions: Number });

const roleColors = {
    admin: 'bg-red-100 text-red-800',
    manager: 'bg-blue-100 text-blue-800',
    staff: 'bg-green-100 text-green-800',
    driver: 'bg-yellow-100 text-yellow-800',
};
</script>

<template>
    <AppLayout title="User Management">
        <template #header>
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="font-bold text-xl text-gray-900">User Management</h2>
                    <p class="text-sm text-gray-500">Manage team members, roles, and permissions</p>
                </div>
                <div class="flex gap-3">
                    <Link :href="route('users.roles')" class="px-4 py-2 text-sm text-gray-700 bg-white border rounded-lg hover:bg-gray-50">Roles & Permissions</Link>
                    <Link :href="route('users.create')" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-indigo-700">+ Add User</Link>
                </div>
            </div>
        </template>

        <div class="p-6 space-y-5">
            <!-- Role Summary Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div v-for="role in roles" :key="role.id" class="bg-white rounded-xl border p-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="px-2 py-1 text-xs rounded-full font-medium capitalize" :class="roleColors[role.name] || 'bg-gray-100 text-gray-800'">{{ role.name }}</span>
                        <span class="text-xs text-gray-400">{{ role.permissions_count }} perms</span>
                    </div>
                    <div class="text-2xl font-bold text-gray-900">{{ users.filter(u => u.roles.includes(role.name)).length }}</div>
                    <div class="text-xs text-gray-500">users</div>
                </div>
            </div>

            <!-- Users Table -->
            <div class="bg-white rounded-xl border overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Permissions</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="user in users" :key="user.id" class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-full bg-gradient-to-br from-indigo-400 to-purple-500 flex items-center justify-center text-white text-xs font-bold">
                                        {{ user.name.charAt(0) }}
                                    </div>
                                    <span class="font-medium text-sm text-gray-900">{{ user.name }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ user.email }}</td>
                            <td class="px-6 py-4">
                                <span v-for="role in user.roles" :key="role"
                                      class="px-2 py-1 text-xs rounded-full font-medium capitalize mr-1"
                                      :class="roleColors[role] || 'bg-gray-100 text-gray-800'">
                                    {{ role }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center text-sm text-gray-500">{{ user.permissions_count }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500">{{ user.created_at }}</td>
                            <td class="px-6 py-4 text-right">
                                <Link :href="route('users.show', user.id)" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium">Manage</Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
