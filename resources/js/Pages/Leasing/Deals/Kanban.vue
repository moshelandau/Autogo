<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, ref, inject, onMounted } from 'vue';

const props = defineProps({ stages: Object, stats: Object });

// Drive the kanban filter from the top-bar search (AppLayout provides
// it). Falls back to a local ref if this page ever gets used outside
// AppLayout. Setting the placeholder makes the top-bar input self-
// describing while on /leasing.
const search = inject('globalSearch', ref(''));
const searchPlaceholder = inject('globalSearchPlaceholder', ref('Search...'));
onMounted(() => { searchPlaceholder.value = 'Filter deals by customer name…'; });

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

// Total deals with at least one unread SMS — surfaced as a stat tile so
// staff can see "I have 3 conversations to respond to" at a glance and
// then scan red-bordered cards to find them.
const dealsWithUnreadCount = computed(() => {
    let n = 0;
    for (const deals of Object.values(props.stages || {})) {
        for (const d of (deals || [])) if (d.unread_sms_count) n++;
    }
    return n;
});

const filteredStages = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return props.stages;
    const out = {};
    for (const [stage, deals] of Object.entries(props.stages || {})) {
        out[stage] = (deals || []).filter((d) => {
            const fn = (d.customer?.first_name || '').toLowerCase();
            const ln = (d.customer?.last_name || '').toLowerCase();
            return fn.includes(q) || ln.includes(q) || `${fn} ${ln}`.includes(q);
        });
    }
    return out;
});

const dragging = ref(null);     // { dealId, fromStage }
const dragOverStage = ref(null);
const dragOverCard = ref(null); // id of card we'd insert BEFORE

const onDragStart = (deal, fromStage, e) => {
    dragging.value = { dealId: deal.id, fromStage };
    e.dataTransfer.effectAllowed = 'move';
    // Force the card itself as the drag ghost so the user sees a card,
    // not a link/text snippet that some browsers default to.
    if (e.currentTarget && e.dataTransfer.setDragImage) {
        const r = e.currentTarget.getBoundingClientRect();
        e.dataTransfer.setDragImage(e.currentTarget, e.clientX - r.left, e.clientY - r.top);
    }
};

const onColumnDragOver = (stage, e) => {
    e.preventDefault();
    dragOverStage.value = stage;
    if (e.target === e.currentTarget) dragOverCard.value = null;
};
const onColumnDragLeave = () => { dragOverStage.value = null; };

const onCardDragOver = (deal, e) => {
    e.preventDefault();
    e.stopPropagation();
    dragOverStage.value = deal.stage;
    dragOverCard.value = deal.id;
};

const reset = () => { dragging.value = null; dragOverStage.value = null; dragOverCard.value = null; };

const onColumnDrop = (toStage) => {
    const drag = dragging.value;
    if (!drag) return reset();
    submit(drag.dealId, toStage, null);
    reset();
};

const onCardDrop = (overDeal, e) => {
    e.stopPropagation();
    const drag = dragging.value;
    if (!drag) return reset();
    if (drag.dealId === overDeal.id) return reset();
    submit(drag.dealId, overDeal.stage, overDeal.id);
    reset();
};

const submit = (dealId, stage, beforeId) => {
    router.post(route('leasing.deals.reorder', dealId), { stage, before_id: beforeId }, {
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
                    <p class="text-xs text-gray-500">Drag cards between or within columns to change stage / reorder</p>
                </div>
                <div class="flex gap-3 items-center">
                    <Link :href="route('leasing.deals.index', { view: 'list' })" class="px-3 py-2 text-sm text-gray-600 bg-white border rounded-md hover:bg-gray-50">List View</Link>
                    <Link :href="route('leasing.deals.create')" class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm hover:bg-indigo-700">+ New Deal</Link>
                </div>
            </div>
        </template>

        <div class="flex flex-col" style="height: calc(100vh - 65px);">
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
                <div v-if="dealsWithUnreadCount > 0"
                     class="bg-red-50 border border-red-300 rounded-lg shadow-sm px-4 py-2 text-center min-w-fit">
                    <div class="text-lg font-bold text-red-600">💬 {{ dealsWithUnreadCount }}</div>
                    <div class="text-xs text-red-700">Unread SMS</div>
                </div>
            </div>

            <div class="px-4 pb-1 board-scroller overflow-x-auto flex-1 min-h-0">
                <div class="flex gap-3 min-w-max h-full">
                    <div v-for="(deals, stage) in filteredStages" :key="stage"
                         @dragover="onColumnDragOver(stage, $event)"
                         @dragleave="onColumnDragLeave"
                         @drop="onColumnDrop(stage)"
                         class="flex-shrink-0 w-72 bg-gray-50 rounded-lg border-t-4 flex flex-col h-full transition"
                         :class="[
                            stageColors[stage],
                            dragOverStage === stage ? 'ring-4 ring-indigo-300' : ''
                         ]">
                        <div class="p-3 border-b bg-white rounded-t-lg flex-shrink-0">
                            <div class="flex justify-between items-center">
                                <h3 class="font-semibold text-sm text-gray-700">{{ stageLabels[stage] }}</h3>
                                <span class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full">
                                    {{ deals?.length || 0 }}<template v-if="search && (props.stages?.[stage]?.length || 0) !== (deals?.length || 0)">/{{ props.stages?.[stage]?.length || 0 }}</template>
                                </span>
                            </div>
                        </div>
                        <div class="p-2 space-y-2 col-scroller overflow-y-auto flex-1 min-h-0">
                            <div v-for="deal in deals" :key="deal.id"
                                 draggable="true"
                                 @dragstart="onDragStart(deal, stage, $event)"
                                 @dragover="onCardDragOver(deal, $event)"
                                 @drop="onCardDrop(deal, $event)"
                                 class="cursor-grab active:cursor-grabbing transition"
                                 :class="dragOverCard === deal.id ? 'pt-3 border-t-2 border-indigo-500' : ''">
                                <Link :href="route('leasing.deals.show', deal.id)" :draggable="false"
                                      class="block bg-white rounded-lg shadow-sm p-3 hover:shadow-md transition-shadow border"
                                      :class="deal.unread_sms_count ? 'border-red-400 ring-2 ring-red-200' : 'border-gray-100'">
                                    <div class="flex justify-between items-start mb-2">
                                        <span class="text-xs font-mono text-gray-400">#{{ deal.deal_number }}</span>
                                        <div class="flex items-center gap-1.5">
                                            <span v-if="deal.unread_sms_count"
                                                  title="Unread SMS — open the deal to read"
                                                  class="px-1.5 py-0.5 text-[10px] font-bold rounded-full bg-red-600 text-white">
                                                💬 {{ deal.unread_sms_count }}
                                            </span>
                                            <span class="px-1.5 py-0.5 text-xs rounded capitalize" :class="priorityColors[deal.priority]">{{ deal.priority }}</span>
                                        </div>
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
                            <div v-if="!deals?.length" class="text-center py-6 text-xs text-gray-400">
                                {{ search ? 'No matches' : 'No deals' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
