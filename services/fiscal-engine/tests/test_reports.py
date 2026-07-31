from pathlib import Path
from app.reports.pdf_report import build_pdf
from app.reports.excel_report import build_excel
def test_empty_reports_are_generated(tmp_path:Path):
    summary={'total_value':'0','ibs_cbs_base':'0','ibs_value':'0','cbs_value':'0','input_count':0,'output_count':0,'input_value':'0','output_value':'0','classification_ok':0}
    company={'legal_name':'Empresa Teste'};period={'start':'2026-01-01','end':'2026-01-31'};catalog={'version':'test','sha256':'abc'}
    build_pdf(tmp_path/'r.pdf',company,period,summary,[],[],catalog,'test');build_excel(tmp_path/'r.xlsx',company,period,summary,[],[],catalog,[])
    assert (tmp_path/'r.pdf').stat().st_size>1000;assert (tmp_path/'r.xlsx').stat().st_size>1000
