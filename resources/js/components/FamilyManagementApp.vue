<script setup>
import { onMounted, reactive, ref } from 'vue';

const users = ref([]);
const families = ref([]);
const selectedFamilyId = ref('');
const selectedFamily = ref(null);
const familyRoles = ref([]);
const familyMembers = ref([]);
const familyPermissions = ref([]);
const loading = ref(false);
const error = ref('');

const familyForm = reactive({
    name: '',
});

const familyEditForm = reactive({
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

const roleEditForm = reactive({
    roleId: '',
    name: '',
    level: 1,
    permissionsCsv: '',
});

const memberRoleSelections = reactive({});
const initialFamilyIdFromQuery = new URLSearchParams(window.location.search).get('family') || '';

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

        if (!selectedFamilyId.value && initialFamilyIdFromQuery) {
            const existsInList = families.value.some((family) => String(family.id) === String(initialFamilyIdFromQuery));

            if (existsInList) {
                selectedFamilyId.value = String(initialFamilyIdFromQuery);
                await loadSelectedFamilyData();
                return;
            }
        }

        if (!selectedFamilyId.value && families.value.length > 0) {
            selectedFamilyId.value = String(families.value[0].id);
            await loadSelectedFamilyData();
            return;
        }

        if (selectedFamilyId.value) {
            const selectedStillExists = families.value.some((family) => String(family.id) === selectedFamilyId.value);

            if (!selectedStillExists) {
                selectedFamilyId.value = families.value.length ? String(families.value[0].id) : '';
            }

            await loadSelectedFamilyData();
        }
    } catch {
        error.value = 'Failed to load families.';
    }
};

