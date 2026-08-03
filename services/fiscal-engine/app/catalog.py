from dataclasses import dataclass
from datetime import date
from sqlalchemy import text
from .database import create_database_engine
@dataclass(slots=True)
class ParamEntry:
    id:str;ncm:str;ex_code:str|None;expected_cst:str|None;expected_cclass_trib:str|None;description:str|None;status:str;validation_issues:list;source_row:int|None;reduction_type:str|None;valid_from:date|None;valid_to:date|None
class CatalogSnapshot:
    def __init__(self,version_id:str):
        self.version_id=version_id;self.issues=[];self.by_key:dict[tuple[str,str|None],list[ParamEntry]]={};self.cclass:dict[str,dict]={};self.cst:set[str]=set();self._load()
    def _load(self):
        engine=create_database_engine()
        with engine.connect() as c:
            for r in c.execute(text('SELECT code,severity,source_sheet,source_row,message,context FROM catalog_import_issues WHERE catalog_version_id=:v'),{'v':self.version_id}).mappings():self.issues.append(dict(r))
            for r in c.execute(text('SELECT cst FROM cst_catalog_entries WHERE catalog_version_id=:v'),{'v':self.version_id}).mappings():self.cst.add(r['cst'])
            for r in c.execute(text('SELECT cclass_trib,cst,ibs_reduction_percent,cbs_reduction_percent,applicable_nfe,valid_from,valid_to,name FROM cclass_catalog_entries WHERE catalog_version_id=:v'),{'v':self.version_id}).mappings():self.cclass[r['cclass_trib']]=dict(r)
            sql="SELECT id,ncm,ex_code,expected_cst,expected_cclass_trib,description,status,validation_issues,source_row,reduction_type,valid_from,valid_to FROM ncm_class_trib_entries WHERE catalog_version_id=:v AND ncm_level='item' AND ncm IS NOT NULL"
            for r in c.execute(text(sql),{'v':self.version_id}).mappings():
                raw=dict(r);raw['validation_issues']=raw['validation_issues'] or []
                e=ParamEntry(**raw);self.by_key.setdefault((e.ncm,e.ex_code),[]).append(e)
        engine.dispose()
    def match(self,ncm:str|None,ex_code:str|None,issued_on:date|None):
        if not ncm:return {'status':'DOCUMENT_NCM_MISSING','entry':None,'strategy':'none'}
        candidates=self.by_key.get((ncm,ex_code)) if ex_code else self.by_key.get((ncm,None))
        strategy='NCM_EXACT_WITH_EX' if ex_code else 'NCM_EXACT'
        if not candidates and ex_code:candidates=self.by_key.get((ncm,None));strategy='NCM_FALLBACK_WITHOUT_EX'
        if not candidates:return {'status':'NCM_NOT_PARAMETERIZED','entry':None,'strategy':'none'}
        eligible=[e for e in candidates if (not issued_on or (not e.valid_from or e.valid_from<=issued_on) and (not e.valid_to or issued_on<=e.valid_to))]
        if not eligible:return {'status':'PARAMETER_OUT_OF_VALIDITY','entry':candidates[0],'strategy':strategy}
        if len(eligible)>1:return {'status':'AMBIGUOUS_PARAMETERIZATION','entry':eligible[0],'strategy':strategy,'candidates':len(eligible)}
        e=eligible[0]
        return {'status':'PARAMETER_INVALID' if e.status=='error' else 'MATCH','entry':e,'strategy':strategy}
