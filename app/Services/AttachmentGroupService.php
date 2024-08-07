<?php
namespace App\Services;

use App\Models\Attachment;
use App\Models\AttachmentGroup;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class AttachmentGroupService {

    public function __construct(AttachmentGroup $attachmentGroup){
        $this->model = $attachmentGroup;
    }

    public function getGroups(){
        //dd(Auth::user()->merchant_id);
        $where = array();
        if(isset(Auth::user()->merchant_id)) {
            $where = array(
                array('column' => 'merchant_id', 'value' => Auth::user()->merchant_id, 'operator' => '='),
                array('column' => 'is_delete', 'value' => 1, 'operator' => '=')
                
            );
        }
        $results['data'] = $this->getList($where, null, null, array(array('column'=>'listorder', 'direction'=>'ASC')));
        $results['count'] = $this->getCount($where);
        $results['errcode'] = 0;
        $results['errmsg'] = '获取数据成功';

        return $results;
    }

    public function postGroups($request_data){
        //dd($request_data['name'][1]);
        if( empty($request_data['name']) ){
            $rt['errcode']=100002;
            $rt['errmsg']='分组名称 出错.';
            return $rt;
        }
        
        $nums = count($request_data['name']);
        $success = 0;
        for ($i=0;$i<$nums;$i++){
            $data_Priv=array();
            //分组名称
            if(empty($request_data['name'][$i])){
                $rt['errcode']=100002;
                $rt['errmsg']='第'.($i+1).'个分组名称 出错';
                return $rt;
            }else{
                $data_Priv['name'] = $request_data['name'][$i];
            }
            //分组排序
            if(empty($request_data['listorder'][$i])){
                $rt['errcode']=100002;
                $rt['errmsg']='第'.($i+1).'个分组排序 不能为空';
                return $rt;
            }else{
                $data_Priv['listorder'] = $request_data['listorder'][$i];
            }
            $data_Priv['merchant_id'] = Auth::user()->merchant_id;
            $data_Priv['is_delete'] = 1;
            $data_Priv['updated_time'] = date('Y-m-d H:i:s');
            
            //分组id
            if(!empty($request_data['id'][$i])){
                $up = AttachmentGroup::where(['merchant_id'=>Auth::user()->merchant_id,'is_delete'=>1,'id'=>$request_data['id'][$i]])->update($data_Priv);
                if($up){
                    $success++;
                }
            }else{
                $data_Priv['created_time'] = date('Y-m-d H:i:s');
                
                $attachment_had = AttachmentGroup::where(['merchant_id'=>Auth::user()->merchant_id,'name'=>$request_data['name'][$i],'is_delete'=>1])->first();
                if(!empty($attachment_had)){
                    continue;
                }
                $rs = AttachmentGroup::insertGetId($data_Priv);
                if($rs){
                    $success++;
                }
            }
        }
        if(empty($success)){
            $rt['errcode']=100003;
            $rt['errmsg']='保存失败';
            $rt['data'] = '';
            return $rt;
        }
        $rt['errcode']=0;
        $rt['errmsg']='保存成功';
        $rt['data'] = '';
        return $rt;
    }

    public function putGroups($request_data,$id){
        //分组id
        if(empty($id)){
            $rt['errcode']=100002;
            $rt['errmsg']='分组id 不能为空';
            return $rt;
        }
        //分组名称
        if(empty($request_data['name'])){
            $rt['errcode']=100002;
            $rt['errmsg']='分组名称 不能为空';
            return $rt;
        }else{
            $data_Priv['name'] = $request_data['name'];
        }
        //分组排序
        if(empty($request_data['listorder'])){
            $rt['errcode']=100002;
            $rt['errmsg']='分组排序 不能为空';
            return $rt;
        }else{
            $data_Priv['listorder'] = $request_data['listorder'];
        }

        $group = $this->model->where('id', '=', $id)->where('merchant_id', '=', Auth::user()->merchant_id)->first();
        $group->name = $request_data['names'];
        $group->merchant_id = Auth::user()->merchant_id;
        $group->listorder = $request_data['listorder'] ? $request_data['listorder'] : 255;
        $group->save();
        $result = array('success'=>true);

        return $result;
    }

    public function deleteGroup($id){
        if(false === empty($id)){
            Attachment::where(['group_id'=>$id,'merchant_id'=>Auth::user()->merchant_id])->update(['group_id'=>0]);
        }
        $rs = $this->model->where(['id'=>$id,'merchant_id'=>Auth::user()->merchant_id])->update(['is_delete'=>'-1']);
        if($rs){
            $result = array('errcode'=>0,'errmsg'=>'删除成功');
        }else{
            $result = array('errcode'=>10001,'errmsg'=>'删除失败');
        }
        
        return $result;
    }

    public function postSetGroup(Request $request_data){
        if(true === empty($request_data['group_id'])){
            return array('error'=>'缺少分组', 'type' => 'AttachmentGroups', 'code' => '1000001');
        }

        if(true === empty($request_data['attachment_ids'])){
            return array('error'=>'缺少要分组的文件', 'type' => 'AttachmentGroups', 'code' => '1000001');
        }

        foreach($request_data['attachment_ids'] AS $id){
            Attachment::where(['id'=>$id,'merchant_id'=>Auth::user()->merchant_id])->update(['group_id'=>$request_data['group_id']]);
        }

        $result = array('success'=>true);
        return $result;
    }

    public function getList($wheres=array(), $offset=null, $limit=null, $sorts=array(), $select=null,$index=array())
    {
        $query = $this->model->query();

        if($index && isset($index['key']) && $index['type']){
            $query->index($index['key'],$index['type']);
        }
        foreach($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        if(!is_null($select)) {
            $query->addSelect($select);
        }
        if(!is_null($offset)) {
            $query->skip($offset);
        }
        if(!is_null($limit)) {
            $query->take($limit);
        }
        if($sorts){
            foreach($sorts as $sort) {
                $query->orderBy($sort['column'], $sort['direction']);
            }
        }
        return $query->get();
    }

    public function getCount($wheres=array())
    {
        $query = $this->model->query();
        foreach($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        return $query->count();
    }
}
?>