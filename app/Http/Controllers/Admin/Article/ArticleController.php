<?php

namespace App\Http\Controllers\Admin\Article;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Models\Article;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use App\Services\ArticleService;


class ArticleController extends Controller
{
    public function __construct()
    {
        $this->merchant_id = Auth::user()->merchant_id;
        $this->user_id = Auth::user()->id;
        // $this->user_id = 1;
        // $this->merchant_id = 1;
    }
    /**
    *资讯列表
    *@param $title string 资讯标题 可选 
    *@param $group_ids int 分组id 可选 
    *@param  $page  int 页码 (可选,默认为1)
    *@param  $pagesize  int 显示条数(可选,默认为3)
    *@param  $c_time  string  排序:按照创建时间 可选参数 desc,asc
    *@param  $u_time string 排序:按照修改时间 可选参数 desc,asc
    *@param  $read_num  int 排序:按照阅读量 可选参数 desc,asc
    *@return array
    *@author renruiqi@dodoca.com
    *
    */

    public function getArticle(ArticleService $article,Request $request)
    {
        $merchant_id =$this->merchant_id;   //商户id
        if((int)$merchant_id < 1) return array('errcode'=>99004,'errmsg'=>'缺少总店id','data'=>['count'=>0,'data'=>$data]);
        $data = $request->all();
        $data['merchant_id'] =$this->merchant_id;
        $data =  $article->getArticleList($data);
        return Response::json($data);
    }



    /**
    *插入资讯
    *@param $title string 资讯标题 必选 
    *@param $intro string 资讯简介 必选 
    *@param $group_ids string 分组id数组  格式 [1,2,3,4,5,6,7,8] 可选 
    *@param $group_name string 分组名称 必选 
    *@param $img_json string 封面图片路径组成字符串 必选 
    *@param $content string 资讯内容 必选 
    *@return array
    *@author renruiqi@dodoca.com
    *
    */
    public function postArticle(Request $request)
    {
        //各种数据的验证
        $data['title'] = trim($request->title); //标题
        $data['intro'] = trim($request->intro); //简介
        $data['img_json'] = trim($request->img_json); //封面图片字符串json
        $data['content'] = trim($request->content); //内容

        if(mb_strlen($data['title'])<1 || mb_strlen($data['title'])>50) return Response::json(array('errcode'=>17011,'errmsg'=>'资讯标题长度为1-50'));
        if(mb_strlen($data['intro'])<1 || mb_strlen($data['intro'])>100) return Response::json(array('errcode'=>17012,'errmsg'=>'资讯摘要长度为1-100'));
        if(mb_strlen($data['img_json'])<1 || mb_strlen($data['content'])<1) return Response::json(array('errcode'=>17013,'errmsg'=>'封面和内容不能为空'));
        $data['group_ids'] = mb_strlen(trim($request->group_ids))<1 ? ',' :trim($request->group_ids); //分组数据
        $data['merchant_id']= $this->merchant_id;
        $data['create_uid']= $this->user_id;        //创建者
        $data['update_uid']= $this->user_id;        //同步至修改者
        $data['is_delete'] = 1;
        $data['read_num']= 0;   //阅读数
        Article::insert_data($data);
        return Response::json(array('errcode'=>0,'errmsg'=>'操作成功'));
    }

    /**
    *删除资讯
    *@param $article_ids string 数组必选 格式 [1,2,3],
    *@return array
    *@author renruiqi@dodoca.com
    *
    */
    public function deleteArticle(Request $request)
    {


        $delete_ids = $request->article_ids;
        if(count($delete_ids)<1) return Response::json(array('errcode'=>17015,'errmsg'=>'参数格式错误'));
        if(is_array($delete_ids)){
            foreach ($delete_ids as $v){
                Article::update_data($v, $this->merchant_id, ['is_delete'=>-1,'update_uid'=>$this->user_id]);
            }
        }else{
                Article::update_data($delete_ids, $this->merchant_id, ['is_delete'=>-1,'update_uid'=>$this->user_id]);
        }
        return Response::json(array('errcode'=>0,'errmsg'=>'操作成功'));
    }

