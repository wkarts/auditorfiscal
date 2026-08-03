from __future__ import annotations
from pathlib import Path
from decimal import Decimal
from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER,TA_LEFT
from reportlab.lib.pagesizes import A4,landscape
from reportlab.lib.styles import getSampleStyleSheet,ParagraphStyle
from reportlab.lib.units import mm
from reportlab.platypus import SimpleDocTemplate,Paragraph,Spacer,Table,TableStyle,PageBreak,KeepTogether
from ..product_reconciliation import build_product_reconciliation
NAVY=colors.HexColor('#12324A');TEAL=colors.HexColor('#137D7B');LIGHT=colors.HexColor('#EAF2F7');MINT=colors.HexColor('#E3F3EE');WARN=colors.HexColor('#FFF0CC');DANGER=colors.HexColor('#FCE5E3');GRID=colors.HexColor('#B8C7D1')
def money(v):return f'R$ {Decimal(str(v or 0)):,.2f}'.replace(',','X').replace('.',',').replace('X','.')
def money_or_dash(v):return money(v) if v is not None else '—'
def p(value,style):return Paragraph(str(value or '—'),style)
def build_pdf(path:Path,company:dict,period:dict,summary:dict,documents:list[dict],findings:list[dict],catalog:dict,template_version:str):
    styles=getSampleStyleSheet();body=ParagraphStyle('body',parent=styles['BodyText'],fontName='Helvetica',fontSize=7.5,leading=9.5,textColor=colors.HexColor('#263746'));small=ParagraphStyle('small',parent=body,fontSize=6.3,leading=7.7);title=ParagraphStyle('title',parent=styles['Title'],fontName='Helvetica-Bold',fontSize=20,leading=23,textColor=colors.white,alignment=TA_LEFT);h1=ParagraphStyle('h1',parent=styles['Heading1'],fontName='Helvetica-Bold',fontSize=17,leading=20,textColor=NAVY,spaceAfter=8);center=ParagraphStyle('center',parent=body,alignment=TA_CENTER)
    def footer(canvas,doc):
        canvas.saveState();canvas.setFont('Helvetica',7);canvas.setFillColor(colors.HexColor('#6B7783'));canvas.drawString(10*mm,7*mm,f"{company.get('legal_name','Empresa')} | Auditoria fiscal IBS/CBS");canvas.drawRightString(landscape(A4)[0]-10*mm,7*mm,f'Página {doc.page}');canvas.restoreState()
    doc=SimpleDocTemplate(str(path),pagesize=landscape(A4),rightMargin=10*mm,leftMargin=10*mm,topMargin=10*mm,bottomMargin=13*mm,title='Relatório Analítico e Sintético - IBS/CBS em NF-e',author='Auditor Fiscal')
    story=[]
    header=Table([[p('Relatório Analítico e Sintético - IBS/CBS em NF-e',title)],[p(f"{company.get('legal_name','')} | {period.get('start') or '—'} a {period.get('end') or '—'} | Catálogo {catalog.get('version','—')}",ParagraphStyle('sub',parent=body,textColor=colors.white,fontSize=8.5))]],colWidths=[landscape(A4)[0]-20*mm]);header.setStyle(TableStyle([('BACKGROUND',(0,0),(-1,-1),NAVY),('LEFTPADDING',(0,0),(-1,-1),9*mm),('RIGHTPADDING',(0,0),(-1,-1),9*mm),('TOPPADDING',(0,0),(-1,0),5*mm),('BOTTOMPADDING',(0,1),(-1,1),4*mm)]));story+=[header,Spacer(1,5*mm)]
    kpis=[('XMLs analisados',len(documents),LIGHT),('Valor total das NF-e',money(summary.get('total_value')),MINT),('Base IBS/CBS',money(summary.get('ibs_cbs_base')),LIGHT),('IBS + CBS',money(Decimal(str(summary.get('ibs_value',0)))+Decimal(str(summary.get('cbs_value',0)))),MINT),('Entradas',f"{summary.get('input_count',0)} XMLs · {money(summary.get('input_value'))}",LIGHT),('Saídas',f"{summary.get('output_count',0)} XMLs · {money(summary.get('output_value'))}",LIGHT),('Itens NCM/Class OK',summary.get('classification_ok',0),WARN),('Achados abertos',len(findings),DANGER)]
    cells=[]
    for label,val,bg in kpis:cells.append(Table([[p(label,center)],[p(f'<b>{val}</b>',ParagraphStyle('kv',parent=center,fontSize=12,leading=14,textColor=NAVY))]],colWidths=[66*mm],rowHeights=[8*mm,13*mm],style=[('BACKGROUND',(0,0),(-1,-1),bg),('BOX',(0,0),(-1,-1),.4,GRID),('VALIGN',(0,0),(-1,-1),'MIDDLE')]))
    story.append(Table([cells[:4],cells[4:]],colWidths=[68.5*mm]*4,rowHeights=[23*mm,23*mm],style=[('VALIGN',(0,0),(-1,-1),'MIDDLE')]))
    priorities=sorted(findings,key=lambda f:{'critical':0,'high':1,'medium':2,'low':3}.get(f.get('severity'),9))[:5]
    conclusion=f"Foram processados {len(documents)} documentos e {sum(len(d.get('items',[])) for d in documents)} itens. A base IBS/CBS consolidada foi {money(summary.get('ibs_cbs_base'))}. O motor reconstruiu a base item a item e cruzou NCM, EX, CST e cClassTrib contra o snapshot {catalog.get('version','')}."
    actions='<br/>'.join(f"{i+1}. {x.get('title')}: {x.get('recommended_action') or x.get('description')}" for i,x in enumerate(priorities)) or 'Nenhuma ação prioritária identificada.'
    block=Table([[p('<b>CONCLUSÃO CENTRAL</b>',center),p('<b>AÇÕES PRIORITÁRIAS</b>',center)],[p(conclusion,body),p(actions,body)]],colWidths=[138*mm,138*mm]);block.setStyle(TableStyle([('BACKGROUND',(0,0),(-1,0),TEAL),('TEXTCOLOR',(0,0),(-1,0),colors.white),('BACKGROUND',(0,1),(0,1),LIGHT),('BACKGROUND',(1,1),(1,1),WARN),('BOX',(0,0),(-1,-1),.5,GRID),('INNERGRID',(0,0),(-1,-1),.3,GRID),('VALIGN',(0,0),(-1,-1),'TOP'),('PADDING',(0,0),(-1,-1),6)]));story+=[Spacer(1,4*mm),block,PageBreak()]
    story += [p('1. Como a base de IBS/CBS foi formada',h1),Table([[p('<b>Fórmula do leiaute NF-e — regra UB16-10</b>',center)],[p('BC IBS/CBS = vProd + vServ + vFrete + vSeg + vOutro + vII - vDesc - vPIS - vCOFINS - vICMS - vICMSUFDest - vFCP - vFCPUFDest - vICMSMono - vISSQN + vIS.',body)]],colWidths=[276*mm],style=[('BACKGROUND',(0,0),(-1,0),TEAL),('TEXTCOLOR',(0,0),(-1,0),colors.white),('BACKGROUND',(0,1),(-1,1),LIGHT),('BOX',(0,0),(-1,-1),.5,TEAL),('PADDING',(0,0),(-1,-1),7)])]
    comps={k:Decimal('0') for k in ['vProd','vServ','vFrete','vSeg','vOutro','vII','vDesc','vPIS','vCOFINS','vICMS','vICMSUFDest','vFCP','vFCPUFDest','vICMSMono','vISSQN','vIS']}
    for d in documents:
        for i in d.get('items',[]):
            for k in comps:comps[k]+=Decimal(str(i.get('tax_components',{}).get(k,0)))
    rows=[['Componente','Sinal','Valor']]+[[k,'-' if k in {'vDesc','vPIS','vCOFINS','vICMS','vICMSUFDest','vFCP','vFCPUFDest','vICMSMono','vISSQN'} else '+',money(v)] for k,v in comps.items() if v]
    story += [Spacer(1,5*mm),_table(rows,[90*mm,25*mm,60*mm],body,small),Spacer(1,5*mm)]
    taxrows=[['Tributo','Valor XML','Recálculo item a item','Diferença'],['IBS',money(summary.get('ibs_value')),money(sum(Decimal(i['ibs_recalculated']) for d in documents for i in d.get('items',[]))),money(sum(Decimal(i['ibs_xml'])-Decimal(i['ibs_recalculated']) for d in documents for i in d.get('items',[])))],['CBS',money(summary.get('cbs_value')),money(sum(Decimal(i['cbs_recalculated']) for d in documents for i in d.get('items',[]))),money(sum(Decimal(i['cbs_xml'])-Decimal(i['cbs_recalculated']) for d in documents for i in d.get('items',[])))]]
    story += [_table(taxrows,[55*mm]*4,body,small),PageBreak()]
    outputs=[d for d in documents if d.get('direction')=='saida'];inputs=[d for d in documents if d.get('direction')=='entrada']
    story += [p('2. Relatório analítico das saídas',h1),_document_table(outputs,body,small),PageBreak(),p('3. Relatório analítico das entradas',h1),_document_table(inputs,body,small),PageBreak()]
    story += [p('4. Inconsistências e pontos de revisão',h1)]
    frows=[['Sev.','NF','Categoria','Achado / evidência','Impacto','Ação recomendada']]
    for f in sorted(findings,key=lambda x:{'critical':0,'high':1,'medium':2,'low':3}.get(x.get('severity'),9)):
        frows.append([f.get('severity','').upper(),_nf_for(f,documents),f.get('category'),f.get('description'),f.get('impact'),f.get('recommended_action')])
    story += [_table(frows,[16*mm,18*mm,35*mm,76*mm,58*mm,73*mm],body,small),PageBreak()]
    story += [p('5. Auditoria NCM × ClassTrib',h1)]
    nrows=[['NF','Item','Produto','NCM/EX','CST/cClass encontrada','CST/cClass esperada','Situação']]
    for d in documents:
        for i in d.get('items',[]):nrows.append([d.get('number'),i.get('item_number'),i.get('description'),f"{i.get('ncm') or '—'} / {i.get('ex_code') or '—'}",f"{i.get('actual_cst') or '—'} / {i.get('actual_cclass_trib') or '—'}",f"{i.get('expected_cst') or '—'} / {i.get('expected_cclass_trib') or '—'}",i.get('classification_status')])
    story += [_table(nrows,[18*mm,12*mm,76*mm,28*mm,40*mm,40*mm,50*mm],body,small),PageBreak()]
    story += [p('6. Conciliação econômica de produtos',h1),_reconciliation_table(documents,body,small),Spacer(1,4*mm),p('A identidade usa, em ordem de confiança, identificador individual, lote, GTIN ou correspondência indicativa por NCM, descrição e unidade. Custos e margens agregados são estimativas limitadas aos XMLs da amostra; estoque inicial ou entrada ausente não representa irregularidade.',body),PageBreak()]
    story += [p('7. Fontes, método e limitações',h1),_table([['Referência','Versão/uso'],['Catálogo CST/cClassTrib e NCM × ClassTrib',f"{catalog.get('version')} · SHA-256 {catalog.get('sha256','—')}"],['Motor fiscal',f"Cálculo item a item, Decimal half-up, template {template_version}"],['Documentos','XMLs são a fonte primária; DANFEs não participam do cálculo.'],['Limitações','Eventos ausentes permanecem como hipóteses; achados cadastrais/econômicos requerem validação fiscal humana.']],[82*mm,194*mm],body,small)]
    doc.build(story,onFirstPage=footer,onLaterPages=footer)

