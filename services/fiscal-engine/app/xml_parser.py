from __future__ import annotations
from datetime import datetime
from decimal import Decimal,ROUND_HALF_UP
from hashlib import sha256
import re
from lxml import etree
from .catalog import CatalogSnapshot
NS={'n':'http://www.portalfiscal.inf.br/nfe'}
ZERO=Decimal('0');CENT=Decimal('0.01')

def _text(node,path,default=''):
    result=node.xpath(path,namespaces=NS)
    if isinstance(result,(str,float,bool)):return str(result) if result not in ('',None) else default
    if not result:return default
    value=result[0]
    if isinstance(value,etree._Element):return (value.text or default).strip()
    return str(value).strip()

def _dec(node,path):
    v=_text(node,path,'0').replace(',','.')
    try:return Decimal(v)
    except:return ZERO

def _sum(node,path):return sum((_dec(x,'.') for x in node.xpath(path,namespaces=NS)),ZERO)
def _money(v:Decimal):return v.quantize(CENT,rounding=ROUND_HALF_UP)
def _iso(value:str):
    if not value:return None
    try:return datetime.fromisoformat(value.replace('Z','+00:00')).isoformat()
    except:return value

def _tax_id(node,prefix):return _text(node,f'{prefix}/n:CNPJ') or _text(node,f'{prefix}/n:CPF') or None

def finding(rule,severity,category,title,description,document_ref,item_number=None,evidence=None,impact=None,action=None,confidence=None):
    return {'document_ref':document_ref,'item_number':item_number,'rule_code':rule,'rule_version':'1.0.0','severity':severity,'category':category,'title':title,'description':description,'impact':impact,'recommended_action':action,'status':'open','confidence':confidence,'evidence':evidence or {}}

def parse_event(data:bytes):
    parser=etree.XMLParser(resolve_entities=False,no_network=True,remove_blank_text=True,huge_tree=False)
    root=etree.fromstring(data,parser)
    if etree.QName(root).localname not in {'procEventoNFe','evento'}:return None
    return {'access_key':_text(root,'.//n:chNFe'),'event_type':_text(root,'.//n:tpEvento'),'status_code':_text(root,'.//n:cStat'),'description':_text(root,'.//n:xEvento'),'registered_at':_iso(_text(root,'.//n:dhRegEvento'))}

