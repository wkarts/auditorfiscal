from app.cancellation import AuditCancelled, is_cancelled_state
from app.main import app


def test_cancelled_state_accepts_request_status_and_soft_delete():
    assert is_cancelled_state("cancelling", None, None)
    assert is_cancelled_state("processing", object(), None)
    assert is_cancelled_state("completed", None, object())
    assert not is_cancelled_state("processing", None, None)


def test_cancelled_exception_has_a_distinct_non_retryable_contract():
    route = next(route for route in app.routes if getattr(route, "path", None) == "/v1/audits/run")
    assert route is not None

    handler = app.exception_handlers[AuditCancelled]
    response = __import__("asyncio").run(handler(None, AuditCancelled("checkpoint")))
    assert response.status_code == 409
    assert b'AUDIT_CANCELLED' in response.body
