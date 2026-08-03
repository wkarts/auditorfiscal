from __future__ import annotations
from pathlib import Path
from hashlib import sha256
from decimal import Decimal,InvalidOperation
from sqlalchemy import text
import re
from .database import create_database_engine
from .xlsx_reader import read_xlsx

def _digits(v):return re.sub(r'\D','',str(v or ''))
def _code(v,width):
    d=_digits(v)
    if not d:return None
    return d.zfill(width) if len(d)<=width else d
def _num(v):
    s=str(v or '').strip().replace('%','')
    if not s or s.upper() in {'NT','N/A'}:return None
    if ',' in s:s=s.replace('.','').replace(',','.')
    try:return float(Decimal(s))
    except InvalidOperation:return None

def normalize_catalog(path:Path,valid_from:str,base_version_id:str|None=None):
    engine=create_database_engine()
    with engine.connect() as c:
        if not base_version_id:base_version_id=c.execute(text("SELECT id FROM fiscal_catalog_versions WHERE status='published' ORDER BY published_at DESC LIMIT 1")).scalar_one()
        csts={r[0] for r in c.execute(text('SELECT cst FROM cst_catalog_entries WHERE catalog_version_id=:v'),{'v':base_version_id})}
        cclasses={r['cclass_trib']:dict(r) for r in c.execute(text('SELECT cclass_trib,cst,ibs_reduction_percent,cbs_reduction_percent FROM cclass_catalog_entries WHERE catalog_version_id=:v'),{'v':base_version_id}).mappings()}
    engine.dispose();book=read_xlsx(path);sheet_name='Tabela Completa' if 'Tabela Completa' in book else next(iter(book));rows=book[sheet_name]
    if not rows:raise ValueError('Planilha sem dados')
    header=[str(x).strip() for x in rows[0]]
    aliases={'ncm':['NCM','NCM '],'ex':['EX'],'description':['DESCRIÇÃO','DESCRIÇÃO '],'rate':['ALÍQUOTA (%)'],'cst':['CST IBS/CBS'],'cclass':['cClassTrib'],'reduction':['Tipo_Redução'],'legal':['LC214_Codigo_raw']}
    idx={k:next((header.index(a) for a in names if a in header),None) for k,names in aliases.items()}
    for required in ['ncm','description','cst','cclass']:
        if idx[required] is None:raise ValueError(f'Coluna obrigatória ausente: {required}')
    entries=[];issues=[];previous=''
    for source_row,row in enumerate(rows[1:],2):
        get=lambda k: row[idx[k]] if idx[k] is not None and idx[k]<len(row) else ''
        ncm_raw=str(get('ncm')).strip();ex_raw=str(get('ex')).strip();inherited=False
        if ncm_raw:previous=ncm_raw
        elif ex_raw and previous:ncm_raw=previous;inherited=True
        nd=_digits(ncm_raw);ncm=nd.zfill(8) if len(nd)==8 else nd or None;level='item' if len(nd)==8 else ('hierarchy' if nd else 'missing')
        cst_raw=str(get('cst')).strip();cc_raw=str(get('cclass')).strip();cst=_code(cst_raw,3);cc=_code(cc_raw,6);rowissues=[]
        def add(code,severity,message,context=None):
            item={'code':code,'severity':severity,'source_sheet':sheet_name,'source_row':source_row,'message':message,'context':context or {}};issues.append(item);rowissues.append({'code':code,'severity':severity,**(context or {})})
        if level=='item' and not cst and not cc:add('MISSING_CLASSIFICATION','warning','NCM sem CST/cClassTrib parametrizado.')
        if cst and (len(_digits(cst_raw))>3 or cst not in csts):add('INVALID_CST','error','CST inválido ou inexistente no catálogo oficial.',{'actual':cst_raw})
        if cst and not cc:add('MISSING_CCLASS','error','CST informado sem cClassTrib.')
        if cc and cc not in cclasses:add('UNKNOWN_CCLASS','error','cClassTrib inexistente no catálogo oficial.',{'actual':cc})
        if cc in cclasses and cst and cclasses[cc]['cst']!=cst:add('CST_CCLASS_MISMATCH','error','CST incompatível com a cClassTrib oficial.',{'expected':cclasses[cc]['cst'],'actual':cst})
        red=str(get('reduction')).strip();m=re.search(r'(\d+(?:[\.,]\d+)?)\s*%',red)
        if m and cc in cclasses:
            stated=float(m.group(1).replace(',','.'));official=max(float(cclasses[cc]['ibs_reduction_percent'] or 0),float(cclasses[cc]['cbs_reduction_percent'] or 0))
            if abs(stated-official)>.001:add('REDUCTION_CONFLICT','error','Redução informada diverge do catálogo oficial.',{'expected':official,'actual':stated})
        status='error' if any(x['severity']=='error' for x in rowissues) else ('warning' if rowissues else 'valid')
        entries.append({'ncm_raw':ncm_raw or None,'ncm':ncm,'ncm_level':level,'ex_code':_code(ex_raw,2) if ex_raw else None,'description':str(get('description')).strip(),'reference_rate':_num(get('rate')),'expected_cst':cst,'expected_cclass_trib':cc,'reduction_type':red or None,'legal_reference_raw':str(get('legal')).strip() or None,'conditions':{},'valid_from':valid_from,'valid_to':None,'allow_child_inheritance':False,'inherited_ncm':inherited,'status':status,'validation_issues':rowissues,'source_sheet':sheet_name,'source_row':source_row})
    keys={};
    for e in entries:
        if e['ncm_level']!='item':continue
        k=(e['ncm'],e['ex_code']);sig=(e['expected_cst'],e['expected_cclass_trib'],e['reduction_type'])
        if k in keys and keys[k]!=sig:
            issues.append({'code':'PARAMETER_CONFLICT','severity':'error','source_sheet':sheet_name,'source_row':e['source_row'],'message':'Há regras conflitantes para a mesma chave NCM/EX.','context':{'ncm':e['ncm'],'ex':e['ex_code']}});e['status']='error';e['validation_issues'].append({'code':'PARAMETER_CONFLICT','severity':'error'})
        keys[k]=sig
    return {'entries':entries,'issues':issues,'manifest':{'source_sha256':sha256(path.read_bytes()).hexdigest(),'source_sheet':sheet_name,'rows':len(entries),'valid':sum(e['status']=='valid' for e in entries),'warnings':sum(e['status']=='warning' for e in entries),'errors':sum(e['status']=='error' for e in entries),'base_version_id':base_version_id}}
