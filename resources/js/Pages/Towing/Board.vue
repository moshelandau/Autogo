<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ columns: Array, trucks: Array, drivers: Array });

const tone = {
    pending:    'bg-amber-50',
    dispatched: 'bg-blue-50',
    en_route:   'bg-indigo-50',
    on_scene:   'bg-violet-50',
    in_transit: 'bg-cyan-50',
    completed:  'bg-emerald-50',
};
const badge = {
    pending:    'bg-amber-600',
    dispatched: 'bg-blue-600',
    en_route:   'bg-indigo-600',
    on_scene:   'bg-violet-600',
    in_transit: 'bg-cyan-600',
    completed:  'bg-emerald-600',
};

const dragging = ref(null);
const dragOver = ref(null);
const onDragStart = (job, fromStatus, e) => { dragging.value = { id: job.id, fromStatus }; e.dataTransfer.effectAllowed = 'move'; };
const onDragOver = (s, e) => { e.preventDefault(); dragOver.value = s; };
const onDragLeave = () => { dragOver.value = null; };
const onDrop = (toStatus) => {
    const d = dragging.value;
    dragOver.value = null; dragging.value = null;
    if (!d || d.fromStatus === toStatus) return;
    router.post(route('towing.status', d.id), { status: toStatus }, { preserveScroll: true, preserveState: false });
};

const priorityDot = { low: 'bg-gray-300', normal: 'bg-blue-400', high: 'bg-amber-500', urgent: 'bg-red-500 animate-pulse' };
</script>

<template>
    <AppLayout title="Towing Board">
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-bold text-xl text-gray-900">🚛 Tow Dispatch Board</h2>
                    <p class="text-sm text-gray-500">Drag a job between columns to update status</p>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('towing.index')" class="px-3 py-1.5 text-sm rounded-lg border bg-white hover:bg-gray-50">📋 List</Link>
                    <Link :href="route('towing.board')" class="px-3 py-1.5 text-sm rounded-lg bg-indigo-600 text-white">📊 Board</Link>
                    <Link :href="route('towing.create')" class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm hover:bg-indigo-700">+ New Job</Link>
                </div>
            </div>
        </template>

        <div class="px-4 pt-4 pb-1 board-scroller overflow-x-auto" style="height: calc(100vh - 65px);">
            <div class="flex gap-4 min-w-max h-full">
                <div v-for="col in columns" :key="col.id"
                     @dragover="onDragOver(col.id, $event)" @dragleave="onDragLeave" @drop="onDrop(col.id)"
                     class="w-72 flex-shrink-0 rounded-xl border-2 transition flex flex-col h-full"
                     :class="[tone[col.id], dragOver === col.id ? 'border-indigo-500 ring-4 ring-indigo-200' : 'border-transparent']">
                    <header class="px-3 py-2.5 rounded-t-xl">
                        <h3 class="font-semibold text-sm text-gray-900 capitalize flex items-center gap-2">
                            <span class="text-[10px] text-white px-1.5 py-0.5 rounded-full font-bold" :class="badge[col.id]">{{ col.count }}</span>
                            {{ col.label }}
                        </h3>
                    </header>
                    <div class="p-2 space-y-2 col-scroller overflow-y-auto flex-1 min-h-0">
                        <div v-for="card in col.cards" :key="card.id"
                             draggable="true" @dragstart="onDragStart(card, col.id, $event)"
                             class="bg-white rounded-lg border shadow-sm p-3 cursor-grab active:cursor-grabbing hover:shadow-md hover:border-indigo-300 transition">
                            <Link :href="route('towing.show', card.id)" class="block">
                                <div class="flex items-start justify-between gap-1 mb-1">
                                    <div class="font-semibold text-xs text-gray-900">{{ card.job_number }}</div>
                                    <span class="w-2 h-2 rounded-full" :class="priorityDot[card.priority]" :title="card.priority"></span>
                                </div>
                                <div class="text-sm font-medium text-gray-900 leading-tight">
                                    {{ card.customer ? `${card.customer.first_name} ${card.customer.last_name}` : (card.caller_name || '—') }}
                                </div>
                                <div v-if="card.vehicle_make" class="text-[11px] text-gray-500 mt-0.5">
                                    🚗 {{ card.vehicle_year }} {{ card.vehicle_make }} {{ card.vehicle_model }} <span v-if="card.vehicle_plate">· {{ card.vehicle_plate }}</span>
                                </div>
                                <div class="text-[11px] text-gray-600 mt-1">📍 {{ card.pickup_city || card.pickup_address }}</div>
                                <div class="text-[11px] text-gray-600">🏁 {{ card.dropoff_city || card.dropoff_address }}</div>
                                <div v-if="card.driver || card.truck" class="text-[10px] text-gray-500 mt-1.5 pt-1.5 border-t">
                                    {{ card.driver?.name }} <span v-if="card.driver && card.truck">·</span> {{ card.truck?.name }}
                                </div>
                            </Link>
                        </div>
                        <p v-if="!col.cards?.length" class="text-center text-xs text-gray-400 py-8 italic">Drop here</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<!-- .board-scroller / .col-scroller styles live in resources/css/app.css -->
