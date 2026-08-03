from __future__ import annotations
from pathlib import Path
from tempfile import TemporaryDirectory
from zipfile import ZipFile,BadZipFile
from hashlib import sha256
from decimal import Decimal
import json
import re
from .catalog import CatalogSnapshot
from .cross_rules import apply_cross_document_rules
from .reports.pdf_report import build_pdf
from .reports.excel_report import build_excel
from .danfe import build_danfe
from .settings import settings
from .storage import ObjectStorage
from .xml_parser import parse_event,parse_invoice,finding
class AuditService:
    def __init__(self):self.storage=ObjectStorage()
    def run(self,payload:dict):
        catalog=CatalogSnapshot(payload['catalog_version_id']);documents=[];findings=[];events=[]
        with TemporaryDirectory(prefix='auditor-') as temp:
            root=Path(temp);xml_candidates=[];pdf_candidates=[]
            for source in payload['source_files']:
                safe_source_name=f"{source['id']}-{Path(source['original_name']).name}"
                local=self.storage.download(source['storage_path'],root/'sources'/safe_source_name)
                if local.suffix.lower()=='.zip':
                    for candidate in self._safe_extract(local,root/'extracted'/source['id']):
                        (xml_candidates if candidate.suffix.lower()=='.xml' else pdf_candidates).append((candidate,source['id']))
                elif local.suffix.lower()=='.xml':xml_candidates.append((local,source['id']))
                elif local.suffix.lower()=='.pdf':pdf_candidates.append((local,source['id']))
            for xml_path,source_id in xml_candidates:
                data=xml_path.read_bytes();tmp_ref=sha256(data).hexdigest()
                try:
                    event=parse_event(data)
                    if event:events.append(event);continue
                    storage_path=f"batches/{payload['batch_id']}/xml/{tmp_ref}.xml"
                    doc,doc_findings=parse_invoice(data,source_id,storage_path,catalog,payload['company']['tax_id'])
                    storage_path=f"batches/{payload['batch_id']}/xml/{doc.get('access_key') or tmp_ref}.xml";doc['xml_storage_path']=storage_path;self.storage.put_bytes(data,storage_path)
                    documents.append(doc);findings.extend(doc_findings)
                except Exception as exc:
                    findings.append(finding('XML-PARSE-001','critical','document','XML inválido ou não suportado',str(exc),tmp_ref,evidence={'file':xml_path.name,'sha256':tmp_ref},impact='Documento não auditado.',action='Corrigir ou substituir o XML de origem.'))
            documents_by_key={document.get('access_key'):document for document in documents if document.get('access_key')}
            attached_danfe=set();documents_by_source={}
            for document in documents:documents_by_source.setdefault(document.get('source_file_id'),[]).append(document)
            for pdf_path,source_id in pdf_candidates:
                match=re.search(r'(?<!\d)(\d{44})(?!\d)',pdf_path.name)
                access_key=match.group(1) if match and match.group(1) in documents_by_key else None
                source_documents=documents_by_source.get(source_id,[])
                if not access_key and len(source_documents)==1:access_key=source_documents[0].get('access_key')
                if not access_key and len(documents)==1 and len(pdf_candidates)==1:access_key=documents[0].get('access_key')
                if not access_key or access_key not in documents_by_key or access_key in attached_danfe:continue
                data=pdf_path.read_bytes()
                if not data.startswith(b'%PDF-'):continue
                danfe_path=f"batches/{payload['batch_id']}/danfe/{access_key}.pdf"
                self.storage.put_bytes(data,danfe_path,content_type='application/pdf')
                documents_by_key[access_key]['danfe_storage_path']=danfe_path;documents_by_key[access_key]['normalized']['danfe_source']='imported';attached_danfe.add(access_key)
            generated_dir=root/'danfe-generated';generated_dir.mkdir()
            for document in documents:
                if document.get('danfe_storage_path'):continue
                reference=document.get('access_key') or document.get('document_ref')
                generated=generated_dir/f'{reference}.pdf';build_danfe(generated,document)
                danfe_path=f"batches/{payload['batch_id']}/danfe/{reference}.pdf"
                self.storage.upload(generated,danfe_path,'application/pdf')
                document['danfe_storage_path']=danfe_path;document['normalized']['danfe_source']='generated_from_xml'
            event_map={e['access_key']:e for e in events if e.get('event_type')=='110111' and e.get('status_code') in {'135','136','155'}}
            for d in documents:
                if d.get('access_key') in event_map:d['status']='cancelled';d['normalized']['cancellation_event']=event_map[d['access_key']]
            findings.extend(apply_cross_document_rules([d for d in documents if d['status']!='cancelled']))
            summary=self._summary(documents,findings)
            period={'start':payload.get('period_start'),'end':payload.get('period_end')};catalog_meta={'id':payload['catalog_version_id'],'version':payload.get('catalog_version'),'sha256':payload.get('catalog_sha256')}
            reports_dir=root/'reports';reports_dir.mkdir();pdf=reports_dir/'relatorio.pdf';xlsx=reports_dir/'relatorio.xlsx'
            build_pdf(pdf,payload['company'],period,summary,documents,findings,catalog_meta,settings.report_template_version)
            build_excel(xlsx,payload['company'],period,summary,documents,findings,catalog_meta,getattr(catalog,'issues',[]))
            reports=[]
            for typ,file,mime in [('pdf',pdf,'application/pdf'),('xlsx',xlsx,'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')]:
                key=f"batches/{payload['batch_id']}/reports/auditoria.{typ}";meta=self.storage.upload(file,key,mime);reports.append({'type':typ,'template_version':settings.report_template_version,'storage_path':key,'size':meta['size'],'sha256':sha256(file.read_bytes()).hexdigest(),'metadata':{'catalog_version':payload.get('catalog_version')}})
        return {'documents':documents,'findings':findings,'summary':summary,'reports':reports,'events':events}
    def _safe_extract(self,archive:Path,dest:Path):
        dest.mkdir(parents=True,exist_ok=True);files=[]
        try:
            with ZipFile(archive) as z:
                members=z.infolist()
                if len(members)>settings.zip_max_files:raise ValueError('ZIP excede o limite de arquivos')
                total=sum(m.file_size for m in members)
                if total>settings.zip_max_uncompressed_mb*1024*1024:raise ValueError('ZIP excede o limite descompactado')
                for m in members:
                    if m.is_dir() or Path(m.filename).suffix.lower() not in {'.xml','.pdf'}:continue
                    member_path=Path(m.filename.replace('\\','/'))
                    if member_path.is_absolute() or '..' in member_path.parts:raise ValueError('Caminho inseguro no ZIP')
                    if (m.external_attr >> 16) & 0o170000 == 0o120000:raise ValueError('Link simbólico não permitido no ZIP')
                    if m.flag_bits & 0x1:raise ValueError('Arquivo criptografado não permitido no ZIP')
                    safe_name=f'{sha256(m.filename.encode()).hexdigest()[:12]}-{Path(m.filename).name}'
                    target=(dest/safe_name).resolve()
                    if not target.is_relative_to(dest.resolve()):raise ValueError('Caminho inseguro no ZIP')
                    with z.open(m) as src,target.open('wb') as out:
                        while chunk:=src.read(1024*1024):out.write(chunk)
                    files.append(target)
        except BadZipFile as e:raise ValueError('Arquivo ZIP inválido') from e
        return files
    def _summary(self,docs,findings):
        active=[d for d in docs if d.get('status')!='cancelled'];inputs=[d for d in active if d.get('direction')=='entrada'];outputs=[d for d in active if d.get('direction')=='saida'];items=[i for d in active for i in d.get('items',[])]
        add=lambda arr,key:str(sum((Decimal(str(x.get(key,0))) for x in arr),Decimal('0')))
        return {'document_count':len(active),'item_count':len(items),'input_count':len(inputs),'output_count':len(outputs),'total_value':add(active,'total_value'),'input_value':add(inputs,'total_value'),'output_value':add(outputs,'total_value'),'ibs_cbs_base':add(active,'ibs_cbs_base'),'ibs_value':add(active,'ibs_value'),'cbs_value':add(active,'cbs_value'),'classification_ok':sum(i.get('classification_status')=='MATCH_EXACT' for i in items),'classification_divergent':sum(i.get('classification_status')!='MATCH_EXACT' for i in items),'finding_count':len(findings),'severity_counts':{s:sum(f.get('severity')==s for f in findings) for s in ['critical','high','medium','low']}}
