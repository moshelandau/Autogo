<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    events:  Array,
    start:   String,
    end:     String,
    filters: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({ locations: [], vehicle_classes: [], brands: [], statuses: [] }) },
    fleet:   { type: Array, default: () => [] },
});

// ── Filters ─────────────────────────────────────────────
const sel = ref({
    location_ids:    [...(props.filters.location_ids || [])].map(Number),
    vehicle_classes: [...(props.filters.vehicle_classes || [])],
    brands:          [...(props.filters.brands || [])],
    statuses:        [...(props.filters.statuses || [])],
});

const toggleArr = (arr, val) => {
    const i = arr.indexOf(val);
    if (i === -1) arr.push(val); else arr.splice(i, 1);
};

const applyFilters = () => {
    router.get(route('rental.calendar'), {
        start: ymd(rangeStart.value),
        end:   ymd(rangeEnd.value),
        view:  viewMode.value,
        ...sel.value,
    }, { preserveState: false, replace: true });
};

const clearFilters = () => {
    sel.value = { location_ids: [], vehicle_classes: [], brands: [], statuses: [] };
    applyFilters();
};

const activeFilterCount = computed(() =>
    (sel.value.location_ids?.length || 0) +
    (sel.value.vehicle_classes?.length || 0) +
    (sel.value.brands?.length || 0) +
    (sel.value.statuses?.length || 0)
);

const filtersOpen = ref(false);

// ── View mode: '2weeks' (default, taller) or 'month' ─────
const urlParams = new URLSearchParams(window.location.search);
const viewMode = ref(urlParams.get('view') || '2weeks');

// Anchor — for month view = 1st of month; for 2-week view = first Sunday of view
const anchor = ref(new Date(props.start + 'T00:00:00'));

// Week of "today" — Sunday of current week
const startOfThisWeek = () => {
    const d = new Date();
    d.setDate(d.getDate() - d.getDay());
    d.setHours(0, 0, 0, 0);
    return d;
};

// On first load, if view=2weeks and the server gave us a month range, snap anchor to start of this week
if (viewMode.value === '2weeks') {
    const a = startOfThisWeek();
    // only snap if anchor isn't a Sunday
    if (anchor.value.getDay() !== 0) anchor.value = a;
}

const rangeStart = computed(() => {
    if (viewMode.value === '2weeks') {
        const d = new Date(anchor.value);
        d.setDate(d.getDate() - d.getDay()); // back up to Sunday
        return d;
    }
    return new Date(anchor.value.getFullYear(), anchor.value.getMonth(), 1);
});

const rangeEnd = computed(() => {
    if (viewMode.value === '2weeks') {
        const d = new Date(rangeStart.value);
        d.setDate(d.getDate() + 13); // 14 days total
        return d;
    }
    return new Date(anchor.value.getFullYear(), anchor.value.getMonth() + 1, 0);
});

const monthLabel = computed(() => {
    if (viewMode.value === '2weeks') {
        const fmt = (d) => d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
        return `${fmt(rangeStart.value)} – ${fmt(rangeEnd.value)}, ${rangeEnd.value.getFullYear()}`;
    }
    return anchor.value.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
});

const gridStart = computed(() => {
    const d = new Date(rangeStart.value);
    d.setDate(d.getDate() - d.getDay());
    return d;
});
const gridEnd = computed(() => {
    const d = new Date(rangeEnd.value);
    d.setDate(d.getDate() + (6 - d.getDay()));
    return d;
});

const weeks = computed(() => {
    const w = [];
    const d = new Date(gridStart.value);
    while (d <= gridEnd.value) {
        const week = [];
        for (let i = 0; i < 7; i++) {
            week.push(new Date(d));
            d.setDate(d.getDate() + 1);
        }
        w.push(week);
    }
    return w;
});

const isSameMonth = (d) => {
    // In 2-week view, "in range" instead of "in month"
    if (viewMode.value === '2weeks') {
        return d >= rangeStart.value && d <= rangeEnd.value;
    }
    return d.getMonth() === anchor.value.getMonth();
};
const isToday = (d) => {
    const t = new Date();
    return d.getFullYear() === t.getFullYear() && d.getMonth() === t.getMonth() && d.getDate() === t.getDate();
};
const ymd = (d) => d.toISOString().slice(0, 10);
const startOfDay = (d) => { const x = new Date(d); x.setHours(0,0,0,0); return x; };
const dayDiff = (a, b) => Math.round((startOfDay(b) - startOfDay(a)) / 86400000);

