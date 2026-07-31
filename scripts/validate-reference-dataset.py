#!/usr/bin/env python3
"""Valida um ZIP de NF-e contra o conjunto de regressão do relatório de referência.

Uso:
    python scripts/validate-reference-dataset.py /caminho/NotasFiscais.zip

O script não grava XMLs no repositório e não depende de PostgreSQL ou MinIO.
Ele carrega o catálogo NCM × ClassTrib diretamente dos dados de seed comprimidos.
"""
from __future__ import annotations

import gzip
import json
import sys
from collections import Counter
from dataclasses import dataclass
from datetime import date
from decimal import Decimal
from pathlib import Path
from zipfile import ZipFile

ROOT = Path(__file__).resolve().parents[1]
ENGINE = ROOT / "services" / "fiscal-engine"
sys.path.insert(0, str(ENGINE))

from app.cross_rules import apply_cross_document_rules  # noqa: E402
from app.xml_parser import parse_invoice  # noqa: E402


@dataclass(slots=True)
class Entry:
    id: str
    ncm: str
    ex_code: str | None
    expected_cst: str | None
    expected_cclass_trib: str | None
    description: str | None
    status: str
    validation_issues: list
    source_row: int | None
    reduction_type: str | None
    valid_from: date | None
    valid_to: date | None


class SeedCatalog:
    version_id = "seed-reference"

    def __init__(self) -> None:
        self.by_key: dict[tuple[str, str | None], list[Entry]] = {}
        seed = ROOT / "apps" / "api" / "database" / "seeders" / "data" / "ncm_class_trib.jsonl.gz"
        with gzip.open(seed, "rt", encoding="utf-8") as stream:
            for line in stream:
                row = json.loads(line)
                if row.get("ncm_level") != "item" or not row.get("ncm"):
                    continue
                entry = Entry(
                    id=str(row.get("id", "")),
                    ncm=row["ncm"],
                    ex_code=row.get("ex_code"),
                    expected_cst=row.get("expected_cst"),
                    expected_cclass_trib=row.get("expected_cclass_trib"),
                    description=row.get("description"),
                    status=row.get("status", "valid"),
                    validation_issues=row.get("validation_issues") or [],
                    source_row=row.get("source_row"),
                    reduction_type=row.get("reduction_type"),
                    valid_from=date.fromisoformat(row["valid_from"]) if row.get("valid_from") else None,
                    valid_to=date.fromisoformat(row["valid_to"]) if row.get("valid_to") else None,
                )
                self.by_key.setdefault((entry.ncm, entry.ex_code), []).append(entry)

    def match(self, ncm: str | None, ex_code: str | None, issued_on: date | None) -> dict:
        if not ncm:
            return {"status": "DOCUMENT_NCM_MISSING", "entry": None, "strategy": "none"}
        candidates = self.by_key.get((ncm, ex_code)) if ex_code else self.by_key.get((ncm, None))
        strategy = "NCM_EXACT_WITH_EX" if ex_code else "NCM_EXACT"
        if not candidates and ex_code:
            candidates = self.by_key.get((ncm, None))
            strategy = "NCM_FALLBACK_WITHOUT_EX"
        if not candidates:
            return {"status": "NCM_NOT_PARAMETERIZED", "entry": None, "strategy": "none"}
        eligible = [
            entry
            for entry in candidates
            if not issued_on
            or (not entry.valid_from or entry.valid_from <= issued_on)
            and (not entry.valid_to or issued_on <= entry.valid_to)
        ]
        if not eligible:
            return {"status": "PARAMETER_OUT_OF_VALIDITY", "entry": candidates[0], "strategy": strategy}
        if len(eligible) > 1:
            return {
                "status": "AMBIGUOUS_PARAMETERIZATION",
                "entry": eligible[0],
                "strategy": strategy,
                "candidates": len(eligible),
            }
        entry = eligible[0]
        return {
            "status": "PARAMETER_INVALID" if entry.status == "error" else "MATCH",
            "entry": entry,
            "strategy": strategy,
        }


def main() -> int:
    if len(sys.argv) != 2:
        print("Uso: python scripts/validate-reference-dataset.py /caminho/NotasFiscais.zip", file=sys.stderr)
        return 2

    archive = Path(sys.argv[1]).resolve()
    if not archive.is_file():
        print(f"Arquivo não encontrado: {archive}", file=sys.stderr)
        return 2

    catalog = SeedCatalog()
    documents: list[dict] = []
    findings: list[dict] = []
    company_tax_id = "27330569000171"

    with ZipFile(archive) as zipped:
        for name in zipped.namelist():
            if not name.lower().endswith(".xml"):
                continue
            document, document_findings = parse_invoice(
                zipped.read(name), name, name, catalog, company_tax_id
            )
            documents.append(document)
            findings.extend(document_findings)

    findings.extend(apply_cross_document_rules(documents))
    outputs = [document for document in documents if document["direction"] == "saida"]
    inputs = [document for document in documents if document["direction"] == "entrada"]

    result = {
        "documents": len(documents),
        "items": sum(len(document["items"]) for document in documents),
        "inputs": len(inputs),
        "outputs": len(outputs),
        "total_value": str(sum(Decimal(document["total_value"]) for document in documents)),
        "output_base": str(sum(Decimal(document["ibs_cbs_base"]) for document in outputs)),
        "output_ibs": str(sum(Decimal(document["ibs_value"]) for document in outputs)),
        "output_cbs": str(sum(Decimal(document["cbs_value"]) for document in outputs)),
        "finding_rules": dict(sorted(Counter(finding["rule_code"] for finding in findings).items())),
    }

    expected = {
        "documents": 34,
        "items": 34,
        "inputs": 11,
        "outputs": 23,
        "total_value": "2797000.00",
        "output_base": "1888209.48",
        "output_ibs": "1888.19",
        "output_cbs": "16993.84",
    }
    mismatches = {key: {"expected": value, "actual": result[key]} for key, value in expected.items() if result[key] != value}

    print(json.dumps(result, ensure_ascii=False, indent=2))
    if mismatches:
        print("\nDivergências no conjunto de regressão:", file=sys.stderr)
        print(json.dumps(mismatches, ensure_ascii=False, indent=2), file=sys.stderr)
        return 1

    print("\nConjunto de referência validado com sucesso.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
