<script setup lang="ts">
import {computed,onBeforeUnmount,onMounted,ref,watch} from 'vue';
import {api,dateTime,formatXml,money,messageOf} from '@/lib/api';
import StatusBadge from '@/components/StatusBadge.vue';

const props=defineProps<{batchId:string;documentId:string}>();
const emit=defineEmits<{close:[];openFull:[documentId:string]}>();
const document=ref<any>(),rawXml=ref(''),tab=ref('nfe'),error=ref(''),loading=ref(true),auxiliaryUrl=ref(''),auxiliaryLoading=ref(false);
const normalized=computed(()=>objectOf(document.value?.normalized));
const labels:Record<string,string>={
  cUF:'Código UF',cNF:'Código numérico',natOp:'Natureza da operação',nature:'Natureza da operação',mod:'Modelo',model:'Modelo',serie:'Série',number:'Número',nNF:'Número da NF-e',
  dhEmi:'Data/hora de emissão',dhSaiEnt:'Data/hora de saída/entrada',tpNF:'Tipo de operação',operation_type:'Tipo de operação',idDest:'Destino da operação',destination:'Destino da operação',cMunFG:'Município do fato gerador',
  tpImp:'Formato de impressão',tpEmis:'Tipo de emissão',cDV:'Dígito verificador',tpAmb:'Ambiente',environment:'Ambiente',finNFe:'Finalidade',purpose:'Finalidade',indFinal:'Consumidor final',consumer:'Consumidor final',indPres:'Presença do comprador',presence:'Presença do comprador',procEmi:'Processo de emissão',verProc:'Versão do processo',
  tax_id:'CNPJ / CPF',name:'Nome / razão social',trade_name:'Nome fantasia',state_registration:'Inscrição estadual',municipal_registration:'Inscrição municipal',email:'E-mail',phone:'Telefone',address:'Endereço',
  status_code:'Código de situação',status_reason:'Situação',received_at:'Recebido em',protocol_number:'Protocolo',freight_mode:'Modalidade do frete',carrier:'Transportador',vehicle_plate:'Placa do veículo',vehicle_state:'UF do veículo',
  invoice_number:'Número da fatura',original_value:'Valor original',discount:'Desconto',net_value:'Valor líquido',method:'Forma de pagamento',value:'Valor',description:'Descrição',
  tax_authority:'Informação ao fisco',taxpayer:'Informação complementar ao contribuinte',references:'Documentos referenciados',xml_sha256:'Hash SHA-256 do XML',
  vBC:'Base de cálculo ICMS',vICMS:'Valor do ICMS',vICMSDeson:'ICMS desonerado',vFCP:'FCP',vBCST:'Base ICMS ST',vST:'ICMS ST',vFCPST:'FCP ST',vProd:'Total dos produtos',vFrete:'Frete',vSeg:'Seguro',vDesc:'Desconto',vII:'II',vIPI:'IPI',vPIS:'PIS',vCOFINS:'COFINS',vOutro:'Outras despesas',vNF:'Valor total da NF-e',vTotTrib:'Tributos aproximados',
};
const auxiliaryLabels:Record<string,string>={55:'DANFE',65:'DANFCE',57:'DACTE',67:'DACTE OS',58:'DAMDFE'};
const auxiliaryType=computed(()=>document.value?.auxiliary_document_type||auxiliaryLabels[String(document.value?.model||'')]||'Documento auxiliar');
const auxiliaryAvailable=computed(()=>Boolean(document.value?.auxiliary_document_storage_path||document.value?.danfe_storage_path));
const accessKeyValid=computed(()=>validateAccessKey(document.value?.access_key));
const portalUrl=computed(()=>{
  const key=String(document.value?.access_key||'').replace(/\D/g,'');
  return ['55','65'].includes(String(document.value?.model))&&key.length===44
    ? `https://www.nfe.fazenda.gov.br/portal/consultaRecaptcha.aspx?AspxAutoDetectCookieSupport=1&nfe=${key}&tipoConsulta=completa`
    : '';
});
const prettyXml=computed(()=>formatXml(rawXml.value));

