<?php

namespace App\Http\Controllers\Admin\Article;

use App\Models\ArticleGroup;
use App\Models\Article;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\ArticleService;

class ArticleGroupController extends Controller
{
    public function __construct()
    {
        $this->merchant_id = Auth::user()->merchant_id;
        // $this->merchant_id = 1;
    }




    /**
    *查询单条数据
    *@param $group_id int 分组id 必选
    */
    public function getOneGroup($group_id)
    {
        if($group_id<1) return Response::json(array('errcode'=>17001,'errmsg'=>'请传入正确的分组id'));
        $data = ArticleGroup::select('id','name')
                        ->where('id',$group_id)
                        ->where('merchant_id',$this->merchant_id)
                        ->where('is_delete',1)
                        ->get();
        if(count($data)<1 ){
            return Response::json(array('errcode'=>17002,'errmsg'=>'该分组已经被删除','data'=>$data));
        }else{
            return Response::json(array('errcode'=>0,'errmsg'=>'查询成功','data'=>$data));
        }
    }


    /**
    *查询所有分组
    **@param  $page  int 页码 (可选,默认为1)
    *@param  $pagesize  int 显示条数(可选,默认为10)
    *@param  $is_all  int 是否显示全部(可选,当参数存在且为1时显示全部)
    */
    public function getGroup(ArticleService $article_service, Request $request)
    {
        
        $_search = $request->all();
        $_search['merchant_id'] =$this->merchant_id;
        $data =  $article_service->getGroupByMerchantId($_search);
        return Response::json($data);
    }

    /**
    *添加分组
    *@param $name string 分组名称 必选
    *@author renruiqi@dodoca.com
    */
    public function postGroup(Request $request)
    {
        $data['name'] = trim($request->name);
        $data['is_delete'] = 1;
        $data['merchant_id'] = $this->merchant_id;
        if(mb_strlen($data['name'])<1 || mb_strlen($data['name'])>20) return Response::json(array('errcode'=>17003,'errmsg'=>'分组名称长度为1-20'));
        //判定是否重名
        $is_repetition = ArticleGroup::select('id')
                        ->where('merchant_id',$this->merchant_id)
                        ->where('is_delete',1)
                        ->where('name',$data['name'])
                        ->count();
        if( $is_repetition >0) return Response::json(array('errcode'=>17006,'errmsg'=>'分组已存在'));

        $res = ArticleGroup::insert_data($data);
        return Response::json(array('errcode'=>0,'errmsg'=>'添加成功'));

    }

    /**
    *修改分组
    *@param $name string 分组名称 必选
    *@author renruiqi@dodoca.com
    */
    public function putGroup(Request $request,$group_id=0)
    {
        if($group_id<1) return Response::json(array('errcode'=>17001,'errmsg'=>'请传入分组id','data'=>$data));
        $data['name'] = trim($request->name);
        if(mb_strlen($data['name'])<1 || mb_strlen($data['name'])>20) return Response::json(array('errcode'=>17003,'errmsg'=>'分组名称长度为1-20'));
        //判定是否重名
        $is_repetition = ArticleGroup::select('id')
                        ->where('merchant_id',$this->merchant_id)
                        ->where('is_delete',1)
                        ->where('id','<>',$group_id)
                        ->where('name',$data['name'])
                        ->count();
        if( $is_repetition >0) return Response::json(array('errcode'=>17006,'errmsg'=>'分组已存在'));
        //事务 (修改文章表中的对应分组名称)
            ArticleGroup::update_data($group_id, $this->merchant_id, $data);
        return Response::json(array('errcode'=>0,'errmsg'=>'修改成功'));
    }

    /**
    *删除分组
    *@param $group_id int 分组id 必选
    *@author renruiqi@dodoca.com
    */
    public function deleteGroup(Request $request,$group_id=0)
    {
        if($group_id<1) return Response::json(array('errcode'=>17001,'errmsg'=>'请传入正确的分组id'));
        //删除分组时,对应分组下不能有文章
        $article_nums = Article::select('id')
                    ->where('merchant_id',$this->merchant_id)
                    ->where('group_ids','like','%,'.$group_id.',%')
                    ->where('is_delete',1)
                    ->count();
        if($article_nums > 0){
            return Response::json(array('errcode'=>17005,'errmsg'=>'该分组下有资讯,不能删除'));
        }else{
            ArticleGroup::update_data($group_id, $this->merchant_id,['is_delete'=>-1]);
            return Response::json(array('errcode'=>0,'errmsg'=>'删除成功'));
        }
    }


}
