from fastapi.testclient import TestClient
from botocore.exceptions import EndpointConnectionError
from sqlalchemy.exc import OperationalError

from app import main


client=TestClient(main.app)


class AvailableStorage:
    def check(self):
        return None


def test_ready_reports_database_and_storage_connections(monkeypatch):
    monkeypatch.setattr(main,'check_database_connection',lambda:None)
    monkeypatch.setattr(main,'ObjectStorage',AvailableStorage)

    response=client.get('/health/ready')

    assert response.status_code == 200
    assert response.json() == {'status':'ready','database':'ok','object_storage':'ok'}


def test_ready_rejects_unavailable_database(monkeypatch):
    def unavailable():
        raise OperationalError('SELECT 1',{},Exception('database unavailable'))

    monkeypatch.setattr(main,'check_database_connection',unavailable)
    monkeypatch.setattr(main,'ObjectStorage',AvailableStorage)

    response=client.get('/health/ready')

    assert response.status_code == 503
    assert response.json() == {'detail':'Database unavailable'}


def test_ready_rejects_unavailable_object_storage(monkeypatch):
    class UnavailableStorage:
        def check(self):
            raise EndpointConnectionError(endpoint_url='http://storage.invalid')

    monkeypatch.setattr(main,'check_database_connection',lambda:None)
    monkeypatch.setattr(main,'ObjectStorage',UnavailableStorage)

    response=client.get('/health/ready')

    assert response.status_code == 503
    assert response.json() == {'detail':'Object storage unavailable'}


def test_error_sanitizer_redacts_credentials():
    message='postgresql://auditor:very-secret@database:5432/auditor token=abc123'

    sanitized=main.sanitize_error_message(message)

    assert 'very-secret' not in sanitized
    assert 'abc123' not in sanitized
    assert '[REDACTED]' in sanitized
