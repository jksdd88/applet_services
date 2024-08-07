<?php
/**
 * Created by PhpStorm.
 * User: lizhenheng
 * Date: 2017-12-26
 * Time: 下午 03:20
 */
namespace App\Services;

use App\Models\SuperAttachment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Http\Request;
use Qiniu\Storage\BucketManager;
use GuzzleHttp\Client;
use Carbon\Carbon;
use App\Models\SuperAttachmentGroup;

class SuperAttachmentService {

    private $super_user_id;

    public function __construct(SuperAttachment $superattachment) {
        $this->model = $superattachment;
        $this->super_user_id=Session::get('super_user.id');
    }

    /*
     * 上传图片保存
     * */
    public function uploadQiniu($file,$url,$group_id)
    {
        $imageData = [
            'super_user_id'=>$this->super_user_id,
            'group_id'=>!empty($group_id)?$group_id:0,
            'type'=>'image',
            'file_name'=>$file->getClientOriginalName(),
            'file_ext'=>'.'.$file->getClientOriginalExtension(),
            'file_size'=>$file->getSize(),
            'mime_type'=>$file->getMimeType(),
            'url'=>$url,
            'is_delete'=>1,
        ];
        $result = SuperAttachment::insert_data($imageData);
        if($result){
            $rt =  ['error_code' => 0, 'errmsg' => '上传成功'];
            return $rt;
        }else{
            $rt =  ['error_code' => 90006, 'errmsg' => '保存失败'];
            return $rt;
        }
    }
    /**
     * 获取文件列表(原业务逻辑)
     */
/*    public function getAttachments($request_data) {
        //dd($request_data['group_id']);

        $pagesize    = isset($request_data['pagesize']) ? $request_data['pagesize'] : 10;
        $page   = isset($request_data['page']) ? ($request_data['page']-1)*$pagesize : 0;

        $slt_Attachment = SuperAttachment::where(['super_user_id'=>$this->super_user_id,'type'=>'image','is_delete'=>1])
            ->select('id','type','file_name','file_ext','url','imageinfo','avinfo','created_time')
            ->where('file_ext','!=','.mp3');
        if(!empty($request_data['file_name'])){
            $slt_Attachment->where('file_name','like','%'.$request_data['file_name'].'%');
        }
        if(isset($request_data['group_id']) && !empty($request_data['group_id'])){
            $slt_Attachment->where('group_id','=',$request_data['group_id']);
        }

        $slt_Attachment_count = $slt_Attachment->count();

        $rt['errcode']=0;
        $rt['errmsg']='获取数据成功';
        $rt['_count']=$slt_Attachment->count();
        $rt['data'] = $slt_Attachment->orderBy('id', 'desc')->skip($page)->take($pagesize)->get();
        if(!empty($rt['data'])){
            foreach ($rt['data'] as $key=>$val){
                $pos=strrpos($val['file_name'], $val['file_ext']);
                $rt['data'][$key]['file_name_without_ext']=substr($val['file_name'], 0,$pos);
                $rt['data'][$key]['url']=$val['url'];//'https://ms.wrcdn.com/'.
            }
        }
        return $rt;
    }
*/
    /**
    *获取文件
    *@author renruiqi@qq.com (原型作者:勇哥)
    *@param  $file_name strint 可选 要搜索的文件名
    *@param  $group_id int 可选 分组id
    *@param  $scene_type 可选 适用场景 0为普通 1为素材库
    *@param  $is_group_name 可选 为1时查询文件所属分组名称
    */
    public function getAttachments($request_data) {
        //dd($request_data['group_id']);
        $pagesize    = isset($request_data['pagesize']) ? $request_data['pagesize'] : 10;
        $page   = isset($request_data['page']) ? ($request_data['page']-1)*$pagesize : 0;

        $slt_Attachment = SuperAttachment::where(['type'=>'image','is_delete'=>1])
            ->select('id','type','file_name','file_ext','url','imageinfo','avinfo','created_time','group_id')
            ->where('file_ext','!=','.mp3');
        if(!empty($request_data['file_name'])){
            $slt_Attachment->where('file_name','like','%'.$request_data['file_name'].'%');
        }
        //类型限定
        $scene_type = isset($request_data['scene_type']) ? (int)$request_data['scene_type'] : 0;
        $is_group_name = isset($request_data['is_group_name']) ? (int)$request_data['is_group_name'] : 0;//是否添加分组名称
        if( $scene_type > 0){
            switch($scene_type){
                case 1:
                    $group_whers = SuperAttachmentGroup::where(['is_delete'=>1,'type'=>1]);
                    if(isset($request_data['group_id'])&& (int)$request_data['group_id']>0){
                        $g_id = (int)$request_data['group_id'];
                        //查询分组表 若有子集则将子集提取出来
                        $group_whers->where(function($query)use($g_id){
                            $query->where('id',$g_id)->orWhere('pid',$g_id);
                        });
                    }
                    $select_gids = $group_whers->lists('id'); 
                    if(count($select_gids)>0){
                        $slt_Attachment->whereIn('group_id',$select_gids->toArray());
                    }else{
                        return array('errcode' => '0','errmsg' => '查询成功', '_count'=>0,'data'=>[]);//非法分组
                    }
                    break;
                default :
                    return array('errcode' => 10001,'errmsg' => '查询失败', ); //写给开发者(添加对应的逻辑)
                    break;
            }
        }else{
            //类型不限定 遵循原来的业务逻辑
            $slt_Attachment->where('super_user_id',$this->super_user_id); //限定当前用户
            if(isset($request_data['group_id'])&& (int)$request_data['group_id']>0){//按分组查询
                $slt_Attachment->where('group_id',(int)$request_data['group_id']);
            }
        }
        // dump($select_gids);   
        // dump($slt_Attachment->toSql());

        $slt_Attachment_count = $slt_Attachment->count();
        $rt['errcode']=0;
        $rt['errmsg']='获取数据成功';
        $rt['_count']=$slt_Attachment->count();
        $rt['data'] = $slt_Attachment->orderBy('id', 'desc')->skip($page)->take($pagesize)->get();
        if(!empty($rt['data'])){
            foreach ($rt['data'] as $key=>$val){
                $pos=strrpos($val['file_name'], $val['file_ext']);
                $rt['data'][$key]['file_name_without_ext']=substr($val['file_name'], 0,$pos);
                $rt['data'][$key]['url']=$val['url'];//'https://ms.wrcdn.com/'.

                //获取所属一级二级分类名称
                if($is_group_name > 0){
                    $rt['data'][$key]['group_name_one'] = '--';
                    $rt['data'][$key]['group_name_two'] = '--';
                    $group_info = SuperAttachmentGroup::get_data_by_id($val['group_id']);
                    if($group_info){
                        if($group_info['pid'] == 0){//一级分组
                            $rt['data'][$key]['group_name_one'] = $group_info['name'];
                        }else{//二级分组
                            $rt['data'][$key]['group_name_two'] = $group_info['name'];
                            $group_info_one = SuperAttachmentGroup::get_data_by_id($group_info['pid']);
                            if($group_info_one) $rt['data'][$key]['group_name_one'] = $group_info_one['name'];
                        }
                    }
                }
            }
        }
        return $rt;
    }



