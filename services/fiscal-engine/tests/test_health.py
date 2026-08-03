from fastapi.testclient import TestClient
from sqlalchemy.exc import OperationalError

from app import main


client=TestClient(main.app)


def test_ready_reports_database_connection(monkeypatch):
    monkeypatch.setattr(main,'check_database_connection',lambda:None)

    response=client.get('/health/ready')

    assert response.status_code == 200
    assert response.json() == {'status':'ready','database':'ok'}


def test_ready_rejects_unavailable_database(monkeypatch):
    def unavailable():
        raise OperationalError('SELECT 1',{},Exception('database unavailable'))

    monkeypatch.setattr(main,'check_database_connection',unavailable)

    response=client.get('/health/ready')

    assert response.status_code == 503
    assert response.json() == {'detail':'Database unavailable'}
