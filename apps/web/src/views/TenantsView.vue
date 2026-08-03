<script setup lang="ts">
import {onMounted,ref} from 'vue';
import {api,messageOf} from '@/lib/api';

const rows=ref<any[]>([]),editing=ref<string>(),loadingLookup=ref(false),error=ref('');
const empty=()=>({legal_name:'',trade_name:'',tax_id:'',email:'',phone:'',active:true});
const form=ref<any>(empty());

async function load(){rows.value=(await api.get('/accounts',{params:{per_page:100}})).data.data}
onMounted(load);
function edit(row:any){editing.value=row.id;form.value={legal_name:row.legal_name,trade_name:row.trade_name||'',tax_id:row.tax_id,email:row.email||'',phone:row.phone||'',active:row.active};error.value=''}
function cancel(){editing.value=undefined;form.value=empty();error.value=''}
async function lookup(){loadingLookup.value=true;error.value='';try{const {data}=await api.post('/accounts/lookup-cnpj',{cnpj:form.value.tax_id});form.value={...form.value,...data,tax_id:data.tax_id}}catch(e){error.value=messageOf(e)}finally{loadingLookup.value=false}}
async function save(){try{if(editing.value)await api.patch(`/accounts/${editing.value}`,form.value);else await api.post('/accounts',form.value);cancel();await load()}catch(e){error.value=messageOf(e)}}
</script>

<template><div class="split">
  <section class="card">
    <div class="card-title"><div><h2>Empresas clientes da plataforma</h2><p>Contas assinantes, como Codesplan, que possuem usuários e uma carteira própria de clientes auditados.</p></div></div>
    <div class="table-scroll"><table><thead><tr><th>Empresa</th><th>CNPJ</th><th>Clientes auditados</th><th>Usuários</th><th>Status</th><th></th></tr></thead><tbody>
      <tr v-for="row in rows" :key="row.id"><td>{{row.legal_name}}<small v-if="row.trade_name"><br>{{row.trade_name}}</small></td><td><code>{{row.tax_id||'Cadastro pendente'}}</code></td><td>{{row.companies_count}}</td><td>{{row.users_count}}</td><td>{{row.active?'Ativa':'Inativa'}}</td><td><button @click="edit(row)">Editar</button></td></tr>
    </tbody></table></div>
  </section>
  <form class="card form-grid one" @submit.prevent="save">
    <h2>{{editing?'Editar empresa da plataforma':'Nova empresa da plataforma'}}</h2>
    <label>CNPJ<div class="actions"><input v-model="form.tax_id" inputmode="numeric" maxlength="18" required><button type="button" :disabled="loadingLookup" @click="lookup">{{loadingLookup?'Consultando...':'Consultar CNPJ'}}</button></div></label>
    <p class="hint">O usuário master pode corrigir o CNPJ inclusive da empresa modelo pré-cadastrada.</p>
    <label>Razão social<input v-model="form.legal_name" required></label>
    <label>Nome fantasia<input v-model="form.trade_name"></label>
    <label>E-mail<input v-model="form.email" type="email"></label>
    <label>Telefone<input v-model="form.phone"></label>
    <label v-if="editing"><span><input v-model="form.active" type="checkbox" style="width:auto"> Empresa ativa</span></label>
    <p v-if="error" class="error">{{error}}</p>
    <div class="form-actions"><button v-if="editing" type="button" @click="cancel">Cancelar</button><button class="primary">{{editing?'Salvar alterações':'Cadastrar empresa'}}</button></div>
  </form>
</div></template>