const loadSelectedFamilyData = async () => {
    if (!selectedFamilyId.value) {
        selectedFamily.value = null;
        familyRoles.value = [];
        familyMembers.value = [];
        familyPermissions.value = [];
        familyEditForm.name = '';
        return;
    }

    try {
        const [familyResponse, rolesResponse, membersResponse, permissionsResponse] = await Promise.all([
            window.axios.get(`/api/families/${selectedFamilyId.value}`),
            window.axios.get(`/api/families/${selectedFamilyId.value}/roles`),
            window.axios.get(`/api/families/${selectedFamilyId.value}/members`),
            window.axios.get(`/api/families/${selectedFamilyId.value}/permissions/me`),
        ]);

        selectedFamily.value = familyResponse.data;
        familyRoles.value = rolesResponse.data;
        familyMembers.value = membersResponse.data;
        familyPermissions.value = permissionsResponse.data.effective_permissions || [];
        familyEditForm.name = familyResponse.data.name;
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

const updateFamily = async () => {
    if (!selectedFamilyId.value) {
        error.value = 'Select a family first.';
        return;
    }

    error.value = '';

    try {
        await window.axios.put(`/api/families/${selectedFamilyId.value}`, {
            name: familyEditForm.name,
        });

        await loadFamilies();
    } catch (requestError) {
        error.value = requestError.response?.data?.message || 'Unable to update family.';
    }
};

const deleteFamily = async () => {
    if (!selectedFamilyId.value) {
        error.value = 'Select a family first.';
        return;
    }

    if (!window.confirm('Delete this family?')) {
        return;
    }

    error.value = '';

    try {
        await window.axios.delete(`/api/families/${selectedFamilyId.value}`);
        selectedFamilyId.value = '';
        await loadFamilies();
    } catch (requestError) {
        error.value = requestError.response?.data?.message || 'Unable to delete family.';
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

const startEditRole = (role) => {
    roleEditForm.roleId = String(role.id);
    roleEditForm.name = role.name;
    roleEditForm.level = role.level;
    roleEditForm.permissionsCsv = (role.permissions || []).join(', ');
};

const updateRole = async () => {
    if (!selectedFamilyId.value || !roleEditForm.roleId) {
        error.value = 'Select a role to edit.';
        return;
    }

    error.value = '';

    try {
        const permissions = roleEditForm.permissionsCsv
            .split(',')
            .map((permission) => permission.trim())
            .filter(Boolean);

        await window.axios.put(`/api/families/${selectedFamilyId.value}/roles/${roleEditForm.roleId}`, {
            name: roleEditForm.name,
            level: Number(roleEditForm.level),
            permissions,
        });

        roleEditForm.roleId = '';
        roleEditForm.name = '';
        roleEditForm.level = 1;
        roleEditForm.permissionsCsv = '';
        await loadSelectedFamilyData();
    } catch (requestError) {
        error.value = requestError.response?.data?.message || 'Unable to update role.';
    }
};

const deleteRole = async (roleId) => {
    if (!selectedFamilyId.value) {
        return;
    }

    if (!window.confirm('Delete this role?')) {
        return;
    }

    error.value = '';

    try {
        await window.axios.delete(`/api/families/${selectedFamilyId.value}/roles/${roleId}`);
        await loadSelectedFamilyData();
    } catch (requestError) {
        error.value = requestError.response?.data?.message || 'Unable to delete role.';
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

const addMember = async () => {
    if (!selectedFamilyId.value) {
        error.value = 'Select a family first.';
        return;
    }

    error.value = '';

    try {
        await window.axios.post(`/api/families/${selectedFamilyId.value}/members`, {
            user_id: Number(assignForm.userId),
            family_role_id: Number(assignForm.familyRoleId),
        });

        assignForm.userId = '';
        assignForm.familyRoleId = '';
        await loadSelectedFamilyData();
    } catch (requestError) {
        error.value = requestError.response?.data?.message || 'Unable to add member.';
    }
};

const updateMemberRole = async (userId) => {
    if (!selectedFamilyId.value || !memberRoleSelections[userId]) {
        error.value = 'Select a new role first.';
        return;
    }

    error.value = '';

    try {
        await window.axios.put(`/api/families/${selectedFamilyId.value}/members/${userId}`, {
            family_role_id: Number(memberRoleSelections[userId]),
        });

        await loadSelectedFamilyData();
    } catch (requestError) {
        error.value = requestError.response?.data?.message || 'Unable to update member role.';
    }
};

const removeMember = async (userId) => {
    if (!selectedFamilyId.value) {
        return;
    }

    if (!window.confirm('Remove this member from family?')) {
        return;
    }

    error.value = '';

    try {
        await window.axios.delete(`/api/families/${selectedFamilyId.value}/members/${userId}`);
        await loadSelectedFamilyData();
    } catch (requestError) {
        error.value = requestError.response?.data?.message || 'Unable to remove member.';
    }
};

const isUserAlreadyMember = (userId) => {
    return familyMembers.value.some((member) => member.user_id === userId);
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

            <form v-if="selectedFamilyId" class="grid gap-2 md:grid-cols-[1fr_auto_auto] mb-4" @submit.prevent="updateFamily">
                <input
                    v-model="familyEditForm.name"
                    type="text"
                    class="w-full border border-gray-300 rounded px-3 py-2"
                    placeholder="Family name"
                >
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                    Update Family
                </button>
                <button type="button" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700" @click="deleteFamily">
                    Delete Family
                </button>
            </form>

            <div class="grid gap-4 md:grid-cols-2">
                <form class="space-y-2" @submit.prevent="createFamilyRole">
                    <h3 class="font-medium">Create Custom Role</h3>
                    <input v-model="roleForm.name" type="text" class="w-full border border-gray-300 rounded px-3 py-2" placeholder="Role name">
                    <input v-model="roleForm.level" type="number" min="1" max="99" class="w-full border border-gray-300 rounded px-3 py-2" placeholder="Hierarchy level (1-99)">
                    <input v-model="roleForm.permissionsCsv" type="text" class="w-full border border-gray-300 rounded px-3 py-2" placeholder="permissions comma-separated">
                    <button type="submit" class="bg-emerald-600 text-white px-4 py-2 rounded hover:bg-emerald-700">Create Role</button>
                </form>

                <form class="space-y-2" @submit.prevent="updateRole">
                    <h3 class="font-medium">Edit Role / Level</h3>
                    <select v-model="roleEditForm.roleId" class="w-full border border-gray-300 rounded px-3 py-2">
                        <option value="">Select role</option>
                        <option v-for="role in familyRoles" :key="`edit-${role.id}`" :value="String(role.id)">
                            {{ role.name }} (level {{ role.level }})
                        </option>
                    </select>
                    <input v-model="roleEditForm.name" type="text" class="w-full border border-gray-300 rounded px-3 py-2" placeholder="Role name">
                    <input v-model="roleEditForm.level" type="number" min="1" max="99" class="w-full border border-gray-300 rounded px-3 py-2" placeholder="Hierarchy level (1-99)">
                    <input v-model="roleEditForm.permissionsCsv" type="text" class="w-full border border-gray-300 rounded px-3 py-2" placeholder="permissions comma-separated">
                    <button type="submit" class="bg-amber-600 text-white px-4 py-2 rounded hover:bg-amber-700">Update Role</button>
                </form>

                <form class="space-y-2" @submit.prevent="addMember">
                    <h3 class="font-medium">Add Member</h3>
                    <select v-model="assignForm.userId" class="w-full border border-gray-300 rounded px-3 py-2">
                        <option value="">Select user</option>
                        <option
                            v-for="user in users"
                            :key="`candidate-${user.id}`"
                            :value="String(user.id)"
                            :disabled="isUserAlreadyMember(user.id)"
                        >
                            {{ user.name }} ({{ user.id }})
                        </option>
                    </select>
                    <select v-model="assignForm.familyRoleId" class="w-full border border-gray-300 rounded px-3 py-2">
                        <option value="">Select family role</option>
                        <option v-for="role in familyRoles" :key="`new-member-${role.id}`" :value="String(role.id)">
                            {{ role.name }} (level {{ role.level }})
                        </option>
                    </select>
                    <button type="submit" class="bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700">Add Member</button>
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
                        <button class="ml-2 text-blue-700" type="button" @click="startEditRole(role)">Edit</button>
                        <button class="ml-2 text-red-700" type="button" @click="deleteRole(role.id)">Delete</button>
                    </li>
                </ul>
            </div>

            <div v-if="familyMembers.length" class="mt-4">
                <h3 class="font-medium mb-2">Family Members</h3>
                <div class="space-y-2">
                    <div
                        v-for="member in familyMembers"
                        :key="member.id"
                        class="border border-gray-200 rounded px-3 py-2 flex flex-col gap-2 md:flex-row md:items-center md:justify-between"
                    >
                        <p class="text-sm text-gray-700">
                            {{ member.user?.name }} ({{ member.user?.email }}) — role: {{ member.role?.name }} (level {{ member.role?.level }})
                        </p>

                        <div class="flex gap-2">
                            <select v-model="memberRoleSelections[member.user_id]" class="border border-gray-300 rounded px-2 py-1 text-sm">
                                <option value="">Select role</option>
                                <option v-for="role in familyRoles" :key="`member-${member.id}-role-${role.id}`" :value="String(role.id)">
                                    {{ role.name }} ({{ role.level }})
                                </option>
                            </select>
                            <button type="button" class="bg-slate-700 text-white px-3 py-1 rounded text-sm" @click="updateMemberRole(member.user_id)">
                                Change Role
                            </button>
                            <button type="button" class="bg-red-700 text-white px-3 py-1 rounded text-sm" @click="removeMember(member.user_id)">
                                Remove
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="selectedFamilyId" class="mt-4">
                <h3 class="font-medium mb-2">My Effective Permissions in Family</h3>
                <p class="text-sm text-gray-700">{{ familyPermissions.join(', ') || 'none' }}</p>
            </div>
        </div>
    </div>
</template>
