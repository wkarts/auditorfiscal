<script setup lang="ts">
import {computed,onMounted,ref,watch} from 'vue';
import {api,messageOf} from '@/lib/api';
import {useAuthStore} from '@/stores/auth';

const auth=useAuthStore();
const users=ref<any[]>([]),roles=ref<any[]>([]),clients=ref<any[]>([]),accounts=ref<any[]>([]),editing=ref<number>(),error=ref('');
const empty=()=>({name:'',email:'',password:'',role:'Auditor Fiscal',account_id:auth.user?.tenant_id||'',access_mode:'selected',client_ids:[] as string[],active:true});
const form=ref<any>(empty());
const availableClients=computed(()=>clients.value.filter(client=>client.tenant_id===form.value.account_id));
const canSave=computed(()=>!!form.value.account_id&&(form.value.role==='Administrador'||form.value.access_mode==='all'||form.value.client_ids.length>0));

async function load(){const [usersResponse,rolesResponse,clientsResponse,accountsResponse]=await Promise.all([api.get('/users'),api.get('/users/roles'),api.get('/clients',{params:{per_page:100}}),api.get('/accounts',{params:{per_page:100}})]);users.value=usersResponse.data.data;roles.value=rolesResponse.data;clients.value=clientsResponse.data.data;accounts.value=accountsResponse.data.data;if(!form.value.account_id)form.value.account_id=auth.user?.tenant_id||accounts.value[0]?.id||''}
onMounted(load);
watch(()=>form.value.account_id,()=>{form.value.client_ids=form.value.client_ids.filter((id:string)=>availableClients.value.some(client=>client.id===id))});
function hasAllClients(user:any){return user.all_clients||user.roles?.some((role:any)=>role.name==='Administrador')}
function edit(user:any){const assigned=user.clients||user.companies||[];editing.value=user.id;form.value={name:user.name,email:user.email,password:'',role:user.roles[0]?.name||'Consulta',account_id:user.tenant_id,access_mode:hasAllClients(user)?'all':'selected',client_ids:assigned.map((client:any)=>client.id),active:user.active};error.value=''}
function cancel(){editing.value=undefined;form.value=empty();if(!form.value.account_id)form.value.account_id=accounts.value[0]?.id||'';error.value=''}
function selectMode(mode:'all'|'selected'){form.value.access_mode=mode;if(mode==='all')form.value.client_ids=[]}
async function save(){try{const payload={...form.value,all_clients:form.value.access_mode==='all',client_ids:form.value.access_mode==='all'?[]:form.value.client_ids};delete payload.access_mode;if(editing.value&&!payload.password)delete payload.password;if(editing.value)await api.patch(`/users/${editing.value}`,payload);else await api.post('/users',payload);cancel();await load()}catch(e){error.value=messageOf(e)}}
</script>

<template><div class="split">
  <section class="card">
    <div class="card-title"><div><h2>Usuários das empresas da plataforma</h2><p>Cada usuário pertence a uma empresa assinante e acessa todos ou apenas clientes auditados selecionados.</p></div></div>
    <div class="table-scroll"><table><thead><tr><th>Nome</th><th>E-mail</th><th v-if="auth.isPlatformAdmin">Empresa</th><th>Perfil</th><th>Clientes permitidos</th><th>Status</th><th></th></tr></thead><tbody>
      <tr v-for="user in users" :key="user.id"><td>{{user.name}}</td><td>{{user.email}}</td><td v-if="auth.isPlatformAdmin">{{user.account?.trade_name||user.account?.legal_name}}</td><td>{{user.roles.map((role:any)=>role.name).join(', ')}}</td><td>{{hasAllClients(user)?'Todos os clientes':`${(user.clients||[]).length} cliente(s)`}}</td><td>{{user.active?'Ativo':'Inativo'}}</td><td><button @click="edit(user)">Editar</button></td></tr>
    </tbody></table></div>
  </section>
  <form class="card form-grid one" @submit.prevent="save">
    <h2>{{editing?'Editar usuário':'Novo usuário'}}</h2>
    <label v-if="auth.isPlatformAdmin">Empresa da plataforma<select v-model="form.account_id" required><option value="" disabled>Selecione a empresa</option><option v-for="account in accounts" :key="account.id" :value="account.id">{{account.trade_name||account.legal_name}} · {{account.tax_id}}</option></select></label>
    <label>Nome<input v-model="form.name" required></label>
    <label>E-mail de acesso<input v-model="form.email" type="email" required></label>
    <label>{{editing?'Nova senha (opcional)':'Senha inicial'}}<input v-model="form.password" type="password" minlength="12" :required="!editing"></label>
    <label>Perfil<select v-model="form.role"><option v-for="role in roles" :key="role.id">{{role.name}}</option></select></label>
    <fieldset><legend>Escopo de acesso</legend><div class="selection-list">
      <label><input type="radio" name="access-mode" :checked="form.access_mode==='all'" @change="selectMode('all')"><span><strong>Todos os clientes da empresa</strong><small><br>Inclui clientes atuais e futuros somente da empresa assinante selecionada.</small></span></label>
      <label><input type="radio" name="access-mode" :checked="form.access_mode==='selected'" @change="selectMode('selected')"><span><strong>Clientes selecionados</strong><small><br>Limita auditorias, relatórios, notas e logs aos clientes marcados.</small></span></label>
    </div></fieldset>
    <fieldset v-if="form.access_mode==='selected'"><legend>Clientes permitidos</legend><div class="selection-list">
      <label v-for="client in availableClients" :key="client.id"><input v-model="form.client_ids" type="checkbox" :value="client.id"><span>{{client.trade_name||client.legal_name}}<small><br>{{client.tax_id}}</small></span></label>
      <span v-if="!availableClients.length" class="hint">Cadastre um cliente para a empresa selecionada.</span>
    </div></fieldset>
    <label v-if="editing"><span><input v-model="form.active" type="checkbox" style="width:auto"> Usuário ativo</span></label>
    <p v-if="error" class="error">{{error}}</p>
    <div class="form-actions"><button v-if="editing" type="button" @click="cancel">Cancelar</button><button class="primary" :disabled="!canSave">{{editing?'Salvar alterações':'Cadastrar usuário'}}</button></div>
  </form>
</div></template>