// ── Vehicle lanes (Gantt-style) ───────────────────────────
// Each unique vehicle gets its own row. Reservations only show on their vehicle's row.
const vehicleLanes = computed(() => {
    // Build map: laneKey -> { key, label, plate, brand, vehicleClass, events: [] }
    const map = new Map();
    (props.events || []).forEach(ev => {
        const key = ev.vehicle_id ? `v${ev.vehicle_id}` : `class:${ev.vehicle_class || 'unassigned'}`;
        if (!map.has(key)) {
            map.set(key, {
                key,
                label: ev.vehicle_label || ev.vehicle_class || 'Unassigned',
                plate: ev.vehicle_plate || null,
                brand: ev.brand || null,
                vehicleClass: ev.vehicle_class || null,
                isUnassigned: !ev.vehicle_id,
                events: [],
            });
        }
        map.get(key).events.push(ev);
    });
    // Sort: assigned vehicles alphabetically, unassigned last
    return [...map.values()].sort((a, b) => {
        if (a.isUnassigned !== b.isUnassigned) return a.isUnassigned ? 1 : -1;
        return a.label.localeCompare(b.label);
    });
});

// Flat list of every day in the range (14 for 2-week, ~30 for month)
const allDays = computed(() => {
    const arr = [];
    const d = new Date(rangeStart.value);
    while (d <= rangeEnd.value) {
        arr.push(new Date(d));
        d.setDate(d.getDate() + 1);
    }
    return arr;
});

// For each vehicle: array of segments positioned by day-index in allDays
const segmentsByVehicle = computed(() => {
    const totalDays = allDays.value.length || 1;
    const rangeStartDay = startOfDay(rangeStart.value);
    const rangeEndDay   = startOfDay(rangeEnd.value);

    return vehicleLanes.value.map(vl => {
        const evWithDates = vl.events.map(ev => ({
            ev,
            s: startOfDay(new Date((ev.start || '').slice(0,10) + 'T00:00:00')),
            e: startOfDay(new Date((ev.end   || ev.start || '').slice(0,10) + 'T00:00:00')),
        })).filter(x => !isNaN(x.s) && !isNaN(x.e))
          .sort((a, b) => a.s - b.s);

        // Same-day handoff detection
        const trimRight = new Set(), trimLeft = new Set();
        for (let i = 0; i < evWithDates.length - 1; i++) {
            for (let j = i + 1; j < evWithDates.length; j++) {
                const a = evWithDates[i], b = evWithDates[j];
                if (b.s > a.e) break;
                if (a.e.getTime() === b.s.getTime()) {
                    trimRight.add(a.ev.id);
                    trimLeft.add(b.ev.id);
                }
            }
        }

        return evWithDates
            .filter(({ s, e }) => !(e < rangeStartDay || s > rangeEndDay))
            .map(({ ev, s, e }) => {
                const segStart = s < rangeStartDay ? rangeStartDay : s;
                const segEnd   = e > rangeEndDay   ? rangeEndDay   : e;
                let startDay = dayDiff(rangeStartDay, segStart);
                let span     = dayDiff(segStart, segEnd) + 1;
                if (trimLeft.has(ev.id)  && segStart.getTime() === s.getTime()) { startDay += 0.5; span -= 0.5; }
                if (trimRight.has(ev.id) && segEnd.getTime()   === e.getTime()) { span -= 0.5; }
                return {
                    event: ev,
                    leftPct:  (startDay / totalDays) * 100,
                    widthPct: (span     / totalDays) * 100,
                    continuesLeft:  s < rangeStartDay,
                    continuesRight: e > rangeEndDay,
                };
            });
    });
});

// Visual config
const ROW_HEIGHT  = 22; // px — each vehicle row
const HEADER_PAD  = 26;
const LABEL_WIDTH = 180; // px — fixed left column for vehicle name

const goMonth = (delta) => {
    const d = new Date(anchor.value);
    if (viewMode.value === '2weeks') {
        d.setDate(d.getDate() + delta * 7); // advance ONE WEEK per click
    } else {
        d.setDate(1);
        d.setMonth(d.getMonth() + delta);
    }
    anchor.value = d;
    reload();
};
const goToday = () => {
    anchor.value = viewMode.value === '2weeks' ? startOfThisWeek() : new Date();
    reload();
};

