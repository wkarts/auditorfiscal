<script setup lang="ts">
import {ref,watch} from 'vue';
import {useAuthStore} from '@/stores/auth';
import {useRoute,useRouter} from 'vue-router';
const auth=useAuthStore(),router=useRouter(),route=useRoute(),menuOpen=ref(false);
watch(()=>route.fullPath,()=>menuOpen.value=false);
async function exit(){await auth.logout();router.push('/login')}
</script>
<template>
  <div class="shell" :class="{'menu-open':menuOpen}">
    <button class="sidebar-backdrop" aria-label="Fechar menu" @click="menuOpen=false"></button>
    <aside class="sidebar">
      <div class="brand"><div class="brand-mark">AF</div><div><strong>Auditor Fiscal</strong><small>IBS/CBS</small></div></div>
      <nav>
        <RouterLink to="/">Visão geral</RouterLink><RouterLink to="/analises">Auditorias</RouterLink><RouterLink to="/analises/nova">Nova auditoria</RouterLink>
        <RouterLink to="/catalogos">NCM × ClassTrib</RouterLink><RouterLink v-if="auth.isAdmin" to="/tenants">Tenants</RouterLink>
        <RouterLink v-if="auth.isAdmin" to="/empresas">Empresas</RouterLink><RouterLink v-if="auth.isAdmin" to="/logs">Logs da aplicação</RouterLink><RouterLink v-if="auth.isAdmin" to="/usuarios">Usuários</RouterLink>
      </nav>
      <div class="sidebar-footer"><span>{{auth.user?.name}}</span><button class="link" @click="exit">Sair</button></div>
    </aside>
    <main>
      <header class="topbar"><button class="mobile-toggle" aria-label="Abrir menu" @click="menuOpen=true">☰</button><div><h1>{{String($route.name||'Auditor Fiscal').replaceAll('-',' ')}}</h1><p>Auditoria determinística e rastreável de documentos fiscais</p></div><span class="env">Produção</span></header>
      <div class="content"><RouterView/></div>
    </main>
  </div>
</template>
