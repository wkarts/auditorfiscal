<script setup lang="ts">
import {onMounted,ref} from 'vue';
import {api,messageOf} from '@/lib/api';
const rows=ref<any[]>([]),editing=ref<string>(),loadingLookup=ref(false),error=ref('');
const empty=()=>({legal_name:'',trade_name:'',tax_id:'',email:'',phone:'',active:true});
const form=ref<any>(empty());
async function load(){rows.value=(await api.get('/tenants',{params:{per_page:100}})).data.data}
onMounted(load);
function edit(row:any){editing.value=row.id;form.value={legal_name:row.legal_name,trade_name:row.trade_name||'',tax_id:row.tax_id,email:row.email||'',phone:row.phone||'',active:row.active};error.value=''}
function cancel(){editing.value=undefined;form.value=empty();error.value=''}
async function lookup(){loadingLookup.value=true;error.value='';try{const {data}=await api.post('/tenants/lookup-cnpj',{cnpj:form.value.tax_id});form.value={...form.value,...data,tax_id:data.tax_id}}catch(e){error.value=messageOf(e)}finally{loadingLookup.value=false}}
async function save(){try{if(editing.value)await api.patch(`/tenants/${editing.value}`,form.value);else await api.post('/tenants',form.value);cancel();await load()}catch(e){error.value=messageOf(e)}}
</script>
<template><div class="split">
  <section class="card"><div class="card-title"><div><h2>Tenants</h2><p>Organizações responsáveis por uma ou várias empresas.</p></div></div><div class="table-scroll"><table><thead><tr><th>Razão social</th><th>CNPJ</th><th>Empresas</th><th>Usuários</th><th>Status</th><th></th></tr></thead><tbody><tr v-for="row in rows" :key="row.id"><td>{{row.legal_name}}<small v-if="row.trade_name"><br>{{row.trade_name}}</small></td><td><code>{{row.tax_id}}</code></td><td>{{row.companies_count}}</td><td>{{row.users_count}}</td><td>{{row.active?'Ativo':'Inativo'}}</td><td><button @click="edit(row)">Editar</button></td></tr></tbody></table></div></section>
  <form class="card form-grid one" @submit.prevent="save"><h2>{{editing?'Editar tenant':'Novo tenant'}}</h2><label>CNPJ<div class="actions"><input v-model="form.tax_id" inputmode="numeric" maxlength="18" required :disabled="!!editing"><button type="button" :disabled="loadingLookup||!!editing" @click="lookup">{{loadingLookup?'Consultando...':'Consultar CNPJ'}}</button></div></label><p class="hint">Consulta pública assistida; confirme os dados antes de salvar. O cadastro manual continua disponível.</p><label>Razão social<input v-model="form.legal_name" required></label><label>Nome fantasia<input v-model="form.trade_name"></label><label>E-mail<input v-model="form.email" type="email"></label><label>Telefone<input v-model="form.phone"></label><label v-if="editing"><span><input v-model="form.active" type="checkbox" style="width:auto"> Tenant ativo</span></label><p v-if="error" class="error">{{error}}</p><div class="form-actions"><button v-if="editing" type="button" @click="cancel">Cancelar</button><button class="primary">{{editing?'Salvar alterações':'Cadastrar tenant'}}</button></div></form>
</div></template>
