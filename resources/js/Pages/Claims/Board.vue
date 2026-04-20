<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ columns: Array, stats: Object });

const fmt = (v) => v ? '$' + parseFloat(v).toLocaleString(undefined, { maximumFractionDigits: 0 }) : '$0';

// Color gradient: early steps → cool blue, later steps → warm green
const stepTones = [
    { bg: 'bg-sky-50',     badge: 'bg-sky-600'     }, // 1. Filed Claim
    { bg: 'bg-indigo-50',  badge: 'bg-indigo-600'  }, // 2. Adjuster Assigned
    { bg: 'bg-violet-50',  badge: 'bg-violet-600'  }, // 3. Appraiser Assigned
    { bg: 'bg-purple-50',  badge: 'bg-purple-600'  }, // 4. Estimate Approved
    { bg: 'bg-fuchsia-50', badge: 'bg-fuchsia-600' }, // 5. Received Estimate Payment
    { bg: 'bg-amber-50',   badge: 'bg-amber-600'   }, // 6. Towing
    { bg: 'bg-orange-50',  badge: 'bg-orange-600'  }, // 7. Towing Payment
    { bg: 'bg-yellow-50',  badge: 'bg-yellow-600'  }, // 8. Rental Payment Request
    { bg: 'bg-lime-50',    badge: 'bg-lime-600'    }, // 9. Received Rental Payment
    { bg: 'bg-emerald-50', badge: 'bg-emerald-600' }, // ✓ Done
];
const toneFor = (col) => col.id === 'done' ? stepTones[9] : stepTones[Number(col.id)] || stepTones[0];

// Drag & drop state
const dragging = ref(null); // { claimId, fromStatus }
const dragOver = ref(null); // status id being hovered

const onDragStart = (claim, fromStatus, e) => {
    dragging.value = { claimId: claim.id, fromStatus };
    e.dataTransfer.effectAllowed = 'move';
};
const onDragOver = (statusId, e) => {
    e.preventDefault();
    dragOver.value = statusId;
};
const onDragLeave = () => { dragOver.value = null; };
const onDrop = (toColId) => {
    const drag = dragging.value;
    dragOver.value = null;
    dragging.value = null;
    if (!drag || drag.fromStatus === toColId) return;
    // Map column id to step_index: numeric id → that step index; 'done' → 9
    const stepIndex = toColId === 'done' ? 9 : Number(toColId);
    router.post(route('claims.step', drag.claimId), { step_index: stepIndex }, {
        preserveScroll: true,
        preserveState: false,
    });
};
</script>

