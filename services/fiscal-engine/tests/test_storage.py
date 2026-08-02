from app.settings import settings
from app.storage import ObjectStorage


class RecordingClient:
    def __init__(self):
        self.put_arguments = None

    def put_object(self, **arguments):
        self.put_arguments = arguments


def test_put_bytes_does_not_force_encryption_on_minio_without_kms(monkeypatch):
    client = RecordingClient()
    monkeypatch.setattr('app.storage.boto3.client', lambda *_args, **_arguments: client)
    monkeypatch.setattr(settings, 's3_server_side_encryption', '')

    ObjectStorage().put_bytes(b'<xml/>', 'validation/document.xml')

    assert 'ServerSideEncryption' not in client.put_arguments


def test_put_bytes_uses_configured_server_side_encryption(monkeypatch):
    client = RecordingClient()
    monkeypatch.setattr('app.storage.boto3.client', lambda *_args, **_arguments: client)
    monkeypatch.setattr(settings, 's3_server_side_encryption', 'AES256')

    ObjectStorage().put_bytes(b'<xml/>', 'validation/document.xml')

    assert client.put_arguments['ServerSideEncryption'] == 'AES256'
