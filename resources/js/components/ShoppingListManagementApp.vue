<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';

const shoppingLists = ref([]);
const products = ref([]);
const users = ref([]);
const families = ref([]);
const selectedListId = ref('');
const selectedList = ref(null);
const availableFamilyMembers = ref([]);
const loading = ref(false);
const error = ref('');

const listForm = reactive({
    name: '',
    description: '',
});

const listEditForm = reactive({
    name: '',
    description: '',
});

const directShareForm = reactive({
    userId: '',
    permission: 'view',
});

const familyShareForm = reactive({
    familyId: '',
    permission: 'view',
});

const familyMemberForm = reactive({
    familyId: '',
    userId: '',
    permission: 'view',
});

const productForm = reactive({
    name: '',
    pictureFile: null,
    description: '',
    quantityType: 'pcs',
});

const productPictureMaxBytes = 4 * 1024 * 1024;

const itemForm = reactive({
    productId: '',
    quantity: '',
    notes: '',
    isCompleted: false,
});

const directSharePermissionEdits = reactive({});
const familySharePermissionEdits = reactive({});
const familyMemberPermissionEdits = reactive({});

const editingProductId = ref(null);
const editingItemId = ref(null);

const effectivePermission = computed(() => selectedList.value?.effective_permission || null);
const canEditList = computed(() => ['owner', 'edit'].includes(effectivePermission.value));
const canManageShares = computed(() => effectivePermission.value === 'owner');

const resetSelectedListState = () => {
    selectedList.value = null;
    availableFamilyMembers.value = [];
    listEditForm.name = '';
    listEditForm.description = '';
    resetItemForm();
};

const loadUsers = async () => {
    const response = await window.axios.get('/api/users');
    users.value = response.data;
};

const loadFamilies = async () => {
    const response = await window.axios.get('/api/families');
    families.value = response.data;
};

const loadProducts = async () => {
    const response = await window.axios.get('/api/products');
    products.value = response.data;
};

const loadShoppingLists = async () => {
    const response = await window.axios.get('/api/shopping-lists');
    shoppingLists.value = response.data;

    if (!selectedListId.value && shoppingLists.value.length > 0) {
        selectedListId.value = String(shoppingLists.value[0].id);
        await loadSelectedList();
        return;
    }

    if (selectedListId.value) {
        const selectedExists = shoppingLists.value.some((list) => String(list.id) === selectedListId.value);

        if (!selectedExists) {
            selectedListId.value = shoppingLists.value.length ? String(shoppingLists.value[0].id) : '';
        }

        await loadSelectedList();
    }
};

const loadSelectedList = async () => {
    if (!selectedListId.value) {
        resetSelectedListState();
        return;
    }

    const response = await window.axios.get(`/api/shopping-lists/${selectedListId.value}`);
    selectedList.value = response.data;
    listEditForm.name = response.data.name;
    listEditForm.description = response.data.description || '';

    Object.keys(directSharePermissionEdits).forEach((key) => {
        delete directSharePermissionEdits[key];
    });

    Object.keys(familySharePermissionEdits).forEach((key) => {
        delete familySharePermissionEdits[key];
    });

    Object.keys(familyMemberPermissionEdits).forEach((key) => {
        delete familyMemberPermissionEdits[key];
    });

    (response.data.user_shares || []).forEach((share) => {
        directSharePermissionEdits[share.user_id] = share.permission;
    });

    (response.data.family_shares || []).forEach((share) => {
        familySharePermissionEdits[share.family_id] = share.permission;
    });

    (response.data.family_member_shares || []).forEach((share) => {
        familyMemberPermissionEdits[`${share.family_id}-${share.user_id}`] = share.permission;
    });
};

const loadFamilyMembers = async (familyId) => {
    if (!familyId) {
        availableFamilyMembers.value = [];
        return;
    }

    const response = await window.axios.get(`/api/families/${familyId}`);
    const uniqueMembers = new Map();

    (response.data.user_roles || []).forEach((assignment) => {
        if (assignment.user) {
            uniqueMembers.set(assignment.user.id, assignment.user);
        }
    });

    if (response.data.owner) {
        uniqueMembers.set(response.data.owner.id, response.data.owner);
    }

    availableFamilyMembers.value = Array.from(uniqueMembers.values());
};