def _table(rows,widths,body,small):
    converted=[]
    for ri,row in enumerate(rows):converted.append([p(v,ParagraphStyle(f'c{ri}',parent=small if len(rows)>8 else body,textColor=colors.white if ri==0 else colors.HexColor('#263746'),fontName='Helvetica-Bold' if ri==0 else 'Helvetica')) for v in row])
    t=Table(converted,colWidths=widths,repeatRows=1);t.setStyle(TableStyle([('BACKGROUND',(0,0),(-1,0),TEAL),('ROWBACKGROUNDS',(0,1),(-1,-1),[colors.white,LIGHT]),('GRID',(0,0),(-1,-1),.25,GRID),('VALIGN',(0,0),(-1,-1),'TOP'),('LEFTPADDING',(0,0),(-1,-1),3),('RIGHTPADDING',(0,0),(-1,-1),3),('TOPPADDING',(0,0),(-1,-1),3),('BOTTOMPADDING',(0,0),(-1,-1),3)]));return t

def _document_table(documents,body,small):
    rows=[['NF','Data','Emitente/Destinatário','Valor NF','Base IBS/CBS','IBS','CBS','Itens','Status']]
    for d in documents:rows.append([d.get('number'),str(d.get('issued_at',''))[:10],d.get('normalized',{}).get('recipient_name') or d.get('normalized',{}).get('issuer_name'),money(d.get('total_value')),money(d.get('ibs_cbs_base')),money(d.get('ibs_value')),money(d.get('cbs_value')),d.get('item_count'),d.get('status')])
    return _table(rows,[18*mm,24*mm,77*mm,31*mm,31*mm,23*mm,25*mm,14*mm,28*mm],body,small)
