<script setup lang="ts">
import {onBeforeUnmount,onMounted,ref} from 'vue';
import {api,messageOf} from '@/lib/api';
import {useRouter} from 'vue-router';
import ClientCombobox from '@/components/ClientCombobox.vue';

interface ClientOption {id:string;legal_name:string;trade_name?:string|null;tax_id:string}

const companies=ref<ClientOption[]>([]),catalogs=ref<any[]>([]),files=ref<File[]>([]),form=ref({company_id:'',catalog_version_id:'',name:'',period_start:'',period_end:''}),loading=ref(false),loadingCompanies=ref(false),error=ref(''),companyError=ref(''),router=useRouter();
let companySearchTimer:number|undefined;
let companyRequest=0;

async function loadCompanies(search=''){
  const request=++companyRequest;
  loadingCompanies.value=true;
  companyError.value='';
  try{
    const {data}=await api.get('/companies',{params:{per_page:50,...(search.trim()?{search:search.trim()}:{})}});
    if(request!==companyRequest)return;
    const selected=companies.value.find(company=>company.id===form.value.company_id);
    companies.value=selected&&!data.data.some((company:ClientOption)=>company.id===selected.id)?[selected,...data.data]:data.data;
    if(!form.value.company_id&&!search)form.value.company_id=companies.value[0]?.id||'';
  }catch(exception){
    if(request===companyRequest)companyError.value=messageOf(exception);
  }finally{
    if(request===companyRequest)loadingCompanies.value=false;
  }
}

function searchCompanies(search:string){
  window.clearTimeout(companySearchTimer);
  companySearchTimer=window.setTimeout(()=>void loadCompanies(search),250);
}

onMounted(async()=>{
  await Promise.all([
    loadCompanies(),
    api.get('/catalogs').then(({data})=>{catalogs.value=data.data.filter((catalog:any)=>catalog.status==='published');form.value.catalog_version_id=catalogs.value[0]?.id||''}),
  ]);
});
onBeforeUnmount(()=>window.clearTimeout(companySearchTimer));
function pick(event:Event){files.value=Array.from((event.target as HTMLInputElement).files||[])}
async function submit(){loading.value=true;error.value='';try{const body=new FormData();Object.entries(form.value).forEach(([key,value])=>value&&body.append(key,value));files.value.forEach(file=>body.append('files[]',file));const {data}=await api.post('/analyses',body,{headers:{'Content-Type':'multipart/form-data'}});router.push(`/analises/${data.id}`)}catch(exception){error.value=messageOf(exception)}finally{loading.value=false}}
</script>

<template><form class="card form-grid" @submit.prevent="submit">
  <div class="card-title span-2"><div><h2>Nova auditoria fiscal</h2><p>Escolha o cliente auditado e envie os XMLs e DANFEs correspondentes.</p></div></div>
  <label>Cliente auditado<ClientCombobox v-model="form.company_id" :options="companies" :loading="loadingCompanies" @search="searchCompanies"/><small class="hint">Digite a razão social, o nome fantasia ou o CNPJ.</small></label>
  <label>Catálogo fiscal<select v-model="form.catalog_version_id" required><option v-for="catalog in catalogs" :key="catalog.id" :value="catalog.id">{{catalog.version}} — {{catalog.name}}</option></select></label>
  <p v-if="companyError" class="error span-2">{{companyError}}</p>
  <p v-else-if="!loadingCompanies&&!companies.length" class="error span-2">Seu usuário não possui nenhum cliente ativo disponível para auditoria.</p>
  <label class="span-2">Nome da auditoria<input v-model="form.name" required placeholder="Ex.: Competência 06/2026 — Dubahia"/></label>
  <label>Início da competência<input v-model="form.period_start" type="date"/></label><label>Fim da competência<input v-model="form.period_end" type="date"/></label>
  <label class="file-drop span-2">XML, ZIP ou DANFE PDF<input type="file" multiple accept=".xml,.zip,.pdf" @change="pick" required/><strong>Selecione um ou vários arquivos</strong><span>{{files.length?`${files.length} arquivo(s) selecionado(s)`:'Limites e proteção contra ZIP bomb são aplicados no processamento.'}}</span></label>
  <p class="error span-2" v-if="error">{{error}}</p>
  <div class="form-actions span-2"><button class="primary" :disabled="loading||!files.length||!form.company_id">{{loading?'Enviando...':'Iniciar auditoria'}}</button></div>
</form></template>
