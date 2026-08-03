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


def test_database_url_encodes_reserved_password_characters(monkeypatch):
    monkeypatch.delenv('DATABASE_URL',raising=False)
    monkeypatch.setenv('DB_HOST','auditor-fiscal-postgres')
    monkeypatch.setenv('DB_PORT','5432')
    monkeypatch.setenv('DB_DATABASE','auditor_fiscal')
    monkeypatch.setenv('DB_USERNAME','auditor')
    monkeypatch.setenv('DB_PASSWORD','password@with:/reserved?characters')

    configured=Settings(_env_file=None)

    assert configured.sqlalchemy_url.host == 'auditor-fiscal-postgres'
    assert configured.sqlalchemy_url.password == 'password@with:/reserved?characters'
    assert configured.sqlalchemy_url.render_as_string(hide_password=False) == (
        'postgresql+psycopg://auditor:password%40with%3A%2Freserved%3Fcharacters'
        '@auditor-fiscal-postgres:5432/auditor_fiscal'
    )


def test_explicit_database_url_remains_supported(monkeypatch):
    monkeypatch.setenv('DATABASE_URL','postgresql+psycopg://custom:encoded%40password@database:5433/custom')

    configured=Settings(_env_file=None)

    assert configured.sqlalchemy_url.host == 'database'
    assert configured.sqlalchemy_url.port == 5433
    assert configured.sqlalchemy_url.password == 'encoded@password'
