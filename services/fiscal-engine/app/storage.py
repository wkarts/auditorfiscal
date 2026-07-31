from pathlib import Path
import boto3
from botocore.config import Config
from .settings import settings
class ObjectStorage:
    def __init__(self):
        self.bucket=settings.aws_bucket
        self.client=boto3.client('s3',endpoint_url=settings.aws_endpoint,aws_access_key_id=settings.aws_access_key_id,aws_secret_access_key=settings.aws_secret_access_key,region_name=settings.aws_default_region,config=Config(s3={'addressing_style':'path'}))
    def download(self,key:str,target:Path)->Path:
        target.parent.mkdir(parents=True,exist_ok=True);self.client.download_file(self.bucket,key,str(target));return target
    def upload(self,source:Path,key:str,content_type:str|None=None)->dict:
        extra={'ServerSideEncryption':'AES256'}
        if content_type:extra['ContentType']=content_type
        self.client.upload_file(str(source),self.bucket,key,ExtraArgs=extra)
        return {'storage_path':key,'size':source.stat().st_size}
    def put_bytes(self,data:bytes,key:str,content_type:str='application/xml')->dict:
        self.client.put_object(Bucket=self.bucket,Key=key,Body=data,ContentType=content_type,ServerSideEncryption='AES256')
        return {'storage_path':key,'size':len(data)}