// Jump to arbitrary date — snap to Sunday of that week in 2-week view
const jumpToDate = (dateStr) => {
    if (!dateStr) return;
    const d = new Date(dateStr + 'T00:00:00');
    if (isNaN(d)) return;
    if (viewMode.value === '2weeks') {
        d.setDate(d.getDate() - d.getDay()); // Sunday of that week
    } else {
        d.setDate(1); // 1st of that month
    }
    anchor.value = d;
    reload();
};

// Slider: maps -52 to +52 weeks from today; current value is week-offset from today
const sliderValue = computed({
    get: () => {
        const today = startOfThisWeek();
        return Math.round((rangeStart.value - today) / (7 * 86400000));
    },
    set: (weekOffset) => {
        const d = startOfThisWeek();
        d.setDate(d.getDate() + Number(weekOffset) * 7);
        anchor.value = d;
        reload();
    },
});
const setView = (mode) => {
    viewMode.value = mode;
    if (mode === '2weeks') anchor.value = startOfThisWeek();
    reload();
};
const reload = () => {
    router.get(route('rental.calendar'), {
        start: ymd(rangeStart.value),
        end:   ymd(rangeEnd.value),
        view:  viewMode.value,
        ...sel.value,
    }, { preserveState: false, replace: true });
};

const statusBg = (s) => ({
    open:      'bg-blue-500',
    rental:    'bg-emerald-500',
    confirmed: 'bg-indigo-500',
    pending:   'bg-amber-500',
    completed: 'bg-gray-400',
    cancelled: 'bg-rose-400',
}[s] || 'bg-gray-500');

// Day-detail modal
const selectedDay = ref(null);
const eventsOnDay = (day) => {
    const t = startOfDay(day).getTime();
    return (props.events || []).filter(ev => {
        const s = startOfDay(new Date((ev.start || '').slice(0,10) + 'T00:00:00')).getTime();
        const e = startOfDay(new Date((ev.end || ev.start || '').slice(0,10) + 'T00:00:00')).getTime();
        return t >= s && t <= e;
    });
};
const selectedEvents = computed(() => selectedDay.value ? eventsOnDay(selectedDay.value) : []);

// Modal view toggle: 'booked' (default) or 'available'
const modalView = ref('booked');

// Vehicles that are FREE on the selected day (no reservation overlapping it)
const availableOnSelectedDay = computed(() => {
    if (!selectedDay.value) return [];
    const t = startOfDay(selectedDay.value).getTime();
    const bookedIds = new Set(
        (props.events || [])
            .filter(ev => {
                const s = startOfDay(new Date((ev.start || '').slice(0,10) + 'T00:00:00')).getTime();
                const e = startOfDay(new Date((ev.end   || ev.start || '').slice(0,10) + 'T00:00:00')).getTime();
                return t >= s && t <= e && !['cancelled','completed'].includes(ev.status);
            })
            .map(ev => ev.vehicle_id)
            .filter(Boolean)
    );
    return (props.fleet || []).filter(v => !bookedIds.has(v.id));
});

const closeModal = () => { selectedDay.value = null; modalView.value = 'booked'; };

const ymdSafe = (d) => d ? d.toISOString().slice(0, 10) : '';

// Sticky header refs (so vehicle column stays visible while horizontally scrolling weeks)
</script>