onMounted(load);onBeforeUnmount(revokeAuxiliary);watch(tab,value=>{if(value==='auxiliary')loadAuxiliary()});
async function load(){loading.value=true;error.value='';try{const [{data},xml]=await Promise.all([api.get(`/analyses/${props.batchId}/documents/${props.documentId}`),api.get(`/analyses/${props.batchId}/documents/${props.documentId}/xml`,{responseType:'text'})]);document.value=data;rawXml.value=xml.data}catch(reason){error.value=messageOf(reason)}finally{loading.value=false}}
function revokeAuxiliary(){if(auxiliaryUrl.value){URL.revokeObjectURL(auxiliaryUrl.value);auxiliaryUrl.value=''}}
async function loadAuxiliary(){if(auxiliaryUrl.value||!auxiliaryAvailable.value)return;auxiliaryLoading.value=true;try{const response=await api.get(`/analyses/${props.batchId}/documents/${props.documentId}/auxiliary-document`,{responseType:'blob'});auxiliaryUrl.value=URL.createObjectURL(response.data)}catch(reason){error.value=messageOf(reason)}finally{auxiliaryLoading.value=false}}
async function download(kind:'xml'|'auxiliary-document'){try{const response=await api.get(`/analyses/${props.batchId}/documents/${props.documentId}/${kind}`,{params:{download:1},responseType:'blob'});const url=URL.createObjectURL(response.data),link=window.document.createElement('a');link.href=url;link.download=`${kind==='xml'?'documento-fiscal':auxiliaryType.value}-${document.value.access_key}.${kind==='xml'?'xml':'pdf'}`;link.click();URL.revokeObjectURL(url)}catch(reason){error.value=messageOf(reason)}}
function objectOf(value:any):Record<string,any>{
  let current=value;
  while(typeof current==='string'){
    try{current=JSON.parse(current)}catch{return {}}
  }
  return current&&typeof current==='object'&&!Array.isArray(current)?current:{};
}
function detailsOf(item:any){return objectOf(item?.details)}
function componentsOf(item:any){return objectOf(item?.tax_components)}
function entries(value:any){return Object.entries(objectOf(value)).filter(([,value])=>value!==''&&value!==null&&value!==undefined&&!(typeof value==='object'&&!Array.isArray(value)&&Object.keys(value).length===0))}
function label(key:string){return labels[key]||key.replaceAll('_',' ').replace(/([a-z])([A-Z])/g,'$1 $2')}
function valueOf(value:any){if(Array.isArray(value))return value.length?value.join(', '):'—';if(typeof value==='object')return JSON.stringify(value,null,2);return value??'—'}
function validateAccessKey(value:any){const key=String(value||'').replace(/\D/g,'');if(key.length!==44)return false;let sum=0,weight=2;for(let index=42;index>=0;index--){sum+=Number(key[index])*weight;weight=weight===9?2:weight+1}const remainder=sum%11;const digit=remainder<2?0:11-remainder;return digit===Number(key[43])}
</script>

