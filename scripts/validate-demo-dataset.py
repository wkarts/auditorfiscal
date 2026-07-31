#!/usr/bin/env python3
"""Valida o XML de demonstração sintético sem acessar dados de clientes."""
from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[1]
ENGINE = ROOT / 'services' / 'fiscal-engine'
sys.path.insert(0, str(ENGINE))

from app.xml_parser import parse_invoice  # noqa: E402


class DemoEntry:
    expected_cst = '000'
    expected_cclass_trib = '000001'
    source_row = 1


class DemoCatalog:
    version_id = 'synthetic-demo'

    def match(self, ncm, ex_code, issued_on):
        return {
            'status': 'MATCH',
            'entry': DemoEntry(),
            'strategy': 'NCM_EXACT',
        }


def main() -> int:
    xml = ROOT / 'examples' / 'xml' / 'NFe-demo-saida.xml'
    document, findings = parse_invoice(
        xml.read_bytes(),
        'synthetic-demo',
        str(xml),
        DemoCatalog(),
        '99999999000191',
    )
    assert document['number'] == '1'
    assert document['total_value'] == '65000.00'
    assert document['ibs_cbs_base'] == '64963.50'
    assert len(document['items']) == 1
    assert document['items'][0]['classification_status'] == 'MATCH_EXACT'
    assert not [f for f in findings if f.get('severity') == 'critical']
    print('Dataset sintético aprovado.')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