<template>
    <AppLayout title="Rental Calendar">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-bold text-xl text-gray-900">Rental Calendar</h2>
                <div class="flex items-center gap-2">
                    <!-- View toggle -->
                    <div class="inline-flex border rounded-lg overflow-hidden text-sm">
                        <button @click="setView('2weeks')"
                            class="px-3 py-1.5 transition"
                            :class="viewMode === '2weeks' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'">
                            2 Weeks
                        </button>
                        <button @click="setView('month')"
                            class="px-3 py-1.5 border-l transition"
                            :class="viewMode === 'month' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'">
                            Month
                        </button>
                    </div>
                    <button @click="goMonth(-1)" class="px-3 py-1.5 rounded-lg border text-sm hover:bg-gray-50">‹</button>
                    <button @click="goToday"   class="px-3 py-1.5 rounded-lg border text-sm hover:bg-gray-50">Today</button>
                    <button @click="goMonth(1)"  class="px-3 py-1.5 rounded-lg border text-sm hover:bg-gray-50">›</button>
                    <input type="date" :value="ymd(rangeStart)" @change="jumpToDate($event.target.value)"
                           class="px-2 py-1.5 rounded-lg border text-sm" title="Jump to any week" />
                    <div class="ml-3 font-semibold text-base text-gray-800 w-56 text-center">{{ monthLabel }}</div>
                </div>
            </div>
        </template>

        <div class="p-4 space-y-3">
            <!-- Filter bar -->
            <div class="bg-white border rounded-xl p-3">
                <div class="flex items-center gap-3 flex-wrap">
                    <button @click="filtersOpen = !filtersOpen"
                        class="flex items-center gap-2 px-3 py-1.5 text-sm rounded-lg border hover:bg-gray-50">
                        <span>🔍</span> Filters
                        <span v-if="activeFilterCount" class="text-xs bg-indigo-600 text-white rounded-full px-2 py-0.5">{{ activeFilterCount }}</span>
                        <span class="text-gray-400">{{ filtersOpen ? '▴' : '▾' }}</span>
                    </button>

                    <!-- Quick chips: locations -->
                    <div v-if="options.locations?.length" class="flex items-center gap-1 flex-wrap">
                        <span class="text-xs text-gray-500 font-medium">Location:</span>
                        <button v-for="l in options.locations" :key="l.id"
                            @click="toggleArr(sel.location_ids, l.id); applyFilters()"
                            class="text-xs px-2.5 py-1 rounded-full border transition"
                            :class="sel.location_ids.includes(l.id) ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-300 hover:border-gray-400'">
                            {{ l.name }}
                        </button>
                    </div>

                    <button v-if="activeFilterCount" @click="clearFilters" class="ml-auto text-xs text-gray-500 hover:text-red-600">Clear all</button>
                </div>

                <!-- Expanded panel -->
                <div v-if="filtersOpen" class="mt-3 pt-3 border-t grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <h4 class="text-xs font-semibold text-gray-700 mb-2">Car Type</h4>
                        <div class="flex flex-wrap gap-1.5">
                            <button v-for="vc in options.vehicle_classes" :key="vc"
                                @click="toggleArr(sel.vehicle_classes, vc); applyFilters()"
                                class="text-xs px-2 py-1 rounded-full border transition"
                                :class="sel.vehicle_classes.includes(vc) ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-gray-600 border-gray-300 hover:border-gray-400'">
                                {{ vc }}
                            </button>
                            <span v-if="!options.vehicle_classes?.length" class="text-xs text-gray-400 italic">None available</span>
                        </div>
                    </div>
                    <div>
                        <h4 class="text-xs font-semibold text-gray-700 mb-2">Brand (Make)</h4>
                        <div class="flex flex-wrap gap-1.5">
                            <button v-for="b in options.brands" :key="b"
                                @click="toggleArr(sel.brands, b); applyFilters()"
                                class="text-xs px-2 py-1 rounded-full border transition"
                                :class="sel.brands.includes(b) ? 'bg-amber-600 text-white border-amber-600' : 'bg-white text-gray-600 border-gray-300 hover:border-gray-400'">
                                {{ b }}
                            </button>
                            <span v-if="!options.brands?.length" class="text-xs text-gray-400 italic">None available</span>
                        </div>
                    </div>
                    <div>
                        <h4 class="text-xs font-semibold text-gray-700 mb-2">Status</h4>
                        <div class="flex flex-wrap gap-1.5">
                            <button v-for="s in options.statuses" :key="s"
                                @click="toggleArr(sel.statuses, s); applyFilters()"
                                class="text-xs px-2 py-1 rounded-full border transition capitalize"
                                :class="sel.statuses.includes(s) ? 'text-white border-transparent ' + statusBg(s) : 'bg-white text-gray-600 border-gray-300 hover:border-gray-400'">
                                {{ s }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Time slider — drag to scrub weeks; ‹/› buttons at each end step 1 week -->
            <div class="bg-white border rounded-xl px-4 py-2.5 flex items-center gap-3">
                <span class="text-xs text-gray-500 whitespace-nowrap">⏪ 6 mo ago</span>
                <button @click="goMonth(-1)" title="Back 1 week"
                    class="w-7 h-7 flex items-center justify-center rounded-full border border-indigo-300 text-indigo-700 hover:bg-indigo-50 text-sm font-bold leading-none">‹</button>
                <input type="range" min="-26" max="26" step="1" :value="sliderValue" @input="sliderValue = $event.target.value"
                       class="cal-slider flex-1" />
                <button @click="goMonth(1)" title="Forward 1 week"
                    class="w-7 h-7 flex items-center justify-center rounded-full border border-indigo-300 text-indigo-700 hover:bg-indigo-50 text-sm font-bold leading-none">›</button>
                <span class="text-xs text-gray-500 whitespace-nowrap">6 mo ahead ⏩</span>
                <button @click="goToday" :disabled="sliderValue === 0"
                    class="px-3 py-1 text-xs rounded-full font-medium border transition disabled:opacity-50 disabled:cursor-not-allowed"
                    :class="sliderValue === 0 ? 'bg-gray-100 text-gray-500 border-gray-200' : 'bg-indigo-600 text-white border-indigo-600 hover:bg-indigo-700'">
                    ⏺ This Week
                </button>
                <span class="text-xs font-semibold text-indigo-600 w-20 text-right">
                    {{ sliderValue === 0 ? 'This week' : sliderValue > 0 ? '+' + sliderValue + ' wks' : sliderValue + ' wks' }}
                </span>
            </div>

            <div class="bg-white border rounded-xl overflow-hidden">
                <!-- Single continuous Gantt: 1 header row + 1 row per vehicle spanning the FULL date range -->
                <div class="cal-scroller overflow-auto" style="max-height: calc(100vh - 280px);">
                    <!-- Date header (sticky) -->
                    <div class="grid border-b bg-gray-50 sticky top-0 z-20 shadow-sm"
                         :style="{ gridTemplateColumns: `${LABEL_WIDTH}px repeat(${allDays.length}, minmax(36px, 1fr))` }">
                        <div class="px-2 py-1 text-[10px] font-semibold text-gray-500 uppercase border-r">Vehicle</div>
                        <div v-for="(day, di) in allDays" :key="di"
                             @click="selectedDay = day"
                             class="px-1 py-1 text-xs border-r last:border-r-0 cursor-pointer transition flex flex-col items-center justify-center"
                             :class="[
                                 isToday(day) ? 'bg-amber-100 ring-2 ring-inset ring-amber-500 z-10' :
                                     (isSameMonth(day) ? 'bg-white' : 'bg-gray-100'),
                                 'hover:bg-amber-50'
                             ]">
                            <span class="text-[9px] text-gray-400 leading-none">{{ ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][day.getDay()] }}</span>
                            <span class="font-semibold mt-0.5"
                                  :class="isToday(day) ? 'bg-amber-600 text-white rounded-full w-5 h-5 flex items-center justify-center text-[10px]' :
                                          isSameMonth(day) ? 'text-gray-900' : 'text-gray-400'">
                                {{ day.getDate() }}
                            </span>
                        </div>
                    </div>

                    <!-- Vehicle rows -->
                    <div v-for="(vehicle, vi) in vehicleLanes" :key="vehicle.key"
                         class="grid border-b last:border-b-0 hover:bg-gray-50/40"
                         :style="{ gridTemplateColumns: `${LABEL_WIDTH}px 1fr`, minHeight: ROW_HEIGHT + 'px' }">
                        <!-- Vehicle label -->
                        <div class="px-2 py-1 text-xs border-r flex flex-col justify-center bg-gray-50/60"
                             :class="vehicle.isUnassigned && 'italic text-gray-500'">
                            <span class="font-medium text-gray-900 truncate" :title="vehicle.label">{{ vehicle.label }}</span>
                            <span v-if="vehicle.plate" class="text-[10px] text-gray-500">🪪 {{ vehicle.plate }}</span>
                        </div>

                        <!-- Single continuous strip across all days -->
                        <div class="relative" :style="{ minHeight: ROW_HEIGHT + 'px' }">
                            <!-- Day-cell separators -->
                            <div class="grid absolute inset-0" :style="{ gridTemplateColumns: `repeat(${allDays.length}, minmax(36px, 1fr))` }">
                                <div v-for="(day, di) in allDays" :key="di"
                                     class="border-r last:border-r-0"
                                     :class="[
                                         isToday(day) ? 'bg-amber-50/60' : (isSameMonth(day) ? '' : 'bg-gray-50/40'),
                                     ]"></div>
                            </div>

                            <!-- Event bars positioned by % across the full range -->
                            <template v-for="seg in segmentsByVehicle[vi]" :key="seg.event.id">
                                <Link :href="route('rental.reservations.show', seg.event.id)"
                                      class="event-stripe absolute block transition-all duration-150 cursor-pointer overflow-hidden"
                                      :class="[
                                        statusBg(seg.event.status),
                                        seg.continuesLeft  ? 'rounded-l-none' : 'rounded-l',
                                        seg.continuesRight ? 'rounded-r-none' : 'rounded-r',
                                      ]"
                                      :style="{
                                          left:  `calc(${seg.leftPct}% + 1px)`,
                                          width: `calc(${seg.widthPct}% - 2px)`,
                                          top:   '3px',
                                          height: (ROW_HEIGHT - 6) + 'px',
                                      }">
                                    <span class="stripe-label block text-white px-1.5 truncate font-medium leading-none pt-1">
                                        {{ seg.event.customer_name || seg.event.title }}
                                    </span>
                                    <span class="event-popover absolute hidden left-0 top-full mt-1 z-40 w-64 bg-white text-gray-900 text-xs rounded-lg shadow-xl border p-2.5 pointer-events-none">
                                        <span class="block font-semibold text-sm leading-tight mb-1">{{ seg.event.title }}</span>
                                        <span class="block text-[11px] text-gray-500">
                                            {{ new Date(seg.event.start).toLocaleDateString() }} → {{ new Date(seg.event.end).toLocaleDateString() }}
                                        </span>
                                        <span class="inline-block mt-1.5 text-[10px] px-1.5 py-0.5 rounded text-white capitalize" :class="statusBg(seg.event.status)">
                                            {{ seg.event.status }}
                                        </span>
                                        <span v-if="seg.event.location_name" class="block text-[10px] text-gray-500 mt-1">📍 {{ seg.event.location_name }}</span>
                                    </span>
                                </Link>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Legend -->
            <div class="mt-4 flex flex-wrap items-center gap-3 text-xs text-gray-600">
                <span class="font-medium">Status:</span>
                <span v-for="s in ['open','rental','confirmed','pending','completed','cancelled']" :key="s" class="flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded" :class="statusBg(s)"></span>{{ s }}
                </span>
                <span class="ml-auto text-gray-400">{{ events?.length || 0 }} reservations</span>
            </div>
        </div>

        <!-- Day modal -->
        <div v-if="selectedDay"
             @click.self="closeModal"
             class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-2xl shadow-xl max-w-xl w-full max-h-[85vh] flex flex-col">
                <header class="flex items-center justify-between p-5 border-b">
                    <div>
                        <h3 class="font-semibold text-lg">{{ selectedDay.toLocaleDateString(undefined, { weekday:'long', month:'long', day:'numeric', year:'numeric' }) }}</h3>
                        <p class="text-xs text-gray-500 mt-0.5">
                            {{ selectedEvents.length }} booked · {{ availableOnSelectedDay.length }} available
                        </p>
                    </div>
                    <button @click="closeModal" class="text-gray-400 hover:text-gray-700 text-2xl leading-none">×</button>
                </header>

                <!-- Switch -->
                <div class="px-5 pt-3">
                    <div class="inline-flex border rounded-lg overflow-hidden text-sm w-full">
                        <button @click="modalView = 'booked'"
                            class="flex-1 px-4 py-2 transition font-medium"
                            :class="modalView === 'booked' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'">
                            📋 Booked ({{ selectedEvents.length }})
                        </button>
                        <button @click="modalView = 'available'"
                            class="flex-1 px-4 py-2 border-l transition font-medium"
                            :class="modalView === 'available' ? 'bg-emerald-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'">
                            ✅ Available to Assign ({{ availableOnSelectedDay.length }})
                        </button>
                    </div>
                </div>

                <div class="p-4 space-y-2 overflow-y-auto">
                    <!-- Booked view -->
                    <template v-if="modalView === 'booked'">
                        <Link v-for="ev in selectedEvents" :key="ev.id"
                              :href="route('rental.reservations.show', ev.id)"
                              class="flex items-center justify-between p-3 rounded-lg border hover:border-indigo-400 hover:shadow-sm transition">
                            <div class="flex items-center gap-3">
                                <span class="w-3 h-3 rounded-full" :class="statusBg(ev.status)"></span>
                                <div>
                                    <div class="font-medium text-sm text-gray-900">{{ ev.title }}</div>
                                    <div class="text-xs text-gray-500">{{ ev.start?.split('T')[0] }} → {{ ev.end?.split('T')[0] }}</div>
                                </div>
                            </div>
                            <span class="text-xs px-2 py-0.5 rounded-full text-white capitalize" :class="statusBg(ev.status)">{{ ev.status }}</span>
                        </Link>
                        <p v-if="!selectedEvents.length" class="text-sm text-gray-400 text-center py-6">No reservations on this day.</p>
                    </template>

                    <!-- Available view -->
                    <template v-if="modalView === 'available'">
                        <Link v-for="v in availableOnSelectedDay" :key="v.id"
                              :href="route('rental.reservations.create', { vehicle_id: v.id, pickup_date: ymdSafe(selectedDay) })"
                              class="flex items-center justify-between p-3 rounded-lg border hover:border-emerald-400 hover:bg-emerald-50/40 hover:shadow-sm transition">
                            <div class="flex items-center gap-3">
                                <span class="text-2xl">🚗</span>
                                <div>
                                    <div class="font-medium text-sm text-gray-900">{{ v.label }}</div>
                                    <div class="text-xs text-gray-500">
                                        <span v-if="v.plate">🪪 {{ v.plate }}</span>
                                        <span v-if="v.class" class="ml-2">{{ v.class }}</span>
                                    </div>
                                </div>
                            </div>
                            <span class="text-xs px-2.5 py-1 rounded-full bg-emerald-600 text-white font-medium">+ Assign</span>
                        </Link>
                        <p v-if="!availableOnSelectedDay.length" class="text-sm text-gray-400 text-center py-6">No vehicles available on this day.</p>
                    </template>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.event-stripe { z-index: 1; }
