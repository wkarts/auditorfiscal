<script setup lang="ts">
import {onMounted,ref} from 'vue';
import {api,messageOf} from '@/lib/api';
import {useAuthStore} from '@/stores/auth';

const auth=useAuthStore();
const rows=ref<any[]>([]),accounts=ref<any[]>([]),editing=ref<string>(),loadingLookup=ref(false),error=ref('');
const empty=()=>({account_id:auth.user?.tenant_id||'',legal_name:'',trade_name:'',tax_id:'',state_registration:'',active:true});
const form=ref<any>(empty());

async function load(){const [clientsResponse,accountsResponse]=await Promise.all([api.get('/clients',{params:{per_page:100}}),api.get('/accounts',{params:{per_page:100}})]);rows.value=clientsResponse.data.data;accounts.value=accountsResponse.data.data;if(!form.value.account_id)form.value.account_id=auth.user?.tenant_id||accounts.value[0]?.id||''}
onMounted(load);
function edit(row:any){editing.value=row.id;form.value={account_id:row.tenant_id,legal_name:row.legal_name,trade_name:row.trade_name||'',tax_id:row.tax_id,state_registration:row.state_registration||'',active:row.active};error.value=''}
function cancel(){editing.value=undefined;form.value=empty();if(!form.value.account_id)form.value.account_id=accounts.value[0]?.id||'';error.value=''}
async function lookup(){loadingLookup.value=true;error.value='';try{const {data}=await api.post('/clients/lookup-cnpj',{cnpj:form.value.tax_id});form.value={...form.value,...data,tax_id:data.tax_id}}catch(e){error.value=messageOf(e)}finally{loadingLookup.value=false}}
async function save(){try{if(editing.value)await api.patch(`/clients/${editing.value}`,form.value);else await api.post('/clients',form.value);cancel();await load()}catch(e){error.value=messageOf(e)}}
</script>

<template><div class="split">
  <section class="card">
    <div class="card-title"><div><h2>Clientes auditados</h2><p>Clientes da empresa assinante para os quais serão enviados XMLs, gerados DANFEs e emitidos relatórios.</p></div></div>
    <div class="table-scroll"><table><thead><tr><th>Cliente</th><th v-if="auth.isPlatformAdmin">Empresa da plataforma</th><th>CNPJ</th><th>IE</th><th>Status</th><th></th></tr></thead><tbody>
      <tr v-for="client in rows" :key="client.id"><td>{{client.legal_name}}<small v-if="client.trade_name"><br>{{client.trade_name}}</small></td><td v-if="auth.isPlatformAdmin">{{client.tenant?.trade_name||client.tenant?.legal_name}}</td><td><code>{{client.tax_id}}</code></td><td>{{client.state_registration||'—'}}</td><td>{{client.active?'Ativo':'Inativo'}}</td><td><button @click="edit(client)">Editar</button></td></tr>
    </tbody></table></div>
  </section>
  <form class="card form-grid one" @submit.prevent="save">
    <h2>{{editing?'Editar cliente auditado':'Novo cliente auditado'}}</h2>
    <label v-if="auth.isPlatformAdmin">Empresa da plataforma<select v-model="form.account_id" required><option value="" disabled>Selecione a conta</option><option v-for="account in accounts" :key="account.id" :value="account.id">{{account.trade_name||account.legal_name}} · {{account.tax_id}}</option></select></label>
    <label>CNPJ<div class="actions"><input v-model="form.tax_id" inputmode="numeric" maxlength="18" required><button type="button" :disabled="loadingLookup" @click="lookup">{{loadingLookup?'Consultando...':'Consultar CNPJ'}}</button></div></label>
    <p class="hint">Este nome e CNPJ identificam o auditado nos relatórios. O CNPJ pode ser corrigido depois.</p>
    <label>Razão social<input v-model="form.legal_name" required></label>
    <label>Nome fantasia<input v-model="form.trade_name"></label>
    <label>Inscrição estadual<input v-model="form.state_registration"></label>
    <label v-if="editing"><span><input v-model="form.active" type="checkbox" style="width:auto"> Cliente ativo</span></label>
    <p v-if="error" class="error">{{error}}</p>
    <div class="form-actions"><button v-if="editing" type="button" @click="cancel">Cancelar</button><button class="primary">{{editing?'Salvar alterações':'Cadastrar cliente'}}</button></div>
  </form>
</div></template>
