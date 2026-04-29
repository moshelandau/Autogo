<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ stages: Object, stats: Object });

const stageLabels = {
    lead: 'Lead', quote: 'Quote', application: 'Application', submission: 'Submission',
    pending: 'Pending', finalize: 'Finalize', outstanding: 'Outstanding', complete: 'Complete',
};

const stageColors = {
    lead: 'border-gray-300', quote: 'border-yellow-400', application: 'border-blue-400',
    submission: 'border-indigo-400', pending: 'border-purple-400', finalize: 'border-orange-400',
    outstanding: 'border-pink-400', complete: 'border-green-400',
};

const priorityColors = { low: 'bg-green-100 text-green-800', medium: 'bg-yellow-100 text-yellow-800', high: 'bg-red-100 text-red-800' };

const dragging = ref(null);
const dragOver = ref(null);

const onDragStart = (deal, fromStage, e) => {
    dragging.value = { dealId: deal.id, fromStage };
    e.dataTransfer.effectAllowed = 'move';
};
const onDragOver = (stage, e) => {
    e.preventDefault();
    dragOver.value = stage;
};
const onDragLeave = () => { dragOver.value = null; };
const onDrop = (toStage) => {
    const drag = dragging.value;
    dragOver.value = null;
    dragging.value = null;
    if (!drag || drag.fromStage === toStage) return;
    router.post(route('leasing.deals.transition', drag.dealId), { stage: toStage }, {
        preserveScroll: true,
        preserveState: false,
    });
};
</script>

<template>
    <AppLayout title="Deals Pipeline">
        <template #header>
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Deals Pipeline</h2>
                    <p class="text-xs text-gray-500">Drag cards across columns to change stage</p>
                </div>
                <div class="flex gap-3">
                    <Link :href="route('leasing.deals.index', { view: 'list' })" class="px-3 py-2 text-sm text-gray-600 bg-white border rounded-md hover:bg-gray-50">List View</Link>
                    <Link :href="route('leasing.deals.create')" class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm hover:bg-indigo-700">+ New Deal</Link>
                </div>
            </div>
        </template>

        <!-- Stats + Board fill the remaining viewport so the horizontal
             scrollbar lives at the bottom of the page (same pattern as
             Claims/Board.vue and Towing/Board.vue). -->
        <div class="flex flex-col" style="height: calc(100vh - 65px);">
            <!-- Stats Bar -->
            <div class="flex gap-4 px-4 pt-4 pb-3 overflow-x-auto flex-shrink-0">
                <div class="bg-white rounded-lg shadow-sm px-4 py-2 text-center min-w-fit">
                    <div class="text-lg font-bold text-gray-900">{{ stats.active_deals }}</div>
                    <div class="text-xs text-gray-500">Active</div>
                </div>
                <div class="bg-white rounded-lg shadow-sm px-4 py-2 text-center min-w-fit">
                    <div class="text-lg font-bold text-red-600">{{ stats.overdue_tasks }}</div>
                    <div class="text-xs text-gray-500">Overdue Tasks</div>
                </div>
                <div class="bg-white rounded-lg shadow-sm px-4 py-2 text-center min-w-fit">
                    <div class="text-lg font-bold text-orange-600">{{ stats.stale_deals }}</div>
                    <div class="text-xs text-gray-500">Stale</div>
                </div>
                <div class="bg-white rounded-lg shadow-sm px-4 py-2 text-center min-w-fit">
                    <div class="text-lg font-bold text-green-600">{{ stats.won_today }}</div>
                    <div class="text-xs text-gray-500">Won Today</div>
                </div>
            </div>

            <!-- Kanban Board -->
            <div class="px-4 pb-1 board-scroller overflow-x-auto flex-1 min-h-0">
                <div class="flex gap-3 min-w-max h-full">
                    <div v-for="(deals, stage) in stages" :key="stage"
                         @dragover="onDragOver(stage, $event)"
                         @dragleave="onDragLeave"
                         @drop="onDrop(stage)"
                         class="flex-shrink-0 w-72 bg-gray-50 rounded-lg border-t-4 flex flex-col h-full transition"
                         :class="[
                            stageColors[stage],
                            dragOver === stage ? 'ring-4 ring-indigo-300' : ''
                         ]">
                        <div class="p-3 border-b bg-white rounded-t-lg flex-shrink-0">
                            <div class="flex justify-between items-center">
                                <h3 class="font-semibold text-sm text-gray-700">{{ stageLabels[stage] }}</h3>
                                <span class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full">{{ deals?.length || 0 }}</span>
                            </div>
                        </div>
                        <div class="p-2 space-y-2 col-scroller overflow-y-auto flex-1 min-h-0">
                            <div v-for="deal in deals" :key="deal.id"
                                 draggable="true"
                                 @dragstart="onDragStart(deal, stage, $event)"
                                 class="cursor-grab active:cursor-grabbing">
                                <Link :href="route('leasing.deals.show', deal.id)"
                                      class="block bg-white rounded-lg shadow-sm p-3 hover:shadow-md transition-shadow border border-gray-100">
                                    <div class="flex justify-between items-start mb-2">
                                        <span class="text-xs font-mono text-gray-400">#{{ deal.deal_number }}</span>
                                        <span class="px-1.5 py-0.5 text-xs rounded capitalize" :class="priorityColors[deal.priority]">{{ deal.priority }}</span>
                                    </div>
                                    <div class="font-medium text-sm text-gray-900 mb-1">{{ deal.customer?.first_name }} {{ deal.customer?.last_name }}</div>
                                    <div v-if="deal.vehicle_make" class="text-xs text-gray-500 mb-2">
                                        {{ deal.vehicle_year }} {{ deal.vehicle_make }} {{ deal.vehicle_model }}
                                    </div>
                                    <div v-else class="text-xs text-gray-400 italic mb-2">No vehicle yet</div>
                                    <div v-if="deal.incomplete_tasks?.length" class="text-xs text-red-500">
                                        {{ deal.incomplete_tasks.length }} incomplete tasks
                                    </div>
                                    <div class="text-xs text-gray-400 mt-1">{{ deal.salesperson?.name || 'Unassigned' }}</div>
                                </Link>
                            </div>
                            <div v-if="!deals?.length" class="text-center py-6 text-xs text-gray-400">No deals</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
