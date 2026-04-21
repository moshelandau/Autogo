<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import DialogModal from '@/Components/DialogModal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    to: { type: String, default: '' },
    customerId: { type: [Number, String], default: null },
    subjectType: { type: String, default: null },
    subjectId: { type: [Number, String], default: null },
    label: { type: String, default: 'Send SMS' },
    template: { type: String, default: '' },
});

const open = ref(false);
const form = useForm({
    to: props.to,
    message: props.template,
    customer_id: props.customerId,
    subject_type: props.subjectType,
    subject_id: props.subjectId,
});

const charsLeft = () => 1600 - (form.message?.length || 0);

const submit = () => {
    form.post(route('sms.send'), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            form.reset('message');
        },
    });
};
</script>

<template>
    <button
        type="button"
        @click="open = true"
        class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-white bg-green-600 hover:bg-green-700 rounded-md"
    >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
        </svg>
        {{ label }}
    </button>

    <DialogModal :show="open" @close="open = false">
        <template #title>Send SMS</template>
        <template #content>
            <div class="space-y-4">
                <div>
                    <InputLabel for="sms-to" value="To (phone number)" />
                    <TextInput id="sms-to" v-model="form.to" type="tel" class="mt-1 block w-full" placeholder="+1 555 123 4567" />
                    <InputError :message="form.errors.to" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="sms-msg" value="Message" />
                    <textarea
                        id="sms-msg"
                        v-model="form.message"
                        rows="5"
                        maxlength="1600"
                        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm"
                        placeholder="Type your message..."
                    />
                    <div class="mt-1 flex justify-between text-xs text-gray-500">
                        <InputError :message="form.errors.message" />
                        <span>{{ charsLeft() }} chars left</span>
                    </div>
                </div>
                <p class="text-xs text-gray-500">
                    Sent via Telebroad. Each ~160-char segment is billed.
                </p>
            </div>
        </template>
        <template #footer>
            <SecondaryButton @click="open = false">Cancel</SecondaryButton>
            <PrimaryButton class="ml-2" :disabled="form.processing || !form.to || !form.message" @click="submit">
                {{ form.processing ? 'Sending...' : 'Send' }}
            </PrimaryButton>
        </template>
    </DialogModal>
</template>
