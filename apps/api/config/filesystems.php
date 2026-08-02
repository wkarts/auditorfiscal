<?php
return ['default'=>env('FILESYSTEM_DISK','local'),'disks'=>[
 'local'=>['driver'=>'local','root'=>storage_path('app/private'),'serve'=>true,'throw'=>false],
 's3'=>['driver'=>'s3','key'=>env('AWS_ACCESS_KEY_ID')?:env('MINIO_ROOT_USER'),'secret'=>env('AWS_SECRET_ACCESS_KEY')?:env('MINIO_ROOT_PASSWORD'),'region'=>env('AWS_DEFAULT_REGION'),'bucket'=>env('AWS_BUCKET'),'url'=>env('AWS_URL'),'endpoint'=>env('AWS_ENDPOINT'),'use_path_style_endpoint'=>env('AWS_USE_PATH_STYLE_ENDPOINT',false),'throw'=>true]
]];
