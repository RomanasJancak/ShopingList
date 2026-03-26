<script setup>
import { onMounted, ref } from 'vue';

const props = defineProps({
    listId: { type: String, required: true },
});

const list = ref(null);
const loading = ref(true);
const error = ref('');
const removing = ref(new Set());

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

const removeItem = async (itemId) => {
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

const pictureUrl = (item) => {
    return item.product?.picture ? `/storage/${item.product.picture}` : null;
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
        <div class="bg-white border-b border-gray-200 px-4 py-3 flex items-center gap-3 sticky top-0 z-10">
            <a href="/shopping-lists" class="text-gray-500 hover:text-gray-800 flex items-center gap-1 text-sm font-medium">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
                Lists
            </a>
            <div class="flex-1 min-w-0">
                <h1 class="text-lg font-bold text-gray-900 truncate leading-tight">
                    {{ list?.name ?? '…' }}
                </h1>
                <p v-if="list?.description" class="text-xs text-gray-500 truncate">{{ list.description }}</p>
            </div>
            <span class="text-sm text-gray-400 shrink-0">
                {{ list?.items?.length ?? 0 }} item{{ (list?.items?.length ?? 0) !== 1 ? 's' : '' }}
            </span>
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
        <div v-else-if="!list?.items?.length" class="flex flex-col items-center justify-center h-64 text-gray-400 gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            </svg>
            <p class="text-sm font-medium">No items in this list</p>
        </div>

        <!-- Item cards: ~2 per viewport on mobile -->
        <div v-else class="flex flex-col gap-4 px-4 py-4 max-w-lg mx-auto">
            <div
                v-for="item in list.items"
                :key="item.id"
                class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden flex flex-col"
                style="min-height: 44vh"
            >
                <!-- Picture or coloured placeholder -->
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

                <!-- Info + actions -->
                <div class="px-4 pt-3 pb-4 flex flex-col gap-3">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900 leading-tight">{{ item.product?.name }}</h2>
                        <p class="text-base text-gray-500 font-medium mt-0.5">{{ formatQuantity(item) }}</p>
                        <p v-if="item.notes" class="text-sm text-gray-400 mt-1 italic">{{ item.notes }}</p>
                    </div>

                    <!-- Buttons -->
                    <div class="grid grid-cols-2 gap-3">
                        <!-- Green Add button -->
                        <button
                            type="button"
                            :disabled="removing.has(item.id)"
                            class="flex items-center justify-center gap-2 bg-green-500 hover:bg-green-600 active:bg-green-700 disabled:opacity-50 text-white font-semibold text-base rounded-xl py-3 transition-colors"
                            @click="removeItem(item.id)"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                            Add
                        </button>

                        <!-- × Remove button -->
                        <button
                            type="button"
                            :disabled="removing.has(item.id)"
                            class="flex items-center justify-center gap-2 bg-gray-100 hover:bg-gray-200 active:bg-gray-300 disabled:opacity-50 text-gray-700 font-semibold text-base rounded-xl py-3 transition-colors"
                            @click="removeItem(item.id)"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            Skip
                        </button>
                    </div>
                </div>
            </div>

            <!-- Done state shown after all items removed -->
            <div v-if="list && list.items.length === 0" class="flex flex-col items-center justify-center py-16 text-gray-400 gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-14 h-14 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="text-base font-semibold text-green-500">All done!</p>
                <a href="/shopping-lists" class="mt-2 text-sm text-blue-500 hover:underline">Back to lists</a>
            </div>
        </div>
    </div>
</template>
