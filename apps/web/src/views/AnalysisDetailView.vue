<script setup lang="ts">
import {computed,onBeforeUnmount,onMounted,ref} from 'vue';
import {useRoute,useRouter} from 'vue-router';
import {AgGridVue} from 'ag-grid-vue3';
import {api,dateTime,money,messageOf} from '@/lib/api';
import StatusBadge from '@/components/StatusBadge.vue';

const route=useRoute(),router=useRouter(),id=String(route.params.id);
const batch=ref<any>(),tab=ref('documents'),documents=ref<any[]>([]),findings=ref<any[]>([]),logs=ref<any[]>([]);
const selected=ref<any>(),loading=ref(true),reprocessing=ref(false),pageError=ref('');
let pollTimer:number|undefined;

const docCols=[
  {field:'number',headerName:'NF',width:100},
  {field:'issued_at',headerName:'Emissão',valueFormatter:(p:any)=>dateTime(p.value),width:160},
  {field:'direction',headerName:'Direção',width:110},
  {field:'issuer_tax_id',headerName:'Emitente',width:150},
  {field:'recipient_tax_id',headerName:'Destinatário',width:150},
  {field:'total_value',headerName:'Valor NF',valueFormatter:(p:any)=>money(p.value),width:150},
  {field:'ibs_cbs_base',headerName:'Base IBS/CBS',valueFormatter:(p:any)=>money(p.value),width:150},
  {field:'ibs_value',headerName:'IBS',valueFormatter:(p:any)=>money(p.value),width:120},
  {field:'cbs_value',headerName:'CBS',valueFormatter:(p:any)=>money(p.value),width:120},
  {field:'items_count',headerName:'Itens',width:90},
  {field:'status',headerName:'Status',width:120},
];
const findingCols=[
  {field:'severity',headerName:'Severidade',width:120},
  {field:'category',headerName:'Categoria',width:160},
  {field:'rule_code',headerName:'Regra',width:190},
  {field:'title',headerName:'Achado',flex:1,minWidth:260},
  {field:'fiscal_document.number',headerName:'NF',width:100},
  {field:'status',headerName:'Status',width:120},
];

onMounted(load);
onBeforeUnmount(()=>window.clearTimeout(pollTimer));

async function load(){
  window.clearTimeout(pollTimer);
  try{
    const [{data:batchData}]=await Promise.all([api.get(`/analyses/${id}`),loadDocuments(),loadFindings(),loadLogs()]);
    batch.value=batchData;
    pageError.value='';
    if(['uploading','queued','processing','retrying'].includes(batchData.status))pollTimer=window.setTimeout(load,5000);
  }catch(error){pageError.value=messageOf(error)}finally{loading.value=false}
}
async function loadDocuments(){documents.value=(await api.get(`/analyses/${id}/documents`,{params:{per_page:500}})).data.data}
async function loadFindings(){findings.value=(await api.get(`/analyses/${id}/findings`,{params:{per_page:500}})).data.data}
async function loadLogs(){logs.value=(await api.get(`/analyses/${id}/logs`,{params:{per_page:200}})).data.data}
async function openDoc(e:any){selected.value=(await api.get(`/analyses/${id}/documents/${e.data.id}`)).data}
async function download(r:any){
  const res=await api.get(`/reports/${r.id}/download`,{responseType:'blob'});
  const url=URL.createObjectURL(res.data),a=document.createElement('a');
  a.href=url;a.download=`auditoria-${id}.${r.type==='pdf'?'pdf':'xlsx'}`;a.click();URL.revokeObjectURL(url);
}
async function resolve(f:any){
  const notes=prompt('Informe a justificativa da resolução:');
  if(notes===null)return;
  await api.patch(`/analyses/${id}/findings/${f.id}`,{status:'resolved',resolution_notes:notes});
  await loadFindings();
}
async function reprocess(){
  if(!confirm('Criar uma nova auditoria usando os mesmos arquivos de origem? O lote atual será preservado para rastreabilidade.'))return;
  reprocessing.value=true;pageError.value='';
  try{
    const {data}=await api.post(`/analyses/${id}/reprocess`);
    await router.push(`/analises/${data.id}`);
  }catch(error){pageError.value=messageOf(error)}finally{reprocessing.value=false}
}

