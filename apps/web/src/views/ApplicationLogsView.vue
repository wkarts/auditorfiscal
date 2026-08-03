<script setup lang="ts">
import {onMounted,ref} from 'vue';
import {api,dateTime,messageOf} from '@/lib/api';
import StatusBadge from '@/components/StatusBadge.vue';
import PaginationBar from '@/components/PaginationBar.vue';

const rows=ref<any[]>([]),meta=ref<any>(),level=ref(''),component=ref(''),search=ref(''),error=ref('');
let searchTimer:number|undefined;

async function load(page=1){
  try{
    const {data}=await api.get('/application-logs',{params:{page,level:level.value||undefined,component:component.value||undefined,search:search.value||undefined,per_page:100}});
    rows.value=data.data;meta.value=data;error.value='';
  }catch(reason){error.value=messageOf(reason)}
}
function delayedLoad(){window.clearTimeout(searchTimer);searchTimer=window.setTimeout(()=>load(1),350)}
async function download(url:string,filename:string){const response=await api.get(url,{params:url.endsWith('/export')?{level:level.value||undefined,component:component.value||undefined,search:search.value||undefined}:undefined,responseType:'blob'});const objectUrl=URL.createObjectURL(response.data),anchor=document.createElement('a');anchor.href=objectUrl;anchor.download=filename;anchor.click();URL.revokeObjectURL(objectUrl)}
onMounted(()=>load());
</script>

<template>
  <section class="card">
    <div class="card-title"><div><h2>Logs da aplicação</h2><p>Eventos técnicos persistidos, pesquisáveis e vinculados às auditorias.</p></div><div class="actions"><button @click="download('/application-logs/export','application-logs.ndjson')">Baixar filtrados</button><button @click="load(meta?.current_page||1)">Atualizar</button></div></div>
    <div class="actions log-filters">
      <select v-model="level" @change="load(1)"><option value="">Todos os níveis</option><option>info</option><option>notice</option><option>warning</option><option>error</option><option>critical</option></select>
      <input v-model="component" placeholder="Componente" @input="delayedLoad">
      <input v-model="search" placeholder="Mensagem, evento ou componente" @input="delayedLoad">
    </div>
    <p v-if="error" class="error">{{error}}</p>
    <div class="table-scroll"><table>
      <thead><tr><th>Data</th><th>Nível</th><th>Componente</th><th>Evento</th><th>Mensagem</th><th>Auditoria</th><th>Incidente</th><th></th></tr></thead>
      <tbody><tr v-for="entry in rows" :key="entry.id">
        <td>{{dateTime(entry.created_at)}}</td><td><StatusBadge :value="entry.level"/></td><td><code>{{entry.component}}</code></td><td>{{entry.event}}</td>
        <td><span>{{entry.message}}</span><details v-if="Object.keys(entry.context||{}).length"><summary>Contexto</summary><pre>{{JSON.stringify(entry.context,null,2)}}</pre></details></td>
        <td><RouterLink v-if="entry.analysis_batch" :to="`/analises/${entry.analysis_batch_id}`">{{entry.analysis_batch.name}}</RouterLink><span v-else>—</span></td>
        <td><code>{{entry.incident_id||'—'}}</code></td><td><button @click="download(`/application-logs/${entry.id}/download`,`application-log-${entry.id}.json`)">Baixar</button></td>
      </tr></tbody>
    </table></div>
    <p v-if="!rows.length&&!error" class="empty-state">Nenhum evento encontrado para os filtros informados.</p>
    <PaginationBar :meta="meta" @change="load"/>
  </section>
</template>