    /**
    *批量修改分组
    *@param $article_ids string 数组形id字符串 必选 格式 ,1,2,3,4,5,6,7,
    *@param $group_ids string 数组形id字符串 必选 格式 ,1,2,3,4,5,6,7,
    *@return array
    *@author renruiqi@dodoca.com
    *
    */
    public function updateArticleGroup(Request $request)
    {
        //将,1,2,3,4,5,6,7,字符串 转换成数组[1,2,3,4,5,6,7]形式
        preg_match_all('/\d+/',$request->article_ids,$ids);
            if(count($ids[0])>0){
                foreach($ids[0] as $vv){
                    $ids[]= intval($vv);
                }
            }
        if(count($ids[0])<1) return Response::json(array('errcode'=>17015,'errmsg'=>'参数格式错误'));
        $group_ids = mb_strlen(trim($request->group_ids))<1 ? ',' :trim($request->group_ids); //分组数据
        foreach ($ids as $v){
            Article::update_data($v, $this->merchant_id, ['group_ids'=>$group_ids,'update_uid'=>$this->user_id]);
        }
        return Response::json(array('errcode'=>0,'errmsg'=>'操作成功'));
    }

    /**
    *修改资讯
    *@param $article_id int 资讯id 必选 
    *@param $title string 资讯标题 必选 
    *@param $intro string 资讯简介 必选 
    *@param $group_ids string 分组id数组  格式 ,1,2,3,4,5,6,7,8, 可选  
    *@param $img_json string 封面图片路径组成字符串 必选 
    *@param $content string 资讯内容 必选 
    *@return array
    *@author renruiqi@dodoca.com
    *
    */
    public function putArticle(Request $request,$article_id)
    {
        //各种数据的验证
        $data['id'] = (int)$article_id; //id
        $data['title'] = trim($request->title); //标题
        $data['intro'] = trim($request->intro); //简介
        $data['img_json'] = trim($request->img_json); //封面图片字符串json
        $data['content'] = trim($request->content); //内容
        if(mb_strlen($data['title'])<1 || mb_strlen($data['title'])>50) return Response::json(array('errcode'=>17011,'errmsg'=>'资讯标题长度为1-50'));
        if(mb_strlen($data['intro'])<1 || mb_strlen($data['intro'])>100) return Response::json(array('errcode'=>17012,'errmsg'=>'资讯摘要长度为1-100'));
        if(mb_strlen($data['img_json'])<1 || mb_strlen($data['content'])<1) return Response::json(array('errcode'=>17013,'errmsg'=>'封面和内容不能为空'));
        if($data['id']<1 ) return Response::json(array('errcode'=>17015,'errmsg'=>'参数格式错误'));
        $data['group_ids'] = mb_strlen(trim($request->group_ids))<1 ? ',' :trim($request->group_ids); //分组数据
        //将数组组合成 ',1,23,' 的形式
        $data['merchant_id']= $this->merchant_id;
        $data['update_uid']= $this->user_id;        //同步至修改者
        Article::update_data($data['id'],$this->merchant_id,$data);
        return Response::json(array('errcode'=>0,'errmsg'=>'操作成功'));
    }


    /**
    *查询单条数据资讯
    *@param $article_id int 资讯id 必选 
    *@return array
    *@author renruiqi@dodoca.com
    *
    */
    public function getOneArticle(Request $request,$article_id)
    {
        $id = $article_id ;
        if($id<1 ) return Response::json(array('errcode'=>17015,'errmsg'=>'参数格式错误'));
        $data = Article::get_data_by_id($id, $this->merchant_id);
        return Response::json(array('errcode'=>0,'errmsg'=>'操作成功','data'=>$data));
    }

    /**
    *资讯阅读数加一
    *@param $article_id  int 资讯id 必选
    */
    public function addReadNum(ArticleService $article,$article_id)
    {
        if($article_id<1 ) return Response::json(array('errcode'=>17015,'errmsg'=>'参数格式错误'));
        $res = $article->addReadNum($this->merchant_id,$article_id);
        if($res['errcode'] !== 0) return Response::json($res); //数据格式错误
        return Response::json(array('errcode'=>0,'errmsg'=>'操作成功'));
    }
}
