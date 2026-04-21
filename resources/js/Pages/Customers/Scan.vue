<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { ref, onMounted, onBeforeUnmount } from 'vue';
import axios from 'axios';

const props = defineProps({ customer: Object, documentTypes: Object });

// ── Plustek scanner state ──────────────────────────────
const scriptLoaded = ref(false);
const scannerStatus = ref('Loading scanner library…');
const scannerStatusClass = ref('bg-gray-100 text-gray-700');
const isScannerReady = ref(false);
const isInitializing = ref(false);
const isScanning = ref(false);
const scannerConnectionFailed = ref(false);
const retryAttempt = ref(0);
const maxRetries = 3;
const retryDelay = 2000;

let myScan = null;

// ── Captured pages ──────────────────────────────────────
const scannedPages = ref([]); // { base64, mime, status: 'pending'|'uploading'|'classified'|'failed', detected_type, detected_fields, document, page_index }

const sigCanvas = ref(null);   // signature canvas
const showSignature = ref(false);
const signatureDataUrl = ref(null);

const delay = (ms) => new Promise(r => setTimeout(r, ms));

const loadScannerLibrary = () => new Promise((resolve, reject) => {
    if (typeof window.WebFxScan !== 'undefined') return resolve();
    const s = document.createElement('script');
    s.src = '/assets/plustek/scan.js';
    s.onload = () => typeof window.WebFxScan !== 'undefined' ? resolve() : reject(new Error('WebFxScan failed to load'));
    s.onerror = () => reject(new Error('Failed to load /assets/plustek/scan.js'));
    document.head.appendChild(s);
});

const initializeScanner = async () => {
    isInitializing.value = true;
    scannerConnectionFailed.value = false;
    for (let attempt = 1; attempt <= maxRetries; attempt++) {
        retryAttempt.value = attempt;
        scannerStatus.value = attempt === 1 ? 'Connecting to scanner…' : `Retrying (${attempt}/${maxRetries})…`;
        scannerStatusClass.value = 'bg-yellow-100 text-yellow-800';
        try {
            await attemptInit();
            isInitializing.value = false;
            retryAttempt.value = 0;
            return;
        } catch (e) {
            console.error(`Scanner init ${attempt} failed:`, e);
            if (attempt === maxRetries) {
                isInitializing.value = false;
                scannerConnectionFailed.value = true;
                scannerStatus.value = `Scanner connection failed after ${maxRetries} attempts. Make sure Plustek service is running on this PC.`;
                scannerStatusClass.value = 'bg-red-100 text-red-800';
                return;
            }
            await delay(retryDelay);
        }
    }
};

const attemptInit = async () => {
    myScan = new window.WebFxScan();
    await myScan.connect({
        ip: '127.0.0.1', port: '17778',
        errorCallback: () => { scannerStatus.value = 'Scanner connection error.'; scannerStatusClass.value = 'bg-red-100 text-red-800'; isScannerReady.value = false; },
        closeCallback: () => { scannerStatus.value = 'Scanner disconnected.'; scannerStatusClass.value = 'bg-red-100 text-red-800'; isScannerReady.value = false; },
    });
    await myScan.setBeforeAutoScanCallback({
        callback: () => {
            isScanning.value = true;
            scannedPages.value = [];
            scannerStatus.value = 'Scanning… feed pages one after another (DL front, DL back, insurance, CC, etc.)';
            scannerStatusClass.value = 'bg-blue-100 text-blue-800';
        },
    });
    await myScan.setAutoScanCallback({
        callback: (file, errCode) => {
            if (errCode !== 0) {
                isScanning.value = false;
                scannerStatus.value = 'Scan failed.';
                scannerStatusClass.value = 'bg-red-100 text-red-800';
                return;
            }
            handleScannedPage(file.base64, file.fileName);
        },
    });
    await myScan.init();
    const { data: optionData } = await myScan.getDeviceList();
    const { options } = optionData;
    if (!options?.length) throw new Error('Scanner not detected');
    const { source = {} } = options[0];
    const { value: sourceAry = [] } = source;
    let selected = sourceAry[0];
    if (sourceAry.includes('Auto')) selected = 'Auto';
    else if (sourceAry.includes('ADF-Duplex')) selected = 'ADF-Duplex';
    else if (sourceAry.includes('Sheetfed-Duplex')) selected = 'Sheetfed-Duplex';
    await myScan.setScanner({ resolution: 300, mode: 'color', brightness: 0, contrast: 0, quality: 90, source: selected });
    isScannerReady.value = true;
    scannerStatus.value = `✅ Scanner ready (source: ${selected})`;
    scannerStatusClass.value = 'bg-green-100 text-green-800';
};

