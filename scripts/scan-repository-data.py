#!/usr/bin/env python3
"""Bloqueia dados pessoais e identificadores fiscais reais no repositório."""
from __future__ import annotations

import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
EXCLUDED_DIRS = {'.git', 'vendor', 'node_modules', '.venv', '__pycache__', 'backups'}
EXCLUDED_SUFFIXES = {'.gz', '.zip', '.png', '.jpg', '.jpeg', '.pdf', '.xlsx', '.bundle'}
ALLOWED_EMAIL_DOMAINS = {'example.invalid', 'example.com', 'localhost', 'auditor.local'}
ALLOWED_EMAILS = {'wkarts@gmail.com'}

EMAIL_RE = re.compile(r'(?i)\b[A-Z0-9._%+-]+@([A-Z0-9.-]+\.[A-Z]{2,}|localhost)\b')
TAX_ID_RE = re.compile(r'(?<!\d)(\d{11}|\d{14})(?!\d)')
ACCESS_KEY_RE = re.compile(r'(?<!\d)\d{44}(?!\d)')


def is_synthetic_digits(value: str) -> bool:
    return len(set(value)) == 1 or value in {'99999999000191', '11111111111'}


def should_scan(path: Path) -> bool:
    if any(part in EXCLUDED_DIRS for part in path.parts):
        return False
    if path.suffix.lower() in EXCLUDED_SUFFIXES:
        return False
    return path.is_file()


def main() -> int:
    findings: list[str] = []
    for path in ROOT.rglob('*'):
        if not should_scan(path):
            continue
        try:
            text = path.read_text(encoding='utf-8')
        except (UnicodeDecodeError, OSError):
            continue
        rel = path.relative_to(ROOT)
        for line_number, line in enumerate(text.splitlines(), 1):
            for match in EMAIL_RE.finditer(line):
                domain = match.group(1).lower()
                email = match.group(0).lower()
                if domain not in ALLOWED_EMAIL_DOMAINS and email not in ALLOWED_EMAILS:
                    findings.append(f'{rel}:{line_number}: e-mail não demonstrativo')
            for match in TAX_ID_RE.finditer(line):
                value = match.group(1)
                if not is_synthetic_digits(value):
                    findings.append(f'{rel}:{line_number}: CPF/CNPJ potencialmente real ({value[:3]}…{value[-2:]})')
            for match in ACCESS_KEY_RE.finditer(line):
                value = match.group(0)
                if len(set(value)) != 1:
                    findings.append(f'{rel}:{line_number}: chave fiscal de 44 dígitos')

    if findings:
        print('Dados potencialmente pessoais encontrados:', file=sys.stderr)
        for finding in findings:
            print(f'  - {finding}', file=sys.stderr)
        return 1
    print('Varredura de privacidade aprovada: apenas dados sintéticos foram encontrados.')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
