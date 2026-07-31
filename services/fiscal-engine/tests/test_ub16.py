from dataclasses import dataclass
from pathlib import Path
from app.xml_parser import parse_invoice
@dataclass
class Entry: expected_cst:str='000';expected_cclass_trib:str='000001';source_row:int=10
class FakeCatalog:
    version_id='test'
    def match(self,ncm,ex,issued):return {'status':'MATCH','entry':Entry(),'strategy':'NCM_EXACT'}
def test_demo_xml_matches_ub16():
    xml=Path(__file__).parent/'fixtures/NFe-demo-saida.xml'
    doc,findings=parse_invoice(xml.read_bytes(),'source','xml/demo.xml',FakeCatalog(),'99999999000191')
    assert doc['number']=='1';assert doc['direction']=='saida';assert doc['ibs_cbs_base']=='64963.50';assert doc['ibs_value']=='64.96';assert doc['cbs_value']=='584.67';assert doc['items'][0]['base_difference']=='0.00';assert not [f for f in findings if f['category']=='calculation']
