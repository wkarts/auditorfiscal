<script setup lang="ts">
import {onMounted,ref,watch} from 'vue';
import {useAuthStore} from '@/stores/auth';
import {useRoute,useRouter} from 'vue-router';
import {api} from '@/lib/api';
import packageMetadata from '../../package.json';
const auth=useAuthStore(),router=useRouter(),route=useRoute(),menuOpen=ref(false),version=ref(packageMetadata.version);
watch(()=>route.fullPath,()=>menuOpen.value=false);
onMounted(async()=>{try{const runtimeVersion=(await api.get('/health/live')).data.version;if(runtimeVersion&&runtimeVersion!=='dev')version.value=runtimeVersion}catch{/* O pacote compilado permanece como fallback confiável. */}});
async function exit(){await auth.logout();router.push('/login')}
</script>
<template>
  <div class="shell" :class="{'menu-open':menuOpen}">
    <button class="sidebar-backdrop" aria-label="Fechar menu" @click="menuOpen=false"></button>
    <aside class="sidebar">
      <div class="brand"><div class="brand-mark">AF</div><div><strong>Auditor Fiscal</strong><small>IBS/CBS</small></div></div>
      <nav>
        <RouterLink to="/">Visão geral</RouterLink><RouterLink to="/analises">Auditorias</RouterLink><RouterLink to="/analises/nova">Nova auditoria</RouterLink>
        <RouterLink to="/catalogos">NCM × ClassTrib</RouterLink><RouterLink v-if="auth.isPlatformAdmin" to="/contas">Empresas da plataforma</RouterLink>
        <RouterLink v-if="auth.isAdmin" to="/clientes">Clientes auditados</RouterLink><RouterLink v-if="auth.isAdmin" to="/logs">Logs da aplicação</RouterLink><RouterLink v-if="auth.isAdmin" to="/usuarios">Usuários</RouterLink>
      </nav>
      <div class="sidebar-footer"><div><span>{{auth.user?.name}}</span><small v-if="version">Auditor v{{version}}</small></div><button class="link" @click="exit">Sair</button></div>
    </aside>
    <main>
      <header class="topbar"><button class="mobile-toggle" aria-label="Abrir menu" @click="menuOpen=true">☰</button><div class="topbar-title"><h1>{{String($route.name||'Auditor Fiscal').replaceAll('-',' ')}}</h1><p>Auditoria determinística e rastreável de documentos fiscais</p></div><div class="topbar-meta"><span v-if="version" class="version-chip">v{{version}}</span><span class="env">Produção</span></div></header>
      <div class="content"><RouterView/></div>
    </main>
  </div>
</template>
