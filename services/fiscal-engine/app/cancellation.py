from __future__ import annotations

from sqlalchemy import text

from .database import create_database_engine


class AuditCancelled(RuntimeError):
    """Raised when the API asks the fiscal engine to stop at a safe checkpoint."""


def is_cancelled_state(status: str | None, cancel_requested_at: object, deleted_at: object) -> bool:
    return status in {"cancelling", "cancelled"} or cancel_requested_at is not None or deleted_at is not None


class AuditCancellationGuard:
    def __init__(self, batch_id: str):
        self.batch_id = batch_id
        self.engine = create_database_engine()

    def __enter__(self) -> "AuditCancellationGuard":
        return self

    def __exit__(self, *_args: object) -> None:
        self.engine.dispose()

    def checkpoint(self, stage: str) -> None:
        with self.engine.connect() as connection:
            row = connection.execute(
                text(
                    "SELECT status, cancel_requested_at, deleted_at "
                    "FROM analysis_batches WHERE CAST(id AS VARCHAR) = :batch_id"
                ),
                {"batch_id": self.batch_id},
            ).mappings().first()

        if row and is_cancelled_state(row["status"], row["cancel_requested_at"], row["deleted_at"]):
            raise AuditCancelled(f"Auditoria cancelada no ponto seguro: {stage}")
