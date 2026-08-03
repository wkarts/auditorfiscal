from pathlib import Path
from tempfile import NamedTemporaryFile
from fastapi import Depends,FastAPI,File,Form,HTTPException,UploadFile
from sqlalchemy.exc import SQLAlchemyError
from prometheus_client import make_asgi_app
from .audit_service import AuditService
from .catalog_import import normalize_catalog
from .database import check_database_connection
from .security import require_internal_token
app=FastAPI(title='Auditor Fiscal Engine',version='1.1.7',docs_url='/docs')
app.mount('/metrics',make_asgi_app())
@app.get('/health/live')
def live():return {'status':'ok','service':'fiscal-engine'}
@app.get('/health/ready')
def ready():
    try:check_database_connection()
    except SQLAlchemyError as exc:raise HTTPException(status_code=503,detail='Database unavailable') from exc
    return {'status':'ready','database':'ok'}
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
