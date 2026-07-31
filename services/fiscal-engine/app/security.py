from fastapi import Header,HTTPException,status
from .settings import settings
async def require_internal_token(authorization:str|None=Header(default=None)):
    expected=f'Bearer {settings.fiscal_engine_token}'
    if authorization!=expected: raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED,detail='Token interno inválido')
