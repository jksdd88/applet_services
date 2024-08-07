<?php

namespace App\Http\Controllers\Admin\Form;

use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;

use Illuminate\Support\Facades\Auth;
use App\Models\Merchant;
use App\Models\FormCate;
use App\Models\FormCateRel;
use App\Models\FormInfo;
use Illuminate\Support\Facades\Response;
use App\Services\FormService;

class FormCateController extends Controller
{


    public function __construct()
     {  
        if (app()->isLocal()) {
            $this->merchant_id = 1;
        } else {
            $this->merchant_id = Auth::user()->merchant_id;
        }
     }

    //test
     public function test()
     {  
        // $res = FormCate::get_data_by_id(7,1);
        // dd($res);
        // $res = FormCateRel::getCateListByFormId(1,1);
        // dump($res);
        // $res = FormCateRel::checkFormCateByFormId(1,1,[31]);
        // dd($res);
     }



    /**
    *查询所有分类
    *@param  $page  int 页码 (可选,默认为1)
    *@param  $pagesize  int 显示条数(可选,默认为10)
    *@return array
    *@author renruiqi@dodoca.com
    */
    public function getFormCate(Request $request)
    {
        $merchant_id =$this->merchant_id;   //商户id
        if((int)$merchant_id < 1) return Response::json(array('errcode'=>150001,'errmsg'=>'缺少商户id','data'=>['count'=>0,'data'=>[]]));
        $page = (int)$request->page <1 ? 1 :(int)$request->page;                    //页码
        $pagesize = (int)$request->pagesize <1 ? 10 :(int)$request->pagesize;       //每页多少条数据
        $offset = ($page-1)*$pagesize;                                              //偏移量
        $query = FormCate::select('id','name','created_time','merchant_id','is_edit')
                    ->where('is_delete',1)
                    ->whereIn('merchant_id',[0,$merchant_id]);                       //本门店+通用分类
        $data['count'] = $query->count();
        if($data['count'] < 1) return Response::json(array('errcode'=>0,'errmsg'=>'查询成功','data'=>['count'=>0,'data'=>[]]));
        $data['data'] = $query->offset($offset)                                     //前台分页数据
                    ->limit($pagesize)
                    ->orderBy('sort','asc')                                         //排序小的在前
                    ->orderBy('id','desc')
                    ->get()
                    ->toArray();

        foreach($data['data'] as $k=>&$v){
            if($v['merchant_id'] === 0){
                $data['data'][$k]['created_time'] = '-';                            //系统分类不设开始时间
            }

            //查询该分类下的表单数量  关联关系:form_cate_rel.form_id=form_info.id
            $data['data'][$k]['form_count'] = FormInfo::where('cate_id',$v['id'])
                    ->where('is_delete',1)
                    ->where('merchant_id',$merchant_id)
                    ->count();

        }
        return Response::json(array('errcode'=>0,'errmsg'=>'查询成功','data'=>$data));
    }

    /**
    *添加分类
    *@param $name string 分类名称 必选
    *@author renruiqi@dodoca.com
    */
    public function postFormCate(Request $request)
    {
        $data['name'] = trim($request->name);
        if(mb_strlen($data['name'])<1 || mb_strlen($data['name'])>20) return Response::json(array('errcode'=>17003,'errmsg'=>'分类名称长度为1-20'));
        $data['is_delete'] = 1;
        $data['is_edit'] = 2;       //可编辑
        $data['sort'] = 100;        //排序默认100
        $data['merchant_id'] = $this->merchant_id;
        //判定是否重名
        $is_repetition = FormCate::whereIn('merchant_id',[0,$this->merchant_id])
                        ->where('is_delete',1)
                        ->where('name',$data['name'])
                        ->count();
        if( $is_repetition >0) return Response::json(array('errcode'=>17006,'errmsg'=>'分类已存在'));

        $res = FormCate::insert_data($data);
        return Response::json(array('errcode'=>0,'errmsg'=>'添加成功'));

    }


    /**
    *修改分类
    *@param $name string 分类名称 必选
    *@param $formcate_id int 分类id 必选  (路由参数)
    *@author renruiqi@dodoca.com
    */
    public function putFormCate(Request $request,$formcate_id=0)
    {
        if($formcate_id<1) return Response::json(array('errcode'=>17001,'errmsg'=>'请传入分类id','data'=>$data));
        $data['name'] = trim($request->name);
        if(mb_strlen($data['name'])<1 || mb_strlen($data['name'])>20) return Response::json(array('errcode'=>17003,'errmsg'=>'分类名称长度为1-20'));
        //判定是否重名
        $is_repetition = FormCate::select('id')
                        ->whereIn('merchant_id',[0,$this->merchant_id])
                        ->where('is_delete',1)
                        ->where('id','<>',$formcate_id)
                        ->where('name',$data['name'])
                        ->count();
        if( $is_repetition >0) return Response::json(array('errcode'=>17006,'errmsg'=>'分类已存在'));
        FormCate::update_data($formcate_id, $this->merchant_id, $data);
        return Response::json(array('errcode'=>0,'errmsg'=>'修改成功'));
    }


    /**
    *删除分类
    *@param $formcate_id int 分类id 必选  (路由参数)
    *@author renruiqi@dodoca.com
    */
    public function deleteFormCate(Request $request,$formcate_id=0)
    {
        if($formcate_id<1) return Response::json(array('errcode'=>17001,'errmsg'=>'请传入正确的分类id'));
        //删除分类时,对应分类下不能有表单
        $form_count = FormInfo::where('cate_id',$formcate_id)
                    ->where('is_delete',1)
                    ->where('merchant_id',$this->merchant_id)
                    ->count();
        if($form_count > 0){
            return Response::json(array('errcode'=>17005,'errmsg'=>'该分类下有表单,不可删除!'));
        }else{
            FormCate::update_data($formcate_id, $this->merchant_id,['is_delete'=>-1]);
            return Response::json(array('errcode'=>0,'errmsg'=>'删除成功'));
        }
    }




}