const triggerScan = async () => {
    if (!isScannerReady.value || !myScan) return;
    try { await myScan.startAutoScan(); }
    catch (e) {
        scannerStatus.value = 'Scan trigger failed: ' + (e.message || e);
        scannerStatusClass.value = 'bg-red-100 text-red-800';
    }
};

const handleScannedPage = async (base64, fileName) => {
    const pageIdx = scannedPages.value.length;
    const data = base64.startsWith('data:') ? base64 : `data:image/jpeg;base64,${base64}`;
    const entry = {
        page_index: pageIdx,
        base64: data,
        preview: data,
        mime: 'image/jpeg',
        status: 'uploading',
        detected_type: null,
        detected_fields: {},
        document: null,
        manual_type: '',
    };
    scannedPages.value.push(entry);

    try {
        const { data: resp } = await axios.post(route('customers.scan.ingest', props.customer.id), {
            image_base64: base64,
            mime: 'image/jpeg',
            page_index: pageIdx,
        });
        entry.status = 'classified';
        entry.detected_type   = resp.detected_type;
        entry.detected_fields = resp.detected_fields || {};
        entry.document        = resp.document;
        entry.manual_type     = resp.detected_type;
    } catch (e) {
        entry.status = 'failed';
    }

    isScanning.value = false;
    if (!scannedPages.value.some(p => p.status === 'uploading')) {
        scannerStatus.value = `✅ Captured ${scannedPages.value.length} page(s)`;
        scannerStatusClass.value = 'bg-green-100 text-green-800';
    }
};

const removePage = (i) => scannedPages.value.splice(i, 1);

// ── Signature pad (basic canvas) ────────────────────────
let drawing = false;
const startDraw = (e) => {
    drawing = true;
    const c = sigCanvas.value;
    const ctx = c.getContext('2d');
    ctx.beginPath();
    const { x, y } = pos(e, c);
    ctx.moveTo(x, y);
};
const moveDraw = (e) => {
    if (!drawing) return;
    const c = sigCanvas.value;
    const ctx = c.getContext('2d');
    const { x, y } = pos(e, c);
    ctx.lineTo(x, y);
    ctx.lineWidth = 2.5;
    ctx.strokeStyle = '#1e293b';
    ctx.lineCap = 'round';
    ctx.stroke();
};
const endDraw = () => { drawing = false; };
const pos = (e, c) => {
    const r = c.getBoundingClientRect();
    if (e.touches?.[0]) return { x: e.touches[0].clientX - r.left, y: e.touches[0].clientY - r.top };
    return { x: e.offsetX, y: e.offsetY };
};
const clearSignature = () => {
    const c = sigCanvas.value;
    c.getContext('2d').clearRect(0, 0, c.width, c.height);
    signatureDataUrl.value = null;
};
const captureSignature = () => {
    signatureDataUrl.value = sigCanvas.value.toDataURL('image/png');
    showSignature.value = false;
};

onMounted(async () => {
    try {
        await loadScannerLibrary();
        scriptLoaded.value = true;
        await initializeScanner();
    } catch (e) {
        scriptLoaded.value = false;
        scannerStatus.value = 'Scanner library failed: ' + e.message + ' — you can still upload manually.';
        scannerStatusClass.value = 'bg-red-100 text-red-800';
    }
});

onBeforeUnmount(() => { try { myScan?.disconnect?.(); } catch {} });
</script>

