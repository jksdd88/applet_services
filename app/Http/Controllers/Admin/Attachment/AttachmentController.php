<?php

namespace App\Http\Controllers\Admin\Attachment;

use App\Http\Controllers\Controller;
use App\Services\AttachmentService;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Response;
use Carbon\Carbon;

use Qiniu\Auth;
use Qiniu\Storage\UploadManager;
use Qiniu\Config;
use Qiniu\Http\HttpClient;
use Qiniu\Storage\ResumeUploader;
use Qiniu\Storage\FormUploader;

use App\Models\Attachment;
use App\Models\UserLog;

class AttachmentController extends Controller {

    private $accessKey;
    private $secretKey;
    private $bucket;
    private $config;
    
    public function __construct(AttachmentService $attachmentservice) {
        $this->service = $attachmentservice;
        $this->accessKey = env('QINIU_ACCESS_KEY');
        $this->secretKey = env('QINIU_SECRET_KEY');
        $this->bucket    = env('QINIU_BUCKET');
        $this->config = new Config();
    }

    /**
     * 获取文件列表
     */
    public function getAttachments(\Illuminate\Http\Request $request) {
        $service_rt = $this->service->getAttachments($request);
        return Response::json($service_rt);
    }

    /**
     * 删除文件
     */
    public function deleteAttachments() {
        $service_rt = $this->service->deleteAttachments(Request::all());
        return Response::json($service_rt);
    }

    /**
     * 修改名称
     */
    public function putAttachment($id) {
        $service_rt = $this->service->putAttachment($id,Request::all());
        return Response::json($service_rt);
    }

    /**
     * 批量设置图片分组
     */
    public function putAttachments() {
        $service_rt = $this->service->putAttachments(Request::all());
        return Response::json($service_rt);
    }
    
