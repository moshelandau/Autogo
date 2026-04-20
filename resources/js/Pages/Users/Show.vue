<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm, router } from '@inertiajs/vue3';

const props = defineProps({ user: Object, allRoles: Array, allPermissions: Object });

const roleForm = useForm({ role: props.user.roles?.[0] || 'staff' });
const updateRole = () => roleForm.post(route('users.update-role', props.user.id));

const passwordForm = useForm({ password: '', password_confirmation: '' });
const resetPassword = () => passwordForm.post(route('users.reset-password', props.user.id), { onSuccess: () => passwordForm.reset() });

const roleColors = { admin: 'bg-red-100 text-red-800', manager: 'bg-blue-100 text-blue-800', staff: 'bg-green-100 text-green-800', driver: 'bg-yellow-100 text-yellow-800' };

const permGroupLabels = {
    customers: 'Customers', reservations: 'Reservations', vehicles: 'Vehicles', fleet: 'Fleet',
    deals: 'Deals', lenders: 'Lenders', quotes: 'Quotes', claims: 'Claims',
    repair: 'Repair Jobs', tow: 'Tow Requests', accounting: 'Accounting',
    expenses: 'Expenses', checks: 'Checks', sms: 'SMS', notifications: 'Notifications',
    templates: 'Templates', settings: 'Settings', users: 'Users', roles: 'Roles', reports: 'Reports',
};
</script>

<template>
    <AppLayout title="Manage User">
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('users.index')" class="text-gray-500 hover:text-gray-700">&larr; Back</Link>
                <h2 class="font-bold text-xl text-gray-900">{{ user.name }}</h2>
                <span v-for="role in user.roles" :key="role" class="px-2 py-1 text-xs rounded-full capitalize" :class="roleColors[role]">{{ role }}</span>
            </div>
        </template>

        <div class="p-6 space-y-6">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- User Info -->
                <div class="bg-white rounded-xl border p-6">
                    <h3 class="font-semibold text-gray-900 mb-4">User Information</h3>
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between"><dt class="text-gray-500">Name</dt><dd class="font-medium">{{ user.name }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Email</dt><dd class="font-medium">{{ user.email }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Created</dt><dd>{{ user.created_at }}</dd></div>
                    </dl>
                </div>

                <!-- Change Role -->
                <div class="bg-white rounded-xl border p-6">
                    <h3 class="font-semibold text-gray-900 mb-4">Change Role</h3>
                    <form @submit.prevent="updateRole" class="space-y-4">
                        <div class="space-y-2">
                            <label v-for="role in allRoles" :key="role.id" class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-colors"
                                   :class="roleForm.role === role.name ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:bg-gray-50'">
                                <input v-model="roleForm.role" :value="role.name" type="radio" class="text-indigo-600" />
                                <span class="text-sm font-medium capitalize">{{ role.name }}</span>
                            </label>
                        </div>
                        <button type="submit" :disabled="roleForm.processing" class="w-full px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700 disabled:opacity-50">Update Role</button>
                    </form>
                </div>
            </div>

            <!-- Reset Password -->
            <div class="bg-white rounded-xl border p-6">
                <h3 class="font-semibold text-gray-900 mb-4">Reset Password</h3>
                <form @submit.prevent="resetPassword" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <input v-model="passwordForm.password" type="password" placeholder="New password" class="block w-full border-gray-300 rounded-lg text-sm" />
                        <p v-if="passwordForm.errors.password" class="mt-1 text-xs text-red-600">{{ passwordForm.errors.password }}</p>
                    </div>
                    <div>
                        <input v-model="passwordForm.password_confirmation" type="password" placeholder="Confirm password" class="block w-full border-gray-300 rounded-lg text-sm" />
                    </div>
                    <div>
                        <button type="submit" :disabled="passwordForm.processing" class="w-full px-4 py-2 bg-red-600 text-white rounded-lg text-sm hover:bg-red-700 disabled:opacity-50">Reset Password</button>
                    </div>
                </form>
            </div>

            <!-- Permissions -->
            <div class="bg-white rounded-xl border p-6">
                <h3 class="font-semibold text-gray-900 mb-4">Current Permissions ({{ user.permissions?.length || 0 }})</h3>
                <div class="flex flex-wrap gap-2">
                    <span v-for="perm in user.permissions" :key="perm"
                          class="px-2.5 py-1 text-xs bg-gray-100 text-gray-700 rounded-lg font-mono">
                        {{ perm }}
                    </span>
                    <span v-if="!user.permissions?.length" class="text-sm text-gray-400">No permissions assigned.</span>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
