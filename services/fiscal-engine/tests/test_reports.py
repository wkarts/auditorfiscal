from pathlib import Path
from app.reports.pdf_report import build_pdf
from app.reports.excel_report import build_excel
from zipfile import ZipFile
from xml.etree import ElementTree
def test_empty_reports_are_generated(tmp_path:Path):
    summary={'total_value':'0','ibs_cbs_base':'0','ibs_value':'0','cbs_value':'0','input_count':0,'output_count':0,'input_value':'0','output_value':'0','classification_ok':0}
    company={'legal_name':'Empresa Teste'};period={'start':'2026-01-01','end':'2026-01-31'};catalog={'version':'test','sha256':'abc'}
    build_pdf(tmp_path/'r.pdf',company,period,summary,[],[],catalog,'test');build_excel(tmp_path/'r.xlsx',company,period,summary,[],[],catalog,[])
    assert (tmp_path/'r.pdf').stat().st_size>1000;assert (tmp_path/'r.xlsx').stat().st_size>1000
    assert (tmp_path/'r.pdf').read_bytes().count(b'/Type /Page')>=7
    with ZipFile(tmp_path/'r.xlsx') as archive:
        root=ElementTree.fromstring(archive.read('xl/workbook.xml'))
        sheets=[node.attrib['name'] for node in root.findall('.//{http://schemas.openxmlformats.org/spreadsheetml/2006/main}sheet')]
    assert {'Tributos','IBS e CBS','Memória de Cálculo','Duplicidades e Eventos'} <= set(sheets)


def test_generic_product_reconciliation_is_exported_to_xlsx(tmp_path: Path):
    summary={'total_value':'160','ibs_cbs_base':'0','ibs_value':'0','cbs_value':'0','input_count':1,'output_count':1,'input_value':'100','output_value':'60','classification_ok':0}
    common={'item_number':1,'product_code':'IOG-1','description':'IOGURTE','ncm':'04031000','ex_code':None,'cfop':'5102','actual_cst':None,'actual_cclass_trib':None,'expected_cst':None,'expected_cclass_trib':None,'classification_status':'NCM_NOT_PARAMETERIZED','tax_components':{},'ibs_cbs_base_xml':'0','ibs_cbs_base_recalculated':'0','base_difference':'0','ibs_xml':'0','ibs_recalculated':'0','cbs_xml':'0','cbs_recalculated':'0','details':{'ean':'7891234567895','quantity':'1','unit':'UN'}}
    documents=[
        {'number':'1','document_ref':'1','issued_at':'2026-01-01T10:00:00-03:00','direction':'entrada','status':'authorized','access_key':'1'*44,'issuer_tax_id':'1','recipient_tax_id':'2','total_value':'100','ibs_cbs_base':'0','ibs_value':'0','cbs_value':'0','item_count':1,'items':[dict(common,product_value='100')]},
        {'number':'2','document_ref':'2','issued_at':'2026-01-02T10:00:00-03:00','direction':'saida','status':'authorized','access_key':'2'*44,'issuer_tax_id':'2','recipient_tax_id':'3','total_value':'60','ibs_cbs_base':'0','ibs_value':'0','cbs_value':'0','item_count':1,'items':[dict(common,product_value='60')]},
    ]
    output=tmp_path/'generic.xlsx'

    build_excel(output,{'legal_name':'Comércio Genérico'},{'start':'2026-01-01','end':'2026-01-31'},summary,documents,[],{'version':'test','sha256':'abc'},[])

    with ZipFile(output) as archive:
        shared=archive.read('xl/sharedStrings.xml').decode('utf-8')
    assert 'Produto / identificador' in shared
    assert 'GTIN 7891234567895' in shared
    assert '04031000' in shared
