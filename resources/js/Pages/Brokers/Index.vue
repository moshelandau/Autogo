<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    brokers: Object,
    filters: Object,
});

const q = ref(props.filters?.q ?? '');
let qTimer = null;
watch(q, (v) => {
    clearTimeout(qTimer);
    qTimer = setTimeout(() => {
        router.get(route('leasing.brokers.index'), { q: v }, { preserveState: true, replace: true });
    }, 250);
});

const newForm = useForm({
    name: '', first_name: '', last_name: '',
    phone: '', email: '', website: '',
    claims_phone: '', claims_email: '', address: '', notes: '',
});
const submitNew = () => newForm.post(route('leasing.brokers.store'), {
    preserveScroll: true,
    onSuccess: () => newForm.reset(),
});

const editing = ref(null);
const editForm = useForm({});
const startEdit = (row) => {
    editing.value = row.id;
    editForm.name = row.name;
    editForm.first_name = row.first_name;
    editForm.last_name = row.last_name;
    editForm.phone = row.phone;
    editForm.email = row.email;
    editForm.website = row.website;
    editForm.claims_phone = row.claims_phone;
    editForm.claims_email = row.claims_email;
    editForm.address = row.address;
    editForm.notes = row.notes;
    editForm.is_active = row.is_active;
};
const saveEdit = () => editForm.put(route('leasing.brokers.update', editing.value), {
    preserveScroll: true,
    onSuccess: () => { editing.value = null; },
});
const cancelEdit = () => { editing.value = null; };

const deactivate = (row) => {
    if (!confirm(`Deactivate ${row.name}? Existing deals keep the link; future deal pickers won't show it.`)) return;
    router.delete(route('leasing.brokers.destroy', row.id), { preserveScroll: true });
};
</script>

<template>
    <AppLayout title="Insurance Brokers">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Insurance Brokers</h2>
        </template>

        <div class="py-6">
            <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Add Broker -->
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-medium text-sm mb-3">Add Broker</h3>
                    <form @submit.prevent="submitNew" class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <input v-model="newForm.name" type="text" placeholder="Company *" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                        <input v-model="newForm.first_name" type="text" placeholder="Contact First" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                        <input v-model="newForm.last_name" type="text" placeholder="Contact Last" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                        <input v-model="newForm.phone" type="text" placeholder="Phone" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                        <input v-model="newForm.email" type="email" placeholder="Email" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                        <input v-model="newForm.claims_phone" type="text" placeholder="Claims Phone" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                        <input v-model="newForm.claims_email" type="email" placeholder="Claims Email" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                        <input v-model="newForm.address" type="text" placeholder="Address" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                        <input v-model="newForm.website" type="text" placeholder="Website" class="block w-full border-gray-300 rounded-md shadow-sm text-sm md:col-span-3" />
                        <button type="submit" :disabled="newForm.processing || !newForm.name"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm hover:bg-indigo-700 disabled:opacity-50">Add Broker</button>
                    </form>
                </div>

                <!-- Search + List -->
                <div class="bg-white shadow-sm rounded-lg">
                    <div class="p-4 border-b">
                        <input v-model="q" type="text" placeholder="Search by company, contact, phone, email…"
                               class="block w-full md:w-96 border-gray-300 rounded-md shadow-sm text-sm" />
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Company</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contact</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Phone</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Claims</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Deals</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <template v-for="row in brokers.data" :key="row.id">
                                    <tr v-if="editing !== row.id" class="hover:bg-gray-50">
                                        <td class="px-4 py-3 text-sm font-medium">{{ row.name }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ [row.first_name, row.last_name].filter(Boolean).join(' ') || row.contact_name || '-' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ row.phone || '-' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ row.email || '-' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">
                                            <div v-if="row.claims_phone">{{ row.claims_phone }}</div>
                                            <div v-if="row.claims_email" class="text-xs">{{ row.claims_email }}</div>
                                            <span v-if="!row.claims_phone && !row.claims_email">-</span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-right">{{ row.deals_count }}</td>
                                        <td class="px-4 py-3">
                                            <span :class="row.is_active ? 'text-green-600' : 'text-gray-400'" class="text-sm">
                                                {{ row.is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right whitespace-nowrap">
                                            <button @click="startEdit(row)" class="text-indigo-600 text-sm hover:underline">Edit</button>
                                            <button v-if="row.is_active" @click="deactivate(row)" class="ml-3 text-red-600 text-sm hover:underline">Deactivate</button>
                                        </td>
                                    </tr>
                                    <tr v-else class="bg-indigo-50">
                                        <td colspan="8" class="p-4">
                                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                                <input v-model="editForm.name" placeholder="Company *" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                                                <input v-model="editForm.first_name" placeholder="Contact First" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                                                <input v-model="editForm.last_name" placeholder="Contact Last" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                                                <input v-model="editForm.phone" placeholder="Phone" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                                                <input v-model="editForm.email" placeholder="Email" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                                                <input v-model="editForm.claims_phone" placeholder="Claims Phone" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                                                <input v-model="editForm.claims_email" placeholder="Claims Email" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                                                <input v-model="editForm.address" placeholder="Address" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                                                <input v-model="editForm.website" placeholder="Website" class="block w-full border-gray-300 rounded-md shadow-sm text-sm md:col-span-3" />
                                                <label class="flex items-center text-sm gap-2">
                                                    <input v-model="editForm.is_active" type="checkbox" /> Active
                                                </label>
                                                <textarea v-model="editForm.notes" rows="2" placeholder="Notes" class="block w-full border-gray-300 rounded-md shadow-sm text-sm md:col-span-4"></textarea>
                                            </div>
                                            <div class="mt-3 flex gap-2">
                                                <button @click="saveEdit" :disabled="editForm.processing"
                                                        class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm hover:bg-indigo-700">Save</button>
                                                <button @click="cancelEdit" class="px-4 py-2 bg-gray-200 rounded-md text-sm">Cancel</button>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                                <tr v-if="!brokers.data.length">
                                    <td colspan="8" class="px-4 py-12 text-center text-sm text-gray-400">
                                        No brokers yet. Add one above.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
