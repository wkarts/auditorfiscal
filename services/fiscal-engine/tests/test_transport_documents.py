from app.xml_parser import parse_invoice


ACCESS_KEY = ''.join(['0'] * 44)
COMPANY_TAX_ID = ''.join(['0'] * 14)
RECIPIENT_TAX_ID = ''.join(['1'] * 14)


def transport_xml() -> bytes:
    return f'''<?xml version="1.0" encoding="UTF-8"?>
<cteProc xmlns="http://www.portalfiscal.inf.br/cte">
  <CTe><infCte Id="CTe{ACCESS_KEY}">
    <ide><cUF>35</cUF><cCT>00000001</cCT><CFOP>5353</CFOP><natOp>Prestacao de servico de transporte</natOp><mod>57</mod><serie>1</serie><nCT>1</nCT><dhEmi>2026-08-04T10:00:00-03:00</dhEmi><tpCTe>0</tpCTe><tpAmb>2</tpAmb></ide>
    <emit><CNPJ>{COMPANY_TAX_ID}</CNPJ><xNome>Transportadora Sintetica LTDA</xNome><IE>ISENTO</IE><enderEmit><xLgr>Rua A</xLgr><nro>1</nro><xBairro>Centro</xBairro><cMun>3550308</cMun><xMun>Sao Paulo</xMun><UF>SP</UF><CEP>01000000</CEP></enderEmit></emit>
    <dest><CNPJ>{RECIPIENT_TAX_ID}</CNPJ><xNome>Destinatario Sintetico LTDA</xNome><enderDest><xLgr>Rua B</xLgr><nro>2</nro><xBairro>Centro</xBairro><cMun>3550308</cMun><xMun>Sao Paulo</xMun><UF>SP</UF><CEP>01000000</CEP></enderDest></dest>
    <vPrest><vTPrest>123.45</vTPrest></vPrest>
  </infCte></CTe>
  <protCTe><infProt><nProt>135260000000001</nProt><cStat>100</cStat><xMotivo>Autorizado o uso do CT-e</xMotivo><dhRecbto>2026-08-04T10:00:01-03:00</dhRecbto></infProt></protCTe>
</cteProc>'''.encode()


def test_cte_is_preserved_as_a_transport_document_for_auxiliary_generation():
    document, findings = parse_invoice(
        transport_xml(),
        'source-1',
        'batches/test/xml/cte.xml',
        None,
        COMPANY_TAX_ID,
    )

    assert findings == []
    assert document['model'] == '57'
    assert document['normalized']['document_type'] == 'CT-e'
    assert document['access_key'] == ACCESS_KEY
    assert document['total_value'] == '123.45'
    assert document['item_count'] == 0
    assert document['status'] == 'authorized'
