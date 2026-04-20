<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    customers: Object,
    filters: Object,
});

const search = ref(props.filters?.search || '');

watch(search, (value) => {
    router.get(route('customers.index'), { search: value }, { preserveState: true, replace: true });
});
</script>

<template>
    <AppLayout title="Customers">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Customers</h2>
                <Link :href="route('customers.create')"
                      class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm hover:bg-indigo-700">
                    + New Customer
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white shadow-sm rounded-lg">
                    <div class="p-4 border-b">
                        <input v-model="search" type="text" placeholder="Search customers..."
                               class="w-full md:w-96 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Phone</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">City</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-6 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="customer in customers.data" :key="customer.id" class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <Link :href="route('customers.show', customer.id)" class="text-indigo-600 hover:text-indigo-900 font-medium">
                                            {{ customer.first_name }} {{ customer.last_name }}
                                        </Link>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ customer.phone || '-' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ customer.email || '-' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ customer.city || '-' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span :class="customer.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                                              class="px-2 py-1 text-xs rounded-full">
                                            {{ customer.is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right">
                                        <Link :href="route('customers.edit', customer.id)" class="text-sm text-gray-500 hover:text-gray-700">Edit</Link>
                                    </td>
                                </tr>
                                <tr v-if="!customers.data?.length">
                                    <td colspan="6" class="px-6 py-8 text-center text-gray-500">No customers found.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <!-- Pagination -->
                    <div v-if="customers.links?.length > 3" class="p-4 border-t flex justify-center gap-1">
                        <Link v-for="link in customers.links" :key="link.label"
                              :href="link.url || '#'"
                              class="px-3 py-1 text-sm rounded"
                              :class="link.active ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-100'"
                              v-html="link.label" />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
