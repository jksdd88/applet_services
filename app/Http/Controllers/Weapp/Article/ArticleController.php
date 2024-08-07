<?php

namespace App\Http\Controllers\Weapp\Article;
use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Support\Facades\Response;
use App\Services\ArticleService;
use App\Facades\Member;

class ArticleController extends Controller
{
    public function __construct()
    {
        $this->merchant_id = Member::merchant_id();
        $this->member_id = Member::id();
    }



    /**
    *查询单条数据文章
    *@param $article_id int 文章id 必选 
    *@return array
    *@author renruiqi@dodoca.com
    *
    */
    public function getOneArticle(ArticleService $article,Request $request,$article_id)
    {
        $id = $article_id ;
        if($id<1 ) return Response::json(array('errcode'=>17015,'errmsg'=>'参数格式错误'));
        $article->addReadNum($this->merchant_id,$id);        //阅读量加一
        $data = Article::get_data_by_id($id, $this->merchant_id);
        return Response::json(array('errcode'=>0,'errmsg'=>'操作成功','data'=>$data));
    }


    /**
    *文章列表
    *@param $title string 文章标题 可选 
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
}
