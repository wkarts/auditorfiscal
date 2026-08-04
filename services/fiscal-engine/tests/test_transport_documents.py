from app.xml_parser import parse_invoice


CTE_XML = b'''<?xml version="1.0" encoding="UTF-8"?>
<cteProc xmlns="http://www.portalfiscal.inf.br/cte">
  <CTe><infCte Id="CTe35260812345678000123570010000000011000000010">
    <ide><cUF>35</cUF><cCT>00000001</cCT><CFOP>5353</CFOP><natOp>Prestacao de servico de transporte</natOp><mod>57</mod><serie>1</serie><nCT>1</nCT><dhEmi>2026-08-04T10:00:00-03:00</dhEmi><tpCTe>0</tpCTe><tpAmb>2</tpAmb></ide>
    <emit><CNPJ>12345678000123</CNPJ><xNome>Transportadora Teste LTDA</xNome><IE>123456789</IE><enderEmit><xLgr>Rua A</xLgr><nro>1</nro><xBairro>Centro</xBairro><cMun>3550308</cMun><xMun>Sao Paulo</xMun><UF>SP</UF><CEP>01000000</CEP></enderEmit></emit>
    <dest><CNPJ>98765432000198</CNPJ><xNome>Destinatario Teste LTDA</xNome><enderDest><xLgr>Rua B</xLgr><nro>2</nro><xBairro>Centro</xBairro><cMun>3550308</cMun><xMun>Sao Paulo</xMun><UF>SP</UF><CEP>01000000</CEP></enderDest></dest>
    <vPrest><vTPrest>123.45</vTPrest></vPrest>
  </infCte></CTe>
  <protCTe><infProt><nProt>135260000000001</nProt><cStat>100</cStat><xMotivo>Autorizado o uso do CT-e</xMotivo><dhRecbto>2026-08-04T10:00:01-03:00</dhRecbto></infProt></protCTe>
</cteProc>'''


def test_cte_is_preserved_as_a_transport_document_for_auxiliary_generation():
    document, findings = parse_invoice(
        CTE_XML,
        'source-1',
        'batches/test/xml/cte.xml',
        None,
        '12345678000123',
    )

    assert findings == []
    assert document['model'] == '57'
    assert document['normalized']['document_type'] == 'CT-e'
    assert document['access_key'] == '35260812345678000123570010000000011000000010'
    assert document['total_value'] == '123.45'
    assert document['item_count'] == 0
    assert document['status'] == 'authorized'
