<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { api, dateTime } from '@/lib/api';
import StatusBadge from '@/components/StatusBadge.vue';

type DashboardBatch = {
  id: string;
  name: string;
  status: string;
  document_count: number;
  finding_count: number;
  created_at: string;
  company?: {
    legal_name?: string;
  };
};

type DashboardData = {
  batches: {
    total?: number;
    processing?: number;
  };
  documents?: number;
  items?: number;
  open_findings: Record<string, number>;
  recent_batches: DashboardBatch[];
};

const data = ref<DashboardData>({
  batches: {},
  open_findings: {},
  recent_batches: [],
});
const loading = ref(true);
const error = ref('');

const openCount = computed(() =>
  Object.values(data.value.open_findings).reduce(
    (total, amount) => total + Number(amount),
    0,
  ),
);

onMounted(async () => {
  try {
    data.value = (await api.get<DashboardData>('/dashboard')).data;
  } catch (exception) {
    error.value = 'Não foi possível carregar o painel. Tente novamente.';
    console.error(exception);
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <div v-if="loading" class="card">Carregando...</div>

  <div v-else-if="error" class="card error">{{ error }}</div>

  <template v-else>
    <section class="kpis">
      <article>
        <span>Lotes</span>
        <strong>{{ data.batches.total || 0 }}</strong>
        <small>{{ data.batches.processing || 0 }} em processamento</small>
      </article>

      <article>
        <span>Documentos</span>
        <strong>{{ data.documents || 0 }}</strong>
        <small>XMLs normalizados</small>
      </article>

      <article>
        <span>Itens</span>
        <strong>{{ data.items || 0 }}</strong>
        <small>linhas auditadas</small>
      </article>

      <article class="danger">
        <span>Críticas abertas</span>
        <strong>{{ openCount }}</strong>
        <small>{{ data.open_findings.critical || 0 }} críticas</small>
      </article>
    </section>

    <section class="card">
      <div class="card-title">
        <div>
          <h2>Auditorias recentes</h2>
          <p>Processamentos mais recentes e seu estado atual.</p>
        </div>

        <RouterLink class="primary" to="/analises/nova">
          Nova auditoria
        </RouterLink>
      </div>

      <table>
        <thead>
          <tr>
            <th>Nome</th>
            <th>Empresa</th>
            <th>Status</th>
            <th>Documentos</th>
            <th>Achados</th>
            <th>Criado em</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="batch in data.recent_batches"
            :key="batch.id"
            class="clickable"
            @click="$router.push(`/analises/${batch.id}`)"
          >
            <td>{{ batch.name }}</td>
            <td>{{ batch.company?.legal_name || '—' }}</td>
            <td><StatusBadge :value="batch.status" /></td>
            <td>{{ batch.document_count }}</td>
            <td>{{ batch.finding_count }}</td>
            <td>{{ dateTime(batch.created_at) }}</td>
          </tr>
        </tbody>
      </table>
    </section>
  </template>
</template>
