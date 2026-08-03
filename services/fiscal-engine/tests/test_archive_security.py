from pathlib import Path
from zipfile import ZipFile

import pytest

from app.audit_service import AuditService


def service():
    return object.__new__(AuditService)


def test_extracts_only_supported_fiscal_files(tmp_path: Path):
    archive = tmp_path/'safe.zip'
    with ZipFile(archive, 'w') as zipped:
        zipped.writestr('nested/nfe.xml', '<NFe/>')
        zipped.writestr('nested/nfe.pdf', b'%PDF-1.4\n')
        zipped.writestr('script.exe', b'ignored')
    files = service()._safe_extract(archive, tmp_path/'out')
    assert sorted(path.suffix for path in files) == ['.pdf', '.xml']


def test_rejects_zip_path_traversal(tmp_path: Path):
    archive = tmp_path/'unsafe.zip'
    with ZipFile(archive, 'w') as zipped:
        zipped.writestr('../outside.xml', '<NFe/>')
    with pytest.raises(ValueError, match='Caminho inseguro'):
        service()._safe_extract(archive, tmp_path/'out')
