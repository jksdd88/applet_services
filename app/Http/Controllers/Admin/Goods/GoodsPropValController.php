<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/9/6
 * Time: 10:01
 * Author: DuMing
 */
namespace App\Http\Controllers\Admin\Goods;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\PropValue;
class GoodsPropValController extends Controller{
    protected $param;
    protected $propval_model;
    protected $user_id;
    public function __construct(Request $request,PropValue $propval_model){
        $this->param = $request->all();
        $this->propval_model = $propval_model;
        $user = Auth::user();
        $this->user_id = $user->merchant_id;
    }

    /**
     * @Author  DuMing
     * 商品规格&属性值列表
     */
    public function index(){
        $prop_id = isset($this->param['prop_id'])?intval($this->param['prop_id']):0;
        if($prop_id <= 0){
            $data['errcode'] = 99001;
            $data['errmsg'] = '参数错误,规格属性值不能为空';
            return Response::json($data, 200);
        }
        $prop_type= isset($this->param['prop_type'])?intval($this->param['prop_type']):0;
        if(!in_array($prop_type,array(1,2))){
            $data['errcode'] = 99001;
            $data['errmsg'] = '参数错误,区分规格或属性类型值错误';
            return Response::json($data, 200);
        }
        $prop_type = $prop_type == 1?0:1;
        $wheres = array(
            array('column'=>'merchant_id','value'=>$this->user_id,'operator'=>'='),
            array('column'=>'prop_id','value'=>$prop_id,'operator'=>'='),
            array('column'=>'prop_type','value'=>$prop_type,'operator'=>'='),
            array('column'=>'is_delete','value'=>1,'operator'=>'=')
        );
        $propvals = $this->propval_model->getDataByWhere($wheres,array('id','merchant_id','prop_id','prop_type','title','created_time','updated_time'))->toArray();
        $data['code'] = 0;
        $data['data'] = $propvals;
        $data['errmsg'] = '';
        return Response::json($data);
    }

    /***
     * @Author  DuMing
     * 商品规格&属性值添加
     */
    public function add(){
        $prop_id = isset($this->param['prop_id'])?intval($this->param['prop_id']):0;
        if($prop_id <= 0){
            $data['errcode'] = 99001;
            $data['errmsg'] = '参数错误,规格属性值不能为空';
            return Response::json($data, 200);
        }
        $prop_type= isset($this->param['prop_type'])?intval($this->param['prop_type']):0;
        if(!in_array($prop_type,array(1,2))){
            $data['errcode'] = 99001;
            $data['errmsg'] = '参数错误,区分规格或属性类型值错误';
            return Response::json($data, 200);
        }
        $prop_type = $prop_type == 1?0:1;
        $title = isset($this->param['title'])?trim($this->param['title']):'';
        if(empty($title)){
            $data['errcode'] = 99001;
            $data['errmsg'] = '参数错误,规格属性值不能为空';
            return Response::json($data, 200);
        }
        $insert_data['merchant_id'] = $this->user_id;
        $insert_data['prop_id'] = $prop_id;
        $insert_data['prop_type'] = $prop_type;
        $insert_data['title'] = $title;
        $insert_data['is_delete'] = 1;
        $res = $this->propval_model->insert_data($insert_data);
        $data = [];
        if($res){
            $data['data'] = array(
                'id'=>$res,
                'merchant_id'=>$this->user_id,
                'prop_type'=>$prop_type,
                'prop_id'=>$prop_id,
                'title'=>$title
            );
            $data['errcode'] = 0;
            $data['errmsg'] = '添加成功';
        }else{
            $data['errcode'] = 1;
            $data['errmsg'] = '添加失败';
        }
        return Response::json($data);
    }

    /***
     * @Author  DuMing
     * 商品规格&属性值删除
     */
    public function delete(){
        $id = isset($this->param['id'])?intval($this->param['id']):0;
        if($id<=0){
            $data['errcode'] = 99001;
            $data['errmsg'] = '参数错误,规格属性值不能为空';
            return Response::json($data, 200);
        }
        $wheres = array(
            array('column'=>'merchant_id','value'=>$this->user_id,'operator'=>'='),
            array('column'=>'id','value'=>$id,'operator'=>'='),
            array('column'=>'is_delete','value'=>1,'operator'=>'=')
        );
        $update_data['is_delete'] = -1;
        $update_data['updated_time'] = date('Y-m-d H:i:s',time());
        $res = $this->propval_model->updateByWhere($wheres,$update_data);
        if($res === false){
            $data['errcode'] = 1;
            $data['errmsg'] = '删除失败';
        }else{
            $data['errcode'] = 0;
            $data['errmsg'] = '删除成功';
        }
        return Response::json($data);
    }
}