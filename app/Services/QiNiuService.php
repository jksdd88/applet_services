<?php

namespace App\Services;

/**
 * 上传文件到七牛服务类
 *
 * @package default
 * @author guoqikai
 **/
use Qiniu\Auth;
use Qiniu\Storage\BucketManager;
use Qiniu\Storage\UploadManager;
use Carbon\Carbon;

class QiNiuService
{
	private $accessKey;
    
    private $secretKey;
    
    private $bucket;

    public function __construct()
    {
        $this->accessKey = env('QINIU_ACCESS_KEY');
        $this->secretKey = env('QINIU_SECRET_KEY');
        $this->bucket    = env('QINIU_BUCKET');
    }

	public function uploadFile($filepath)
    {
    	if(!empty($filepath)){
			$basename = basename($filepath);
			$ext      = fileext($basename);

			$filename = $this->getRandChar().'.'.$ext;

	        $key = Carbon::now()->year.'/'.Carbon::now()->month.'/'.Carbon::now()->day.'/'.$filename;

	        $auth = new Auth($this->accessKey, $this->secretKey);
	        $token = $auth->uploadToken($this->bucket);

	        $uploadMgr = new UploadManager();

	        $response = $uploadMgr->putFile($token, $key, $filepath);

	        $success = $response[0];
	        $error   = $response[1];

	        if ($error === null) {
	            return ['errcode' => 0, 'errmsg' => '上传成功', 'data' => $success['key']];
	        } else {
	            return ['errcode' => 90003, 'errmsg' => '上传失败'];
	        }
    	}
    }

    private function getRandChar(){
        $str = null;
        $strPol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
        $max = strlen($strPol)-1;

        for($i=0;$i<28;$i++){
            $str.=$strPol[rand(0,$max)];
        }
        return $str;
    }
} // END class 
