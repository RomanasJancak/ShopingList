<script setup>
import { onMounted, ref } from 'vue';

const permissions = ref([]);
const roles = ref([]);
const loading = ref(false);
const error = ref('');
const canViewPermissions = ref(false);
const canViewRoles = ref(false);

const loadPermissions = async () => {
    try {
        const response = await window.axios.get('/api/access-control/permissions');
        permissions.value = response.data;
        canViewPermissions.value = true;
    } catch (requestError) {
        if (requestError.response?.status === 403) {
            canViewPermissions.value = false;
            return;
        }

        throw requestError;
    }
};

const loadRoles = async () => {
    try {
        const response = await window.axios.get('/api/access-control/roles');
        roles.value = response.data;
        canViewRoles.value = true;
    } catch (requestError) {
        if (requestError.response?.status === 403) {
            canViewRoles.value = false;
            return;
        }

        throw requestError;
    }
};

onMounted(async () => {
    loading.value = true;
    error.value = '';

    try {
        await Promise.all([
            loadPermissions(),
            loadRoles(),
        ]);
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

                <ul v-else class="space-y-2 text-sm">
                    <li v-for="permission in permissions" :key="permission.id" class="border border-gray-200 rounded p-2">
                        <p class="font-medium">{{ permission.name }}</p>
                        <p class="text-gray-600">Guard: {{ permission.guard_name }}</p>
                        <p class="text-gray-600">Assigned roles: {{ permission.roles_count }}</p>
                        <p class="text-gray-600">
                            Roles: {{ (permission.roles || []).map((role) => role.name).join(', ') || 'none' }}
                        </p>
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

                <ul v-else class="space-y-2 text-sm">
                    <li v-for="role in roles" :key="role.id" class="border border-gray-200 rounded p-2">
                        <p class="font-medium">{{ role.name }}</p>
                        <p class="text-gray-600">Guard: {{ role.guard_name }}</p>
                        <p class="text-gray-600">Users assigned: {{ role.users_count }}</p>
                        <p class="text-gray-600">Permissions count: {{ role.permissions_count }}</p>
                        <p class="text-gray-600">
                            Permissions: {{ (role.permissions || []).map((permission) => permission.name).join(', ') || 'none' }}
                        </p>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>