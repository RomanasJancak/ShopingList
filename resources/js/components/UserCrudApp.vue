<script setup>
import { onMounted, reactive, ref } from 'vue';

const props = defineProps({
    isAuthenticated: {
        type: Boolean,
        default: false,
    },
    oauthStatus: {
        type: String,
        default: '',
    },
    oauthError: {
        type: String,
        default: '',
    },
});

const users = ref([]);
const loading = ref(false);
const error = ref('');

const form = reactive({
    name: '',
    email: '',
    password: '',
});

const editingId = ref(null);
const fieldErrors = reactive({});

const clearForm = () => {
    form.name = '';
    form.email = '';
    form.password = '';
    editingId.value = null;
    Object.keys(fieldErrors).forEach((key) => {
        delete fieldErrors[key];
    });
};

const loadUsers = async () => {
    loading.value = true;
    error.value = '';

    try {
        const response = await window.axios.get('/api/users');
        users.value = response.data;
    } catch (loadError) {
        error.value = 'Failed to load users.';
    } finally {
        loading.value = false;
    }
};

const fillFormForEdit = (user) => {
    form.name = user.name;
    form.email = user.email;
    form.password = '';
    editingId.value = user.id;
    Object.keys(fieldErrors).forEach((key) => {
        delete fieldErrors[key];
    });
};

const submitForm = async () => {
    error.value = '';
    Object.keys(fieldErrors).forEach((key) => {
        delete fieldErrors[key];
    });

    try {
        if (editingId.value) {
            await window.axios.put(`/api/users/${editingId.value}`, {
                name: form.name,
                email: form.email,
                password: form.password || null,
            });
        } else {
            await window.axios.post('/api/users', {
                name: form.name,
                email: form.email,
                password: form.password,
            });
        }

        clearForm();
        await loadUsers();
    } catch (submitError) {
        if (submitError.response?.status === 422 && submitError.response?.data?.errors) {
            Object.assign(fieldErrors, submitError.response.data.errors);
            return;
        }

        error.value = 'Unable to save user.';
    }
};

const deleteUser = async (id) => {
    error.value = '';

    if (!window.confirm('Delete this user?')) {
        return;
    }

    try {
        await window.axios.delete(`/api/users/${id}`);

        if (editingId.value === id) {
            clearForm();
        }

        await loadUsers();
    } catch {
        error.value = 'Unable to delete user.';
    }
};

onMounted(loadUsers);
</script>

<template>
    <div class="mx-auto max-w-4xl p-6">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-bold">User CRUD</h1>

            <div class="flex gap-2">
                <a
                    href="/auth/google/redirect"
                    class="bg-slate-800 text-white px-4 py-2 rounded hover:bg-slate-900"
                >
                    Continue with Google
                </a>

                <a
                    v-if="props.isAuthenticated"
                    href="/auth/google/link"
                    class="bg-emerald-600 text-white px-4 py-2 rounded hover:bg-emerald-700"
                >
                    Link Google Account
                </a>
            </div>
        </div>

        <p v-if="props.oauthStatus" class="text-emerald-700 mb-4">{{ props.oauthStatus }}</p>
        <p v-if="props.oauthError" class="text-red-700 mb-4">{{ props.oauthError }}</p>

        <div class="bg-white p-4 rounded-lg border border-gray-200 mb-6">
            <h2 class="text-lg font-semibold mb-4">{{ editingId ? 'Edit User' : 'Create User' }}</h2>

            <form class="grid gap-4 md:grid-cols-3" @submit.prevent="submitForm">
                <div>
                    <label class="block text-sm font-medium mb-1">Name</label>
                    <input
                        v-model="form.name"
                        type="text"
                        class="w-full border border-gray-300 rounded px-3 py-2"
                        placeholder="John Doe"
                    >
                    <p v-if="fieldErrors.name" class="text-red-600 text-sm mt-1">{{ fieldErrors.name[0] }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Email</label>
                    <input
                        v-model="form.email"
                        type="email"
                        class="w-full border border-gray-300 rounded px-3 py-2"
                        placeholder="john@example.com"
                    >
                    <p v-if="fieldErrors.email" class="text-red-600 text-sm mt-1">{{ fieldErrors.email[0] }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">
                        Password {{ editingId ? '(leave empty to keep current)' : '' }}
                    </label>
                    <input
                        v-model="form.password"
                        type="password"
                        class="w-full border border-gray-300 rounded px-3 py-2"
                        placeholder="********"
                    >
                    <p v-if="fieldErrors.password" class="text-red-600 text-sm mt-1">{{ fieldErrors.password[0] }}</p>
                </div>

                <div class="md:col-span-3 flex gap-2">
                    <button
                        type="submit"
                        class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"
                    >
                        {{ editingId ? 'Update User' : 'Create User' }}
                    </button>

                    <button
                        v-if="editingId"
                        type="button"
                        class="bg-gray-200 text-gray-800 px-4 py-2 rounded hover:bg-gray-300"
                        @click="clearForm"
                    >
                        Cancel
                    </button>
                </div>
            </form>
        </div>

        <p v-if="error" class="text-red-700 mb-4">{{ error }}</p>
        <p v-if="loading" class="text-gray-600">Loading users...</p>

        <div v-else class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <table class="min-w-full">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="text-left p-3">ID</th>
                        <th class="text-left p-3">Name</th>
                        <th class="text-left p-3">Email</th>
                        <th class="text-left p-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="user in users" :key="user.id" class="border-t border-gray-200">
                        <td class="p-3">{{ user.id }}</td>
                        <td class="p-3">{{ user.name }}</td>
                        <td class="p-3">{{ user.email }}</td>
                        <td class="p-3 flex gap-2">
                            <button
                                class="bg-amber-500 text-white px-3 py-1 rounded hover:bg-amber-600"
                                @click="fillFormForEdit(user)"
                            >
                                Edit
                            </button>
                            <button
                                class="bg-red-600 text-white px-3 py-1 rounded hover:bg-red-700"
                                @click="deleteUser(user.id)"
                            >
                                Delete
                            </button>
                        </td>
                    </tr>
                    <tr v-if="users.length === 0">
                        <td class="p-4 text-center text-gray-500" colspan="4">No users found.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>