    /**
     * 删除文件
     */
    public function deleteAttachments($ids) {
        $super_user_id = $this->super_user_id;
        $result = '';
        if ($ids) {
            $idarr = explode(',', $ids['id']);
            $result = SuperAttachment::whereIn('id',  $idarr)
                ->where('super_user_id', '=', $super_user_id)
                ->update(['is_delete'=>'-1','updated_time'=>date('Y-m-d H:i:s')]);
        }
        if ($result) {
            return array('errmsg' => '删除成功', 'errcode' => '0');
        } else {
            return array('errmsg' => '删除失败', 'errcode' => '1000003');
        }
    }

    /**
     * 修改名称+分组
     */
    public function putAttachment($id, $parms) {
        $super_user_id = $this->super_user_id;
        $info = $this->model->where('id', '=', $id)->where('super_user_id', '=', $super_user_id)->first();
        if ($info) {
            $info->file_name = (isset($parms['file_name']) ? $parms['file_name'] : $info['file_name']).$info['file_ext'];
            $info->group_id = (isset($parms['group_id']) ? (int)$parms['group_id'] : $info['group_id']);
            $res = $info->save();
            return array('errmsg' => '修改成功', 'errcode' => '0');
        }
        return array('errmsg' => '修改失败', 'errcode' => '1000002');
    }

