import {ModuleRegistry,AllCommunityModule} from 'ag-grid-community';ModuleRegistry.registerModules([AllCommunityModule]);
import {createApp} from 'vue';import {createPinia} from 'pinia';import App from './App.vue';import router from './router';import './assets/main.css';import 'ag-grid-community/styles/ag-grid.css';import 'ag-grid-community/styles/ag-theme-quartz.css';
createApp(App).use(createPinia()).use(router).mount('#app');
