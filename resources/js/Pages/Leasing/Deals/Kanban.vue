<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';

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
</script>

<template>
    <AppLayout title="Deals Pipeline">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Deals Pipeline</h2>
                <div class="flex gap-3">
                    <Link :href="route('leasing.deals.index', { view: 'list' })" class="px-3 py-2 text-sm text-gray-600 bg-white border rounded-md hover:bg-gray-50">List View</Link>
                    <Link :href="route('leasing.deals.create')" class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm hover:bg-indigo-700">+ New Deal</Link>
                </div>
            </div>
        </template>

        <div class="py-4">
            <div class="max-w-full mx-auto px-4">
                <!-- Stats Bar -->
                <div class="flex gap-4 mb-4 overflow-x-auto">
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
                <div class="flex gap-3 overflow-x-auto pb-4" style="min-height: 70vh;">
                    <div v-for="(deals, stage) in stages" :key="stage"
                         class="flex-shrink-0 w-72 bg-gray-50 rounded-lg border-t-4" :class="stageColors[stage]">
                        <div class="p-3 border-b bg-white rounded-t-lg">
                            <div class="flex justify-between items-center">
                                <h3 class="font-semibold text-sm text-gray-700">{{ stageLabels[stage] }}</h3>
                                <span class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full">{{ deals?.length || 0 }}</span>
                            </div>
                        </div>
                        <div class="p-2 space-y-2 overflow-y-auto" style="max-height: 65vh;">
                            <Link v-for="deal in deals" :key="deal.id"
                                  :href="route('leasing.deals.show', deal.id)"
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
                            <div v-if="!deals?.length" class="text-center py-6 text-xs text-gray-400">No deals</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