    /**
     * 批量设置图片分组
     */
    public function putAttachments($parms) {
        $super_user_id = $this->super_user_id;
        if(!isset($parms['group_id']) || empty($parms['group_id'])){
            return array('errmsg' => '图片分组id 不能为空', 'errcode' => '1000001');
        }
        $attachmentGroup = SuperAttachmentGroup::where(['id'=>$parms['group_id'],'super_user_id'=> $super_user_id,'is_delete'=>1])->first();
        if(empty($attachmentGroup)){
            return array('errmsg' => '查询不到此分组信息', 'errcode' => '1000002');
        }
        if(!isset($parms['id']) || empty($parms['id'])){
            return array('errmsg' => '图片id 不能为空', 'errcode' => '1000003');
        }
        if (isset($parms['id']) && !empty($parms['id'])) {
            if(!is_array($parms['id'])){
                $idarr = explode(',', $parms['id']);
            }else{
                $idarr = array(str_replace('"', '', $parms['id']));
            }

            $result = SuperAttachment::whereIn('id',  $idarr)
                ->where('super_user_id', '=', $super_user_id)
                ->update(['group_id'=>$parms['group_id']]);
        }

        if ($result) {
            return array('errmsg' => '设置分组 成功', 'errcode' => '0');
        }
        return array('errmsg' => '设置分组 失败', 'errcode' => '1000004');
    }

    /**
     * 获取七牛云uptoken
     */
    public function qiniuToken() {
        $ak = env('QINIU_ACCESS_KEY');
        $sk = env('QINIU_SECRET_KEY');
        $bucket = env('QINIU_BUCKET');//七牛上传的空间名
        $auth = new \Qiniu\Auth($ak, $sk);
        $policy = array(
            'saveKey' => '$(year)/$(mon)/$(day)/$(etag)$(ext)',
            'callbackUrl' => route('qiniu_callback'),
            'callbackBody' => 'super_user_id=$(x:super_user_id)&group_id=$(x:group_id)&url=$(key)&file_name=$(fname)&file_size=$(fsize)&file_ext=$(ext)&mime_type=$(mimeType)&imageinfo=$(imageInfo)&avinfo=$(avinfo)',
        );

        #临时处理http引起的七牛无法正常回调的问题
        if(false === stristr($policy['callbackUrl'], 'https')){
            $policy['callbackUrl'] = str_replace('http://', 'https://', $policy['callbackUrl']);
        }

        $token = $auth->uploadToken($bucket, null, 3600, $policy);
        return $token;
    }

    /**
     * 七牛云回调
     */
    public function qiniuCallback($attachment) {
        $attachment_logs = $attachment;

        if(isset($attachment['mime_type'])) {
            $attachment['type'] = explode('/', $attachment['mime_type'])[0];
            $attachment['is_delete'] = 1;
        }
        $result = $this->create($attachment);

        $result['avinfo'] = json_decode($result['avinfo'], true);
        $result['imageinfo'] = json_decode($result['imageinfo'], true);
        return $result;
    }

    //提取网络图片并保存到七牛云
    public function takeOnlinePicture($request_data)
    {
        if (strpos($request_data->url,'?')){
            return array('获取失败', 422);
        }
        $pic = $this->takesOnlinePicture($request_data->all());
        if(isset($pic['type']) && $pic['type'] == 'QINIU')
        {
            return array($pic, 422);
        }
        return $pic;
    }


