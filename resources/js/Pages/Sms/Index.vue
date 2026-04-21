<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
    conversations: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const search = ref(props.filters.search || '');

// Debounced live search as you type
let searchTimer = null;
const submit = () => {
    router.get(route('sms.index'), { search: search.value }, {
        preserveState: true, preserveScroll: true, replace: true,
    });
};
const onSearchInput = () => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(submit, 300);
};

// Live updates — poll every 3s (preserve current search)
let pollTimer = null;
const poll = () => {
    router.get(route('sms.index'), { search: search.value }, {
        only: ['conversations', 'smsUnreadCount'],
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};
onMounted(() => { pollTimer = setInterval(poll, 3000); });
onBeforeUnmount(() => { if (pollTimer) clearInterval(pollTimer); });

const fmtTime = (iso) => {
    if (!iso) return '';
    const d = new Date(iso);
    const today = new Date();
    if (d.toDateString() === today.toDateString()) {
        return d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
    }
    return d.toLocaleDateString();
};
</script>

<template>
    <AppLayout title="SMS Inbox">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">SMS Inbox</h2>
        </template>

        <div class="py-6">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                    <div class="p-4 border-b">
                        <input v-model="search" @input="onSearchInput" type="search"
                            placeholder="Search messages, numbers, names..."
                            class="w-full border-gray-300 rounded-md text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    </div>

                    <div v-if="conversations.length === 0" class="p-12 text-center text-gray-500">
                        <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                        </svg>
                        No SMS conversations yet.
                    </div>

                    <ul v-else class="divide-y">
                        <li v-for="c in conversations" :key="c.phone">
                            <Link :href="route('sms.show', c.phone)"
                                class="flex items-start gap-3 p-4 hover:bg-gray-50">
                                <div class="flex-shrink-0 w-10 h-10 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center font-semibold">
                                    {{ (c.customer_name || c.phone).charAt(0).toUpperCase() }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-medium text-gray-900 truncate">
                                        {{ c.customer_name || c.phone }}
                                        <span v-if="c.customer_name" class="text-xs text-gray-400 font-normal ml-1">{{ c.phone }}</span>
                                    </p>
                                    <p class="text-sm text-gray-600 truncate mt-0.5">
                                        <span v-if="c.last_dir === 'outbound'" class="text-gray-400">You: </span>
                                        {{ c.last_body }}
                                    </p>
                                </div>
                                <div class="flex flex-col items-end gap-1 ml-2 flex-shrink-0">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs text-gray-500">{{ fmtTime(c.last_at) }}</span>
                                        <span v-if="c.unread_count > 0"
                                            class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 text-xs font-bold text-white bg-green-500 rounded-full">
                                            {{ c.unread_count }}
                                        </span>
                                    </div>
                                    <span v-if="c.assignee_name"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 text-[11px] font-medium text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-full">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                        {{ c.assignee_name }}
                                    </span>
                                    <span v-else
                                        class="inline-flex items-center px-2 py-0.5 text-[11px] font-medium text-gray-500 bg-gray-100 rounded-full">
                                        Unassigned
                                    </span>
                                </div>
                            </Link>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
