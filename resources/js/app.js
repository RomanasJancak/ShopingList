import './bootstrap';
import { createApp } from 'vue';
import UserCrudApp from './components/UserCrudApp.vue';

const appElement = document.getElementById('app');

if (appElement) {
	const { isAuthenticated, oauthStatus, oauthError } = appElement.dataset;

	createApp(UserCrudApp, {
		isAuthenticated: isAuthenticated === '1',
		oauthStatus: oauthStatus || '',
		oauthError: oauthError || '',
	}).mount(appElement);
}
