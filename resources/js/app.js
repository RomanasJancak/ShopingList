import './bootstrap';
import { createApp } from 'vue';
import UserCrudApp from './components/UserCrudApp.vue';
import FamilyManagementApp from './components/FamilyManagementApp.vue';
import ShoppingListManagementApp from './components/ShoppingListManagementApp.vue';

const appElement = document.getElementById('app');

if (appElement) {
	const { page, isAuthenticated, oauthStatus, oauthError } = appElement.dataset;

	if (page === 'families') {
		createApp(FamilyManagementApp).mount(appElement);
	} else if (page === 'shopping-lists') {
		createApp(ShoppingListManagementApp).mount(appElement);
	} else {
		createApp(UserCrudApp, {
			isAuthenticated: isAuthenticated === '1',
			oauthStatus: oauthStatus || '',
			oauthError: oauthError || '',
		}).mount(appElement);
	}
}
