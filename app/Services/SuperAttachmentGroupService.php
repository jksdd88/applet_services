<?php
/**
 * Created by PhpStorm.
 * User: lizhenheng
 * Date: 2017-12-26
 * Time: 下午 03:33
 */
namespace App\Services;

use App\Models\SuperAttachment;
use App\Models\SuperAttachmentGroup;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Http\Request;

class SuperAttachmentGroupService {

    private $super_user_id;
    public function __construct(SuperAttachmentGroup $superattachmentGroup){
        $this->model = $superattachmentGroup;
        $this->super_user_id=Session::get('super_user.id');
        if(app()->isLocal()){
            $this->super_user_id=4;
        }
    }

    /**
    *查询分组
    *@author renruiqi@qq.com  (原型作者:勇哥)
    *@param pid int 可选 父级分类id 默认0
    *@param type int 可选 类型   0普通,1素材(默认0)
    */
    public function getGroups($request_data=[]){
        //dd(Auth::user()->merchant_id);
        $where =[];
        $where[] = array('column' => 'is_delete', 'value' => 1, 'operator' => '=');
        // if(isset($this->super_user_id)) {
        //     $where[] =array('column' => 'super_user_id', 'value' => $this->super_user_id, 'operator' => '=');
        // }
        // dd($where);
        //是否设置类别
        $type = (isset($request_data['type']) && (int)$request_data['type']>0) ? (int)$request_data['type'] : 0;
        $where[] = array('column' => 'type', 'value' => $type, 'operator' => '=');
        //是否设置pid
        $pid = (isset($request_data['pid']) && (int)$request_data['pid']>0) ?  (int)$request_data['pid'] : 0;
        $where[] = array('column' => 'pid', 'value' => $pid , 'operator' => '=');

        $results['data'] = $this->getList($where, null, null, array(array('column'=>'listorder', 'direction'=>'ASC')));
        $results['count'] = $this->getCount($where);
        $results['errcode'] = 0;
        $results['errmsg'] = '获取数据成功';


        //为一级分类添加子类  ($type ==1 or $type == 二级分类类型)
        if($pid ==0 && count($results['data'])>0 and ($type ==1)){
            foreach($results['data'] as &$v){
                $child_where = array(
                    array('column' => 'pid', 'value' => $v->id, 'operator' => '='),//pid=$v->id
                    array('column' => 'is_delete', 'value' => 1, 'operator' => '=')
                );
                $v['chlid_info'] = $this->getList($child_where, null, null, array(array('column'=>'listorder', 'direction'=>'ASC')));
            }
        }
        // $results['input_data'] = $request_data;
        return $results;
    }