const createShoppingList = async () => {
    error.value = '';

    try {
        await window.axios.post('/api/shopping-lists', {
            name: listForm.name,
            description: listForm.description,
        });

        listForm.name = '';
        listForm.description = '';
        await loadShoppingLists();
    } catch (requestError) {
        error.value = requestError.response?.data?.message || 'Unable to create shopping list.';
    }
};

const updateShoppingList = async () => {
    if (!selectedListId.value) {
        return;
    }

    error.value = '';

    try {
        await window.axios.put(`/api/shopping-lists/${selectedListId.value}`, {
            name: listEditForm.name,
            description: listEditForm.description,
        });

        await loadShoppingLists();
    } catch (requestError) {
        error.value = requestError.response?.data?.message || 'Unable to update shopping list.';
    }
};

const deleteShoppingList = async () => {
    if (!selectedListId.value || !window.confirm('Delete this shopping list?')) {
        return;
    }

    error.value = '';

    try {
        await window.axios.delete(`/api/shopping-lists/${selectedListId.value}`);
        selectedListId.value = '';
        await loadShoppingLists();
    } catch (requestError) {
        error.value = requestError.response?.data?.message || 'Unable to delete shopping list.';
    }
};

const resetProductForm = () => {
    productForm.name = '';
    productForm.pictureFile = null;
    productForm.description = '';
    productForm.quantityType = 'pcs';
    editingProductId.value = null;
};

const startEditProduct = (product) => {
    productForm.name = product.name;
    productForm.pictureFile = null;
    productForm.description = product.description || '';
    productForm.quantityType = product.quantity_type;
    editingProductId.value = product.id;
};

const formatBytes = (bytes) => {
    if (!Number.isFinite(bytes) || bytes < 0) {
        return 'unknown size';
    }

    if (bytes < 1024) {
        return `${bytes} B`;
    }

    const kb = bytes / 1024;
    if (kb < 1024) {
        return `${kb.toFixed(1)} KB`;
    }

    const mb = kb / 1024;
    return `${mb.toFixed(2)} MB`;
};

const onProductPictureSelected = (event) => {
    const [file] = event.target.files || [];
    if (!file) {
        productForm.pictureFile = null;
        return;
    }

    if (file.size > productPictureMaxBytes) {
        const selectedSize = formatBytes(file.size);
        const allowedSize = formatBytes(productPictureMaxBytes);

        console.error('Selected product picture exceeds allowed size.', {
            name: file.name,
            type: file.type,
            size: file.size,
            selectedSize,
            allowedSize,
        });

        error.value = `Picture is too large (${selectedSize}). Maximum allowed size is ${allowedSize}.`;
        productForm.pictureFile = null;
        event.target.value = '';
        return;
    }

    error.value = '';
    productForm.pictureFile = file;
};

const submitProduct = async () => {
    error.value = '';

    try {
        const payload = new FormData();
        payload.append('name', productForm.name);
        payload.append('description', productForm.description || '');
        payload.append('quantity_type', productForm.quantityType);

        if (productForm.pictureFile) {
            payload.append('picture', productForm.pictureFile);
        }

        if (editingProductId.value) {
            payload.append('_method', 'PUT');
            await window.axios.post(`/api/products/${editingProductId.value}`, payload, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
            });
        } else {
            await window.axios.post('/api/products', payload, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
            });
        }

        resetProductForm();
        await loadProducts();
    } catch (requestError) {
        const responseData = requestError.response?.data;
        const pictureValidationError = responseData?.errors?.picture?.[0];

        console.error('Failed to save product.', {
            message: requestError.message,
            status: requestError.response?.status,
            statusText: requestError.response?.statusText,
            responseData,
            validationErrors: responseData?.errors,
            selectedPicture: productForm.pictureFile
                ? {
                    name: productForm.pictureFile.name,
                    type: productForm.pictureFile.type,
                    size: productForm.pictureFile.size,
                }
                : null,
        });

        error.value = pictureValidationError || responseData?.message || 'Failed to save product.';
    }
};

