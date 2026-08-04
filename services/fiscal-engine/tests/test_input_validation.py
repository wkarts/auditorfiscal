import asyncio
from copy import deepcopy
from dataclasses import dataclass
from pathlib import Path

import pytest
from lxml import etree
from starlette.requests import Request

from app.input_validation import CompanyDocumentMismatch,DuplicateItemNumber,FiscalInputError
from app.main import app
from app.xml_parser import NS,parse_invoice


@dataclass
class Entry:
    expected_cst:str='000'
    expected_cclass_trib:str='000001'
    source_row:int=10


class FakeCatalog:
    version_id='test'
    def match(self,_ncm,_ex,_issued):return {'status':'MATCH','entry':Entry(),'strategy':'NCM_EXACT'}


def fixture_xml()->bytes:
    return (Path(__file__).parent/'fixtures/NFe-demo-saida.xml').read_bytes()


def test_rejects_xml_that_does_not_belong_to_selected_company():
    with pytest.raises(CompanyDocumentMismatch) as raised:
        parse_invoice(fixture_xml(),'source','xml/demo.xml',FakeCatalog(),'11111111111111')
    assert raised.value.error_code=='XML_COMPANY_MISMATCH'


def test_rejects_duplicate_item_number_before_database_persistence():
    root=etree.fromstring(fixture_xml())
    info=root.xpath('.//n:infNFe',namespaces=NS)[0]
    item=info.xpath('./n:det',namespaces=NS)[0]
    info.insert(info.index(item)+1,deepcopy(item))

    with pytest.raises(DuplicateItemNumber) as raised:
        parse_invoice(etree.tostring(root),'source','xml/demo.xml',FakeCatalog(),'99999999000191')
    assert raised.value.error_code=='XML_DUPLICATE_ITEM_NUMBER'


def test_input_error_handler_returns_non_retryable_contract_without_tax_ids():
    handler=app.exception_handlers[FiscalInputError]
    request=Request({'type':'http','method':'POST','path':'/v1/audits/run','headers':[],'query_string':b'','server':('test',80),'client':('test',1),'scheme':'http'})
    response=asyncio.run(handler(request,CompanyDocumentMismatch()))
    assert response.status_code==422
    assert b'XML_COMPANY_MISMATCH' in response.body
    assert b'11111111111111' not in response.body
