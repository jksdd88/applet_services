<?php
/**
 * 商品分类
 */
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Services\UserPrivService;

class GoodsCat extends Model {

    protected $table = 'goods_cat';

    protected $guarded = ['id'];

    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';

    public function getList($wheres=array(), $offset=null, $limit=null, $sorts=array(), $select=null,$index=array()){

        $query = $this->query();

        //是否有预约商品的权限
//        $appointservice_priv = UserPrivService::getUserPrivs();
//        if(!in_array('appointservice', $appointservice_priv)){
//            $cat_type = 0;
//            foreach($wheres as $where) {
//                if($where['column'] == 'cat_type'){
//                    $cat_type = 1;
//                }
//            }
//            if($cat_type == 0){
//                array_push($wheres,array('column'=>'cat_type', 'value'=>0, 'operator'=>'='));
//            }
//        }

        if($index && isset($index['key']) && $index['type']){
            $query->index($index['key'],$index['type']);
        }

        foreach($wheres as $where) {
            if($where['operator']=='wherein'){
                $query->whereIn($where['column'], $where['value']);
            }else{
                $query->where($where['column'], $where['operator'], $where['value']);
            }
            
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
        $rs = $query->get();
        return $rs;
    }


    /***
     * @Author  DuMing
     * 判断是否拥有预约服务权限
     * 当前类目是否是预约服务类目
     */
    public function has_appoint_priv($goods_cat){
       $cats = $this->getList(array(
           array('column'=>'cat_type', 'value'=>1, 'operator'=>'=')
       ))->toArray();
       $ids = array_column($cats,'id');
       $appointservice_priv = UserPrivService::getUserPrivs();
       if(!in_array('trade_apptmanage', $appointservice_priv) && in_array($goods_cat,$ids)){
            return false;
       }
       return true;
    }

    public function getCount($wheres=array()){
        $query = $this->query();
        foreach($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        return $query->count();
    }

    /**
     * 获取单条数据
     * @author denghongmei@dodoca.com
     */
    public function get_data_by_id($id, $fields = '*')
    {
        if(!$id || !is_numeric($id))return;
        $data = self::query()->select(\DB::raw($fields))->where(['id'=>$id])->first();
        return $data;
    }

    /**
     * @param $where
     * @param string $fields
     * @Author  DuMing
     * 获取分类信息
     */
    public function getCatByWhere($wheres,$fields = '*'){
        $query = $this->query();
        foreach($wheres as $where) {
            if($where['operator'] == 'in'){
                $query->whereIn($where['column'], $where['value']);
            }else{
                $query->where($where['column'], $where['operator'], $where['value']);
            }
        }
        return $query->select($fields)->get();
    }
    public function getCountByWhere($wheres=array()){
        $query = $this->query();
        foreach($wheres as $where) {
            if($where['operator'] == 'wherein'){
                $query->whereIn($where['column'], $where['value']);
            }else{
                $query->where($where['column'], $where['operator'], $where['value']);
            }
        }
        return $query->count();
    }

    /***
     * @param $parent_id
     * @Author  DuMing
     * 获取父级下所有的子分类id
     */
    public function getAllChildCat($parent_id,&$allCat=array()){
         if(!is_array($parent_id)){
             $parent_id = [$parent_id];
         }
         $cat = $this->getCatByWhere(array(
             array('column'=>'parent_id','value'=>$parent_id,'operator'=>'in')
         ),array('id'))->toArray();
        if(!empty($cat)){
            $ids = array_column($cat,'id');
            $allCat = array_merge($allCat,$ids);
            $this->getAllChildCat($ids,$allCat);
        }else{
            if(empty($allCat)){
                $allCat = [];
            }
        }
        return $allCat;
    }

    /***
     * @Author  DuMing
     * 通过最后一个分类，获取分类的所有名称
     */
    public function getParentCat($cat_id,&$cats=array()){
        $cat = $this->getCatByWhere(array(
            array('column'=>'id','value'=>$cat_id,'operator'=>'=')
        ),array('parent_id','title'))->toArray();
        if(!empty($cat)){
            $cats[] = $cat[0]['title'];
            if($cat[0]['parent_id']!=0){
                $this->getParentCat($cat[0]['parent_id'],$cats);
            }
        }
        return $cats;
    }
}