const deleteProduct = async (productId) => {
    if (!window.confirm('Delete this product?')) {
        return;
    }

    error.value = '';

    try {
        await window.axios.delete(`/api/products/${productId}`);
        await loadProducts();

        if (itemForm.productId === String(productId)) {
            itemForm.productId = '';
        }
    } catch (requestError) {
        error.value = requestError.response?.data?.message || 'Unable to delete product.';
    }
};

const resetItemForm = () => {
    itemForm.productId = '';
    itemForm.quantity = '';
    itemForm.notes = '';
    itemForm.isCompleted = false;
    editingItemId.value = null;
};

const startEditItem = (item) => {
    itemForm.productId = String(item.product_id);
    itemForm.quantity = item.quantity;
    itemForm.notes = item.notes || '';
    itemForm.isCompleted = Boolean(item.is_completed);
    editingItemId.value = item.id;
};

const submitItem = async () => {
    if (!selectedListId.value) {
        return;
    }

    error.value = '';

    try {
        const payload = {
            product_id: Number(itemForm.productId),
            quantity: Number(itemForm.quantity),
            notes: itemForm.notes || null,
            is_completed: itemForm.isCompleted,
        };

        if (editingItemId.value) {
            await window.axios.put(`/api/shopping-lists/${selectedListId.value}/items/${editingItemId.value}`, payload);
        } else {
            await window.axios.post(`/api/shopping-lists/${selectedListId.value}/items`, payload);
        }

        resetItemForm();
        await loadSelectedList();
    } catch (requestError) {
        error.value = requestError.response?.data?.message || 'Unable to save item.';
    }
};

const deleteItem = async (itemId) => {
    if (!window.confirm('Delete this item from the shopping list?')) {
        return;
    }

    error.value = '';

    try {
        await window.axios.delete(`/api/shopping-lists/${selectedListId.value}/items/${itemId}`);
        await loadSelectedList();
    } catch (requestError) {
        error.value = requestError.response?.data?.message || 'Unable to delete item.';
    }
};

const shareWithUser = async () => {
    if (!selectedListId.value) {
        return;
    }

    error.value = '';

    try {
        await window.axios.post(`/api/shopping-lists/${selectedListId.value}/users`, {
            user_id: Number(directShareForm.userId),
            permission: directShareForm.permission,
        });

        directShareForm.userId = '';
        directShareForm.permission = 'view';
        await loadSelectedList();
    } catch (requestError) {
        error.value = requestError.response?.data?.message || 'Unable to share with user.';
    }
};

const updateDirectShare = async (userId) => {
    error.value = '';

    try {
        await window.axios.put(`/api/shopping-lists/${selectedListId.value}/users/${userId}`, {
            permission: directSharePermissionEdits[userId] || 'view',
        });

        await loadSelectedList();
    } catch (requestError) {
        error.value = requestError.response?.data?.message || 'Unable to update direct share.';
    }
};

const removeDirectShare = async (userId) => {
    if (!window.confirm('Remove this direct share?')) {
        return;
    }

    error.value = '';

    try {
        await window.axios.delete(`/api/shopping-lists/${selectedListId.value}/users/${userId}`);
        await loadSelectedList();
    } catch (requestError) {
        error.value = requestError.response?.data?.message || 'Unable to remove direct share.';
    }
};

const shareWithFamily = async () => {
    error.value = '';

    try {
        await window.axios.post(`/api/shopping-lists/${selectedListId.value}/families`, {
            family_id: Number(familyShareForm.familyId),
            permission: familyShareForm.permission,
        });

        familyShareForm.familyId = '';
        familyShareForm.permission = 'view';
        await loadSelectedList();
    } catch (requestError) {
        error.value = requestError.response?.data?.message || 'Unable to share with family.';
    }
};

const updateFamilyShare = async (familyId) => {
    error.value = '';

    try {
        await window.axios.put(`/api/shopping-lists/${selectedListId.value}/families/${familyId}`, {
            permission: familySharePermissionEdits[familyId] || 'view',
        });

        await loadSelectedList();
    } catch (requestError) {
        error.value = requestError.response?.data?.message || 'Unable to update family share.';
    }
};