<template>
  <div class="modal-backdrop fiscal-inspection-backdrop" @click.self="emit('close')">
    <section class="modal fiscal-inspection-modal" role="dialog" aria-modal="true" aria-label="Detalhamento completo do documento fiscal">
      <div v-if="loading" class="inspection-loading">Carregando dados completos do XML fiscal…</div>
      <template v-else-if="document">
        <header class="inspection-header">
          <div><span class="eyebrow">Consulta somente leitura</span><h2>{{document.model==='55'?'NF-e':document.model==='65'?'NFC-e':'Documento fiscal'}} {{document.number||'—'}} · Série {{document.series||'—'}}</h2><code>{{document.access_key||'Chave de acesso não informada'}}</code></div>
          <div class="inspection-actions"><StatusBadge :value="document.status"/><button class="icon-button" aria-label="Fechar detalhamento" @click="emit('close')">×</button></div>
        </header>
        <div class="inspection-commandbar">
          <span :class="['key-validation',accessKeyValid?'valid':'invalid']">{{accessKeyValid?'Chave de acesso com dígito verificador válido':'Chave ausente ou dígito verificador inválido'}}</span>
          <button @click="download('xml')">Baixar XML</button><button v-if="auxiliaryAvailable" @click="download('auxiliary-document')">Baixar {{auxiliaryType}}</button>
          <a v-if="portalUrl" class="primary" :href="portalUrl" target="_blank" rel="noopener noreferrer">Consultar no Portal Nacional ↗</a>
          <button @click="emit('openFull',document.id)">Abrir em página</button>
        </div>
        <p v-if="portalUrl" class="hint">A consulta oficial abre o Portal Nacional com a chave preenchida; a confirmação depende do hCaptcha exigido pelo próprio portal.</p>
        <p v-if="error" class="error">{{error}}</p>
        <nav class="inspection-tabs" aria-label="Seções do documento fiscal">
          <button :class="{active:tab==='nfe'}" @click="tab='nfe'">NF-e</button><button :class="{active:tab==='issuer'}" @click="tab='issuer'">Emitente</button><button :class="{active:tab==='recipient'}" @click="tab='recipient'">Destinatário</button><button :class="{active:tab==='items'}" @click="tab='items'">Produtos e serviços ({{document.items.length}})</button><button :class="{active:tab==='totals'}" @click="tab='totals'">Totais</button><button :class="{active:tab==='transport'}" @click="tab='transport'">Transporte</button><button :class="{active:tab==='billing'}" @click="tab='billing'">Cobrança</button><button :class="{active:tab==='additional'}" @click="tab='additional'">Informações adicionais</button><button :class="{active:tab==='xml'}" @click="tab='xml'">XML</button><button :class="{active:tab==='auxiliary'}" @click="tab='auxiliary'">{{auxiliaryType}}</button>
        </nav>
        <main class="inspection-body">
          <section v-show="tab==='nfe'" class="inspection-section"><h3>Dados da NF-e</h3><dl class="inspection-fields"><dt>Chave de acesso</dt><dd><code>{{document.access_key||'—'}}</code></dd><dt>Emissão</dt><dd>{{dateTime(document.issued_at)}}</dd><dt>Direção</dt><dd>{{document.direction}}</dd><template v-for="[key,value] in entries(normalized.identification)" :key="key"><dt>{{label(key)}}</dt><dd>{{valueOf(value)}}</dd></template></dl><h3>Situação atual</h3><dl class="inspection-fields"><template v-for="[key,value] in entries(normalized.protocol)" :key="key"><dt>{{label(key)}}</dt><dd>{{valueOf(value)}}</dd></template></dl></section>
          <section v-show="tab==='issuer'" class="inspection-section"><h3>Emitente</h3><dl class="inspection-fields"><template v-for="[key,value] in entries(normalized.issuer)" :key="key"><dt>{{label(key)}}</dt><dd><pre v-if="typeof value==='object'">{{valueOf(value)}}</pre><template v-else>{{valueOf(value)}}</template></dd></template></dl></section>
          <section v-show="tab==='recipient'" class="inspection-section"><h3>Destinatário</h3><dl class="inspection-fields"><template v-for="[key,value] in entries(normalized.recipient)" :key="key"><dt>{{label(key)}}</dt><dd><pre v-if="typeof value==='object'">{{valueOf(value)}}</pre><template v-else>{{valueOf(value)}}</template></dd></template></dl></section>
          <section v-show="tab==='items'" class="inspection-section"><h3>Dados dos produtos e serviços</h3><div class="table-scroll"><table><thead><tr><th>Item</th><th>Produto / serviço</th><th>NCM</th><th>CFOP</th><th>Quantidade</th><th>Unidade</th><th>Valor unitário</th><th>Valor total</th></tr></thead><tbody><template v-for="item in document.items" :key="item.id"><tr><td>{{item.item_number}}</td><td><strong>{{item.description||'—'}}</strong><small v-if="item.product_code"><br>Código: {{item.product_code}}</small></td><td>{{item.ncm||'—'}}<template v-if="item.ex_code"> / {{item.ex_code}}</template></td><td>{{item.cfop||'—'}}</td><td>{{detailsOf(item).quantity||'—'}}</td><td>{{detailsOf(item).unit||'—'}}</td><td>{{detailsOf(item).unit_value||'—'}}</td><td>{{money(item.product_value)}}</td></tr><tr class="inspection-item-details"><td></td><td colspan="7"><details><summary>Tributos, rastreabilidade, identificadores e informações adicionais</summary><div class="item-detail-grid"><dl class="inspection-fields compact"><template v-for="[key,value] in entries(detailsOf(item))" :key="key"><dt>{{label(key)}}</dt><dd><pre v-if="typeof value==='object'">{{valueOf(value)}}</pre><template v-else>{{valueOf(value)}}</template></dd></template></dl><dl class="inspection-fields compact"><template v-for="[key,value] in entries(componentsOf(item))" :key="key"><dt>{{label(key)}}</dt><dd>{{money(value)}}</dd></template><template v-if="!entries(detailsOf(item)).length&&!entries(componentsOf(item)).length"><dt>Detalhe</dt><dd>O XML não informou dados adicionais para este item.</dd></template></dl></div></details></td></tr></template><tr v-if="!document.items.length"><td colspan="8" class="empty-state">Este modelo não possui itens de produto normalizados.</td></tr></tbody></table></div></section>
          <section v-show="tab==='totals'" class="inspection-section"><h3>Totais da NF-e</h3><div class="totals-grid"><article v-for="[key,value] in entries(normalized.totals)" :key="key"><span>{{label(key)}}</span><strong>{{money(value)}}</strong></article></div><h3>Totais IBS/CBS calculados na auditoria</h3><div class="totals-grid"><article><span>Base IBS/CBS</span><strong>{{money(document.ibs_cbs_base)}}</strong></article><article><span>IBS</span><strong>{{money(document.ibs_value)}}</strong></article><article><span>CBS</span><strong>{{money(document.cbs_value)}}</strong></article><article><span>Valor da NF</span><strong>{{money(document.total_value)}}</strong></article></div></section>
          <section v-show="tab==='transport'" class="inspection-section"><h3>Transporte</h3><dl class="inspection-fields"><template v-for="[key,value] in entries(normalized.transport)" :key="key"><dt>{{label(key)}}</dt><dd><pre v-if="typeof value==='object'">{{valueOf(value)}}</pre><template v-else>{{valueOf(value)}}</template></dd></template></dl></section>
          <section v-show="tab==='billing'" class="inspection-section"><h3>Cobrança</h3><dl class="inspection-fields"><template v-for="[key,value] in entries(normalized.billing)" :key="key"><dt>{{label(key)}}</dt><dd>{{key.includes('value')||key==='discount'?money(value):valueOf(value)}}</dd></template></dl><h3>Pagamentos</h3><div class="table-scroll"><table><thead><tr><th>Forma</th><th>Descrição</th><th>Valor</th></tr></thead><tbody><tr v-for="payment in normalized.payments||[]" :key="`${payment.method}-${payment.value}`"><td>{{payment.method||'—'}}</td><td>{{payment.description||'—'}}</td><td>{{money(payment.value)}}</td></tr><tr v-if="!(normalized.payments||[]).length"><td colspan="3" class="empty-state">O XML não possui grupo de pagamento informado.</td></tr></tbody></table></div></section>
          <section v-show="tab==='additional'" class="inspection-section"><h3>Informações adicionais e referências</h3><dl class="inspection-fields"><template v-for="[key,value] in entries(normalized.additional_information)" :key="key"><dt>{{label(key)}}</dt><dd><pre v-if="typeof value==='object'">{{valueOf(value)}}</pre><template v-else>{{valueOf(value)}}</template></dd></template><dt>Documentos referenciados</dt><dd>{{valueOf(normalized.identification?.references)}}</dd></dl><h3>Críticas da auditoria</h3><div class="table-scroll"><table><thead><tr><th>Severidade</th><th>Regra</th><th>Descrição</th><th>Ação recomendada</th></tr></thead><tbody><tr v-for="finding in document.findings" :key="finding.id"><td><StatusBadge :value="finding.severity"/></td><td><code>{{finding.rule_code}}</code></td><td>{{finding.description}}</td><td>{{finding.recommended_action||'—'}}</td></tr><tr v-if="!document.findings.length"><td colspan="4" class="empty-state">Sem críticas vinculadas a este documento.</td></tr></tbody></table></div></section>
          <section v-show="tab==='xml'" class="inspection-section"><h3>XML fiscal original</h3><p class="hint">O conteúdo é exibido em modo leitura e o download preserva o arquivo original.</p><pre class="code-view">{{prettyXml}}</pre></section>
          <section v-show="tab==='auxiliary'" class="inspection-section"><h3>{{auxiliaryType}}</h3><p v-if="document.auxiliary_document_source==='imported_original'">PDF original recebido com a importação.</p><p v-else-if="document.auxiliary_document_source==='nfephp_generated'">Representação auxiliar gerada do XML. O XML autorizado é a fonte fiscal válida.</p><p v-else>Documento auxiliar ainda não está disponível.</p><iframe v-if="auxiliaryUrl" class="pdf-frame" :src="auxiliaryUrl" :title="auxiliaryType"></iframe><p v-else-if="auxiliaryLoading">Carregando {{auxiliaryType}}…</p><p v-else-if="!auxiliaryAvailable" class="empty-state">Não há documento auxiliar armazenado para esta nota.</p></section>
        </main>
      </template>
      <p v-else class="error">{{error||'Não foi possível carregar o documento.'}}</p>
    </section>
  </div>
</template>