    /**
    *批量添加分组 / 批量修改分组
    *@author renruiqi@qq.com (原型作者:勇哥)
    *@param name array 必选  名称数组
    *@param orderlist array 必选  排序数组
    *@param ids array 可选    分组id数组  (若存在功能变为修改)
    */
    public function postGroups($request_data){
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
            if(!isset($request_data['name'][$i]) 
                || mb_strlen(trim($request_data['name'][$i])) >= 20 
                || mb_strlen(trim($request_data['name'][$i])) == 0)
            {
                $rt['errcode']=100002;
                $rt['errmsg']='第'.($i+1).'个分组名称 出错';
                return $rt;
            }else{
                $data_Priv['name'] = trim($request_data['name'][$i]);
            }
            //分组排序
            if(empty($request_data['listorder'][$i])){
                $rt['errcode']=100002;
                $rt['errmsg']='第'.($i+1).'个分组排序 不能为空';
                return $rt;
            }else{
                $data_Priv['listorder'] = $request_data['listorder'][$i];
            }
            $data_Priv['super_user_id'] = $this->super_user_id;
            $data_Priv['is_delete'] = 1;
            $data_Priv['updated_time'] = date('Y-m-d H:i:s');

            //pid+type
            $data_Priv['pid'] = (isset($request_data['pid']) && (int)$request_data['pid']>0) ? (int)$request_data['pid'] : 0;
            // $rt['in_pid'] = $request_data['pid'];
            // $rt['pid'] =$data_Priv['pid']
            $data_Priv['type'] = (isset($request_data['type']) && (int)$request_data['type']>0) ? (int)$request_data['type'] : 0;

            //分组id
            if(!empty($request_data['id'][$i])){
                //id存在则修改
                $wheres = ['is_delete'=>1,'id'=>$request_data['id'][$i]];
                if($data_Priv['type']>0){
                    $wheres['type'] = $data_Priv['type'];
                }
                $up = SuperAttachmentGroup::where($wheres)->update($data_Priv);
                if($up){
                    $success++;
                }
            }else{
                $data_Priv['created_time'] = date('Y-m-d H:i:s');
                //name存在则跳过 不存在则添加
                $rt['name'][$i] = $request_data['name'][$i];
                $wheres = ['name'=>$request_data['name'][$i],'is_delete'=>1];
                if($data_Priv['type']>0){
                    $wheres['type'] = $data_Priv['type'];
                }
                $attachment_had = SuperAttachmentGroup::where($wheres)->first();
                if(!empty($attachment_had)){
                    continue;
                }
                $rs = SuperAttachmentGroup::insertGetId($data_Priv);
                if($rs){
                    $success++;
                }
            }
        }
        if(($success)<1){
            $rt['errcode']=100003;
            $rt['errmsg']='保存失败,分组名称不能重复';
            $rt['data'] = '';
            return $rt;
        }
        $rt['errcode']=0;
        $rt['errmsg']='保存成功';
        $rt['data'] = '';
        return $rt;
    }

    /**
    *删除分组
    *@author renruiqi@qq.com (原型作者:勇哥)
    *@param id int 必选 分组id
    */
    public function deleteGroup($id){
       $id = intval($id);
       if($id < 1 ) return array('errcode'=>10001,'errmsg'=>'请传入正确的分组id');
       //是否可以删除
       $is_delete = $this->is_delete($id);
       if($is_delete['errcode'] !=0 ) return $is_delete;

        if(false === empty($id)){ //删除分组时将文件分组置为0
            SuperAttachment::where(['group_id'=>$id])->update(['group_id'=>0]);
        }
        $rs = $this->model->where(['id'=>$id])->update(['is_delete'=>'-1']);
        if($rs){
            $result = array('errcode'=>0,'errmsg'=>'删除成功');
        }else{
            $result = array('errcode'=>10001,'errmsg'=>'删除失败');
        }

        return $result;
    }

    /**
    *分组是否可以删除
    *@author renruiqi@qq.com
    *@param id int 必选 分组id
    */
    public function is_delete($id)
    {
        //是否可以删除
        $info = $this->model->where(['id'=>$id,'is_delete'=>1])->find($id);
        if($info){
            switch($info->type){
                case 0:
                    return $data = array('errcode'=>0,'errmsg'=>'允许删除');
                    break;
                case 1:
                    //一级分类判定下面是否有子分类
                    $res = $this->model->where(['pid'=>$info->id,'is_delete'=>1])->first();
                    if(count($res)>0){
                        return array('errcode'=>10001,'errmsg'=>'该分组下有子分组,禁止删除');
                    }else{
                        //分组下是否有图片
                        $img_count = SuperAttachment::where(['type'=>'image','is_delete'=>1,'group_id'=>$info->id ])->count();
                        if($img_count >0 )  return array('errcode'=>10002,'errmsg'=>'该分组下有图片,禁止删除');
                        return array('errcode'=>0,'errmsg'=>'允许删除');
                    }
                    break;
                default :
                    //请添加验证条件
                    return array('errcode'=>10001,'errmsg'=>'请添加限定条件');
                    break;
            }
        }
        return array('errcode'=>10001,'errmsg'=>'分组id错误');
    }






    //修改分组名称 排序
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
        $group = $this->model->where('id', '=', $id)->first();
        $group->name = $request_data['name'];
        $group->super_user_id = $this->super_user_id;
        $group->listorder = $request_data['listorder'] ? $request_data['listorder'] : 255;
        $group->save();
        $result = ['errcode'=>0,'errmsg'=>'修改成功'];
        return $result;
    }

    /**
    *设置文件分组
    */
    public function postSetGroup(Request $request_data){
        if(true === empty($request_data['group_id'])){
            return array('error'=>'缺少分组', 'type' => 'SuperAttachmentGroups', 'code' => '1000001');
        }

        if(true === empty($request_data['attachment_ids'])){
            return array('error'=>'缺少要分组的文件', 'type' => 'SuperAttachmentGroups', 'code' => '1000001');
        }

        foreach($request_data['attachment_ids'] AS $id){
            SuperAttachment::where(['id'=>$id])->update(['group_id'=>$request_data['group_id']]);
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
        // return $query->toSql();
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
