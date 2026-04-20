<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({ grouped: Object, stats: Object });

const form = useForm({ name: '', category: 'general', document_number: '', expiration_date: '', notes: '' });
const addDoc = () => form.post(route('business-documents.store'), { onSuccess: () => form.reset() });

const categoryLabels = { general: 'General', high_rental: 'High Rental', leasing: 'Leasing', bodyshop: 'Bodyshop' };
const categoryColors = { general: 'bg-gray-50 border-gray-200', high_rental: 'bg-blue-50 border-blue-200', leasing: 'bg-green-50 border-green-200', bodyshop: 'bg-orange-50 border-orange-200' };
</script>

<template>
    <AppLayout title="Business Documents">
        <template #header>
            <div>
                <h2 class="font-bold text-xl text-gray-900">Business Documents</h2>
                <p class="text-sm text-gray-500">Licenses, certifications, and compliance tracking</p>
            </div>
        </template>

        <div class="p-6 space-y-5">
            <div class="grid grid-cols-3 gap-4">
                <div class="bg-white rounded-xl border p-4 text-center"><div class="text-2xl font-bold text-gray-900">{{ stats.total }}</div><div class="text-xs text-gray-500">Total</div></div>
                <div class="bg-white rounded-xl border p-4 text-center"><div class="text-2xl font-bold text-yellow-600">{{ stats.expiring_soon }}</div><div class="text-xs text-gray-500">Expiring Soon</div></div>
                <div class="bg-white rounded-xl border p-4 text-center"><div class="text-2xl font-bold text-red-600">{{ stats.expired }}</div><div class="text-xs text-gray-500">Expired</div></div>
            </div>

            <!-- Add Document -->
            <div class="bg-white rounded-xl border p-5">
                <h3 class="font-semibold text-gray-900 mb-3">Add Document</h3>
                <form @submit.prevent="addDoc" class="grid grid-cols-2 md:grid-cols-5 gap-3">
                    <input v-model="form.name" type="text" placeholder="Document name *" class="col-span-2 border-gray-300 rounded-lg text-sm" />
                    <select v-model="form.category" class="border-gray-300 rounded-lg text-sm">
                        <option value="general">General</option><option value="high_rental">High Rental</option><option value="leasing">Leasing</option><option value="bodyshop">Bodyshop</option>
                    </select>
                    <input v-model="form.expiration_date" type="date" class="border-gray-300 rounded-lg text-sm" placeholder="Expiration" />
                    <button type="submit" :disabled="form.processing || !form.name" class="bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700 disabled:opacity-50">Add</button>
                </form>
            </div>

            <!-- Grouped Documents -->
            <div v-for="(docs, category) in grouped" :key="category" class="bg-white rounded-xl border overflow-hidden">
                <div class="px-5 py-3 border-b" :class="categoryColors[category] || 'bg-gray-50'">
                    <h3 class="font-semibold text-sm capitalize">{{ categoryLabels[category] || category }}</h3>
                </div>
                <div class="divide-y">
                    <div v-for="doc in docs" :key="doc.id" class="flex items-center gap-4 px-5 py-3 hover:bg-gray-50">
                        <div class="flex-1">
                            <span class="font-medium text-sm">{{ doc.name }}</span>
                            <span v-if="doc.document_number" class="text-xs text-gray-400 ml-2">#{{ doc.document_number }}</span>
                        </div>
                        <span v-if="doc.expiration_date" class="text-xs"
                              :class="new Date(doc.expiration_date) < new Date() ? 'text-red-600 font-bold' : new Date(doc.expiration_date) < new Date(Date.now() + 30*86400000) ? 'text-yellow-600 font-bold' : 'text-gray-400'">
                            {{ doc.expiration_date }}
                        </span>
                        <span v-else class="text-xs text-gray-300">No expiry</span>
                    </div>
                </div>
            </div>

            <div v-if="!Object.keys(grouped || {}).length" class="bg-white rounded-xl border p-12 text-center text-gray-400">No documents yet.</div>
        </div>
    </AppLayout>
</template>
