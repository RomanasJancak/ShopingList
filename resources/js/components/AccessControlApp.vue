<script setup>
import { onMounted, reactive, ref } from 'vue';

const permissions = ref([]);
const roles = ref([]);
const loading = ref(false);
const error = ref('');
const canViewPermissions = ref(false);
const canManagePermissions = ref(false);
const canViewRoles = ref(false);
const canManageRoles = ref(false);

const permissionForm = reactive({
    name: '',
    guardName: 'web',
});

const editingPermissionId = ref(null);

const roleForm = reactive({
    name: '',
    guardName: 'web',
    permissionIds: [],
});

const editingRoleId = ref(null);

const loadCapabilities = async () => {
    const response = await window.axios.get('/api/access-control/capabilities');

    canViewPermissions.value = response.data.can_view_permissions;
    canManagePermissions.value = response.data.can_manage_permissions;
    canViewRoles.value = response.data.can_view_roles;
    canManageRoles.value = response.data.can_manage_roles;
};

const loadPermissions = async () => {
    if (!canViewPermissions.value) {
        permissions.value = [];
        return;
    }

    const response = await window.axios.get('/api/access-control/permissions');
    permissions.value = response.data;
};

const loadRoles = async () => {
    if (!canViewRoles.value) {
        roles.value = [];
        return;
    }

    const response = await window.axios.get('/api/access-control/roles');
    roles.value = response.data;
};

const loadAll = async () => {
    await Promise.all([
        loadPermissions(),
        loadRoles(),
    ]);
};

const resetPermissionForm = () => {
    permissionForm.name = '';
    permissionForm.guardName = 'web';
    editingPermissionId.value = null;
};

const startEditPermission = (permission) => {
    permissionForm.name = permission.name;
    permissionForm.guardName = permission.guard_name;
    editingPermissionId.value = permission.id;
};

const submitPermission = async () => {
    error.value = '';

    try {
        const payload = {
            name: permissionForm.name,
            guard_name: permissionForm.guardName || 'web',
        };

        if (editingPermissionId.value) {
            await window.axios.put(`/api/access-control/permissions/${editingPermissionId.value}`, payload);
        } else {
            await window.axios.post('/api/access-control/permissions', payload);
        }

        resetPermissionForm();
        await loadAll();
    } catch (requestError) {
        error.value = requestError.response?.data?.message || 'Unable to save permission.';
    }
};

const deletePermission = async (permissionId) => {
    if (!window.confirm('Delete this permission?')) {
        return;
    }

    error.value = '';

    try {
        await window.axios.delete(`/api/access-control/permissions/${permissionId}`);
        await loadAll();
    } catch (requestError) {
        error.value = requestError.response?.data?.message || 'Unable to delete permission.';
    }
};

const resetRoleForm = () => {
    roleForm.name = '';
    roleForm.guardName = 'web';
    roleForm.permissionIds = [];
    editingRoleId.value = null;
};

const startEditRole = (role) => {
    roleForm.name = role.name;
    roleForm.guardName = role.guard_name;
    roleForm.permissionIds = (role.permissions || []).map((permission) => permission.id);
    editingRoleId.value = role.id;
};

const submitRole = async () => {
    error.value = '';

    try {
        const payload = {
            name: roleForm.name,
            guard_name: roleForm.guardName || 'web',
            permission_ids: roleForm.permissionIds,
        };

        if (editingRoleId.value) {
            await window.axios.put(`/api/access-control/roles/${editingRoleId.value}`, payload);
        } else {
            await window.axios.post('/api/access-control/roles', payload);
        }

        resetRoleForm();
        await loadAll();
    } catch (requestError) {
        error.value = requestError.response?.data?.message || 'Unable to save role.';
    }
};

const deleteRole = async (roleId) => {
    if (!window.confirm('Delete this role?')) {
        return;
    }

    error.value = '';

    try {
        await window.axios.delete(`/api/access-control/roles/${roleId}`);
        await loadAll();
    } catch (requestError) {
        error.value = requestError.response?.data?.message || 'Unable to delete role.';
    }
};

