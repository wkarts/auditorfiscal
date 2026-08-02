from secrets import token_hex

from app.settings import Settings


def test_minio_credentials_are_used_when_aws_credentials_are_empty(monkeypatch):
    access_key = token_hex(12)
    secret_key = token_hex(24)
    monkeypatch.setenv('AWS_ACCESS_KEY_ID', '')
    monkeypatch.setenv('AWS_SECRET_ACCESS_KEY', '')
    monkeypatch.setenv('MINIO_ROOT_USER', access_key)
    monkeypatch.setenv('MINIO_ROOT_PASSWORD', secret_key)

    configured = Settings(_env_file=None)

    assert configured.aws_access_key_id == access_key
    assert configured.aws_secret_access_key == secret_key
