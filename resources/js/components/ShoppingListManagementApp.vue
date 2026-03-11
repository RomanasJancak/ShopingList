<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';

const shoppingLists = ref([]);
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

const directSharePermissionEdits = reactive({});
const familySharePermissionEdits = reactive({});
const familyMemberPermissionEdits = reactive({});

const effectivePermission = computed(() => selectedList.value?.effective_permission || null);
const canEditList = computed(() => ['owner', 'edit'].includes(effectivePermission.value));
const canManageShares = computed(() => effectivePermission.value === 'owner');

const resetSelectedListState = () => {
    selectedList.value = null;
    availableFamilyMembers.value = [];
    listEditForm.name = '';
    listEditForm.description = '';
};

const loadUsers = async () => {
    const response = await window.axios.get('/api/users');
    users.value = response.data;
};

const loadFamilies = async () => {
    const response = await window.axios.get('/api/families');
    families.value = response.data;
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
        await Promise.all([loadUsers(), loadFamilies()]);
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

        <div class="bg-white p-4 rounded-lg border border-gray-200">
            <div class="grid gap-4 md:grid-cols-2 mb-4">
                <select v-model="selectedListId" class="w-full border border-gray-300 rounded px-3 py-2" @change="loadSelectedList">
                    <option value="">Select shopping list</option>
                    <option v-for="shoppingList in shoppingLists" :key="shoppingList.id" :value="String(shoppingList.id)">
                        {{ shoppingList.name }} ({{ shoppingList.effective_permission }})
                    </option>
                </select>

                <div v-if="selectedList" class="text-sm text-gray-700 flex items-center">
                    Effective permission: <strong class="ml-1">{{ selectedList.effective_permission }}</strong>
                </div>
            </div>

            <div v-if="selectedList" class="space-y-6">
                <form class="grid gap-4 md:grid-cols-[1fr_1fr_auto_auto]" @submit.prevent="updateShoppingList">
                    <input v-model="listEditForm.name" type="text" class="w-full border border-gray-300 rounded px-3 py-2" :disabled="!canEditList" placeholder="List name">
                    <input v-model="listEditForm.description" type="text" class="w-full border border-gray-300 rounded px-3 py-2" :disabled="!canEditList" placeholder="Description">
                    <button type="submit" class="bg-emerald-600 text-white px-4 py-2 rounded hover:bg-emerald-700 disabled:bg-gray-300" :disabled="!canEditList">Update</button>
                    <button type="button" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 disabled:bg-gray-300" :disabled="!canManageShares" @click="deleteShoppingList">Delete</button>
                </form>

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