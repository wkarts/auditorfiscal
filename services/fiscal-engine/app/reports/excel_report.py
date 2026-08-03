from pathlib import Path
from decimal import Decimal
import xlsxwriter
from ..product_reconciliation import build_product_reconciliation
MONEY='#,##0.00';PCT='0.0000%'
def build_excel(path:Path,company:dict,period:dict,summary:dict,documents:list[dict],findings:list[dict],catalog:dict,parameter_issues:list[dict]|None=None):
    wb=xlsxwriter.Workbook(str(path));wb.set_properties({'title':'Auditoria Fiscal IBS/CBS','company':company.get('legal_name',''),'comments':'Gerado pelo Auditor Fiscal'})
    fmt={'title':wb.add_format({'bold':True,'font_size':18,'font_color':'#FFFFFF','bg_color':'#12324A','align':'left','valign':'vcenter'}),'subtitle':wb.add_format({'font_color':'#FFFFFF','bg_color':'#12324A'}),'header':wb.add_format({'bold':True,'font_color':'#FFFFFF','bg_color':'#137D7B','border':1,'align':'center','valign':'vcenter','text_wrap':True}),'cell':wb.add_format({'border':1,'border_color':'#D8E1E7','valign':'top'}),'money':wb.add_format({'border':1,'border_color':'#D8E1E7','num_format':MONEY}),'date':wb.add_format({'border':1,'border_color':'#D8E1E7','num_format':'dd/mm/yyyy'}),'kpi':wb.add_format({'bold':True,'font_size':15,'font_color':'#12324A','bg_color':'#EAF2F7','border':1,'align':'center','valign':'vcenter'}),'label':wb.add_format({'bold':True,'font_color':'#496171','bg_color':'#EAF2F7','border':1,'align':'center'}),'critical':wb.add_format({'bg_color':'#FCE5E3','font_color':'#9B1C13','border':1}),'warning':wb.add_format({'bg_color':'#FFF0CC','font_color':'#7B5700','border':1})}
    ws=wb.add_worksheet('Resumo Executivo');ws.hide_gridlines(2);ws.set_column('A:H',20);ws.set_row(0,32);ws.merge_range('A1:H1','Relatório Analítico e Sintético - IBS/CBS em NF-e',fmt['title']);ws.merge_range('A2:H2',f"{company.get('legal_name','')} | {period.get('start') or '—'} a {period.get('end') or '—'} | Catálogo {catalog.get('version','')}",fmt['subtitle'])
    kpis=[('XMLs analisados',len(documents)),('Valor total das NF-e',float(summary.get('total_value',0))),('Base IBS/CBS',float(summary.get('ibs_cbs_base',0))),('IBS + CBS',float(summary.get('ibs_value',0))+float(summary.get('cbs_value',0))),('Entradas',summary.get('input_count',0)),('Saídas',summary.get('output_count',0)),('NCM/Class OK',summary.get('classification_ok',0)),('Achados abertos',len(findings))]
    for idx,(label,value) in enumerate(kpis):r=3+(idx//4)*3;c=(idx%4)*2;ws.merge_range(r,c,r,c+1,label,fmt['label']);ws.merge_range(r+1,c,r+2,c+1,value,fmt['kpi']);
    ws.write('A11','Ações prioritárias',fmt['header']);ws.merge_range('B11:H11','Descrição',fmt['header']);
    for i,f in enumerate(sorted(findings,key=lambda x:{'critical':0,'high':1,'medium':2,'low':3}.get(x.get('severity'),9))[:10],start=11):ws.write(i,0,f.get('severity'),fmt['critical'] if f.get('severity') in {'critical','high'} else fmt['warning']);ws.merge_range(i,1,i,7,f"{f.get('title')}: {f.get('recommended_action') or f.get('description')}",fmt['cell'])
    _notes_sheet(wb,fmt,documents);_items_sheet(wb,fmt,documents);_tax_sheet(wb,fmt,documents);_ibs_cbs_sheet(wb,fmt,documents);_reconciliation_sheet(wb,fmt,documents);_memory_sheet(wb,fmt,documents);_duplicates_events_sheet(wb,fmt,documents,findings);_findings_sheet(wb,fmt,findings,documents);_ncm_sheet(wb,fmt,documents);_quality_sheet(wb,fmt,parameter_issues or []);_snapshot_sheet(wb,fmt,documents,catalog);_method_sheet(wb,fmt,catalog,company,period)
    wb.close()
def _write_table(ws,headers,rows,widths,fmt,name):
    ws.freeze_panes(1,0);ws.write_row(0,0,headers,fmt['header']);
    for c,w in enumerate(widths):ws.set_column(c,c,w)
    for r,row in enumerate(rows,1):
        for c,v in enumerate(row):ws.write(r,c,v,fmt['money'] if isinstance(v,(float,int)) and any(x in headers[c].lower() for x in ['valor','base','ibs','cbs','pis','cofins','diferença','custo','venda','margem']) else fmt['cell'])
    if rows:ws.add_table(0,0,len(rows),len(headers)-1,{'name':name,'columns':[{'header':h} for h in headers],'style':'Table Style Medium 2'})
def _notes_sheet(wb,fmt,docs):
    ws=wb.add_worksheet('Notas - Sintético');headers=['NF','Chave','Emissão','Direção','Emitente','Destinatário','Valor NF','Base IBS/CBS','IBS','CBS','Itens','Status'];rows=[[d.get('number'),d.get('access_key'),d.get('issued_at'),d.get('direction'),d.get('issuer_tax_id'),d.get('recipient_tax_id'),float(d.get('total_value',0)),float(d.get('ibs_cbs_base',0)),float(d.get('ibs_value',0)),float(d.get('cbs_value',0)),d.get('item_count'),d.get('status')] for d in docs];_write_table(ws,headers,rows,[10,46,22,10,16,16,15,15,12,12,8,14],fmt,'NotasSintetico')
def _items_sheet(wb,fmt,docs):
    ws=wb.add_worksheet('Itens - Analítico');components=['vProd','vServ','vFrete','vSeg','vOutro','vII','vDesc','vPIS','vCOFINS','vICMS','vICMSUFDest','vFCP','vFCPUFDest','vICMSMono','vISSQN','vIS'];headers=['NF','Item','Código','Produto','GTIN','NCM','EX','CFOP','Quantidade','Unidade','CST','cClassTrib','CST esperada','cClass esperada']+components+['Base XML','Base recalculada','Diferença','IBS XML','IBS recalc.','CBS XML','CBS recalc.','Situação'];rows=[]
    for d in docs:
        for i in d.get('items',[]):
            details=i.get('details',{});rows.append([d.get('number'),i.get('item_number'),i.get('product_code'),i.get('description'),details.get('ean_taxable') or details.get('ean'),i.get('ncm'),i.get('ex_code'),i.get('cfop'),details.get('quantity'),details.get('unit'),i.get('actual_cst'),i.get('actual_cclass_trib'),i.get('expected_cst'),i.get('expected_cclass_trib')]+[float(i.get('tax_components',{}).get(k,0)) for k in components]+[float(i.get('ibs_cbs_base_xml',0)),float(i.get('ibs_cbs_base_recalculated',0)),float(i.get('base_difference',0)),float(i.get('ibs_xml',0)),float(i.get('ibs_recalculated',0)),float(i.get('cbs_xml',0)),float(i.get('cbs_recalculated',0)),i.get('classification_status')])
    _write_table(ws,headers,rows,[10,7,16,38,16,11,7,8,12,9,8,12,10,13]+[12]*len(components)+[14]*7+[24],fmt,'ItensAnalitico')
def _tax_sheet(wb,fmt,docs):
    ws=wb.add_worksheet('Tributos');rows=[]
    for d in docs:
        for i in d.get('items',[]):
            c=i.get('tax_components',{});details=i.get('details',{});rows.append([d.get('number'),i.get('item_number'),i.get('ncm'),details.get('icms_cst'),float(c.get('vICMS',0)),details.get('pis_cst'),float(c.get('vPIS',0)),details.get('cofins_cst'),float(c.get('vCOFINS',0)),float(c.get('vFCP',0)),float(c.get('vII',0)),float(c.get('vISSQN',0))])
    _write_table(ws,['NF','Item','NCM','CST ICMS','ICMS','CST PIS','PIS','CST COFINS','COFINS','FCP','II','ISSQN'],rows,[10,7,11,11,14,10,14,13,14,14,14,14],fmt,'TributosItens')
def _ibs_cbs_sheet(wb,fmt,docs):
    ws=wb.add_worksheet('IBS e CBS');rows=[]
    for d in docs:
        for i in d.get('items',[]):rows.append([d.get('number'),i.get('item_number'),i.get('ncm'),i.get('actual_cst'),i.get('actual_cclass_trib'),float(i.get('ibs_cbs_base_xml',0)),float(i.get('ibs_cbs_base_recalculated',0)),float(i.get('base_difference',0)),float(i.get('ibs_xml',0)),float(i.get('ibs_recalculated',0)),float(i.get('cbs_xml',0)),float(i.get('cbs_recalculated',0)),i.get('classification_status')])
    _write_table(ws,['NF','Item','NCM','CST','cClassTrib','Base XML','Base recalculada','Diferença base','IBS XML','IBS recalc.','CBS XML','CBS recalc.','Situação'],rows,[10,7,11,8,13,15,17,16,14,14,14,14,28],fmt,'IbsCbsItens')
def _memory_sheet(wb,fmt,docs):
    ws=wb.add_worksheet('Memória de Cálculo');rows=[]
    for d in docs:
        for i in d.get('items',[]):
            c=i.get('tax_components',{});rows.append([d.get('number'),i.get('item_number'),*[float(c.get(k,0)) for k in ['vProd','vServ','vFrete','vSeg','vOutro','vII','vDesc','vPIS','vCOFINS','vICMS','vICMSUFDest','vFCP','vFCPUFDest','vICMSMono','vISSQN','vIS']],float(i.get('ibs_cbs_base_xml',0)),float(i.get('ibs_cbs_base_recalculated',0)),float(i.get('base_difference',0))])
    names=['vProd','vServ','vFrete','vSeg','vOutro','vII','vDesc','vPIS','vCOFINS','vICMS','vICMSUFDest','vFCP','vFCPUFDest','vICMSMono','vISSQN','vIS'];_write_table(ws,['NF','Item',*names,'Base XML','Base UB16-10','Diferença'],rows,[10,7]+[12]*len(names)+[15,16,14],fmt,'MemoriaCalculo')
def _duplicates_events_sheet(wb,fmt,docs,findings):
    ws=wb.add_worksheet('Duplicidades e Eventos');rows=[]
    for d in docs:
        event=d.get('normalized',{}).get('cancellation_event')
        if event:rows.append(['evento',d.get('number'),d.get('access_key'),event.get('event_type'),event.get('status_code'),event.get('description'),event.get('registered_at')])
    for f in findings:
        if f.get('category')=='duplicate':rows.append(['possível duplicidade',next((d.get('number') for d in docs if d.get('document_ref')==f.get('document_ref')),'Lote'),'',f.get('rule_code'),f.get('severity'),f.get('description'),str(f.get('evidence',{}))])
    _write_table(ws,['Tipo','NF','Chave','Evento/Regra','Status','Descrição','Data/Evidência'],rows,[20,12,46,24,12,60,55],fmt,'DuplicidadesEventos')
def _reconciliation_sheet(wb,fmt,docs):
    ws=wb.add_worksheet('Entradas x Saídas');rows=[]
    for row in build_product_reconciliation(docs):
        rows.append([row['identifier'],row['identity_type'],row['confidence'],row.get('ncm'),row.get('unit'),float(row['input_quantity']),float(row['output_quantity']),float(row['input_value']),float(row['output_value']),float(row['estimated_cost']) if row['estimated_cost'] is not None else None,float(row['margin']) if row['margin'] is not None else None,row['status']])
    _write_table(ws,['Produto / identificador','Tipo','Confiança','NCM','Unidade','Qtd. entrada','Qtd. saída','Valor de entrada','Valor de saída','Custo estimado','Margem estimada','Situação'],rows,[48,18,14,11,10,14,14,17,17,17,17,28],fmt,'EntradasSaidas')
def _findings_sheet(wb,fmt,findings,docs):
    ws=wb.add_worksheet('Inconsistências');nf={d['document_ref']:d.get('number') for d in docs};rows=[[f.get('severity'),nf.get(f.get('document_ref'),'Lote'),f.get('item_number'),f.get('category'),f.get('rule_code'),f.get('title'),f.get('description'),f.get('impact'),f.get('recommended_action'),f.get('status')] for f in findings];_write_table(ws,['Severidade','NF','Item','Categoria','Regra','Título','Evidência','Impacto','Ação recomendada','Status'],rows,[12,10,8,18,24,30,55,45,55,14],fmt,'Inconsistencias');ws.conditional_format(1,0,max(len(rows),1),0,{'type':'text','criteria':'containing','value':'critical','format':fmt['critical']})
def _ncm_sheet(wb,fmt,docs):
    ws=wb.add_worksheet('Auditoria NCM-ClassTrib');rows=[]
    for d in docs:
        for i in d.get('items',[]):rows.append([d.get('number'),i.get('item_number'),i.get('description'),i.get('ncm'),i.get('ex_code'),i.get('actual_cst'),i.get('actual_cclass_trib'),i.get('expected_cst'),i.get('expected_cclass_trib'),i.get('classification_status'),i.get('catalog_match',{}).get('strategy'),i.get('catalog_match',{}).get('parameter_source_row')])
    _write_table(ws,['NF','Item','Produto','NCM','EX','CST encontrada','cClass encontrada','CST esperada','cClass esperada','Situação','Estratégia','Linha origem'],rows,[10,7,42,11,7,13,15,13,15,30,24,12],fmt,'AuditoriaNcm')
def _quality_sheet(wb,fmt,issues):
    ws=wb.add_worksheet('Qualidade Parametrização');rows=[[i.get('severity'),i.get('code'),i.get('source_sheet'),i.get('source_row'),i.get('message'),str(i.get('context',''))] for i in issues];_write_table(ws,['Severidade','Código','Aba','Linha','Mensagem','Contexto'],rows,[12,28,20,10,55,70],fmt,'QualidadeParametros')
def _snapshot_sheet(wb,fmt,docs,catalog):
    ws=wb.add_worksheet('Parametrização Utilizada');seen=set();rows=[]
    for d in docs:
        for i in d.get('items',[]):
            key=(i.get('ncm'),i.get('ex_code'),i.get('expected_cst'),i.get('expected_cclass_trib'))
            if key not in seen:seen.add(key);rows.append([*key,i.get('catalog_match',{}).get('parameter_source_row'),catalog.get('version'),catalog.get('sha256')])
    _write_table(ws,['NCM','EX','CST esperada','cClass esperada','Linha origem','Versão','SHA-256'],rows,[12,8,13,15,12,24,68],fmt,'SnapshotParametros')
def _method_sheet(wb,fmt,catalog,company,period):
    ws=wb.add_worksheet('Fontes e Método');rows=[['Empresa',company.get('legal_name')],['Período',f"{period.get('start')} a {period.get('end')}"],['Catálogo',catalog.get('version')],['SHA-256',catalog.get('sha256')],['Método','Leitura direta dos XMLs; reconstrução UB16-10 item a item; cruzamento NCM/EX/CST/cClassTrib; regras cruzadas por documento.'],['Arredondamento','Decimal ROUND_HALF_UP para centavos.'],['Salvaguarda','Achados fiscais devem ser conciliados com escrituração, eventos SEFAZ e orientação vigente antes de correção.']];_write_table(ws,['Campo','Descrição'],rows,[28,110],fmt,'FontesMetodo')
