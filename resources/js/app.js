import './bootstrap';
import { createApp } from 'vue';
import UserCrudApp from './components/UserCrudApp.vue';

const appElement = document.getElementById('app');

if (appElement) {
	createApp(UserCrudApp).mount(appElement);
}