<template>
    <AppLayout title="Claims Board">
        <template #header>
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="font-bold text-xl text-gray-900">Insurance Claims — Board</h2>
                    <p class="text-sm text-gray-500">Drag cards across columns to update status</p>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('claims.index')" class="px-3 py-1.5 text-sm rounded-lg border bg-white hover:bg-gray-50">📋 List</Link>
                    <Link :href="route('claims.board')" class="px-3 py-1.5 text-sm rounded-lg bg-indigo-600 text-white">📊 Board</Link>
                    <Link :href="route('claims.create')" class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm hover:bg-indigo-700">+ New Claim</Link>
                </div>
            </div>
        </template>

        <div class="px-4 pt-4 pb-1 board-scroller overflow-x-auto" style="height: calc(100vh - 65px);">
            <div class="flex gap-4 min-w-max h-full">
                <div v-for="col in columns" :key="col.id"
                     @dragover="onDragOver(col.id, $event)"
                     @dragleave="onDragLeave"
                     @drop="onDrop(col.id)"
                     class="w-72 flex-shrink-0 rounded-xl border-2 transition flex flex-col h-full"
                     :class="[
                        toneFor(col).bg,
                        dragOver === col.id ? 'border-indigo-500 ring-4 ring-indigo-200' : 'border-transparent'
                     ]">
                    <!-- Column header -->
                    <header class="px-3 py-2.5 rounded-t-xl" :class="[toneFor(col).bg]">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5">
                                    <span class="text-[10px] text-white w-5 h-5 rounded-full flex items-center justify-center font-bold flex-shrink-0"
                                          :class="toneFor(col).badge">{{ col.id === 'done' ? '✓' : col.step }}</span>
                                    <h3 class="font-semibold text-sm text-gray-900 leading-tight">{{ col.label }}</h3>
                                </div>
                                <div class="flex items-center gap-2 mt-1.5 text-[11px] text-gray-600">
                                    <span class="font-medium">{{ col.count }} claim{{ col.count === 1 ? '' : 's' }}</span>
                                    <span v-if="col.total">·</span>
                                    <span v-if="col.total">{{ fmt(col.total) }}</span>
                                </div>
                            </div>
                        </div>
                    </header>

                    <!-- Cards -->
                    <div class="p-2 space-y-2 col-scroller overflow-y-auto flex-1 min-h-0">
                        <div v-for="card in col.cards" :key="card.id"
                             draggable="true"
                             @dragstart="onDragStart(card, col.id, $event)"
                             class="bg-white rounded-lg border shadow-sm p-3 cursor-grab active:cursor-grabbing hover:shadow-md hover:border-indigo-300 transition">
                            <Link :href="route('claims.show', card.id)" class="block">
                                <div class="flex items-start justify-between gap-2 mb-1">
                                    <div class="font-semibold text-sm text-gray-900 leading-tight">
                                        {{ card.customer?.first_name }} {{ card.customer?.last_name }}
                                    </div>
                                    <span class="text-[10px] text-gray-400">#{{ card.id }}</span>
                                </div>

                                <div v-if="card.vehicle_make || card.vehicle_year" class="text-xs text-gray-600 mb-1">
                                    🚗 {{ card.vehicle_year }} {{ card.vehicle_make }} {{ card.vehicle_model }}
                                </div>

                                <div v-if="card.accident_date" class="text-xs text-gray-500">
                                    📅 {{ card.accident_date }}
                                </div>

                                <div v-if="card.insurance_entries?.length" class="mt-1.5 space-y-0.5">
                                    <div v-for="ie in card.insurance_entries" :key="ie.id"
                                         class="text-[10px] bg-gray-100 rounded px-1.5 py-0.5 inline-block mr-1">
                                        🏢 {{ ie.insurance_company }} <span v-if="ie.claim_number" class="text-gray-500">#{{ ie.claim_number }}</span>
                                    </div>
                                </div>

                                <!-- Step progress bar -->
                                <div class="mt-2">
                                    <div class="flex justify-between text-[10px] text-gray-500 mb-0.5">
                                        <span>Step {{ (card.completed_count || 0) }}/9</span>
                                        <span class="font-bold text-gray-900">{{ fmt(card.approved_amount || card.estimate_amount) }}</span>
                                    </div>
                                    <div class="h-1.5 bg-gray-200 rounded-full overflow-hidden">
                                        <div class="h-full bg-emerald-500 transition-all" :style="{ width: ((card.completed_count || 0)/9*100) + '%' }"></div>
                                    </div>
                                </div>
                                <div v-if="card.customer?.phone" class="mt-1.5 text-[10px] text-gray-500">📞 {{ card.customer.phone }}</div>
                            </Link>
                        </div>

                        <p v-if="!col.cards?.length" class="text-center text-xs text-gray-400 py-8 italic">
                            Drop a claim here
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style>
/* Always-visible horizontal scrollbar on the board */
.board-scroller {
    scrollbar-gutter: stable;
    scrollbar-width: auto;
    scrollbar-color: #4f46e5 #e2e8f0;
    padding-bottom: 12px;
}
.board-scroller::-webkit-scrollbar {
    height: 14px !important;
    width: 14px !important;
    background: #e2e8f0;
    -webkit-appearance: none;
    display: block !important;
}
.board-scroller::-webkit-scrollbar-track {
    background: #e2e8f0;
    border-top: 1px solid #cbd5e1;
}
.board-scroller::-webkit-scrollbar-thumb {
    background-color: #6366f1;
    border-radius: 7px;
    border: 2px solid #e2e8f0;
    min-width: 60px;
}
.board-scroller::-webkit-scrollbar-thumb:hover {
    background-color: #4f46e5;
}

/* Per-column vertical scrollbar */
.col-scroller {
    scrollbar-width: thin;
    scrollbar-color: #94a3b8 transparent;
}
.col-scroller::-webkit-scrollbar { width: 8px !important; -webkit-appearance: none; }
.col-scroller::-webkit-scrollbar-thumb { background-color: #94a3b8; border-radius: 4px; }
.col-scroller::-webkit-scrollbar-thumb:hover { background-color: #64748b; }
</style>