const removeFamilyShare = async (familyId) => {
    if (!window.confirm('Remove this family share?')) {
        return;
    }

    error.value = '';

    try {
        await window.axios.delete(`/api/shopping-lists/${selectedListId.value}/families/${familyId}`);
        await loadSelectedList();
    } catch (requestError) {
        error.value = requestError.response?.data?.message || 'Unable to remove family share.';
    }
};

const shareWithFamilyMember = async () => {
    error.value = '';

    try {
        await window.axios.post(`/api/shopping-lists/${selectedListId.value}/families/${familyMemberForm.familyId}/members`, {
            user_id: Number(familyMemberForm.userId),
            permission: familyMemberForm.permission,
        });

        familyMemberForm.userId = '';
        familyMemberForm.permission = 'view';
        await loadSelectedList();
    } catch (requestError) {
        error.value = requestError.response?.data?.message || 'Unable to share with family member.';
    }
};

const updateFamilyMemberShare = async (familyId, userId) => {
    error.value = '';

    try {
        await window.axios.put(`/api/shopping-lists/${selectedListId.value}/families/${familyId}/members/${userId}`, {
            permission: familyMemberPermissionEdits[`${familyId}-${userId}`] || 'view',
        });

        await loadSelectedList();
    } catch (requestError) {
        error.value = requestError.response?.data?.message || 'Unable to update family member share.';
    }
};

const removeFamilyMemberShare = async (familyId, userId) => {
    if (!window.confirm('Remove this family member share?')) {
        return;
    }

    error.value = '';

    try {
        await window.axios.delete(`/api/shopping-lists/${selectedListId.value}/families/${familyId}/members/${userId}`);
        await loadSelectedList();
    } catch (requestError) {
        error.value = requestError.response?.data?.message || 'Unable to remove family member share.';
    }
};

watch(
    () => familyMemberForm.familyId,
    async (familyId) => {
        familyMemberForm.userId = '';
        await loadFamilyMembers(familyId);
    }
);

