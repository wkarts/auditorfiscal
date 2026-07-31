from pathlib import Path
import xlsxwriter
from app.xlsx_reader import read_xlsx
def test_reads_shared_and_inline_strings(tmp_path:Path):
    p=tmp_path/'catalog.xlsx';w=xlsxwriter.Workbook(str(p));s=w.add_worksheet('Tabela Completa');s.write_row(0,0,['NCM','EX','DESCRIÇÃO','CST IBS/CBS','cClassTrib']);s.write_row(1,0,['87032100','','Veículo','000','000001']);w.close();data=read_xlsx(p);assert data['Tabela Completa'][1][0]=='87032100';assert data['Tabela Completa'][1][4]=='000001'
