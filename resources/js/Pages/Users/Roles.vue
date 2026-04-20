<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ roles: Array, permissionGroups: Object });

const editingRole = ref(null);
const permForm = useForm({ permissions: [] });

const startEditing = (role) => {
    editingRole.value = role.id;
    permForm.permissions = [...role.permissions];
};

const savePermissions = (roleId) => {
    permForm.post(route('users.update-role-permissions', roleId), {
        onSuccess: () => { editingRole.value = null; },
    });
};

const togglePerm = (perm) => {
    const idx = permForm.permissions.indexOf(perm);
    if (idx >= 0) permForm.permissions.splice(idx, 1);
    else permForm.permissions.push(perm);
};

const roleColors = { admin: 'bg-red-100 text-red-800 border-red-200', manager: 'bg-blue-100 text-blue-800 border-blue-200', staff: 'bg-green-100 text-green-800 border-green-200', driver: 'bg-yellow-100 text-yellow-800 border-yellow-200' };
</script>

<template>
    <AppLayout title="Roles & Permissions">
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('users.index')" class="text-gray-500 hover:text-gray-700">&larr; Back</Link>
                <h2 class="font-bold text-xl text-gray-900">Roles & Permissions</h2>
            </div>
        </template>

        <div class="p-6 space-y-6">
            <!-- Role Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div v-for="role in roles" :key="role.id" class="bg-white rounded-xl border p-6">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <span class="px-3 py-1 text-sm rounded-full font-semibold capitalize" :class="roleColors[role.name] || 'bg-gray-100 text-gray-800'">{{ role.name }}</span>
                            <p class="text-xs text-gray-500 mt-2">{{ role.users_count }} users &middot; {{ role.permissions.length }} permissions</p>
                        </div>
                        <button v-if="editingRole !== role.id" @click="startEditing(role)" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">Edit Permissions</button>
                        <div v-else class="flex gap-2">
                            <button @click="savePermissions(role.id)" class="text-xs bg-green-600 text-white px-3 py-1 rounded-lg">Save</button>
                            <button @click="editingRole = null" class="text-xs text-gray-500">Cancel</button>
                        </div>
                    </div>

                    <!-- Permission List -->
                    <div v-if="editingRole === role.id">
                        <div v-for="(perms, group) in permissionGroups" :key="group" class="mb-3">
                            <p class="text-xs font-semibold text-gray-500 uppercase mb-1 capitalize">{{ group }}</p>
                            <div class="flex flex-wrap gap-1.5">
                                <button v-for="p in perms" :key="p.name"
                                        @click="togglePerm(p.name)"
                                        class="px-2 py-1 text-xs rounded-lg border transition-colors"
                                        :class="permForm.permissions.includes(p.name) ? 'bg-indigo-100 border-indigo-300 text-indigo-800' : 'bg-gray-50 border-gray-200 text-gray-500'">
                                    {{ p.name }}
                                </button>
                            </div>
                        </div>
                    </div>
                    <div v-else class="flex flex-wrap gap-1.5">
                        <span v-for="perm in role.permissions" :key="perm" class="px-2 py-1 text-xs bg-gray-50 text-gray-600 rounded-lg">{{ perm }}</span>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
