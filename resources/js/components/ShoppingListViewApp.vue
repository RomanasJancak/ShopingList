<script setup>
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    listId: { type: String, required: true },
    showProductPictures: { type: Boolean, default: true },
});

const list = ref(null);
const loading = ref(true);
const error = ref('');
const removing = ref(new Set());
const menuOpen = ref(false);

const activeItems = computed(() => {
    if (!list.value?.items) {
        return [];
    }

    return list.value.items.filter((item) => !item.is_skipped);
});

const skippedItems = computed(() => {
    if (!list.value?.items) {
        return [];
    }

    return list.value.items.filter((item) => item.is_skipped);
});

const loadList = async () => {
    loading.value = true;
    error.value = '';

    try {
        const response = await window.axios.get(`/api/shopping-lists/${props.listId}`);
        list.value = response.data;
    } catch (e) {
        error.value = e.response?.data?.message || 'Failed to load shopping list.';
    } finally {
        loading.value = false;
    }
};

const setItemSkipStateLocally = (itemId, isSkipped) => {
    if (!list.value?.items) {
        return;
    }

    list.value.items = list.value.items.map((item) => {
        if (item.id !== itemId) {
            return item;
        }

        return {
            ...item,
            is_skipped: isSkipped,
        };
    });
};

const updateItemSkipState = async (itemId, isSkipped) => {
    const item = list.value?.items?.find((candidate) => candidate.id === itemId);
    if (!item) {
        return;
    }

    await window.axios.put(`/api/shopping-lists/${props.listId}/items/${itemId}`, {
        product_id: item.product_id,
        quantity: Number(item.quantity),
        notes: item.notes,
        is_completed: Boolean(item.is_completed),
        is_skipped: isSkipped,
    });

    setItemSkipStateLocally(itemId, isSkipped);
};

const completeItem = async (itemId) => {
    if (removing.value.has(itemId)) {
        return;
    }

    removing.value = new Set([...removing.value, itemId]);

    try {
        await window.axios.delete(`/api/shopping-lists/${props.listId}/items/${itemId}`);
        list.value.items = list.value.items.filter((item) => item.id !== itemId);
    } catch (e) {
        error.value = e.response?.data?.message || 'Failed to remove item.';
    } finally {
        const next = new Set(removing.value);
        next.delete(itemId);
        removing.value = next;
    }
};

const skipItem = async (itemId) => {
    if (removing.value.has(itemId)) {
        return;
    }

    error.value = '';
    removing.value = new Set([...removing.value, itemId]);

    try {
        await updateItemSkipState(itemId, true);
    } catch (e) {
        error.value = e.response?.data?.message || 'Failed to skip item.';
    } finally {
        const next = new Set(removing.value);
        next.delete(itemId);
        removing.value = next;
    }
};

const returnSkippedItem = async (itemId) => {
    if (removing.value.has(itemId)) {
        return;
    }

    error.value = '';
    removing.value = new Set([...removing.value, itemId]);

    try {
        await updateItemSkipState(itemId, false);
    } catch (e) {
        error.value = e.response?.data?.message || 'Failed to return skipped item.';
    } finally {
        const next = new Set(removing.value);
        next.delete(itemId);
        removing.value = next;
    }
};

const returningAllSkipped = ref(false);

const returnAllSkippedItems = async () => {
    if (!skippedItems.value.length || returningAllSkipped.value) {
        return;
    }

    returningAllSkipped.value = true;
    error.value = '';

    try {
        await window.axios.post(`/api/shopping-lists/${props.listId}/items/return-skipped`);

        if (list.value?.items) {
            list.value.items = list.value.items.map((item) => ({
                ...item,
                is_skipped: false,
            }));
        }
    } catch (e) {
        error.value = e.response?.data?.message || 'Failed to return skipped items.';
    } finally {
        returningAllSkipped.value = false;
    }
};

