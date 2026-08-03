import json
import logging
import re
import traceback
from pathlib import Path
from tempfile import NamedTemporaryFile
from uuid import uuid4

from botocore.exceptions import BotoCoreError,ClientError
from fastapi import Depends,FastAPI,File,Form,HTTPException,Request,UploadFile
from fastapi.responses import JSONResponse
from prometheus_client import make_asgi_app
from sqlalchemy.exc import SQLAlchemyError

from .audit_service import AuditService
from .catalog_import import normalize_catalog
from .database import check_database_connection
from .security import require_internal_token
from .storage import ObjectStorage


logger=logging.getLogger('auditor-fiscal-engine')
app=FastAPI(title='Auditor Fiscal Engine',version='1.1.14',docs_url='/docs')
app.mount('/metrics',make_asgi_app())


def sanitize_error_message(message:str)->str:
    message=re.sub(r'(://[^:/\s]+:)[^@\s]+@',r'\1[REDACTED]@',message)
    message=re.sub(r'\bBearer\s+[^\s,;]+','Bearer [REDACTED]',message,flags=re.IGNORECASE)
    message=re.sub(
        r'\b(password|passwd|secret|token|authorization|app[_-]?key|access[_-]?key)\b(\s*[=:]\s*)[^\s,;]+',
        r'\1\2[REDACTED]',
        message,
        flags=re.IGNORECASE,
    )
    return message[:4000]


@app.exception_handler(Exception)
async def unexpected_error(request:Request,exception:Exception):
    incident_id=str(uuid4())
    technical_message=sanitize_error_message(str(exception))
    logger.error(json.dumps({
        'level':'error',
        'component':'fiscal-engine',
        'event':'unhandled_exception',
        'incident_id':incident_id,
        'method':request.method,
        'path':request.url.path,
        'exception_class':exception.__class__.__name__,
        'technical_message':technical_message,
        'traceback':sanitize_error_message(traceback.format_exc()),
    },ensure_ascii=False))
    return JSONResponse(status_code=500,content={
        'detail':'O motor fiscal falhou durante o processamento.',
        'error_code':'FISCAL_ENGINE_ERROR',
        'incident_id':incident_id,
        'exception_class':exception.__class__.__name__,
        'technical_message':technical_message,
    })


@app.get('/health/live')
def live():return {'status':'ok','service':'fiscal-engine'}


@app.get('/health/ready')
def ready():
    try:check_database_connection()
    except SQLAlchemyError as exc:raise HTTPException(status_code=503,detail='Database unavailable') from exc
    try:ObjectStorage().check()
    except (BotoCoreError,ClientError) as exc:raise HTTPException(status_code=503,detail='Object storage unavailable') from exc
    return {'status':'ready','database':'ok','object_storage':'ok'}


@app.post('/v1/audits/run',dependencies=[Depends(require_internal_token)])
def run_audit(payload:dict):return AuditService().run(payload)


@app.post('/v1/catalogs/normalize',dependencies=[Depends(require_internal_token)])
async def normalize(file:UploadFile=File(...),valid_from:str=Form(...),base_version_id:str|None=Form(default=None),catalog_version_id:str|None=Form(default=None)):
    suffix=Path(file.filename or 'catalog.xlsx').suffix
    with NamedTemporaryFile(suffix=suffix,delete=False) as tmp:
        while chunk:=await file.read(1024*1024):tmp.write(chunk)
        path=Path(tmp.name)
    try:return normalize_catalog(path,valid_from,base_version_id)
    finally:path.unlink(missing_ok=True)
