from app.cross_rules import apply_cross_document_rules


def test_detects_synthetic_duplicate():
    documents = [
        {
            "number": "1001",
            "document_ref": "synthetic-1001",
            "recipient_tax_id": "11111111111",
            "direction": "saida",
            "issued_at": "2026-01-15T10:00:00-03:00",
            "total_value": "65000.00",
            "items": [
                {
                    "item_number": 1,
                    "chassis": "9ZZZZZZZZZZZZZZZZ",
                    "product_value": "65000.00",
                    "pis_cofins": "36.50",
                    "pis_cofins_base": "1000.00",
                }
            ],
        },
        {
            "number": "1002",
            "document_ref": "synthetic-1002",
            "recipient_tax_id": "11111111111",
            "direction": "saida",
            "issued_at": "2026-01-15T10:04:00-03:00",
            "total_value": "65000.00",
            "items": [
                {
                    "item_number": 1,
                    "chassis": "9ZZZZZZZZZZZZZZZZ",
                    "product_value": "65000.00",
                    "pis_cofins": "36.50",
                    "pis_cofins_base": "1000.00",
                }
            ],
        },
    ]

    findings = apply_cross_document_rules(documents)
    assert any(f["category"] == "duplicate" for f in findings)
    assert any(f["rule_code"] == "RECONCILIATION-MISSING-INPUT-001" for f in findings)


def test_detects_negative_margin_after_chassis_reconciliation():
    documents = [
        {"number":"10","document_ref":"in","direction":"entrada","issued_at":"2026-01-01T10:00:00-03:00","total_value":"100.00","items":[{"item_number":1,"chassis":"9ZZZZZZZZZZZZZZZZ","product_value":"100.00","pis_cofins":"0","pis_cofins_base":"0"}]},
        {"number":"11","document_ref":"out","direction":"saida","issued_at":"2026-01-02T10:00:00-03:00","total_value":"90.00","recipient_tax_id":"1","items":[{"item_number":1,"chassis":"9ZZZZZZZZZZZZZZZZ","product_value":"90.00","pis_cofins":"0","pis_cofins_base":"0"}]},
    ]
    findings = apply_cross_document_rules(documents)
    assert any(f["rule_code"] == "NEGATIVE-MARGIN-001" for f in findings)