const pictureUrl = (item) => {
    if (!props.showProductPictures) {
        return null;
    }

    return item.product?.picture_url ?? null;
};

const formatQuantity = (item) => {
    const qty = parseFloat(item.quantity);
    const formatted = qty % 1 === 0 ? qty.toFixed(0) : qty.toString();
    return `${formatted} ${item.product?.quantity_type ?? ''}`.trim();
};

onMounted(loadList);
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <!-- Header bar -->
        <div class="bg-white border-b border-gray-200 px-4 py-3 flex items-center justify-between sticky top-0 z-20">
            <div class="min-w-0 pr-3">
                <h1 class="text-lg font-bold text-gray-900 truncate leading-tight">
                    {{ list?.name ?? '…' }}
                </h1>
                <p v-if="list?.description" class="text-xs text-gray-500 truncate">{{ list.description }}</p>
            </div>

            <button
                type="button"
                class="inline-flex items-center justify-center rounded-md border border-gray-200 bg-white p-2 text-gray-700 hover:bg-gray-50"
                @click="menuOpen = !menuOpen"
                aria-label="Toggle menu"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
        </div>

        <div v-if="menuOpen" class="absolute right-4 top-16 z-30 w-56 rounded-lg border border-gray-200 bg-white shadow-lg">
            <div class="px-4 py-2 text-xs text-gray-500 border-b border-gray-100">
                {{ activeItems.length }} item{{ activeItems.length !== 1 ? 's' : '' }}
            </div>
            <a href="/shopping-lists" class="block px-4 py-3 text-sm text-gray-800 hover:bg-gray-50">All Shopping Lists</a>
            <a href="/profile" class="block px-4 py-3 text-sm text-gray-800 hover:bg-gray-50">My Profile</a>
            <a href="/families" class="block px-4 py-3 text-sm text-gray-800 hover:bg-gray-50">Families</a>
            <a href="/docs" class="block px-4 py-3 text-sm text-gray-800 hover:bg-gray-50">Docs</a>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex items-center justify-center h-64">
            <div class="w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
        </div>

        <!-- Error -->
        <div v-else-if="error" class="mx-4 mt-6 bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm">
            {{ error }}
        </div>

        <!-- Empty -->
        <div v-else-if="activeItems.length === 0 && skippedItems.length === 0" class="flex flex-col items-center justify-center h-64 text-gray-400 gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            </svg>
            <p class="text-sm font-medium">No items in this list</p>
        </div>

        <!-- Item cards: image mode stays visual, no-image mode becomes a compact list -->
        <div v-else class="flex flex-col gap-2 px-3 py-3 max-w-lg mx-auto">
            <div v-if="activeItems.length === 0 && skippedItems.length" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                All active items are skipped. Use "Return skipped" to bring them back.
            </div>

            <div
                v-for="item in activeItems"
                :key="item.id"
                class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden"
                :class="showProductPictures ? 'flex flex-col' : 'p-3'"
                :style="showProductPictures ? 'min-height: 44vh' : ''"
            >
                <template v-if="showProductPictures">
                    <div class="flex-1 flex items-center justify-center bg-gray-50 overflow-hidden">
                        <img
                            v-if="pictureUrl(item)"
                            :src="pictureUrl(item)"
                            :alt="item.product?.name"
                            class="w-full h-full object-cover"
                        >
                        <div v-else class="flex flex-col items-center justify-center gap-2 text-gray-300 p-6">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-16 h-16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span class="text-xs">No image</span>
                        </div>
                    </div>

                    <div class="px-4 pt-3 pb-4 flex flex-col gap-3">
                        <div>
                            <h2 class="text-xl font-bold text-gray-900 leading-tight">{{ item.product?.name }}</h2>
                            <p class="text-base text-gray-500 font-medium mt-0.5">{{ formatQuantity(item) }}</p>
                            <p v-if="item.notes" class="text-sm text-gray-400 mt-1 italic">{{ item.notes }}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <button
                                type="button"
                                :disabled="removing.has(item.id)"
                                class="flex items-center justify-center gap-2 bg-green-500 hover:bg-green-600 active:bg-green-700 disabled:opacity-50 text-white font-semibold text-base rounded-xl py-3 transition-colors"
                                @click="completeItem(item.id)"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                Add
                            </button>

                            <button
                                type="button"
                                :disabled="removing.has(item.id)"
                                class="flex items-center justify-center gap-2 bg-gray-100 hover:bg-gray-200 active:bg-gray-300 disabled:opacity-50 text-gray-700 font-semibold text-base rounded-xl py-3 transition-colors"
                                @click="skipItem(item.id)"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                                Skip
                            </button>
                        </div>
                    </div>
                </template>

                <template v-else>
                    <div class="flex items-stretch gap-2">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2">
                                <h2 class="text-sm font-semibold text-gray-900 truncate leading-tight">{{ item.product?.name }}</h2>
                                <span class="shrink-0 text-[11px] font-medium text-gray-600">{{ formatQuantity(item) }}</span>
                            </div>
                            <p v-if="item.notes" class="mt-0.5 text-xs text-gray-500 italic line-clamp-2">{{ item.notes }}</p>
                        </div>

                        <div class="w-10 shrink-0 flex flex-col gap-1">
                            <button
                                type="button"
                                :disabled="removing.has(item.id)"
                                class="h-8 rounded-md bg-green-500 text-white transition-colors hover:bg-green-600 active:bg-green-700 disabled:opacity-50"
                                @click="completeItem(item.id)"
                                aria-label="Add item"
                                title="Add"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                            </button>

                            <button
                                type="button"
                                :disabled="removing.has(item.id)"
                                class="h-8 rounded-md bg-gray-100 text-gray-700 transition-colors hover:bg-gray-200 active:bg-gray-300 disabled:opacity-50"
                                @click="skipItem(item.id)"
                                aria-label="Skip item"
                                title="Skip"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </template>
            </div>

            <div v-if="skippedItems.length" class="rounded-xl border border-amber-200 bg-white p-3">
                <div class="mb-2 flex items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold text-amber-900">Skipped ({{ skippedItems.length }})</h3>
                    <button
                        type="button"
                        class="rounded-md bg-amber-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-amber-700"
                        :disabled="returningAllSkipped"
                        @click="returnAllSkippedItems"
                    >
                        {{ returningAllSkipped ? 'Returning...' : 'Return skipped' }}
                    </button>
                </div>

                <div class="space-y-1.5">
                    <div
                        v-for="item in skippedItems"
                        :key="`skipped-${item.id}`"
                        class="flex items-center justify-between gap-2 rounded-lg border border-amber-100 bg-amber-50 px-2.5 py-2"
                    >
                        <div class="min-w-0 flex items-center gap-2">
                            <p class="truncate text-xs font-medium text-amber-900">{{ item.product?.name }}</p>
                            <span class="shrink-0 text-[11px] text-amber-700">{{ formatQuantity(item) }}</span>
                        </div>

                        <button
                            type="button"
                            :disabled="removing.has(item.id)"
                            class="rounded-md border border-amber-300 bg-white px-2 py-1 text-[11px] font-semibold text-amber-800 hover:bg-amber-100"
                            @click="returnSkippedItem(item.id)"
                        >
                            Return
                        </button>
                    </div>
                </div>
            </div>

            <!-- Done state shown after all items removed -->
            <div v-if="activeItems.length === 0 && skippedItems.length === 0" class="flex flex-col items-center justify-center py-16 text-gray-400 gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-14 h-14 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="text-base font-semibold text-green-500">All done!</p>
                <a href="/shopping-lists" class="mt-2 text-sm text-blue-500 hover:underline">Back to lists</a>
            </div>
        </div>
    </div>
</template>
