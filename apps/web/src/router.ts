import {createRouter,createWebHistory} from 'vue-router';
import {useAuthStore} from '@/stores/auth';

const routes=[
  {path:'/login',component:()=>import('@/views/LoginView.vue')},
  {path:'/',component:()=>import('@/layouts/AppLayout.vue'),meta:{auth:true},children:[
    {path:'',name:'visão geral',component:()=>import('@/views/DashboardView.vue')},
    {path:'analises',name:'auditorias',component:()=>import('@/views/AnalysesView.vue')},
    {path:'analises/nova',name:'nova auditoria',component:()=>import('@/views/AnalysisUploadView.vue')},
    {path:'analises/:id',name:'detalhe da auditoria',component:()=>import('@/views/AnalysisDetailView.vue')},
    {path:'analises/:id/documentos/:documentId',name:'detalhe da NF-e',component:()=>import('@/views/FiscalDocumentDetailView.vue')},
    {path:'logs',name:'logs da aplicação',component:()=>import('@/views/ApplicationLogsView.vue'),meta:{admin:true}},
    {path:'catalogos',name:'catálogos fiscais',component:()=>import('@/views/CatalogsView.vue')},
    {path:'catalogos/:id',name:'detalhe do catálogo',component:()=>import('@/views/CatalogDetailView.vue')},
    {path:'contas',name:'empresas da plataforma',component:()=>import('@/views/TenantsView.vue'),meta:{platformAdmin:true}},
    {path:'tenants',redirect:'/contas'},
    {path:'clientes',name:'clientes auditados',component:()=>import('@/views/CompaniesView.vue'),meta:{admin:true}},
    {path:'empresas',redirect:'/clientes'},
    {path:'usuarios',name:'usuários',component:()=>import('@/views/UsersView.vue'),meta:{admin:true}},
  ]},
  {path:'/:pathMatch(.*)*',redirect:'/'},
];

const router=createRouter({history:createWebHistory(),routes});
router.beforeEach(async to=>{const auth=useAuthStore();if(!auth.ready)await auth.restore();if(to.meta.auth&&!auth.authenticated)return '/login';if(to.path==='/login'&&auth.authenticated)return '/';if(to.meta.platformAdmin&&!auth.isPlatformAdmin)return '/';if(to.meta.admin&&!auth.isAdmin)return '/'});
export default router;
