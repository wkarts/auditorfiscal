from app.danfe import build_danfe


def test_builds_danfe_from_normalized_xml_document(tmp_path):
    output = tmp_path / "danfe.pdf"
    document = {
        "access_key": "29260627330569000171550010000037491000007080",
        "number": "3749",
        "series": "1",
        "model": "55",
        "issued_at": "2026-06-30T13:48:00-03:00",
        "direction": "saida",
        "issuer_tax_id": "27330569000171",
        "recipient_tax_id": "49211625500010",
        "total_value": "117500.00",
        "normalized": {
            "nature": "VENDA DE MERCADORIA",
            "issuer": {"name": "Emitente Ltda.", "tax_id": "27330569000171", "address": {"street": "Rua A", "number": "10", "city": "Salvador", "state": "BA"}},
            "recipient": {"name": "Cliente Auditável", "tax_id": "49211625500010", "address": {"street": "Rua B", "number": "20", "city": "Salvador", "state": "BA"}},
            "totals": {"vProd": "117500.00", "vNF": "117500.00", "vBC": "116770.00"},
            "protocol": {"number": "129260895602226", "status_reason": "Autorizado o uso da NF-e"},
        },
        "items": [{"item_number": 1, "product_code": "ABC", "description": "Produto genérico", "ncm": "87032310", "cfop": "5102", "product_value": "117500.00", "actual_cst": "000", "actual_cclass_trib": "000001", "details": {"quantity": "1", "unit": "UN", "unit_value": "117500.00"}}],
    }

    build_danfe(output, document)

    assert output.read_bytes().startswith(b"%PDF-")
    assert output.stat().st_size > 2000
