<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({ checklist: Object });
const cl = props.checklist;

const toggleItem = (itemId) => {
    router.post(route('leasing.documents.toggle', { checklist: cl.id, item: itemId }));
};

const progress = cl.items?.length ? (cl.items.filter(i => i.is_collected).length / cl.items.length * 100) : 0;
</script>

<template>
    <AppLayout title="Document Checklist">
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="route('leasing.documents.index')" class="text-gray-500 hover:text-gray-700">&larr; Back</Link>
                    <h2 class="font-bold text-xl text-gray-900">{{ cl.customer?.first_name }} {{ cl.customer?.last_name }}</h2>
                    <span class="px-3 py-1 text-sm rounded-full" :class="cl.status === 'complete' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'">{{ cl.status }}</span>
                </div>
            </div>
        </template>

        <div class="p-6">
            <div class="max-w-2xl mx-auto space-y-6">
                <!-- Progress -->
                <div class="bg-white rounded-xl border p-6">
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="font-semibold text-gray-900">Document Collection Progress</h3>
                        <span class="text-sm font-mono" :class="progress === 100 ? 'text-green-600' : 'text-gray-500'">
                            {{ cl.items?.filter(i => i.is_collected).length }}/{{ cl.items?.length }}
                        </span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-3">
                        <div class="bg-gradient-to-r from-blue-500 to-green-500 h-3 rounded-full transition-all duration-500" :style="{ width: progress + '%' }"></div>
                    </div>
                    <p v-if="cl.deal" class="text-xs text-gray-500 mt-3">Linked to Deal #{{ cl.deal.deal_number }}</p>
                </div>

                <!-- Checklist Items -->
                <div class="bg-white rounded-xl border p-6">
                    <h3 class="font-semibold text-gray-900 mb-4">Required Documents</h3>
                    <div class="space-y-1">
                        <div v-for="item in cl.items" :key="item.id"
                             class="flex items-center gap-4 py-3 px-4 rounded-xl hover:bg-gray-50 transition-colors cursor-pointer"
                             @click="toggleItem(item.id)">
                            <div class="w-7 h-7 rounded-full border-2 flex items-center justify-center flex-shrink-0 transition-all duration-200"
                                 :class="item.is_collected ? 'bg-green-500 border-green-500 text-white shadow-sm shadow-green-500/30' : 'border-gray-300 hover:border-green-400'">
                                <svg v-if="item.is_collected" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                            </div>
                            <div class="flex-1">
                                <span class="text-sm font-medium" :class="item.is_collected ? 'line-through text-gray-400' : 'text-gray-900'">
                                    {{ item.name }}
                                </span>
                            </div>
                            <span v-if="item.collected_at" class="text-xs text-gray-400">
                                {{ new Date(item.collected_at).toLocaleDateString() }}
                            </span>
                        </div>
                    </div>
                </div>

                <div v-if="cl.notes" class="bg-white rounded-xl border p-6">
                    <h3 class="font-semibold text-gray-900 mb-2">Notes</h3>
                    <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ cl.notes }}</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