    //暂时不用
    public function getsAttachments($data) {
        if(!empty($this->super_user_id)) {
            if (isset($data['type'])) {
                $wheres = array(
                    array('column' => 'type', 'value' => $data['type'], 'operator' => '='),
                    array('column' => 'super_user_id', 'value' => $this->super_user_id, 'operator' => '=')
                );
            } else {
                $wheres = array(
                    array('column' => 'super_user_id', 'value' => $this->super_user_id, 'operator' => '=')
                );
            }
        } else {
            //无需登录可以选择图片 add by zhangchangchun@dodoca.com
            $session = Session::get('unlimited_article');
            if($session) {
                $wheres = array(
                    array('column' => 'super_user_id', 'value' => 2, 'operator' => '=')
                );
            } else {
                $wheres = array(
                    array('column' => 'super_user_id', 'value' => -1, 'operator' => '=')
                );
            }
        }

        if(true === isset($data['group_id'])){
            $wheres = array_merge($wheres, [['column' => 'group_id', 'value' => $data['group_id'], 'operator' => '=']]);
        }

        $offset = isset($data['offset']) ? $data['offset'] : null;
        $limit = isset($data['limit']) ? $data['limit'] : null;
        $sorts = array(
            array('column' => 'id', 'direction' => 'desc')
        );
        //根据文件名搜索
        if(isset($data['title']) && $data['title']){
            $wheres =  array_merge($wheres,[['column'=>'file_name','value' => '%'.$data['title'].'%', 'operator' => 'like']]);
        }

        //查出必要字段
        $data = $this->getList($wheres, $offset, $limit, $sorts,array('id','file_name','imageinfo','avinfo','group_id','url','created_time','type'));
        foreach ($data as &$_item) {
            $_item['imageinfo'] = json_decode($_item['imageinfo'], true);
            $_item['avinfo'] = json_decode($_item['avinfo'], true);
            $file_name = isset($_item['file_name']) && $_item['file_name'] ? $_item['file_name'] : [];
            if(true === empty($file_name))
                continue;
            $file_name_arr = explode('.',$file_name);
            array_pop($file_name_arr);
            $_item['file_name_without_ext'] = implode('.',$file_name_arr);
        }
        $result['data'] = $data;
        $result['_count'] = $this->getCount($wheres);
        return $result;
    }

    public function getAttachmentById($id) {
        $wheres = array(
            array('column' => 'id', 'value' => $id, 'operator' => '=')
        );
        $attachmentItem = $this->get($wheres);
        return $attachmentItem;
    }

    //提取网络图片并保存到七牛云
    public function takesOnlinePicture($data,$merchant_id = null)
    {
        $url = $data['url'];
        $url = preg_replace( '/(?:^[\'"]+|[\'"\/]+$)/', '', $url );


        $ext=strtolower(strrchr($url,'.'));
        if($ext!='.gif' && $ext!='.jpg' && $ext!='.png' && $ext!='.jpeg'){return false;}
        $filename=$this->getRandChar().$ext;
        $ak     = env('QINIU_ACCESS_KEY');
        $sk     = env('QINIU_SECRET_KEY');
        $bucket = env('QINIU_BUCKET');
        $auth = new \Qiniu\Auth($ak, $sk);
        $key = Carbon::now()->year.'/'.Carbon::now()->month.'/'.Carbon::now()->day.'/'.$filename;
        $bucketManager = new BucketManager($auth);
        try{
            list($retData, $error) = $bucketManager->fetch($url,$bucket,$key);
            if($error) {
                return array('errmsg' => $error->message(), 'type' => 'QINIU', 'errcode' => $error->code());
            }
        }
        catch (\Exception $e)
        {
            throw new \Exception($e->getMessage());
        }
        if($retData)
        {
            $imageData = [
                'super_user_id'=>$this->super_user_id ? $this->super_user_id : 0,
                'type'=>'image',
                'file_name'=>$filename,
                'file_ext'=>$ext,
                'file_size'=>$retData['fsize'],
                'mime_type'=>$retData['mimeType'],
                'url'=>$retData['key']
            ];
            $attachment = SuperAttachment::create($imageData);
            if($attachment)
            {
                $client = new Client(['base_uri' => env('QINIU_DOMAIN')]);
                $res = $client->request('GET',$attachment['url'].'?imageInfo');
                $infoData = $res->getBody()->getContents();

                $au = SuperAttachment::where(['id'=>$attachment['id']])->update(['imageinfo'=>$infoData]);
                $au && $attachment['imageinfo'] = $infoData;
            }
            return $attachment;
        }
        return false;
    }

    private function getRandChar(){
        $str = null;
        $strPol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
        $max = strlen($strPol)-1;

        for($i=0;$i<28;$i++){
            $str.=$strPol[rand(0,$max)];//rand($min,$max)生成介于min和max两个数之间的一个随机整数
        }
        return $str;
    }

    public function create($data)
    {
        return $this->model->create($data);
    }
}