def _nf_for(f,documents):
    ref=f.get('document_ref');d=next((x for x in documents if x.get('document_ref')==ref),None);return d.get('number') if d else 'Lote'
def _reconciliation_table(documents,body,small):
    statuses={'in_stock':'Em estoque/sem saída','missing_input':'Entrada ausente na amostra','insufficient_quantity_data':'Quantidade insuficiente','insufficient_input_quantity':'Estoque inicial/entradas insuficientes','review_identity':'Revisar correspondência','negative_margin':'Margem negativa','zero_margin':'Margem zero','reconciled':'Conciliado','reconciled_estimate':'Estimativa conciliada'}
    rows=[['Produto / identificador','Tipo / confiança','Qtd. entrada','Qtd. saída','Custo estimado','Venda','Margem estimada','Situação']]
    for row in build_product_reconciliation(documents):
        identity_types={'chassis':'Chassi','imei':'IMEI','serial':'Série','aggregation_code':'Agregação','lot':'Lote','gtin':'GTIN','ncm_description':'NCM + descrição'};confidence={'exact':'Exata','high':'Alta','indicative':'Indicativa'}
        rows.append([row['identifier'],f"{identity_types.get(row['identity_type'],row['identity_type'])} / {confidence.get(row['confidence'],row['confidence'])}",row['input_quantity'],row['output_quantity'],money_or_dash(row['estimated_cost']),money(row['output_value']),money_or_dash(row['margin']),statuses.get(row['status'],row['status'])])
    if len(rows)==1:rows.append(['Nenhum identificador de produto suficiente para conciliação','—','—','—','—','—','—','Não avaliado'])
    return _table(rows,[57*mm,33*mm,24*mm,24*mm,34*mm,31*mm,34*mm,39*mm],body,small)
