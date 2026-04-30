<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({ dealers: Object, filters: Object });

const q = ref(props.filters?.q ?? '');
let qTimer = null;
watch(q, (v) => {
    clearTimeout(qTimer);
    qTimer = setTimeout(() => {
        router.get(route('leasing.dealers.index'), { q: v }, { preserveState: true, replace: true });
    }, 250);
});

const newForm = useForm({
    name: '', contact_name: '', phone: '', email: '', website: '',
    address: '', city: '', state: '', zip: '', makes_carried: '', notes: '',
});
const submitNew = () => newForm.post(route('leasing.dealers.store'), {
    preserveScroll: true, onSuccess: () => newForm.reset(),
});

const editing = ref(null);
const editForm = useForm({});
const startEdit = (row) => {
    editing.value = row.id;
    Object.assign(editForm, {
        name: row.name, contact_name: row.contact_name, phone: row.phone, email: row.email,
        website: row.website, address: row.address, city: row.city, state: row.state, zip: row.zip,
        makes_carried: row.makes_carried, notes: row.notes, is_active: row.is_active,
    });
};
const saveEdit = () => editForm.put(route('leasing.dealers.update', editing.value), {
    preserveScroll: true, onSuccess: () => { editing.value = null; },
});
const cancelEdit = () => { editing.value = null; };
const deactivate = (row) => {
    if (!confirm(`Deactivate ${row.name}? Existing deals keep the link.`)) return;
    router.delete(route('leasing.dealers.destroy', row.id), { preserveScroll: true });
};
</script>

<template>
    <AppLayout title="Dealerships">
        <template #header><h2 class="font-semibold text-xl text-gray-800 leading-tight">Dealerships</h2></template>
        <div class="py-6">
            <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-medium text-sm mb-3">Add Dealership</h3>
                    <form @submit.prevent="submitNew" class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <input v-model="newForm.name" placeholder="Name *" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                        <input v-model="newForm.contact_name" placeholder="Contact" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                        <input v-model="newForm.phone" placeholder="Phone" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                        <input v-model="newForm.email" type="email" placeholder="Email" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                        <input v-model="newForm.address" placeholder="Address" class="block w-full border-gray-300 rounded-md shadow-sm text-sm md:col-span-2" />
                        <input v-model="newForm.city" placeholder="City" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                        <input v-model="newForm.state" placeholder="ST" maxlength="2" class="block w-full border-gray-300 rounded-md shadow-sm text-sm uppercase" />
                        <input v-model="newForm.zip" placeholder="ZIP" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                        <input v-model="newForm.makes_carried" placeholder='Makes carried (e.g. "Honda, Toyota")' class="block w-full border-gray-300 rounded-md shadow-sm text-sm md:col-span-2" />
                        <button type="submit" :disabled="newForm.processing || !newForm.name"
                                class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm hover:bg-indigo-700 disabled:opacity-50">Add</button>
                    </form>
                </div>

                <div class="bg-white shadow-sm rounded-lg">
                    <div class="p-4 border-b">
                        <input v-model="q" type="text" placeholder="Search by name, contact, city, state, makes…"
                               class="block w-full md:w-96 border-gray-300 rounded-md shadow-sm text-sm" />
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contact</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Phone</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Location</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Makes</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Deals</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <template v-for="row in dealers.data" :key="row.id">
                                    <tr v-if="editing !== row.id" class="hover:bg-gray-50">
                                        <td class="px-4 py-3 text-sm font-medium">{{ row.name }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ row.contact_name || '-' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ row.phone || '-' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ [row.city, row.state].filter(Boolean).join(', ') || '-' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600 max-w-xs truncate">{{ row.makes_carried || '-' }}</td>
                                        <td class="px-4 py-3 text-sm text-right">{{ row.deals_count }}</td>
                                        <td class="px-4 py-3"><span :class="row.is_active ? 'text-green-600' : 'text-gray-400'" class="text-sm">{{ row.is_active ? 'Active' : 'Inactive' }}</span></td>
                                        <td class="px-4 py-3 text-right whitespace-nowrap">
                                            <button @click="startEdit(row)" class="text-indigo-600 text-sm hover:underline">Edit</button>
                                            <button v-if="row.is_active" @click="deactivate(row)" class="ml-3 text-red-600 text-sm hover:underline">Deactivate</button>
                                        </td>
                                    </tr>
                                    <tr v-else class="bg-indigo-50">
                                        <td colspan="8" class="p-4">
                                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                                <input v-model="editForm.name" placeholder="Name *" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                                                <input v-model="editForm.contact_name" placeholder="Contact" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                                                <input v-model="editForm.phone" placeholder="Phone" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                                                <input v-model="editForm.email" placeholder="Email" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                                                <input v-model="editForm.address" placeholder="Address" class="block w-full border-gray-300 rounded-md shadow-sm text-sm md:col-span-2" />
                                                <input v-model="editForm.city" placeholder="City" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                                                <input v-model="editForm.state" placeholder="ST" maxlength="2" class="block w-full border-gray-300 rounded-md shadow-sm text-sm uppercase" />
                                                <input v-model="editForm.zip" placeholder="ZIP" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                                                <input v-model="editForm.makes_carried" placeholder="Makes carried" class="block w-full border-gray-300 rounded-md shadow-sm text-sm md:col-span-2" />
                                                <input v-model="editForm.website" placeholder="Website" class="block w-full border-gray-300 rounded-md shadow-sm text-sm md:col-span-2" />
                                                <label class="flex items-center text-sm gap-2"><input v-model="editForm.is_active" type="checkbox" /> Active</label>
                                                <textarea v-model="editForm.notes" rows="2" placeholder="Notes" class="block w-full border-gray-300 rounded-md shadow-sm text-sm md:col-span-4"></textarea>
                                            </div>
                                            <div class="mt-3 flex gap-2">
                                                <button @click="saveEdit" :disabled="editForm.processing" class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm hover:bg-indigo-700">Save</button>
                                                <button @click="cancelEdit" class="px-4 py-2 bg-gray-200 rounded-md text-sm">Cancel</button>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                                <tr v-if="!dealers.data.length"><td colspan="8" class="px-4 py-12 text-center text-sm text-gray-400">No dealerships yet. Add one above.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
