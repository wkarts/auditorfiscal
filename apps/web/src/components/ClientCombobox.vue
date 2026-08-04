<script setup lang="ts">
import {computed,nextTick,ref,useId,watch} from 'vue';

interface ClientOption {
  id:string;
  legal_name:string;
  trade_name?:string|null;
  tax_id:string;
}

const props=withDefaults(defineProps<{
  modelValue:string;
  options:ClientOption[];
  loading?:boolean;
}>(),{loading:false});
const emit=defineEmits<{
  (event:'update:modelValue',value:string):void;
  (event:'search',value:string):void;
}>();

const input=ref<HTMLInputElement|null>(null);
const query=ref('');
const expanded=ref(false);
const activeIndex=ref(-1);
const listboxId=`client-listbox-${useId().replace(/:/g,'')}`;

const selected= computed(()=>props.options.find(option=>option.id===props.modelValue));
const optionLabel=(option:ClientOption)=>`${option.trade_name||option.legal_name} · ${option.tax_id}`;
const normalize=(value:string)=>value.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase('pt-BR').trim();
const onlyDigits=(value:string)=>value.replace(/\D/g,'');
const filteredOptions=computed(()=>{
  const selectedLabel=selected.value?optionLabel(selected.value):'';
  const term=query.value===selectedLabel?'':normalize(query.value);
  const termDigits=onlyDigits(query.value);
  if(!term)return props.options;
  return props.options.filter(option=>{
    const searchable=normalize(`${option.trade_name||''} ${option.legal_name} ${option.tax_id}`);
    return searchable.includes(term)||(termDigits.length>0&&onlyDigits(searchable).includes(termDigits));
  });
});
const activeOptionId=computed(()=>activeIndex.value>=0?`${listboxId}-option-${activeIndex.value}`:undefined);

function syncSelectedLabel(){
  query.value=selected.value?optionLabel(selected.value):'';
}

function open(){
  expanded.value=true;
  activeIndex.value=filteredOptions.value.length?Math.max(0,filteredOptions.value.findIndex(option=>option.id===props.modelValue)):-1;
  nextTick(()=>input.value?.select());
}

function search(event:Event){
  query.value=(event.target as HTMLInputElement).value;
  expanded.value=true;
  activeIndex.value=filteredOptions.value.length?0:-1;
  emit('search',query.value);
}

function select(option:ClientOption){
  emit('update:modelValue',option.id);
  query.value=optionLabel(option);
  expanded.value=false;
  activeIndex.value=-1;
  emit('search','');
}

function close(){
  const hadSearch=query.value!==''&&query.value!==(selected.value?optionLabel(selected.value):'');
  expanded.value=false;
  activeIndex.value=-1;
  syncSelectedLabel();
  if(hadSearch)emit('search','');
}

function move(direction:1|-1){
  if(!expanded.value){open();return;}
  const total=filteredOptions.value.length;
  if(!total)return;
  activeIndex.value=(activeIndex.value+direction+total)%total;
}

function chooseActive(){
  const option=filteredOptions.value[activeIndex.value];
  if(option)select(option);
}

watch(()=>props.modelValue,syncSelectedLabel,{immediate:true});
watch(()=>props.options,()=>{
  if(!expanded.value)syncSelectedLabel();
  if(expanded.value&&activeIndex.value>=filteredOptions.value.length){
    activeIndex.value=filteredOptions.value.length?0:-1;
  }
});
</script>

<template>
  <div class="client-combobox" :class="{'is-open':expanded}">
    <input
      ref="input"
      :value="query"
      type="text"
      role="combobox"
      autocomplete="off"
      aria-autocomplete="list"
      :aria-expanded="expanded"
      :aria-controls="listboxId"
      :aria-activedescendant="activeOptionId"
      placeholder="Digite o nome ou CNPJ do cliente"
      required
      @focus="open"
      @input="search"
      @keydown.down.prevent="move(1)"
      @keydown.up.prevent="move(-1)"
      @keydown.enter.prevent="chooseActive"
      @keydown.esc.prevent="close"
      @keydown.tab="close"
      @blur="close"
    >
    <span class="combobox-chevron" aria-hidden="true">⌄</span>
    <ul v-if="expanded" :id="listboxId" class="client-options" role="listbox">
      <li v-if="loading" class="combobox-status" role="status">Buscando clientes…</li>
      <li v-else-if="!filteredOptions.length" class="combobox-status" role="status">Nenhum cliente localizado.</li>
      <li
        v-for="(option,index) in filteredOptions"
        v-else
        :id="`${listboxId}-option-${index}`"
        :key="option.id"
        role="option"
        :aria-selected="option.id===modelValue"
        :class="{active:index===activeIndex,selected:option.id===modelValue}"
        @mouseenter="activeIndex=index"
        @mousedown.prevent="select(option)"
        @click="select(option)"
      >
        <span>
          <strong>{{option.trade_name||option.legal_name}}</strong>
          <small v-if="option.trade_name&&option.trade_name!==option.legal_name">{{option.legal_name}}</small>
        </span>
        <code>{{option.tax_id}}</code>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.client-combobox{position:relative;min-width:0}.client-combobox.is-open{z-index:20}.client-combobox input{padding-right:34px}.combobox-chevron{position:absolute;right:11px;top:50%;translate:0 -54%;pointer-events:none;color:#536977;font-size:17px}.client-options{position:absolute;top:calc(100% + 4px);left:0;right:0;z-index:30;max-height:270px;overflow:auto;margin:0;padding:4px;list-style:none;background:#fff;border:1px solid #b9c9d2;border-radius:7px;box-shadow:0 14px 35px #142d3e26}.client-options li{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:9px 10px;border-radius:5px;color:#263d4c;cursor:pointer}.client-options li.active{background:#e8f4f3;color:#0b625f}.client-options li.selected{font-weight:700}.client-options li span{display:grid;gap:2px;min-width:0}.client-options strong,.client-options small{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.client-options small{color:#687b88;font-weight:400}.client-options code{flex:0 0 auto;color:#536977;font:11px ui-monospace,SFMono-Regular,Consolas,monospace}.client-options .combobox-status{display:block;color:#687b88;cursor:default;font-weight:400}.client-options .combobox-status:hover{background:transparent}@media(max-width:680px){.client-options li{align-items:flex-start;flex-direction:column;gap:4px}.client-options code{padding-left:0}}
</style>
