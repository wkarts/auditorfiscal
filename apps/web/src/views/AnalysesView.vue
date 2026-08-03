<script setup lang="ts">
import {computed,onBeforeUnmount,onMounted,ref} from 'vue';
import {api,dateTime,messageOf} from '@/lib/api';
import {useAuthStore} from '@/stores/auth';
import StatusBadge from '@/components/StatusBadge.vue';
import PaginationBar from '@/components/PaginationBar.vue';
import AnalysisActionDialog from '@/components/AnalysisActionDialog.vue';

type LifecycleAction='cancel'|'delete'|'restore';
const auth=useAuthStore();
const rows=ref<any[]>([]),meta=ref<any>(),summary=ref<any>({}),status=ref(''),visibility=ref<'active'|'deleted'>('active'),search=ref('');
const loading=ref(true),pageError=ref(''),actionError=ref(''),actionBusy=ref(false),pending=ref<{action:LifecycleAction;row:any}|null>(null);
let pollTimer:number|undefined;
const hasActive=computed(()=>rows.value.some(row=>['uploading','queued','processing','retrying','cancelling'].includes(row.status)));
const stats=computed(()=>[
  {label:visibility.value==='deleted'?'Excluídas':'Total de auditorias',value:summary.value.total||0,tone:'neutral'},
  {label:'Em andamento',value:summary.value.active||0,tone:'active'},
  {label:'Concluídas',value:summary.value.completed||0,tone:'success'},
  {label:'Com falha',value:summary.value.failed||0,tone:'danger'},
  {label:'Canceladas',value:summary.value.cancelled||0,tone:'muted'},
]);

onMounted(()=>load());
onBeforeUnmount(()=>window.clearTimeout(pollTimer));
async function load(page=1,silent=false){
  window.clearTimeout(pollTimer);
  if(!silent)loading.value=true;
  try{
    const {data}=await api.get('/analyses',{params:{page,status:status.value||undefined,visibility:visibility.value,search:search.value.trim()||undefined}});
    rows.value=data.data;meta.value=data;summary.value=data.summary||{};pageError.value='';
    if(rows.value.some(row=>['uploading','queued','processing','retrying','cancelling'].includes(row.status)))pollTimer=window.setTimeout(()=>load(Number(meta.value?.current_page||1),true),8000);
  }catch(error){pageError.value=messageOf(error)}finally{loading.value=false}
}
function changeVisibility(value:'active'|'deleted'){visibility.value=value;status.value='';load(1)}
function openAction(action:LifecycleAction,row:any){pending.value={action,row};actionError.value=''}
function closeAction(){if(!actionBusy.value)pending.value=null}
async function confirmAction(reason:string){
  if(!pending.value)return;
  actionBusy.value=true;actionError.value='';
  try{
    const {action,row}=pending.value;
    if(action==='cancel')await api.post(`/analyses/${row.id}/cancel`,{reason:reason||undefined});
    else if(action==='delete')await api.delete(`/analyses/${row.id}`,{data:{reason:reason||undefined}});
    else await api.post(`/analyses/${row.id}/restore`,{reason:reason||undefined});
    pending.value=null;await load(Number(meta.value?.current_page||1));
  }catch(error){actionError.value=messageOf(error)}finally{actionBusy.value=false}
}
function progress(row:any){return Math.max(0,Math.min(100,Math.round(Number(row.progress||0)*100)))}
</script>

