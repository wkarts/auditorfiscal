from app.cross_rules import apply_cross_document_rules


def test_used_vehicle_margin_uses_explicit_pis_cofins_base():
    documents = [
        {
            "document_ref": "entrada",
            "number": "3724",
            "issued_at": "2026-06-10T10:00:00-03:00",
            "direction": "entrada",
            "recipient_tax_id": "27330569000171",
            "total_value": "77000.00",
            "items": [
                {
                    "item_number": 1,
                    "chassis": "3N8CP5HE9HL476837",
                    "product_value": "77000.00",
                    "pis_cofins": "0.00",
                    "pis_cofins_base": "0.00",
                }
            ],
        },
        {
            "document_ref": "saida",
            "number": "3739",
            "issued_at": "2026-06-29T11:00:00-03:00",
            "direction": "saida",
            "recipient_tax_id": "00000000000",
            "total_value": "80000.00",
            "items": [
                {
                    "item_number": 1,
                    "chassis": "3N8CP5HE9HL476837",
                    "product_value": "80000.00",
                    "pis_cofins": "177.02",
                    "pis_cofins_base": "4850.00",
                }
            ],
        },
    ]

    findings = apply_cross_document_rules(documents)
    margin = next(f for f in findings if f["rule_code"] == "USED-VEHICLE-MARGIN-001")

    assert margin["evidence"]["document_margin"] == "3000.00"
    assert margin["evidence"]["inferred_pis_cofins_base"] == "4850.00"
    assert margin["evidence"]["difference"] == "1850.00"