const totals=computed(()=>batch.value?.summary||{});
const failure=computed(()=>batch.value?.error||{});
const active=computed(()=>['uploading','queued','processing','retrying'].includes(batch.value?.status));
const logContext=(entry:any)=>Object.keys(entry.context||{}).length?JSON.stringify(entry.context,null,2):'';
</script>

<template>
  <div v-if="loading" class="card">Carregando auditoria...</div>
  <template v-else-if="batch">
    <p v-if="pageError" class="error">{{pageError}}</p>
    <section class="card hero">
      <div>
        <div class="eyebrow">{{batch.company.legal_name}} · {{batch.catalog_version.version}}</div>
        <h2>{{batch.name}}</h2>
        <p>{{batch.period_start||'Período não informado'}} a {{batch.period_end||'—'}} · Criada em {{dateTime(batch.created_at)}}</p>
        <p v-if="batch.reprocessed_from" class="reprocess-hint">Reprocessada de <RouterLink :to="`/analises/${batch.reprocessed_from.id}`">{{batch.reprocessed_from.name}}</RouterLink>.</p>
      </div>
      <div class="actions">
        <StatusBadge :value="batch.status"/>
        <button v-if="batch.can_reprocess" :disabled="reprocessing" @click="reprocess">{{reprocessing?'Enviando...':'Reprocessar auditoria'}}</button>
        <button v-for="r in batch.reports" :key="r.id" @click="download(r)">Baixar {{r.type.toUpperCase()}}</button>
      </div>
    </section>

    <section v-if="batch.status==='failed'" class="card failure-card">
      <div class="failure-heading"><div><span class="badge badge-error">Falha</span><h3>Não foi possível concluir esta auditoria</h3></div><button v-if="batch.can_reprocess" :disabled="reprocessing" @click="reprocess">Reprocessar agora</button></div>
      <p>{{failure.message||'O processamento terminou com erro. Consulte os dados técnicos e o histórico abaixo.'}}</p>
      <dl>
        <dt>Código</dt><dd><code>{{failure.code||'ANALYSIS_FAILED'}}</code></dd>
        <dt>Incidente</dt><dd><code>{{failure.incident_id||'—'}}</code></dd>
        <dt>Tentativa</dt><dd>{{failure.attempt||batch.attempt_count||'—'}}</dd>
        <dt>Ocorrido em</dt><dd>{{dateTime(failure.occurred_at||batch.finished_at)}}</dd>
        <template v-if="failure.technical_message"><dt>Detalhe técnico</dt><dd><code>{{failure.technical_message}}</code></dd></template>
        <template v-if="failure.response_body"><dt>Resposta do serviço</dt><dd><code>{{failure.response_body}}</code></dd></template>
      </dl>
    </section>

    <section class="kpis">
      <article><span>Documentos</span><strong>{{batch.document_count}}</strong></article>
      <article><span>Valor total</span><strong>{{money(totals.total_value)}}</strong></article>
      <article><span>Base IBS/CBS</span><strong>{{money(totals.ibs_cbs_base)}}</strong></article>
      <article><span>Tentativas</span><strong>{{batch.attempt_count||0}}</strong></article>
    </section>

    <p v-if="active" class="hint">Processamento em andamento. Esta página atualiza automaticamente a cada 5 segundos.</p>
    <p v-else-if="!batch.can_reprocess&&batch.reprocess_block_reason" class="hint">{{batch.reprocess_block_reason}}</p>

    <div class="tabs">
      <button :class="{active:tab==='documents'}" @click="tab='documents'">Documentos</button>
      <button :class="{active:tab==='findings'}" @click="tab='findings'">Críticas ({{findings.length}})</button>
      <button :class="{active:tab==='logs'}" @click="tab='logs'">Processamento e erros ({{batch.application_logs_count}})</button>
      <button :class="{active:tab==='method'}" @click="tab='method'">Método e snapshot</button>
    </div>

    <section class="card" v-show="tab==='documents'">
      <AgGridVue theme="legacy" class="ag-theme-quartz grid" :rowData="documents" :columnDefs="docCols" :pagination="true" :paginationPageSize="50" @rowClicked="openDoc"/>
    </section>
    <section class="card" v-show="tab==='findings'">
      <AgGridVue theme="legacy" class="ag-theme-quartz grid" :rowData="findings" :columnDefs="findingCols" :pagination="true" :paginationPageSize="50" @rowDoubleClicked="resolve($event.data)"/>
      <p class="hint">Clique duas vezes em uma crítica para registrar a resolução.</p>
    </section>
    <section class="card" v-show="tab==='logs'">
      <div v-if="logs.length" class="log-timeline">
        <article v-for="entry in logs" :key="entry.id" class="log-entry">
          <span :class="`log-marker badge-${entry.level}`"></span>
          <div>
            <div class="log-heading"><strong>{{entry.event}}</strong><StatusBadge :value="entry.level"/><time>{{dateTime(entry.created_at)}}</time></div>
            <p>{{entry.message}}</p>
            <p class="hint">{{entry.component}}<template v-if="entry.attempt"> · tentativa {{entry.attempt}}</template><template v-if="entry.incident_id"> · incidente <code>{{entry.incident_id}}</code></template></p>
            <details v-if="logContext(entry)"><summary>Contexto técnico</summary><pre>{{logContext(entry)}}</pre></details>
          </div>
        </article>
      </div>
      <p v-else class="empty-state">Nenhum evento foi registrado para esta auditoria.</p>
    </section>
    <section class="card prose" v-show="tab==='method'">
      <h3>Rastreabilidade</h3>
      <dl>
        <dt>Versão de catálogo</dt><dd>{{batch.catalog_version.version}}</dd>
        <dt>Hash da fonte</dt><dd><code>{{batch.catalog_version.source_sha256}}</code></dd>
        <dt>Regra de cálculo</dt><dd>UB16-10, calculada item a item com Decimal e arredondamento configurado.</dd>
        <dt>Snapshot</dt><dd>Os resultados não mudam quando uma versão posterior do catálogo é publicada.</dd>
        <template v-if="batch.reprocesses?.length"><dt>Reprocessamentos</dt><dd><RouterLink v-for="item in batch.reprocesses" :key="item.id" :to="`/analises/${item.id}`">{{item.name}} ({{item.status}})<br></RouterLink></dd></template>
      </dl>
    </section>

    <div class="drawer" v-if="selected">
      <button class="drawer-close" @click="selected=null">×</button><h2>NF {{selected.number}}</h2><p><code>{{selected.access_key}}</code></p>
      <div class="kpis compact"><article><span>Valor</span><strong>{{money(selected.total_value)}}</strong></article><article><span>Base</span><strong>{{money(selected.ibs_cbs_base)}}</strong></article></div>
      <h3>Itens</h3><table><thead><tr><th>#</th><th>Descrição</th><th>NCM/EX</th><th>Encontrado</th><th>Esperado</th><th>Situação</th></tr></thead><tbody><tr v-for="i in selected.items" :key="i.id"><td>{{i.item_number}}</td><td>{{i.description}}</td><td>{{i.ncm}} / {{i.ex_code||'—'}}</td><td>{{i.actual_cst}} / {{i.actual_cclass_trib}}</td><td>{{i.expected_cst}} / {{i.expected_cclass_trib}}</td><td><StatusBadge :value="i.classification_status"/></td></tr></tbody></table>
      <details><summary>XML normalizado</summary><pre>{{JSON.stringify(selected.normalized,null,2)}}</pre></details>
    </div>
  </template>
  <p v-else class="error">{{pageError||'Auditoria não encontrada.'}}</p>
</template>