<template>
  <div class="audit-workspace">
    <section class="audit-command card">
      <div>
        <span class="eyebrow">Central operacional</span>
        <h2>Auditorias fiscais</h2>
        <p>Monitore cada lote, intervenha com segurança e preserve todo o histórico fiscal.</p>
      </div>
      <RouterLink class="primary primary-prominent" to="/analises/nova"><span aria-hidden="true">＋</span> Nova auditoria</RouterLink>
    </section>

    <section class="audit-stats" aria-label="Resumo das auditorias">
      <article v-for="item in stats" :key="item.label" :class="`stat-${item.tone}`"><span>{{item.label}}</span><strong>{{item.value}}</strong></article>
    </section>

    <section class="card audit-list-card">
      <div class="audit-list-heading">
        <div class="segmented-control" aria-label="Visibilidade">
          <button :class="{active:visibility==='active'}" @click="changeVisibility('active')">Ativas</button>
          <button v-if="auth.isAdmin" :class="{active:visibility==='deleted'}" @click="changeVisibility('deleted')">Excluídas</button>
        </div>
        <form class="audit-filters" @submit.prevent="load(1)">
          <label class="search-field"><span class="sr-only">Pesquisar</span><input v-model="search" type="search" placeholder="Buscar auditoria, cliente ou CNPJ"><button type="submit">Buscar</button></label>
          <select v-model="status" aria-label="Filtrar por status" @change="load(1)">
            <option value="">Todos os status</option><option value="uploading">Recebendo</option><option value="queued">Na fila</option><option value="processing">Processando</option><option value="retrying">Nova tentativa</option><option value="cancelling">Cancelando</option><option value="completed">Concluída</option><option value="failed">Falhou</option><option value="cancelled">Cancelada</option><option value="superseded">Substituída</option>
          </select>
          <button type="button" class="icon-button" aria-label="Atualizar lista" :disabled="loading" @click="load(Number(meta?.current_page||1))">↻</button>
        </form>
      </div>

      <p v-if="pageError" class="error">{{pageError}}</p>
      <div v-if="loading" class="audit-loading"><span></span><span></span><span></span></div>
      <div v-else-if="rows.length" class="table-scroll audit-table-scroll">
        <table class="audit-table">
          <thead><tr><th>Auditoria e cliente</th><th>Status e progresso</th><th>Resultado</th><th>Ocorrência recente</th><th>Criação</th><th><span class="sr-only">Ações</span></th></tr></thead>
          <tbody>
            <tr v-for="row in rows" :key="row.id" :class="{'is-deleted':row.deleted_at}">
              <td data-label="Auditoria e cliente"><RouterLink v-if="!row.deleted_at" class="audit-name" :to="`/analises/${row.id}`">{{row.name}}</RouterLink><strong v-else class="audit-name">{{row.name}}</strong><span class="audit-company">{{row.company?.legal_name||'Cliente não identificado'}}</span><small>{{row.catalog_version?.version||'Catálogo não informado'}}</small></td>
              <td data-label="Status e progresso"><StatusBadge :value="row.status"/><div class="progress-track" :aria-label="`Progresso: ${progress(row)}%`"><span :style="{width:`${progress(row)}%`}"></span></div><small>{{progress(row)}}% processado</small></td>
              <td data-label="Resultado"><div class="result-pair"><span><strong>{{row.documents_count||0}}</strong> documentos</span><span><strong>{{row.findings_count||0}}</strong> achados</span></div></td>
              <td data-label="Ocorrência recente" class="audit-occurrence"><template v-if="row.error"><code>{{row.error.code||'ANALYSIS_FAILED'}}</code><span>{{row.error.message}}</span></template><span v-else-if="row.status==='cancelled'">Processamento cancelado com rastreabilidade preservada.</span><span v-else>Sem ocorrências críticas.</span></td>
              <td data-label="Criação"><time>{{dateTime(row.created_at)}}</time><small v-if="row.deleted_at">Excluída em {{dateTime(row.deleted_at)}}</small></td>
              <td data-label="Ações" class="row-actions"><RouterLink v-if="!row.deleted_at" class="compact-action" :to="`/analises/${row.id}`">Abrir</RouterLink><button v-if="row.can_cancel" class="compact-action button-warning" @click="openAction('cancel',row)">Cancelar</button><button v-if="row.can_delete" class="compact-action button-danger-subtle" @click="openAction('delete',row)">Excluir</button><button v-if="row.can_restore" class="compact-action button-success" @click="openAction('restore',row)">Restaurar</button></td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-else class="empty-audit-state"><div aria-hidden="true">◎</div><h3>{{visibility==='deleted'?'Nenhuma auditoria excluída':'Nenhuma auditoria encontrada'}}</h3><p>{{search||status?'Revise os filtros aplicados.':'Importe XML ou ZIP para iniciar uma análise fiscal rastreável.'}}</p><RouterLink v-if="visibility==='active'&&!search&&!status" class="primary" to="/analises/nova">Criar primeira auditoria</RouterLink></div>
      <div class="audit-list-footer"><small v-if="hasActive">Atualização automática ativa a cada 8 segundos.</small><span v-else></span><PaginationBar v-if="meta" :meta="meta" @change="load"/></div>
    </section>
  </div>

  <AnalysisActionDialog v-if="pending" :action="pending.action" :name="pending.row.name" :busy="actionBusy" :error="actionError" @close="closeAction" @confirm="confirmAction"/>
</template>