onMounted(async () => {
    loading.value = true;
    error.value = '';

    try {
        await loadCapabilities();
        await loadAll();
    } catch {
        error.value = 'Failed to load access control data.';
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="mx-auto max-w-6xl p-6">
        <h1 class="text-2xl font-bold mb-6">Access Control</h1>

        <p v-if="error" class="text-red-700 mb-4">{{ error }}</p>
        <p v-if="loading" class="text-gray-600">Loading access control data...</p>

        <div class="grid gap-6 md:grid-cols-2">
            <div class="bg-white p-4 rounded-lg border border-gray-200">
                <h2 class="text-lg font-semibold mb-3">Permissions</h2>

                <p v-if="!canViewPermissions" class="text-sm text-gray-600">
                    You do not have permission to view permissions.
                </p>

                <div v-else-if="permissions.length === 0" class="text-sm text-gray-600">
                    No permissions found.
                </div>

                <form v-if="canManagePermissions" class="grid gap-2 mb-4" @submit.prevent="submitPermission">
                    <input v-model="permissionForm.name" type="text" class="border border-gray-300 rounded px-3 py-2" placeholder="Permission name">
                    <input v-model="permissionForm.guardName" type="text" class="border border-gray-300 rounded px-3 py-2" placeholder="Guard (web)">
                    <div class="flex gap-2">
                        <button type="submit" class="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700">
                            {{ editingPermissionId ? 'Update Permission' : 'Create Permission' }}
                        </button>
                        <button v-if="editingPermissionId" type="button" class="bg-gray-200 text-gray-800 px-3 py-1 rounded hover:bg-gray-300" @click="resetPermissionForm">
                            Cancel
                        </button>
                    </div>
                </form>

                <ul v-if="canViewPermissions && permissions.length > 0" class="space-y-2 text-sm">
                    <li v-for="permission in permissions" :key="permission.id" class="border border-gray-200 rounded p-2">
                        <p class="font-medium">{{ permission.name }}</p>
                        <p class="text-gray-600">Guard: {{ permission.guard_name }}</p>
                        <p class="text-gray-600">Assigned roles: {{ permission.roles_count }}</p>
                        <p class="text-gray-600">
                            Roles: {{ (permission.roles || []).map((role) => role.name).join(', ') || 'none' }}
                        </p>
                        <div v-if="canManagePermissions" class="mt-2 flex gap-2">
                            <button type="button" class="bg-amber-600 text-white px-3 py-1 rounded hover:bg-amber-700" @click="startEditPermission(permission)">
                                Edit
                            </button>
                            <button type="button" class="bg-red-600 text-white px-3 py-1 rounded hover:bg-red-700" @click="deletePermission(permission.id)">
                                Delete
                            </button>
                        </div>
                    </li>
                </ul>
            </div>

            <div class="bg-white p-4 rounded-lg border border-gray-200">
                <h2 class="text-lg font-semibold mb-3">Roles</h2>

                <p v-if="!canViewRoles" class="text-sm text-gray-600">
                    You do not have permission to view roles.
                </p>

                <div v-else-if="roles.length === 0" class="text-sm text-gray-600">
                    No roles found.
                </div>

                <form v-if="canManageRoles" class="grid gap-2 mb-4" @submit.prevent="submitRole">
                    <input v-model="roleForm.name" type="text" class="border border-gray-300 rounded px-3 py-2" placeholder="Role name">
                    <input v-model="roleForm.guardName" type="text" class="border border-gray-300 rounded px-3 py-2" placeholder="Guard (web)">
                    <label class="text-sm text-gray-700">Role permissions</label>
                    <select v-model="roleForm.permissionIds" multiple class="border border-gray-300 rounded px-3 py-2 min-h-32">
                        <option v-for="permission in permissions" :key="`permission-opt-${permission.id}`" :value="permission.id">
                            {{ permission.name }}
                        </option>
                    </select>
                    <div class="flex gap-2">
                        <button type="submit" class="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700">
                            {{ editingRoleId ? 'Update Role' : 'Create Role' }}
                        </button>
                        <button v-if="editingRoleId" type="button" class="bg-gray-200 text-gray-800 px-3 py-1 rounded hover:bg-gray-300" @click="resetRoleForm">
                            Cancel
                        </button>
                    </div>
                </form>

                <ul v-if="canViewRoles && roles.length > 0" class="space-y-2 text-sm">
                    <li v-for="role in roles" :key="role.id" class="border border-gray-200 rounded p-2">
                        <p class="font-medium">{{ role.name }}</p>
                        <p class="text-gray-600">Guard: {{ role.guard_name }}</p>
                        <p class="text-gray-600">Users assigned: {{ role.users_count }}</p>
                        <p class="text-gray-600">Permissions count: {{ role.permissions_count }}</p>
                        <p class="text-gray-600">
                            Permissions: {{ (role.permissions || []).map((permission) => permission.name).join(', ') || 'none' }}
                        </p>
                        <div v-if="canManageRoles" class="mt-2 flex gap-2">
                            <button type="button" class="bg-amber-600 text-white px-3 py-1 rounded hover:bg-amber-700" @click="startEditRole(role)">
                                Edit
                            </button>
                            <button type="button" class="bg-red-600 text-white px-3 py-1 rounded hover:bg-red-700" @click="deleteRole(role.id)">
                                Delete
                            </button>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>