    /**
     * 获取七牛云uptoken
     */
    public function qiniuToken() {
        $token = $this->service->qiniuToken();
        return Response::json(['token' => $token]);
    }
    /**
     * base64方式上传图片到七牛云
     */
    public function request_by_curl(\Illuminate\Http\Request $request) {
        $arr_userlog = array();
        if($request->hasFile('file')){
            foreach ($request['file'] as $key=>$val){
                $arr_userlog[] = (array)$val;
            }
        }
        $data = array(
            'merchant_id'    => 1,
            'user_id'    => 1,
            'type' => 46,
            'url' => json_encode(array('method'=>'POST','route'=>'auth/login.json')),
            'content' => json_encode(array(
                'requestall'=>$request->all(),
                'fileall'=>$arr_userlog
            )),
            'ip' => $request->ip(),
            'created_time' => date('Y-m-d H:i:s')
        );
        UserLog::create($data);
        
        $rt = $img = array();
        if($request->hasFile('file')){
            foreach ($request['file'] as $key=>$val){
                $file = $val;
                if ($file->isValid()) {
                    $originalname = $file->getClientOriginalName(); // 文件原名
                    $ext          = $file->getClientOriginalExtension();     // 扩展名
                    $realPath     = $file->getRealPath();   //临时文件的绝对路径
                    // 上传文件
                    $filename = $this->getRandChar().'.'.$ext;
        
                    $key = Carbon::now()->year.'/'.Carbon::now()->month.'/'.Carbon::now()->day.'/'.$filename;
        
                    $extarr = ['jpg', 'png', 'jpeg','mp3','gif'];
                    if(!in_array(strtolower($ext), $extarr)){
                        $rt =  ['error_code' => 90002, 'errmsg' => '只支持(.jpg .png .jpeg .mp3 .gif)后缀的图片'];
                        return Response::json($rt);
                    }else if($file->getSize()>1024*1024){
                        $rt =  ['error_code' => 90007, 'errmsg' => '单张图片最大1M'];
                        return Response::json($rt);
                    }
        
                    $auth = new Auth($this->accessKey, $this->secretKey);
                    $token = $auth->uploadToken($this->bucket);
        
                    list($accessKey, $bucket, $err) = \Qiniu\explodeUpToken($token);
                    $upHost = $this->config->getUpHost($this->accessKey, $bucket);
                    //dd($upHost);
                    
                    $headers = array();
                    $headers[] = 'Content-Type:application/octet-stream';
                    $headers[] = 'Authorization:UpToken '.$token;//
                    $headers[] = 'Content-Disposition: form-data; name="file"; filename="'.$request['file']['name'].'"';
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL,$upHost.'/putb64/-1');
                    //curl_setopt($ch, CURLOPT_HEADER, 0);
                    curl_setopt($ch, CURLOPT_HTTPHEADER ,$headers);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    //curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $request['file']);
                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                    $response = curl_exec($ch);
                    curl_close($ch);
        
                    $success = $response[0];
                    $error   = $response[1];
                    
                    if ($error === null) {
                        $this->service->uploadQiniu($file,$success['key'],$request['group_id']);
                        $img = array(
                            'file_name' => $originalname,
                            'file_url' => $success['key']
                        );
                        dd($img);
                    } else {
                        $rt =  ['error_code' => 90003, 'errmsg' => '上传失败'];
                        return Response::json($rt);
                    }
                }else{
                    $rt =  ['error_code' => 90004, 'errmsg' => $file->getErrorMessage()];
                    return Response::json($rt);
                }
            }
            $rt =  ['error_code' => 0, 'errmsg' => '上传成功', 'img' => $img];
            return Response::json($rt);
        }else{
            $rt =  ['errcode' => 90001, 'errmsg' => '未提供文件'];
            return Response::json($rt);
        }
        
    }
    /**
     * 七牛云回调
     */
    public function qiniuCallback() {
        $service_rt = $this->service->qiniuCallback(Request::all());
        return Response::json($service_rt);
    }

    //提取网络图片并保存到七牛云
    public function takeOnlinePicture(\Illuminate\Http\Request $request)
    {
        $service_rt = $this->service->takeOnlinePicture($request);
        return Response::json($service_rt);
    }
    
    public function getAttachmentById($id) {
        $service_rt = $this->service->getAttachmentById($id);
        return Response::json($service_rt);
    }
   
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function uploadQiniu(\Illuminate\Http\Request $request)
    {
        $arr_userlog = array();
        if($request->hasFile('file')){
            foreach ($request['file'] as $key=>$val){
                $arr_userlog[] = (array)$val;
            }
        }
        $data = array(
            'merchant_id'    => 1,
            'user_id'    => 1,
            'type' => 46,
            'url' => json_encode(array('method'=>'POST','route'=>'auth/login.json')),
            'content' => json_encode(array(
                'requestall'=>$request->all(),
                'fileall'=>$arr_userlog
            )),
            'ip' => $request->ip(),
            'created_time' => date('Y-m-d H:i:s')
        );
        UserLog::create($data);
        
        $rt = $img = array();
        if($request->hasFile('file')){
            foreach ($request['file'] as $key=>$val){
                $file = $val;
                if ($file->isValid()) {
                    $originalname = $file->getClientOriginalName(); // 文件原名
                    $ext          = $file->getClientOriginalExtension();     // 扩展名
                    $realPath     = $file->getRealPath();   //临时文件的绝对路径
                    // 上传文件
                    $filename = $this->getRandChar().'.'.$ext;
            
                    $key = Carbon::now()->year.'/'.Carbon::now()->month.'/'.Carbon::now()->day.'/'.$filename;
            
                    $extarr = ['jpg', 'png', 'jpeg','mp3','gif'];
                    if(!in_array(strtolower($ext), $extarr)){
                        $rt =  ['error_code' => 90002, 'errmsg' => '只支持(.jpg .png .jpeg .mp3 .gif)后缀的图片'];
                        return Response::json($rt);
                    }else if($file->getSize()>1024*1024){
                        $rt =  ['error_code' => 90007, 'errmsg' => '单张图片最大1M'];
                        return Response::json($rt);
                    }
            
                    $auth = new Auth($this->accessKey, $this->secretKey);
                    $token = $auth->uploadToken($this->bucket);
            
                    $uploadMgr = new UploadManager();
            
                    $response = $uploadMgr->putFile($token, $key, $realPath);
            
                    $success = $response[0];
                    $error   = $response[1];
                    //dd($response);
                    if ($error === null) {
                        $this->service->uploadQiniu($file,$success['key'],$request['group_id']);
                        $img = array(
                            'file_name' => $originalname,
                            'file_url' => $success['key']
                        );
                    } else {
                        $rt =  ['error_code' => 90003, 'errmsg' => '上传失败'];
                        return Response::json($rt);
                    }
                }else{
                    $rt =  ['error_code' => 90004, 'errmsg' => $file->getErrorMessage()];
                    return Response::json($rt);
                }
            }
            $rt =  ['error_code' => 0, 'errmsg' => '上传成功', 'img' => $img];
            return Response::json($rt);
        }else{
            $rt =  ['errcode' => 90001, 'errmsg' => '未提供文件'];
            return Response::json($rt);
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