<template>
    <AppLayout title="Scan Customer Documents">
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('customers.show', customer.id)" class="text-gray-500 hover:text-gray-700">&larr; Back</Link>
                <h2 class="font-bold text-xl text-gray-900">📄 Scan: {{ customer.first_name }} {{ customer.last_name }}</h2>
            </div>
        </template>

        <div class="p-6 max-w-5xl mx-auto space-y-5">
            <!-- Status -->
            <div class="rounded-xl p-4 text-sm" :class="scannerStatusClass">
                <div class="font-semibold">{{ scannerStatus }}</div>
                <div v-if="retryAttempt > 0 && retryAttempt < maxRetries" class="text-xs mt-1">Attempt {{ retryAttempt }} of {{ maxRetries }}…</div>
            </div>

            <div v-if="scannerConnectionFailed" class="bg-red-50 border-2 border-red-300 rounded-xl p-4">
                <p class="text-sm text-red-800 font-semibold mb-2">Scanner not reachable.</p>
                <ul class="text-xs text-red-700 list-disc ml-5">
                    <li>Make sure the Plustek scanner is plugged in via USB</li>
                    <li>Make sure the Plustek scanner service is running on this computer (port 17778)</li>
                </ul>
                <button @click="initializeScanner" class="mt-3 px-3 py-1.5 text-sm bg-red-600 text-white rounded">🔄 Retry</button>
            </div>

            <!-- Big Scan button -->
            <button @click="triggerScan"
                    :disabled="!isScannerReady || isScanning || isInitializing || scannerConnectionFailed"
                    class="w-full p-5 bg-indigo-600 text-white text-lg font-bold rounded-xl shadow hover:bg-indigo-700 disabled:bg-gray-400 disabled:cursor-not-allowed">
                <span v-if="isScanning">⏳ Scanning… (feed pages one after another)</span>
                <span v-else-if="isInitializing">⏳ Initializing scanner…</span>
                <span v-else>📄 Scan Documents (DL, Insurance, CC — feed all pages)</span>
            </button>

            <!-- Captured pages with auto-classification -->
            <div v-if="scannedPages.length" class="bg-white rounded-xl border p-5">
                <h3 class="font-semibold mb-3">Captured Pages — AI auto-classified</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div v-for="(p, i) in scannedPages" :key="i" class="border rounded-xl overflow-hidden">
                        <img :src="p.preview" class="w-full h-48 object-contain bg-gray-100" />
                        <div class="p-3 space-y-2">
                            <div class="flex items-center justify-between text-xs">
                                <span v-if="p.status === 'uploading'" class="text-blue-600">⏳ Classifying…</span>
                                <span v-else-if="p.status === 'classified'" class="text-emerald-600 font-semibold">✓ Classified</span>
                                <span v-else class="text-red-600">✗ Failed</span>
                                <button @click="removePage(i)" class="text-red-500 hover:text-red-700">×</button>
                            </div>
                            <div>
                                <label class="block text-[10px] font-semibold text-gray-600">Document type</label>
                                <select v-model="p.manual_type" class="mt-1 w-full border-gray-300 rounded text-xs">
                                    <option v-for="(label, val) in documentTypes" :key="val" :value="val">{{ label }}</option>
                                </select>
                            </div>
                            <div v-if="Object.keys(p.detected_fields || {}).length" class="text-[10px] text-gray-600 bg-gray-50 rounded p-2">
                                <strong class="block mb-0.5">Detected:</strong>
                                <div v-for="(v, k) in p.detected_fields" :key="k">{{ k }}: {{ v }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Signature -->
            <div class="bg-white rounded-xl border p-5">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-semibold">✍️ Customer Signature (rental agreement)</h3>
                    <button @click="showSignature = true" class="text-sm bg-emerald-600 text-white px-3 py-1.5 rounded">
                        {{ signatureDataUrl ? 'Re-sign' : 'Sign on screen' }}
                    </button>
                </div>
                <div v-if="signatureDataUrl" class="border rounded-lg p-2 bg-gray-50">
                    <img :src="signatureDataUrl" alt="Signature" class="max-h-32" />
                    <div class="text-xs text-emerald-700 mt-2">✓ Signed — will be saved to the rental agreement document.</div>
                </div>
            </div>

            <!-- Signature modal -->
            <div v-if="showSignature" @click.self="showSignature = false"
                 class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
                <div class="bg-white rounded-2xl max-w-2xl w-full p-5">
                    <h3 class="font-bold text-lg mb-2">Sign in the box</h3>
                    <p class="text-xs text-gray-500 mb-3">By signing, customer agrees to the rental agreement terms (full responsibility for damage, no third-party repair, etc.)</p>
                    <canvas ref="sigCanvas" width="600" height="200"
                            class="w-full border-2 border-dashed border-gray-300 rounded-lg cursor-crosshair touch-none"
                            @mousedown="startDraw" @mousemove="moveDraw" @mouseup="endDraw" @mouseleave="endDraw"
                            @touchstart.prevent="startDraw" @touchmove.prevent="moveDraw" @touchend.prevent="endDraw"></canvas>
                    <div class="flex justify-between mt-3">
                        <button @click="clearSignature" class="px-3 py-1.5 text-sm bg-gray-100 rounded">Clear</button>
                        <div class="space-x-2">
                            <button @click="showSignature = false" class="px-3 py-1.5 text-sm bg-gray-100 rounded">Cancel</button>
                            <button @click="captureSignature" class="px-4 py-1.5 text-sm bg-emerald-600 text-white rounded font-semibold">✓ Save Signature</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
