<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm, Link } from '@inertiajs/vue3';

const props = defineProps({ roles: Array, permissionTypes: Array });

const form = useForm({
    name: '', email: '', password: '', password_confirmation: '', role: 'staff', permission_type_id: '',
});

const submit = () => form.post(route('users.store'));

const roleColors = { admin: 'border-red-500 bg-red-50', manager: 'border-blue-500 bg-blue-50', staff: 'border-green-500 bg-green-50', driver: 'border-yellow-500 bg-yellow-50' };
const roleDescriptions = {
    admin: 'Full access to everything — manage users, settings, all modules',
    manager: 'Access to all business modules, can create/edit but not manage system settings',
    staff: 'Can view and create in most modules, limited editing',
    driver: 'Limited access — tow requests and vehicle viewing only',
};
</script>

<template>
    <AppLayout title="Add User">
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('users.index')" class="text-gray-500 hover:text-gray-700">&larr; Back</Link>
                <h2 class="font-bold text-xl text-gray-900">Add Team Member</h2>
            </div>
        </template>

        <div class="p-6">
            <div class="max-w-2xl mx-auto">
                <form @submit.prevent="submit" class="bg-white rounded-xl border p-6 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Full Name *</label>
                            <input v-model="form.name" type="text" class="mt-1 block w-full border-gray-300 rounded-lg text-sm" placeholder="e.g. Berry Davidowitz" />
                            <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Email *</label>
                            <input v-model="form.email" type="email" class="mt-1 block w-full border-gray-300 rounded-lg text-sm" />
                            <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Password *</label>
                            <input v-model="form.password" type="password" class="mt-1 block w-full border-gray-300 rounded-lg text-sm" />
                            <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Confirm Password *</label>
                            <input v-model="form.password_confirmation" type="password" class="mt-1 block w-full border-gray-300 rounded-lg text-sm" />
                        </div>
                    </div>

                    <!-- Permission Type -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Permission Type</label>
                        <select v-model="form.permission_type_id" class="mt-1 block w-full border-gray-300 rounded-lg text-sm">
                            <option value="">None (use role defaults)</option>
                            <option v-for="pt in permissionTypes" :key="pt.id" :value="pt.id">{{ pt.name }}</option>
                        </select>
                    </div>

                    <!-- Role Selection -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-3">Role *</label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <label v-for="role in roles" :key="role.id"
                                   class="flex items-start gap-3 p-4 border-2 rounded-xl cursor-pointer transition-all"
                                   :class="form.role === role.name ? (roleColors[role.name] || 'border-gray-500 bg-gray-50') : 'border-gray-200 hover:border-gray-300'">
                                <input v-model="form.role" :value="role.name" type="radio" class="mt-0.5" />
                                <div>
                                    <span class="font-medium text-sm capitalize">{{ role.name }}</span>
                                    <p class="text-xs text-gray-500 mt-0.5">{{ roleDescriptions[role.name] || '' }}</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3">
                        <Link :href="route('users.index')" class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-lg">Cancel</Link>
                        <button type="submit" :disabled="form.processing" class="px-6 py-2 text-sm text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                            {{ form.processing ? 'Creating...' : 'Create User' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