onMounted(async () => {
    loading.value = true;
    error.value = '';

    try {
        await Promise.all([loadUsers(), loadFamilies(), loadProducts()]);
        await loadShoppingLists();
    } catch {
        error.value = 'Failed to load shopping list data.';
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="mx-auto max-w-6xl p-6">
        <h1 class="text-2xl font-bold mb-6">Shopping Lists</h1>

        <p v-if="error" class="text-red-700 mb-4">{{ error }}</p>
        <p v-if="loading" class="text-gray-600">Loading shopping lists...</p>

        <div class="bg-white p-4 rounded-lg border border-gray-200 mb-6">
            <h2 class="text-lg font-semibold mb-4">Create Shopping List</h2>

            <form class="grid gap-4 md:grid-cols-3" @submit.prevent="createShoppingList">
                <input v-model="listForm.name" type="text" class="w-full border border-gray-300 rounded px-3 py-2" placeholder="Weekly groceries">
                <input v-model="listForm.description" type="text" class="w-full border border-gray-300 rounded px-3 py-2" placeholder="Optional description">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Create List</button>
            </form>
        </div>

        <div class="bg-white p-4 rounded-lg border border-gray-200 mb-6">
            <h2 class="text-lg font-semibold mb-4">Products</h2>

            <form class="grid gap-4 md:grid-cols-4 mb-4" @submit.prevent="submitProduct">
                <input v-model="productForm.name" type="text" class="w-full border border-gray-300 rounded px-3 py-2" placeholder="Product name">
                <input type="file" accept="image/*" class="w-full border border-gray-300 rounded px-3 py-2" @change="onProductPictureSelected">
                <input v-model="productForm.description" type="text" class="w-full border border-gray-300 rounded px-3 py-2" placeholder="Description">
                <select v-model="productForm.quantityType" class="w-full border border-gray-300 rounded px-3 py-2">
                    <option value="pcs">pcs</option>
                    <option value="kg">kg</option>
                </select>
                <div class="md:col-span-4 flex gap-2">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                        {{ editingProductId ? 'Update Product' : 'Create Product' }}
                    </button>
                    <button v-if="editingProductId" type="button" class="bg-gray-200 text-gray-800 px-4 py-2 rounded hover:bg-gray-300" @click="resetProductForm">
                        Cancel
                    </button>
                </div>
            </form>

            <div v-if="products.length" class="space-y-2">
                <div v-for="product in products" :key="product.id" class="border border-gray-200 rounded p-3 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p class="font-medium">{{ product.name }}</p>
                        <img v-if="product.picture_url" :src="product.picture_url" alt="Product image" class="w-16 h-16 object-cover rounded border border-gray-200 my-1">
                        <p class="text-sm text-gray-600">{{ product.description || 'No description' }}</p>
                        <p class="text-sm text-gray-600">Quantity type: {{ product.quantity_type }}</p>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" class="bg-amber-600 text-white px-3 py-1 rounded hover:bg-amber-700" @click="startEditProduct(product)">
                            Edit
                        </button>
                        <button type="button" class="bg-red-600 text-white px-3 py-1 rounded hover:bg-red-700" @click="deleteProduct(product.id)">
                            Delete
                        </button>
                    </div>
                </div>
            </div>
            <p v-else class="text-sm text-gray-500">No products found.</p>
        </div>

        <div class="bg-white p-4 rounded-lg border border-gray-200">
            <div class="grid gap-4 md:grid-cols-2 mb-4">
                <select v-model="selectedListId" class="w-full border border-gray-300 rounded px-3 py-2" @change="loadSelectedList">
                    <option value="">Select shopping list</option>
                    <option v-for="shoppingList in shoppingLists" :key="shoppingList.id" :value="String(shoppingList.id)">
                        {{ shoppingList.name }} ({{ shoppingList.effective_permission }})
                    </option>
                </select>

                <div v-if="selectedList" class="text-sm text-gray-700 flex items-center gap-3">
                    Effective permission: <strong class="ml-1">{{ selectedList.effective_permission }}</strong>
                    <a
                        :href="`/shopping-lists/${selectedList.id}`"
                        class="ml-auto bg-blue-600 text-white px-3 py-1.5 rounded hover:bg-blue-700 text-sm font-medium"
                    >
                        Open List View
                    </a>
                </div>
            </div>

            <div v-if="selectedList" class="space-y-6">
                <form class="grid gap-4 md:grid-cols-[1fr_1fr_auto_auto]" @submit.prevent="updateShoppingList">
                    <input v-model="listEditForm.name" type="text" class="w-full border border-gray-300 rounded px-3 py-2" :disabled="!canEditList" placeholder="List name">
                    <input v-model="listEditForm.description" type="text" class="w-full border border-gray-300 rounded px-3 py-2" :disabled="!canEditList" placeholder="Description">
                    <button type="submit" class="bg-emerald-600 text-white px-4 py-2 rounded hover:bg-emerald-700 disabled:bg-gray-300" :disabled="!canEditList">Update</button>
                    <button type="button" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 disabled:bg-gray-300" :disabled="!canManageShares" @click="deleteShoppingList">Delete</button>
                </form>

                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                    <h3 class="font-medium mb-3">List Items</h3>

                    <form class="grid gap-4 md:grid-cols-4 mb-4" @submit.prevent="submitItem">
                        <select v-model="itemForm.productId" class="w-full border border-gray-300 rounded px-3 py-2" :disabled="!canEditList">
                            <option value="">Select product</option>
                            <option v-for="product in products" :key="`item-product-${product.id}`" :value="String(product.id)">
                                {{ product.name }} ({{ product.quantity_type }})
                            </option>
                        </select>
                        <input v-model="itemForm.quantity" type="number" step="0.01" min="0.01" class="w-full border border-gray-300 rounded px-3 py-2" :disabled="!canEditList" placeholder="Quantity">
                        <input v-model="itemForm.notes" type="text" class="w-full border border-gray-300 rounded px-3 py-2" :disabled="!canEditList" placeholder="Notes">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input v-model="itemForm.isCompleted" type="checkbox" :disabled="!canEditList">
                            Completed
                        </label>
                        <div class="md:col-span-4 flex gap-2">
                            <button type="submit" class="bg-emerald-600 text-white px-4 py-2 rounded hover:bg-emerald-700 disabled:bg-gray-300" :disabled="!canEditList">
                                {{ editingItemId ? 'Update Item' : 'Add Item' }}
                            </button>
                            <button v-if="editingItemId" type="button" class="bg-gray-200 text-gray-800 px-4 py-2 rounded hover:bg-gray-300" @click="resetItemForm">
                                Cancel
                            </button>
                        </div>
                    </form>

                    <div v-if="selectedList.items?.length" class="space-y-2">
                        <div v-for="item in selectedList.items" :key="item.id" class="border border-gray-200 rounded px-3 py-2 flex flex-col gap-2 md:flex-row md:items-center md:justify-between bg-white">
                            <div>
                                <p class="font-medium">{{ item.product?.name }}</p>
                                <p class="text-sm text-gray-600">{{ item.quantity }} {{ item.product?.quantity_type }}</p>
                                <p class="text-sm text-gray-600">{{ item.notes || 'No notes' }}</p>
                                <p class="text-sm text-gray-600">Status: {{ item.is_completed ? 'completed' : 'pending' }}</p>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" class="bg-amber-600 text-white px-3 py-1 rounded hover:bg-amber-700 disabled:bg-gray-300" :disabled="!canEditList" @click="startEditItem(item)">
                                    Edit
                                </button>
                                <button type="button" class="bg-red-600 text-white px-3 py-1 rounded hover:bg-red-700 disabled:bg-gray-300" :disabled="!canEditList" @click="deleteItem(item.id)">
                                    Delete
                                </button>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-500">No items in this shopping list yet.</p>
                </div>

                <div class="grid gap-6 md:grid-cols-3">
                    <form class="space-y-2" @submit.prevent="shareWithUser">
                        <h3 class="font-medium">Share Directly With User</h3>
                        <select v-model="directShareForm.userId" class="w-full border border-gray-300 rounded px-3 py-2" :disabled="!canManageShares">
                            <option value="">Select user</option>
                            <option v-for="user in users" :key="`user-${user.id}`" :value="String(user.id)">
                                {{ user.name }}
                            </option>
                        </select>
                        <select v-model="directShareForm.permission" class="w-full border border-gray-300 rounded px-3 py-2" :disabled="!canManageShares">
                            <option value="view">view</option>
                            <option value="edit">edit</option>
                        </select>
                        <button type="submit" class="bg-slate-800 text-white px-4 py-2 rounded hover:bg-slate-900 disabled:bg-gray-300" :disabled="!canManageShares">Share User</button>
                    </form>

                    <form class="space-y-2" @submit.prevent="shareWithFamily">
                        <h3 class="font-medium">Share With Family</h3>
                        <select v-model="familyShareForm.familyId" class="w-full border border-gray-300 rounded px-3 py-2" :disabled="!canManageShares">
                            <option value="">Select family</option>
                            <option v-for="family in families" :key="`family-${family.id}`" :value="String(family.id)">
                                {{ family.name }}
                            </option>
                        </select>
                        <select v-model="familyShareForm.permission" class="w-full border border-gray-300 rounded px-3 py-2" :disabled="!canManageShares">
                            <option value="view">view</option>
                            <option value="edit">edit</option>
                        </select>
                        <button type="submit" class="bg-violet-700 text-white px-4 py-2 rounded hover:bg-violet-800 disabled:bg-gray-300" :disabled="!canManageShares">Share Family</button>
                    </form>

                    <form class="space-y-2" @submit.prevent="shareWithFamilyMember">
                        <h3 class="font-medium">Share With Family Member</h3>
                        <select v-model="familyMemberForm.familyId" class="w-full border border-gray-300 rounded px-3 py-2" :disabled="!canManageShares">
                            <option value="">Select family</option>
                            <option v-for="family in families" :key="`member-family-${family.id}`" :value="String(family.id)">
                                {{ family.name }}
                            </option>
                        </select>
                        <select v-model="familyMemberForm.userId" class="w-full border border-gray-300 rounded px-3 py-2" :disabled="!canManageShares">
                            <option value="">Select family member</option>
                            <option v-for="member in availableFamilyMembers" :key="`member-${member.id}`" :value="String(member.id)">
                                {{ member.name }}
                            </option>
                        </select>
                        <select v-model="familyMemberForm.permission" class="w-full border border-gray-300 rounded px-3 py-2" :disabled="!canManageShares">
                            <option value="view">view</option>
                            <option value="edit">edit</option>
                        </select>
                        <button type="submit" class="bg-amber-600 text-white px-4 py-2 rounded hover:bg-amber-700 disabled:bg-gray-300" :disabled="!canManageShares">Share Member</button>
                    </form>
                </div>

                <div>
                    <h3 class="font-medium mb-2">Direct User Shares</h3>
                    <div v-if="selectedList.user_shares?.length" class="space-y-2">
                        <div v-for="share in selectedList.user_shares" :key="share.id" class="flex flex-wrap items-center gap-2 border border-gray-200 rounded px-3 py-2">
                            <span class="text-sm text-gray-700">{{ share.user?.name }} ({{ share.permission }})</span>
                            <select v-model="directSharePermissionEdits[share.user_id]" class="border border-gray-300 rounded px-2 py-1 text-sm" :disabled="!canManageShares">
                                <option value="view">view</option>
                                <option value="edit">edit</option>
                            </select>
                            <button type="button" class="bg-slate-700 text-white px-3 py-1 rounded text-sm disabled:bg-gray-300" :disabled="!canManageShares" @click="updateDirectShare(share.user_id)">Update</button>
                            <button type="button" class="bg-red-700 text-white px-3 py-1 rounded text-sm disabled:bg-gray-300" :disabled="!canManageShares || share.permission === 'owner'" @click="removeDirectShare(share.user_id)">Remove</button>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-500">No direct user shares.</p>
                </div>

                <div>
                    <h3 class="font-medium mb-2">Family Shares</h3>
                    <div v-if="selectedList.family_shares?.length" class="space-y-2">
                        <div v-for="share in selectedList.family_shares" :key="share.id" class="flex flex-wrap items-center gap-2 border border-gray-200 rounded px-3 py-2">
                            <span class="text-sm text-gray-700">{{ share.family?.name }} ({{ share.permission }})</span>
                            <select v-model="familySharePermissionEdits[share.family_id]" class="border border-gray-300 rounded px-2 py-1 text-sm" :disabled="!canManageShares">
                                <option value="view">view</option>
                                <option value="edit">edit</option>
                            </select>
                            <button type="button" class="bg-slate-700 text-white px-3 py-1 rounded text-sm disabled:bg-gray-300" :disabled="!canManageShares" @click="updateFamilyShare(share.family_id)">Update</button>
                            <button type="button" class="bg-red-700 text-white px-3 py-1 rounded text-sm disabled:bg-gray-300" :disabled="!canManageShares" @click="removeFamilyShare(share.family_id)">Remove</button>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-500">No family shares.</p>
                </div>

                <div>
                    <h3 class="font-medium mb-2">Family Member Shares</h3>
                    <div v-if="selectedList.family_member_shares?.length" class="space-y-2">
                        <div v-for="share in selectedList.family_member_shares" :key="share.id" class="flex flex-wrap items-center gap-2 border border-gray-200 rounded px-3 py-2">
                            <span class="text-sm text-gray-700">{{ share.family?.name }} / {{ share.user?.name }} ({{ share.permission }})</span>
                            <select v-model="familyMemberPermissionEdits[`${share.family_id}-${share.user_id}`]" class="border border-gray-300 rounded px-2 py-1 text-sm" :disabled="!canManageShares">
                                <option value="view">view</option>
                                <option value="edit">edit</option>
                            </select>
                            <button type="button" class="bg-slate-700 text-white px-3 py-1 rounded text-sm disabled:bg-gray-300" :disabled="!canManageShares" @click="updateFamilyMemberShare(share.family_id, share.user_id)">Update</button>
                            <button type="button" class="bg-red-700 text-white px-3 py-1 rounded text-sm disabled:bg-gray-300" :disabled="!canManageShares" @click="removeFamilyMemberShare(share.family_id, share.user_id)">Remove</button>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-500">No family member shares.</p>
                </div>
            </div>
        </div>
    </div>
</template>