.event-stripe:hover {
    z-index: 50;
    height: 22px !important;
    margin-top: -6px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.18);
    filter: brightness(1.1);
}
.event-stripe:hover .stripe-label { font-size: 11px; line-height: 22px; }
.event-stripe:hover .event-popover { display: block; }

/* Tiny readable label inside the 9px stripe */
.stripe-label {
    font-size: 8px;
    line-height: 9px;
    letter-spacing: 0.01em;
    -webkit-font-smoothing: antialiased;
}


</style>

<style>
/* Global (un-scoped) — webkit pseudo-elements need to be unscoped to apply */
.cal-scroller {
    scrollbar-gutter: stable;
    scrollbar-width: auto;
    scrollbar-color: #4f46e5 #e2e8f0;
}
.cal-scroller::-webkit-scrollbar {
    width: 14px !important;
    height: 14px !important;
    background: #e2e8f0;
    -webkit-appearance: none;
    display: block !important;
}
.cal-scroller::-webkit-scrollbar-track {
    background: #e2e8f0;
    border-left: 1px solid #cbd5e1;
}
.cal-scroller::-webkit-scrollbar-thumb {
    background-color: #6366f1;
    border-radius: 7px;
    border: 2px solid #e2e8f0;
    min-height: 30px;
}
.cal-scroller::-webkit-scrollbar-thumb:hover {
    background-color: #4f46e5;
}
.cal-scroller::-webkit-scrollbar-corner {
    background: #e2e8f0;
}

/* Calendar time slider */
.cal-slider {
    -webkit-appearance: none;
    appearance: none;
    height: 6px;
    background: linear-gradient(to right, #e0e7ff 0%, #6366f1 50%, #e0e7ff 100%);
    border-radius: 3px;
    outline: none;
}
.cal-slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: #4f46e5;
    border: 3px solid white;
    box-shadow: 0 2px 6px rgba(0,0,0,0.2);
    cursor: pointer;
}
.cal-slider::-moz-range-thumb {
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: #4f46e5;
    border: 3px solid white;
    box-shadow: 0 2px 6px rgba(0,0,0,0.2);
    cursor: pointer;
}
</style>
