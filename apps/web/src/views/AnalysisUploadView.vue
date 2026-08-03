<script setup lang="ts">
import {onMounted,ref} from 'vue';
import {api,messageOf} from '@/lib/api';
import {useRouter} from 'vue-router';

const companies=ref<any[]>([]),catalogs=ref<any[]>([]),files=ref<File[]>([]),form=ref({company_id:'',catalog_version_id:'',name:'',period_start:'',period_end:''}),loading=ref(false),error=ref(''),router=useRouter();

onMounted(async()=>{companies.value=(await api.get('/companies',{params:{per_page:100}})).data.data;catalogs.value=(await api.get('/catalogs')).data.data.filter((catalog:any)=>catalog.status==='published');form.value.company_id=companies.value[0]?.id||'';form.value.catalog_version_id=catalogs.value[0]?.id||''});
function pick(event:Event){files.value=Array.from((event.target as HTMLInputElement).files||[])}
async function submit(){loading.value=true;error.value='';try{const body=new FormData();Object.entries(form.value).forEach(([key,value])=>value&&body.append(key,value));files.value.forEach(file=>body.append('files[]',file));const {data}=await api.post('/analyses',body,{headers:{'Content-Type':'multipart/form-data'}});router.push(`/analises/${data.id}`)}catch(exception){error.value=messageOf(exception)}finally{loading.value=false}}
</script>

<template><form class="card form-grid" @submit.prevent="submit">
  <div class="card-title span-2"><div><h2>Nova auditoria fiscal</h2><p>Escolha o cliente auditado e envie os XMLs e DANFEs correspondentes.</p></div></div>
  <label>Cliente auditado<select v-model="form.company_id" required><option value="" disabled>Selecione o cliente</option><option v-for="client in companies" :key="client.id" :value="client.id">{{client.trade_name||client.legal_name}} · {{client.tax_id}}</option></select></label>
  <label>Catálogo fiscal<select v-model="form.catalog_version_id" required><option v-for="catalog in catalogs" :key="catalog.id" :value="catalog.id">{{catalog.version}} — {{catalog.name}}</option></select></label>
  <p v-if="!companies.length" class="error span-2">Seu usuário não possui nenhum cliente ativo disponível para auditoria.</p>
  <label class="span-2">Nome da auditoria<input v-model="form.name" required placeholder="Ex.: Competência 06/2026 — Dubahia"/></label>
  <label>Início da competência<input v-model="form.period_start" type="date"/></label><label>Fim da competência<input v-model="form.period_end" type="date"/></label>
  <label class="file-drop span-2">XML, ZIP ou DANFE PDF<input type="file" multiple accept=".xml,.zip,.pdf" @change="pick" required/><strong>Selecione um ou vários arquivos</strong><span>{{files.length?`${files.length} arquivo(s) selecionado(s)`:'Limites e proteção contra ZIP bomb são aplicados no processamento.'}}</span></label>
  <p class="error span-2" v-if="error">{{error}}</p>
  <div class="form-actions span-2"><button class="primary" :disabled="loading||!files.length||!form.company_id">{{loading?'Enviando...':'Iniciar auditoria'}}</button></div>
</form></template>
