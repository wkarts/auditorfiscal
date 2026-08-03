<script setup lang="ts">
import {onMounted,ref} from 'vue';
import {api,dateTime} from '@/lib/api';
import StatusBadge from '@/components/StatusBadge.vue';
import PaginationBar from '@/components/PaginationBar.vue';

const rows=ref<any[]>([]),meta=ref<any>(),status=ref('');
async function load(page=1){const {data}=await api.get('/analyses',{params:{page,status:status.value||undefined}});rows.value=data.data;meta.value=data}
onMounted(()=>load());
</script>

<template>
  <section class="card">
    <div class="card-title">
      <div><h2>Lotes de auditoria</h2><p>Histórico imutável por empresa, catálogo e competência.</p></div>
      <div class="actions">
        <select v-model="status" @change="load(1)">
          <option value="">Todos os status</option><option>uploading</option><option>queued</option><option>processing</option><option>retrying</option><option>completed</option><option>failed</option><option>superseded</option>
        </select>
        <RouterLink class="primary" to="/analises/nova">Importar XML/ZIP</RouterLink>
      </div>
    </div>
    <table>
      <thead><tr><th>Auditoria</th><th>Empresa</th><th>Catálogo</th><th>Status</th><th>Progresso</th><th>Documentos</th><th>Achados</th><th>Erro recente</th><th>Data</th></tr></thead>
      <tbody><tr v-for="r in rows" :key="r.id" class="clickable" @click="$router.push(`/analises/${r.id}`)">
        <td><strong>{{r.name}}</strong></td><td>{{r.company?.legal_name}}</td><td>{{r.catalog_version?.version}}</td><td><StatusBadge :value="r.status"/></td>
        <td>{{Math.round(Number(r.progress)*100)}}%</td><td>{{r.documents_count}}</td><td>{{r.findings_count}}</td>
        <td class="error-summary"><template v-if="r.error"><code>{{r.error.code||'ANALYSIS_FAILED'}}</code><small>{{r.error.message}}</small></template><span v-else>—</span></td>
        <td>{{dateTime(r.created_at)}}</td>
      </tr></tbody>
    </table>
    <PaginationBar :meta="meta" @change="load"/>
  </section>
</template>
