<?php

namespace App\Http\Controllers\Weapp\Attachment;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Carbon\Carbon;
use Qiniu\Auth;
use Qiniu\Storage\BucketManager;
use Qiniu\Storage\UploadManager;

class AttachmentController extends Controller
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
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function uploadQiniu(Request $request)
    {
        if ($request->hasFile('file')) {
            $file = $request->file('file');

            if ($file->isValid()) {
                $originalname = $file->getClientOriginalName(); // 文件原名
                $ext          = $file->getClientOriginalExtension();     // 扩展名
                $realPath     = $file->getRealPath();   //临时文件的绝对路径
                // 上传文件
                $filename = $this->getRandChar().$ext;

                $key = Carbon::now()->year.'/'.Carbon::now()->month.'/'.Carbon::now()->day.'/'.$filename;

                $extarr = ['gif', 'jpg', 'png', 'jpeg'];
                if(!in_array(strtolower($ext), $extarr)){
                    return ['errcode' => 90002, 'errmsg' => '上传文件格式不正确'];
                }

                $auth = new Auth($this->accessKey, $this->secretKey);
                $token = $auth->uploadToken($this->bucket);

                $uploadMgr = new UploadManager();

                $response = $uploadMgr->putFile($token, $key, $realPath);

                $success = $response[0];
                $error   = $response[1];

                if ($error === null) {
                    return $success['key'];
                } else {
                    return ['errcode' => 90003, 'errmsg' => '上传失败'];
                }
            }else{
                return ['errcode' => 90004, 'errmsg' => $file->getErrorMessage()];
            }
        }else{
            return ['errcode' => 90001, 'errmsg' => '未提供文件'];
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
}
