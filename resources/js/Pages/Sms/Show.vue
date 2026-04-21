<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import { ref, nextTick, onMounted, onBeforeUnmount, watch } from 'vue';

const props = defineProps({
    phone:    { type: String, required: true },
    messages: { type: Array,  default: () => [] },
    customer: { type: Object, default: null },
});

const reply = useForm({
    to: props.phone,
    message: '',
    customer_id: props.customer?.id || null,
    subject_type: props.customer ? 'App\\Models\\Customer' : null,
    subject_id: props.customer?.id || null,
});

const threadEl = ref(null);
const scrollBottom = () => nextTick(() => { if (threadEl.value) threadEl.value.scrollTop = threadEl.value.scrollHeight; });
onMounted(scrollBottom);

// Live updates — poll every 5s for new messages
let pollTimer = null;
const poll = () => {
    router.reload({
        only: ['messages'],
        preserveScroll: true,
        preserveState: true,
    });
};
onMounted(() => { pollTimer = setInterval(poll, 5000); });
onBeforeUnmount(() => { if (pollTimer) clearInterval(pollTimer); });
// Auto-scroll when new messages arrive
watch(() => props.messages?.length, () => scrollBottom());

const send = () => {
    if (!reply.message.trim()) return;
    reply.post(route('sms.send'), {
        preserveScroll: true,
        onSuccess: () => { reply.reset('message'); scrollBottom(); },
    });
};

const fmt = (iso) => {
    if (!iso) return '';
    return new Date(iso).toLocaleString([], { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
};
</script>

<template>
    <AppLayout :title="`SMS — ${customer ? customer.first_name + ' ' + customer.last_name : phone}`">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('sms.index')" class="text-indigo-600 hover:text-indigo-800 text-sm">← Inbox</Link>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ customer ? `${customer.first_name} ${customer.last_name}` : phone }}
                </h2>
                <span v-if="customer" class="text-sm text-gray-500">{{ phone }}</span>
                <Link v-if="customer" :href="route('customers.show', customer.id)"
                    class="ml-auto text-sm text-indigo-600 hover:text-indigo-800">View Customer →</Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white shadow-sm rounded-lg flex flex-col" style="height: calc(100vh - 220px)">
                    <!-- Messages — bottom-anchored like iMessage -->
                    <div ref="threadEl" class="flex-1 overflow-y-auto bg-gray-50">
                        <div class="min-h-full flex flex-col justify-end p-4 space-y-3">
                            <div v-if="messages.length === 0" class="text-center text-gray-400 py-8">
                                No messages yet — send the first one below.
                            </div>
                            <div v-for="m in messages" :key="m.id" class="flex"
                                :class="m.direction === 'outbound' ? 'justify-end' : 'justify-start'">
                                <div class="max-w-md">
                                    <div class="px-4 py-2 rounded-2xl text-sm whitespace-pre-wrap break-words"
                                        :class="m.direction === 'outbound'
                                            ? 'bg-indigo-600 text-white rounded-br-sm'
                                            : 'bg-white text-gray-900 border rounded-bl-sm'">
                                        <template v-if="m.attachments && m.attachments.media">
                                            <a v-for="(att, i) in m.attachments.media" :key="i" :href="att.url" target="_blank" class="block mb-1 last:mb-0">
                                                <img v-if="att.mime && att.mime.startsWith('image/')" :src="att.url"
                                                    class="rounded-lg max-w-full max-h-72 object-contain" :alt="att.name" />
                                                <span v-else class="underline">{{ att.name }}</span>
                                            </a>
                                        </template>
                                        <span v-if="m.body">{{ m.body }}</span>
                                    </div>
                                    <div class="text-xs text-gray-400 mt-1"
                                        :class="m.direction === 'outbound' ? 'text-right' : 'text-left'">
                                        {{ fmt(m.sent_at || m.created_at) }}
                                        <span v-if="m.direction === 'outbound'" class="ml-1">· {{ m.status }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Reply box -->
                    <form @submit.prevent="send" class="border-t p-3 flex items-end gap-2 bg-white">
                        <textarea v-model="reply.message" rows="2"
                            placeholder="Type a message…"
                            class="flex-1 border-gray-300 rounded-lg text-sm resize-none focus:border-indigo-500 focus:ring-indigo-500"
                            @keydown.enter.exact.prevent="send" />
                        <button type="submit"
                            :disabled="reply.processing || !reply.message.trim()"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 disabled:opacity-50">
                            {{ reply.processing ? 'Sending…' : 'Send' }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
