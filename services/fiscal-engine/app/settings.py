from pydantic import model_validator
from pydantic_settings import BaseSettings,SettingsConfigDict
from sqlalchemy import URL,make_url
from sqlalchemy.exc import ArgumentError
class Settings(BaseSettings):
    model_config=SettingsConfigDict(env_file='.env',extra='ignore')
    database_url:str|None=None
    db_host:str='auditor-fiscal-postgres'
    db_port:int=5432
    db_database:str='auditor_fiscal'
    db_username:str='auditor'
    db_password:str=''
    fiscal_engine_token:str=''
    aws_access_key_id:str='auditor'
    aws_secret_access_key:str=''
    aws_default_region:str='us-east-1'
    aws_bucket:str='auditor-fiscal'
    aws_endpoint:str='http://auditor-fiscal-minio:9000'
    aws_use_path_style_endpoint:bool=True
    minio_root_user:str='auditor'
    minio_root_password:str=''
    s3_server_side_encryption:str=''
    zip_max_files:int=50000
    zip_max_uncompressed_mb:int=4096
    report_template_version:str='ibs-cbs-executivo@1.0.0'

    @property
    def sqlalchemy_url(self)->URL:
        if self.database_url:
            try:
                configured_url=make_url(self.database_url)
            except ArgumentError:
                configured_url=None
            if configured_url is not None and configured_url.host and not configured_url.host.startswith('@'):
                return configured_url
        return URL.create(
            drivername='postgresql+psycopg',
            username=self.db_username,
            password=self.db_password,
            host=self.db_host,
            port=self.db_port,
            database=self.db_database,
        )

    @model_validator(mode='after')
    def use_minio_credentials_when_aws_credentials_are_empty(self):
        if not self.aws_access_key_id:
            self.aws_access_key_id=self.minio_root_user
        if not self.aws_secret_access_key:
            self.aws_secret_access_key=self.minio_root_password
        return self
settings=Settings()
