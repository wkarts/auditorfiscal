from pydantic_settings import BaseSettings,SettingsConfigDict
class Settings(BaseSettings):
    model_config=SettingsConfigDict(env_file='.env',extra='ignore')
    database_url:str='postgresql+psycopg://auditor:secret@postgres:5432/auditor_fiscal'
    fiscal_engine_token:str='change-me'
    aws_access_key_id:str='auditor'
    aws_secret_access_key:str='secret'
    aws_default_region:str='us-east-1'
    aws_bucket:str='auditor-fiscal'
    aws_endpoint:str='http://minio:9000'
    aws_use_path_style_endpoint:bool=True
    zip_max_files:int=50000
    zip_max_uncompressed_mb:int=4096
    report_template_version:str='ibs-cbs-executivo@1.0.0'
settings=Settings()
