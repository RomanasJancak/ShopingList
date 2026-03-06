<script setup>
import { onMounted, reactive, ref } from 'vue';

const users = ref([]);
const families = ref([]);
const selectedFamilyId = ref('');
const familyRoles = ref([]);
const familyPermissions = ref([]);
const loading = ref(false);
const error = ref('');

const familyForm = reactive({
    name: '',
});

const roleForm = reactive({
    name: '',
    level: 1,
    permissionsCsv: '',
});

const assignForm = reactive({
    userId: '',
    familyRoleId: '',
});

const loadUsers = async () => {
    try {
        const response = await window.axios.get('/api/users');
        users.value = response.data;
    } catch {
        error.value = 'Failed to load users.';
    }
};

const loadFamilies = async () => {
    try {
        const response = await window.axios.get('/api/families');
        families.value = response.data;

        if (!selectedFamilyId.value && families.value.length > 0) {
            selectedFamilyId.value = String(families.value[0].id);
            await loadSelectedFamilyData();
        }
    } catch {
        error.value = 'Failed to load families.';
    }
};

const loadSelectedFamilyData = async () => {
    if (!selectedFamilyId.value) {
        familyRoles.value = [];
        familyPermissions.value = [];
        return;
    }

    try {
        const [rolesResponse, permissionsResponse] = await Promise.all([
            window.axios.get(`/api/families/${selectedFamilyId.value}/roles`),
            window.axios.get(`/api/families/${selectedFamilyId.value}/permissions/me`),
        ]);

        familyRoles.value = rolesResponse.data;
        familyPermissions.value = permissionsResponse.data.effective_permissions || [];
    } catch {
        error.value = 'Failed to load family details.';
    }
};

const createFamily = async () => {
    error.value = '';

    try {
        await window.axios.post('/api/families', {
            name: familyForm.name,
        });

        familyForm.name = '';
        await loadFamilies();
    } catch (requestError) {
        error.value = requestError.response?.data?.message || 'Unable to create family.';
    }
};

const createFamilyRole = async () => {
    if (!selectedFamilyId.value) {
        error.value = 'Select a family first.';
        return;
    }

    error.value = '';

    try {
        const permissions = roleForm.permissionsCsv
            .split(',')
            .map((permission) => permission.trim())
            .filter(Boolean);

        await window.axios.post(`/api/families/${selectedFamilyId.value}/roles`, {
            name: roleForm.name,
            level: Number(roleForm.level),
            permissions,
        });

        roleForm.name = '';
        roleForm.level = 1;
        roleForm.permissionsCsv = '';
        await loadSelectedFamilyData();
    } catch (requestError) {
        error.value = requestError.response?.data?.message || 'Unable to create role.';
    }
};

const assignFamilyRole = async () => {
    if (!selectedFamilyId.value) {
        error.value = 'Select a family first.';
        return;
    }

    error.value = '';

    try {
        await window.axios.post(`/api/families/${selectedFamilyId.value}/assign-role`, {
            user_id: Number(assignForm.userId),
            family_role_id: Number(assignForm.familyRoleId),
        });

        assignForm.userId = '';
        assignForm.familyRoleId = '';
        await loadSelectedFamilyData();
    } catch (requestError) {
        error.value = requestError.response?.data?.message || 'Unable to assign role.';
    }
};

onMounted(async () => {
    loading.value = true;
    await loadUsers();
    await loadFamilies();
    loading.value = false;
});
</script>

<template>
    <div class="mx-auto max-w-5xl p-6">
        <h1 class="text-2xl font-bold mb-6">Family Management</h1>

        <p v-if="error" class="text-red-700 mb-4">{{ error }}</p>
        <p v-if="loading" class="text-gray-600">Loading...</p>

        <div class="bg-white p-4 rounded-lg border border-gray-200 mb-6">
            <h2 class="text-lg font-semibold mb-4">Family Groups</h2>

            <div class="grid gap-4 md:grid-cols-2 mb-4">
                <form class="flex gap-2" @submit.prevent="createFamily">
                    <input
                        v-model="familyForm.name"
                        type="text"
                        class="w-full border border-gray-300 rounded px-3 py-2"
                        placeholder="New family name"
                    >
                    <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">
                        Create
                    </button>
                </form>

                <select
                    v-model="selectedFamilyId"
                    class="w-full border border-gray-300 rounded px-3 py-2"
                    @change="loadSelectedFamilyData"
                >
                    <option value="">Select family</option>
                    <option v-for="family in families" :key="family.id" :value="String(family.id)">
                        {{ family.name }}
                    </option>
                </select>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <form class="space-y-2" @submit.prevent="createFamilyRole">
                    <h3 class="font-medium">Create Custom Role</h3>
                    <input v-model="roleForm.name" type="text" class="w-full border border-gray-300 rounded px-3 py-2" placeholder="Role name">
                    <input v-model="roleForm.level" type="number" min="1" max="99" class="w-full border border-gray-300 rounded px-3 py-2" placeholder="Hierarchy level (1-99)">
                    <input v-model="roleForm.permissionsCsv" type="text" class="w-full border border-gray-300 rounded px-3 py-2" placeholder="permissions comma-separated">
                    <button type="submit" class="bg-emerald-600 text-white px-4 py-2 rounded hover:bg-emerald-700">Create Role</button>
                </form>

                <form class="space-y-2" @submit.prevent="assignFamilyRole">
                    <h3 class="font-medium">Assign Role to User</h3>
                    <select v-model="assignForm.userId" class="w-full border border-gray-300 rounded px-3 py-2">
                        <option value="">Select user</option>
                        <option v-for="user in users" :key="user.id" :value="String(user.id)">{{ user.name }} ({{ user.id }})</option>
                    </select>
                    <select v-model="assignForm.familyRoleId" class="w-full border border-gray-300 rounded px-3 py-2">
                        <option value="">Select family role</option>
                        <option v-for="role in familyRoles" :key="role.id" :value="String(role.id)">
                            {{ role.name }} (level {{ role.level }})
                        </option>
                    </select>
                    <button type="submit" class="bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700">Assign Role</button>
                </form>
            </div>

            <div v-if="familyRoles.length" class="mt-4">
                <h3 class="font-medium mb-2">Family Roles (Hierarchy)</h3>
                <ul class="space-y-1 text-sm text-gray-700">
                    <li v-for="role in familyRoles" :key="role.id">
                        {{ role.name }} — level {{ role.level }} — permissions: {{ (role.permissions || []).join(', ') || 'none' }}
                    </li>
                </ul>
            </div>

            <div v-if="selectedFamilyId" class="mt-4">
                <h3 class="font-medium mb-2">My Effective Permissions in Family</h3>
                <p class="text-sm text-gray-700">{{ familyPermissions.join(', ') || 'none' }}</p>
            </div>
        </div>
    </div>
</template>