def parse_invoice(data:bytes,source_file_id:str,xml_storage_path:str,catalog:CatalogSnapshot,company_tax_id:str):
    parser=etree.XMLParser(resolve_entities=False,no_network=True,remove_blank_text=True,huge_tree=False)
    root=etree.fromstring(data,parser)
    inf=(root.xpath('.//n:infNFe',namespaces=NS) or [None])[0]
    if inf is None:raise ValueError('XML não contém infNFe')
    access_key=(inf.get('Id') or '').removeprefix('NFe') or _text(root,'.//n:protNFe/n:infProt/n:chNFe')
    document_ref=access_key or sha256(data).hexdigest()
    issuer=_tax_id(inf,'./n:emit');recipient=_tax_id(inf,'./n:dest')
    tp_nf=_text(inf,'./n:ide/n:tpNF')
    direction=('saida' if tp_nf=='1' else 'entrada') if issuer==company_tax_id else ('entrada' if recipient==company_tax_id else ('saida' if tp_nf=='1' else 'entrada'))
    issued_at=_iso(_text(inf,'./n:ide/n:dhEmi') or _text(inf,'./n:ide/n:dEmi'))
    issued_on=datetime.fromisoformat(issued_at).date() if issued_at else None
    items=[];findings=[]
    for det in inf.xpath('./n:det',namespaces=NS):
        nitem=int(det.get('nItem','0'));prod=(det.xpath('./n:prod',namespaces=NS) or [det])[0];tax=(det.xpath('./n:imposto',namespaces=NS) or [det])[0]
        components={
          'vProd':_dec(prod,'./n:vProd'),'vServ':_dec(tax,'./n:ISSQN/n:vServ'),'vFrete':_dec(prod,'./n:vFrete'),'vSeg':_dec(prod,'./n:vSeg'),'vOutro':_dec(prod,'./n:vOutro'),'vII':_sum(tax,'.//n:II/n:vII'),'vDesc':_dec(prod,'./n:vDesc'),'vPIS':_sum(tax,'.//n:PIS/*/n:vPIS'),'vCOFINS':_sum(tax,'.//n:COFINS/*/n:vCOFINS'),'vICMS':_sum(tax,'.//n:ICMS/*/n:vICMS'),'vICMSUFDest':_sum(tax,'.//n:ICMSUFDest/n:vICMSUFDest'),'vFCP':_sum(tax,'.//n:ICMS/*/n:vFCP'),'vFCPUFDest':_sum(tax,'.//n:ICMSUFDest/n:vFCPUFDest'),'vICMSMono':_sum(tax,'.//n:ICMS/*/n:vICMSMono'),'vISSQN':_sum(tax,'.//n:ISSQN/n:vISSQN'),'vIS':_sum(tax,'.//n:IS//n:vIS')}
        has_base_group=bool(tax.xpath('./n:IBSCBS/n:gIBSCBS',namespaces=NS))
        base_calc=_money(components['vProd']+components['vServ']+components['vFrete']+components['vSeg']+components['vOutro']+components['vII']-components['vDesc']-components['vPIS']-components['vCOFINS']-components['vICMS']-components['vICMSUFDest']-components['vFCP']-components['vFCPUFDest']-components['vICMSMono']-components['vISSQN']+components['vIS'])
        base_xml=_money(_dec(tax,'./n:IBSCBS/n:gIBSCBS/n:vBC'))
        if not has_base_group:base_calc=ZERO
        ibs_rate=_dec(tax,'./n:IBSCBS/n:gIBSCBS/n:gIBSUF/n:pIBSUF')+_dec(tax,'./n:IBSCBS/n:gIBSCBS/n:gIBSMun/n:pIBSMun')
        cbs_rate=_dec(tax,'./n:IBSCBS/n:gIBSCBS/n:gCBS/n:pCBS')
        ibs_xml=_money(_dec(tax,'./n:IBSCBS/n:gIBSCBS/n:vIBS') or (_dec(tax,'./n:IBSCBS/n:gIBSCBS/n:gIBSUF/n:vIBSUF')+_dec(tax,'./n:IBSCBS/n:gIBSCBS/n:gIBSMun/n:vIBSMun')))
        cbs_xml=_money(_dec(tax,'./n:IBSCBS/n:gIBSCBS/n:gCBS/n:vCBS'))
        ibs_calc=_money(base_xml*ibs_rate/Decimal('100'));cbs_calc=_money(base_xml*cbs_rate/Decimal('100'))
        ncm=re.sub(r'\D','',_text(prod,'./n:NCM'));ex_code=_text(prod,'./n:EXTIPI') or None
        actual_cst=_text(tax,'./n:IBSCBS/n:CST') or None;actual_cc=_text(tax,'./n:IBSCBS/n:cClassTrib') or None
        match=catalog.match(ncm or None,ex_code,issued_on);entry=match.get('entry');expected_cst=entry.expected_cst if entry else None;expected_cc=entry.expected_cclass_trib if entry else None
        class_status=match['status']
        if class_status=='MATCH':
            if actual_cst==expected_cst and actual_cc==expected_cc:class_status='MATCH_EXACT'
            elif not actual_cst or not actual_cc:class_status='DOCUMENT_CLASSIFICATION_MISSING'
            elif actual_cst!=expected_cst and actual_cc!=expected_cc:class_status='DOCUMENT_CST_CCLASS_DIVERGENT'
            elif actual_cst!=expected_cst:class_status='DOCUMENT_CST_DIVERGENT'
            else:class_status='DOCUMENT_CCLASS_DIVERGENT'
        evidence={'ncm':ncm,'ex_code':ex_code,'actual_cst':actual_cst,'actual_cclass_trib':actual_cc,'expected_cst':expected_cst,'expected_cclass_trib':expected_cc,'strategy':match.get('strategy'),'parameter_source_row':entry.source_row if entry else None,'catalog_version_id':catalog.version_id}
        if class_status!='MATCH_EXACT':
            sev='high' if 'DIVERGENT' in class_status or class_status in {'PARAMETER_INVALID','AMBIGUOUS_PARAMETERIZATION'} else 'medium'
            findings.append(finding('NCM-CLASS-001',sev,'ncm_class_trib','Divergência NCM × ClassTrib',f'Situação identificada: {class_status}.',document_ref,nitem,evidence,'Pode comprometer o enquadramento fiscal, a alíquota e a apuração assistida.','Revisar a parametrização e o XML conforme o catálogo vigente.'))
        if has_base_group and abs(base_xml-base_calc)>CENT:
            findings.append(finding('UB16-10-BASE-001','critical','calculation','Base IBS/CBS divergente','A base informada no XML difere da reconstrução da regra UB16-10.',document_ref,nitem,{'base_xml':str(base_xml),'base_recalculated':str(base_calc),'difference':str(base_xml-base_calc),'components':{k:str(v) for k,v in components.items()}},'Pode alterar os débitos de IBS e CBS.','Reprocessar o cálculo do item e revisar os componentes da base.'))
        if abs(ibs_xml-ibs_calc)>CENT or abs(cbs_xml-cbs_calc)>CENT:
            findings.append(finding('IBS-CBS-VALUE-001','high','calculation','IBS/CBS divergente','Os tributos destacados divergem do recálculo item a item.',document_ref,nitem,{'ibs_xml':str(ibs_xml),'ibs_recalculated':str(ibs_calc),'cbs_xml':str(cbs_xml),'cbs_recalculated':str(cbs_calc),'ibs_rate':str(ibs_rate),'cbs_rate':str(cbs_rate)},'Pode causar diferença na apuração.','Revisar alíquotas, base e arredondamento Decimal half-up.'))
        pis_base=_dec(tax,'.//n:PIS/*/n:vBC');pis_rate=_dec(tax,'.//n:PIS/*/n:pPIS');pis_xml=_money(_dec(tax,'.//n:PIS/*/n:vPIS'));pis_expected=_money(pis_base*pis_rate/Decimal('100'))
        cof_base=_dec(tax,'.//n:COFINS/*/n:vBC');cof_rate=_dec(tax,'.//n:COFINS/*/n:pCOFINS');cof_xml=_money(_dec(tax,'.//n:COFINS/*/n:vCOFINS'));cof_expected=_money(cof_base*cof_rate/Decimal('100'))
        # Em operações de veículos usados, PIS e COFINS normalmente compartilham a mesma base.
        # A base explícita do XML é preservada para conciliação exata; inferência por alíquota
        # combinada é usada apenas como fallback quando o documento não trouxer vBC.
        pis_cofins_base=_money(pis_base or cof_base)
        if (pis_base and abs(pis_xml-pis_expected)>ZERO) or (cof_base and abs(cof_xml-cof_expected)>ZERO):
            findings.append(finding('PIS-COFINS-ROUND-001','medium','rounding','Arredondamento de PIS/COFINS divergente','O valor informado diverge do arredondamento comercial para centavos.',document_ref,nitem,{'pis_base':str(pis_base),'pis_rate':str(pis_rate),'pis_xml':str(pis_xml),'pis_expected':str(pis_expected),'cofins_base':str(cof_base),'cofins_rate':str(cof_rate),'cofins_xml':str(cof_xml),'cofins_expected':str(cof_expected)},'A diferença propaga-se para a base IBS/CBS.','Padronizar o motor fiscal para Decimal ROUND_HALF_UP e validar a tolerância documental.'))
        used=_text(prod,'./n:indBemMovelUsado')=='1';additional=_text(det,'./n:infAdProd');chassis_match=re.search(r'CHASSI:\s*([A-Z0-9]{17})',additional,re.I);plate_match=re.search(r'PLACA:\s*([A-Z0-9-]+)',additional,re.I)
        if (used or chassis_match) and ncm and not ncm.startswith('87'):
            findings.append(finding('NCM-VEHICLE-001','high','ncm','NCM incompatível com veículo','Item identificado como bem móvel usado/veículo está fora do capítulo 87.',document_ref,nitem,{'ncm':ncm,'description':_text(prod,'./n:xProd'),'chassis':chassis_match.group(1) if chassis_match else None},'Pode afetar incidência, obrigações acessórias e Imposto Seletivo.','Revisar o cadastro fiscal do produto.'))
        if direction=='entrada' and used and actual_cst=='410' and actual_cc=='410999':
            findings.append(finding('USED-GOOD-CLASS-001','high','classification','Aquisição de bem usado em classificação genérica','Aquisição onerosa para revenda foi informada em cClassTrib 410999.',document_ref,nitem,{'actual':'410999','candidate':'410017','indBemMovelUsado':True},'Pode impedir a identificação automática do crédito presumido.','Validar o uso de CST 410/cClassTrib 410017 com a assessoria fiscal e a tabela vigente.'))
        items.append({'item_number':nitem,'product_code':_text(prod,'./n:cProd') or None,'description':_text(prod,'./n:xProd') or None,'ncm':ncm or None,'ex_code':ex_code,'cfop':_text(prod,'./n:CFOP') or None,'actual_cst':actual_cst,'actual_cclass_trib':actual_cc,'expected_cst':expected_cst,'expected_cclass_trib':expected_cc,'classification_status':class_status,'product_value':str(_money(components['vProd'])),'ibs_cbs_base_xml':str(base_xml),'ibs_cbs_base_recalculated':str(base_calc),'base_difference':str(_money(base_xml-base_calc)),'ibs_xml':str(ibs_xml),'ibs_recalculated':str(ibs_calc),'cbs_xml':str(cbs_xml),'cbs_recalculated':str(cbs_calc),'tax_components':{k:str(v) for k,v in components.items()},'catalog_match':evidence,'chassis':chassis_match.group(1).upper() if chassis_match else None,'plate':plate_match.group(1).upper() if plate_match else None,'used_movable_good':used,'pis_cofins':str(_money(components['vPIS']+components['vCOFINS'])),'pis_cofins_base':str(pis_cofins_base)})
    total=sum((Decimal(i['product_value']) for i in items),ZERO);base=sum((Decimal(i['ibs_cbs_base_xml']) for i in items),ZERO);ibs=sum((Decimal(i['ibs_xml']) for i in items),ZERO);cbs=sum((Decimal(i['cbs_xml']) for i in items),ZERO)
    doc={'document_ref':document_ref,'source_file_id':source_file_id,'access_key':access_key or None,'model':_text(inf,'./n:ide/n:mod') or None,'series':_text(inf,'./n:ide/n:serie') or None,'number':_text(inf,'./n:ide/n:nNF') or None,'issued_at':issued_at,'direction':direction,'status':'authorized' if _text(root,'.//n:protNFe/n:infProt/n:cStat') in {'100','150'} else 'parsed','issuer_tax_id':issuer,'recipient_tax_id':recipient,'total_value':str(_money(_dec(inf,'./n:total/n:ICMSTot/n:vNF') or total)),'ibs_cbs_base':str(_money(base)),'ibs_value':str(_money(ibs)),'cbs_value':str(_money(cbs)),'item_count':len(items),'normalized':{'issuer_name':_text(inf,'./n:emit/n:xNome'),'recipient_name':_text(inf,'./n:dest/n:xNome'),'nature':_text(inf,'./n:ide/n:natOp'),'protocol':_text(root,'.//n:protNFe/n:infProt/n:nProt'),'authorization_status':_text(root,'.//n:protNFe/n:infProt/n:cStat'),'xml_sha256':sha256(data).hexdigest()},'xml_storage_path':xml_storage_path,'items':items}
    return doc,findings
