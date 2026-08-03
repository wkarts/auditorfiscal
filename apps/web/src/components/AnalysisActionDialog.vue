<script setup lang="ts">
import {computed,ref,watch} from 'vue';

const props=defineProps<{action:'cancel'|'delete'|'restore';name:string;busy?:boolean;error?:string}>();
const emit=defineEmits<{close:[];confirm:[reason:string]}>();
const reason=ref('');
watch(()=>props.action,()=>reason.value='');
const content=computed(()=>({
  cancel:{title:'Cancelar processamento',description:'O motor fiscal será interrompido no próximo ponto seguro. Arquivos de origem e eventos já registrados serão preservados.',confirm:'Solicitar cancelamento',tone:'warning'},
  delete:{title:'Mover auditoria para excluídas',description:'A auditoria sairá das listagens e relatórios ativos, mas seus XMLs, DANFEs, achados e logs permanecerão preservados e poderão ser restaurados.',confirm:'Mover para excluídas',tone:'danger'},
  restore:{title:'Restaurar auditoria',description:'A auditoria voltará às listagens, relatórios e consultas ativas com todo o histórico preservado.',confirm:'Restaurar auditoria',tone:'success'},
}[props.action]));
function close(){if(!props.busy)emit('close')}
</script>

<template>
  <div class="modal-backdrop lifecycle-backdrop" @click.self="close">
    <form class="modal lifecycle-dialog" role="dialog" aria-modal="true" :aria-labelledby="`lifecycle-${action}`" @submit.prevent="emit('confirm',reason.trim())">
      <div class="dialog-icon" :class="`dialog-icon-${content.tone}`" aria-hidden="true">{{action==='cancel'?'Ⅱ':action==='delete'?'×':'↻'}}</div>
      <div>
        <div class="dialog-heading">
          <div><span class="eyebrow">Ação operacional</span><h2 :id="`lifecycle-${action}`">{{content.title}}</h2></div>
          <button type="button" class="icon-button" aria-label="Fechar" :disabled="busy" @click="close">×</button>
        </div>
        <p>{{content.description}}</p>
        <div class="dialog-target"><span>Auditoria</span><strong>{{name}}</strong></div>
        <label>Motivo <small>(opcional, recomendado para rastreabilidade)</small><textarea v-model="reason" rows="3" maxlength="500" placeholder="Descreva o motivo desta ação"></textarea></label>
        <p v-if="error" class="error">{{error}}</p>
        <div class="form-actions">
          <button type="button" :disabled="busy" @click="close">Voltar</button>
          <button type="submit" class="lifecycle-confirm" :class="`button-${content.tone}`" :disabled="busy">{{busy?'Processando...':content.confirm}}</button>
        </div>
      </div>
    </form>
  </div>
</template>
