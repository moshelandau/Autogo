<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm, router } from '@inertiajs/vue3';

const props = defineProps({ claim: Object });
const c = props.claim;
const fmt = (v) => v ? '$' + parseFloat(v).toLocaleString(undefined, {minimumFractionDigits: 2}) : '-';

const commentForm = useForm({ body: '' });
const addComment = () => { commentForm.post(route('rental-claims.comment', c.id), { onSuccess: () => commentForm.reset() }); };
const changeStatus = (status) => router.post(route('rental-claims.status', c.id), { status });

// Damage photo upload
const photoForm = useForm({ photos: [] });
const onPhotos = (e) => { photoForm.photos = Array.from(e.target.files); };
const uploadPhotos = () => {
    if (!photoForm.photos.length) return;
    photoForm.post(route('rental-claims.photos.store', c.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => photoForm.reset('photos'),
    });
};
const deletePhoto = (doc) => {
    if (!confirm('Delete this photo?')) return;
    router.delete(route('rental-claims.photos.destroy', [c.id, doc.id]), { preserveScroll: true });
};

const statusColors = { new: 'bg-blue-100 text-blue-800', pending_documents: 'bg-yellow-100 text-yellow-800', completed: 'bg-green-100 text-green-800', approved: 'bg-emerald-100 text-emerald-800' };
</script>

<template>
    <AppLayout title="Rental Claim">
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="route('rental-claims.index')" class="text-gray-500 hover:text-gray-700">&larr; Back</Link>
                    <h2 class="font-bold text-xl text-gray-900">{{ c.customer?.first_name }} {{ c.customer?.last_name }}</h2>
                    <span class="px-3 py-1 text-sm rounded-full capitalize" :class="statusColors[c.status]">{{ c.status?.replace('_', ' ') }}</span>
                </div>
                <div class="flex gap-2">
                    <button v-if="c.status !== 'pending_documents'" @click="changeStatus('pending_documents')" class="text-xs px-3 py-1.5 bg-yellow-100 text-yellow-800 rounded-lg hover:bg-yellow-200">Pending Docs</button>
                    <button v-if="c.status !== 'completed'" @click="changeStatus('completed')" class="text-xs px-3 py-1.5 bg-green-100 text-green-800 rounded-lg hover:bg-green-200">Complete</button>
                    <button v-if="c.status !== 'approved'" @click="changeStatus('approved')" class="text-xs px-3 py-1.5 bg-emerald-100 text-emerald-800 rounded-lg hover:bg-emerald-200">Approve</button>
                </div>
            </div>
        </template>

        <div class="p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <div class="bg-white rounded-xl border p-5">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase mb-3">Customer</h3>
                    <p class="font-bold">{{ c.customer?.first_name }} {{ c.customer?.last_name }}</p>
                    <p class="text-sm text-gray-600">{{ c.customer?.phone }}</p>
                    <p class="text-xs text-gray-400 mt-2 capitalize">Brand: {{ c.brand?.replace('_', ' ') }}</p>
                </div>
                <div class="bg-white rounded-xl border p-5">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase mb-3">Vehicle & Damage</h3>
                    <p v-if="c.vehicle" class="font-bold text-sm">{{ c.vehicle.year }} {{ c.vehicle.make }} {{ c.vehicle.model }}</p>
                    <p v-if="c.vehicle" class="text-xs text-gray-500">Plate: {{ c.vehicle.license_plate }}</p>
                    <p v-if="c.incident_date" class="text-xs text-gray-500 mt-2">Date: {{ c.incident_date }}</p>
                    <p v-if="c.damage_description" class="text-sm text-gray-700 mt-2">{{ c.damage_description }}</p>
                </div>
                <div class="bg-white rounded-xl border p-5">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase mb-3">Financial</h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between"><span class="text-gray-500">Damage</span><span class="font-bold">{{ fmt(c.damage_amount) }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Deductible</span><span>{{ fmt(c.deductible_amount) }}</span></div>
                        <div class="flex justify-between border-t pt-2"><span class="text-gray-500">Collected</span><span class="font-bold text-green-600">{{ fmt(c.collected_amount) }}</span></div>
                    </div>
                    <div v-if="c.insurance_company" class="mt-3 pt-3 border-t text-xs text-gray-500">
                        <div>Insurance: {{ c.insurance_company }}</div>
                        <div v-if="c.insurance_claim_number">Claim #: {{ c.insurance_claim_number }}</div>
                    </div>
                </div>
            </div>

            <!-- Damage photos -->
            <div class="bg-white rounded-xl border p-5">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase">📸 Damage Photos ({{ c.documents?.filter(d => d.type === 'damage_photo').length || 0 }})</h3>
                    <label class="bg-rose-600 text-white px-3 py-1.5 rounded-lg text-sm cursor-pointer hover:bg-rose-700">
                        📷 Add Photos
                        <input type="file" multiple accept="image/*" capture="environment" @change="onPhotos" class="hidden" />
                    </label>
                </div>
                <div v-if="photoForm.photos.length" class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-3 flex items-center justify-between">
                    <span class="text-sm">{{ photoForm.photos.length }} file(s) selected</span>
                    <button @click="uploadPhotos" :disabled="photoForm.processing" class="px-4 py-1.5 bg-emerald-600 text-white rounded-lg text-sm disabled:opacity-50">
                        {{ photoForm.processing ? 'Uploading…' : `Upload ${photoForm.photos.length} photo(s)` }}
                    </button>
                </div>
                <div v-if="c.documents?.length" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                    <div v-for="d in c.documents.filter(x => x.type === 'damage_photo')" :key="d.id"
                         class="relative aspect-square rounded-lg overflow-hidden border-2 border-rose-300 group">
                        <a :href="`/storage/${d.path}`" target="_blank">
                            <img :src="`/storage/${d.path}`" :alt="d.name" class="w-full h-full object-cover" />
                        </a>
                        <div class="absolute inset-x-0 bottom-0 bg-black/60 text-white px-2 py-1 flex items-center justify-between text-[10px]">
                            <span class="truncate">{{ d.name }}</span>
                            <button @click="deletePhoto(d)" class="opacity-0 group-hover:opacity-100 text-red-300 hover:text-white">×</button>
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm text-gray-400 text-center py-4 italic">
                    No photos yet — upload damage photos so insurance has full documentation.
                </p>
            </div>

            <!-- Comments -->
            <div class="bg-white rounded-xl border p-5">
                <h3 class="text-sm font-semibold text-gray-500 uppercase mb-3">Comments & Activity</h3>
                <form @submit.prevent="addComment" class="flex gap-3 mb-4">
                    <input v-model="commentForm.body" type="text" placeholder="Add a comment..." class="flex-1 border-gray-300 rounded-lg text-sm" />
                    <button type="submit" :disabled="commentForm.processing || !commentForm.body" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm disabled:opacity-50">Post</button>
                </form>
                <div v-for="comment in c.comments" :key="comment.id" class="border-b last:border-0 py-3">
                    <div class="flex justify-between text-xs text-gray-400 mb-1">
                        <span class="font-medium text-gray-600">{{ comment.user?.name || 'System' }}</span>
                        <span>{{ new Date(comment.created_at).toLocaleString() }}</span>
                    </div>
                    <p class="text-sm text-gray-800 whitespace-pre-wrap">{{ comment.body }}</p>
                </div>
                <p v-if="!c.comments?.length" class="text-sm text-gray-400">No comments yet.</p>
            </div>
        </div>
    </AppLayout>
</template>
