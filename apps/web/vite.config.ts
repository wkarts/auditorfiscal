import {defineConfig} from 'vite';
import vue from '@vitejs/plugin-vue';
export default defineConfig({plugins:[vue()],resolve:{alias:{'@':'/src'}},server:{host:'0.0.0.0',proxy:{'/api':{target:'http://api:8080',changeOrigin:true}}},build:{sourcemap:false}});
