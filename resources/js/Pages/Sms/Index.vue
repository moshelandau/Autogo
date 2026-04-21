<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
    conversations: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const search = ref(props.filters.search || '');
const submit = () => router.get(route('sms.index'), { search: search.value }, { preserveState: true });

// Live updates — poll every 5s
let pollTimer = null;
const poll = () => router.reload({ only: ['conversations', 'smsUnreadCount'], preserveScroll: true, preserveState: true });
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
                        <input v-model="search" @keydown.enter="submit" type="search"
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
                                    <div class="flex items-baseline justify-between">
                                        <p class="font-medium text-gray-900 truncate">
                                            {{ c.customer_name || c.phone }}
                                            <span v-if="c.customer_name" class="text-xs text-gray-400 font-normal ml-1">{{ c.phone }}</span>
                                        </p>
                                        <span class="text-xs text-gray-500 ml-2">{{ fmtTime(c.last_at) }}</span>
                                    </div>
                                    <p class="text-sm text-gray-600 truncate mt-0.5">
                                        <span v-if="c.last_dir === 'outbound'" class="text-gray-400">You: </span>
                                        {{ c.last_body }}
                                    </p>
                                </div>
                                <span v-if="c.unread_count > 0"
                                    class="ml-2 inline-flex items-center justify-center px-2 py-0.5 text-xs font-bold text-white bg-green-500 rounded-full">
                                    {{ c.unread_count }}
                                </span>
                            </Link>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
