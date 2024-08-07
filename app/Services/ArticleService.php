<?php
/**
*文章service 用户前台组件->文章模块
*author  renruiqi@dodoca.com
*@datatime  2017-11-3 17:00:00
*/
namespace App\Services;
use App\Models\Article;
use App\Models\ArticleGroup;
use Illuminate\Support\Facades\Response;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;


class ArticleService
{

    /**
    *文章列表
    *@param $_search['merchant_id'] int 门店id 必选 
    *@param $_search['title'] string 文章标题 可选 
    *@param $_search['group_ids'] int 分组id 可选 
    *@param  $_search['page']  int 页码 (可选,默认为1)
    *@param  $_search['pagesize']  int 显示条数(可选,默认为10)
    *@param  $_search['c_time']  string  排序:按照创建时间 可选参数 desc,asc
    *@param  $_search['u_time'] string 排序:按照修改时间 可选参数 desc,asc
    *@param  $_search['read_num']  int 排序:按照阅读量 可选参数 desc,asc
    *@return array
    *@author renruiqi@dodoca.com
    *
    */
    public function getArticleList(array $_search)
    {
        if(empty($_search['merchant_id']) || (int)$_search['merchant_id'] <1) return ['errcode'=>99004,'errmsg'=>'缺少总店id'];
        $page = (empty($_search['page']) || (int)$_search['page'] <1) ? 1 : (int)$_search['page'];
        $pagesize = (empty($_search['pagesize']) || (int)$_search['pagesize'] <1) ? 10 : (int)$_search['pagesize'];
        $offset = ($page-1)*$pagesize;                                              //偏移量

        $query = Article::select('id','title','intro','img_json','content','group_ids','read_num','created_time','updated_time')
                    ->where('merchant_id',(int)$_search['merchant_id'])
                    ->where('is_delete',1);
        if( isset($_search['group_ids']) && (int)$_search['group_ids'] > 0){
            $query = $query->where('group_ids','like','%,'.(int)$_search['group_ids'].',%');
        }
        if(!empty($_search['title'])){
            $query = $query->where('title','like','%'.trim($_search['title']).'%');
        }
        $data['count'] = $query->count();
        //排序条件 可选:创建时间,修改时间,阅读量
        if(isset($_search['c_time'])&&$_search['c_time']==='asc'){
            $query = $query->orderBy('created_time','asc');
        }elseif(!empty($_search['u_time'])){
            if($_search['u_time']==='asc'){
                $query = $query->orderBy('updated_time','asc');
            }
            if($_search['u_time']==='desc'){
                $query = $query->orderBy('updated_time','desc');
            }
        }elseif(!empty($_search['read_num'])){
            if($_search['read_num']==='asc'){
                $query = $query->orderBy('read_num','asc');
            }
            if($_search['read_num']==='desc'){
                $query = $query->orderBy('read_num','desc');
            }
        }else{
            $query = $query->orderBy('created_time','desc');
        }
        $data['list'] = $query -> offset($offset)
                    ->limit($pagesize)
                    ->get();
        if(count($data['list']) <1) return (['errcode'=>0,'errmsg'=>'查询成功','data'=>$data]);
        //查询所有分类
        $res = $this->getGroupByMerchantId(array('merchant_id'=>$_search['merchant_id']));
        if($res['errcode'] !== 0){
            return $res;
        }else{
            $group_name_list = $res['data']['list'];
        }
        if(count($group_name_list)<1){
            foreach($data['list'] as $v){
                 $v['group_name_str'] = '--'; 
            }
        }else{
            // $data['list'] = $data['list']->toArray();
            $group_name_list = $group_name_list->toArray();
            foreach($group_name_list as $k=>$v){
                $group_all[$v['id']] = $v['name'];                           //组成新的数组   $group_list[id]=>name
            } 
            //循环列表 添加分组名称
            foreach($data['list'] as &$v){
                // $article_group_ids = [];              
                $group_name_array = [] ;
            //将文章数组中的group_ids 分割成数组形式
                preg_match_all('/\d+/',$v->group_ids,$preg_group_list);
                    if(count($preg_group_list[0])>0){
                        $preg_group_list[0] = array_unique($preg_group_list[0]);   //去重
                        foreach($preg_group_list[0] as $vv){
                            if(array_key_exists(intval($vv),$group_all)){
                                $group_name_array[] = $group_all[intval($vv)];        //组装数组
                            }
                        }
                        // $v['group_name_array'] = $group_name_array;
                        $v['group_name_str'] = join($group_name_array,',');
                    }else{
                        // $v['group_name_array'] = ['--'];               //无分组文章
                        $v['group_name_str'] = '--'; 
                    }
            }//foreach结束
        }  //if else 结束
        
        return (['errcode'=>0,'errmsg'=>'查询成功','data'=>$data]);
    }


    /**
    *根据文章id数组文章列表
    *@param $_search['merchant_id'] int 门店id 必选 
    *@param $_search['article_ids'] array 文章id数组 必选 示例[1,2,3,4,5]
    *@return array
    *@author renruiqi@dodoca.com
    *
    */
    public function getArticleListByIds(array $_search)
    {

        if(empty($_search['merchant_id']) || (int)$_search['merchant_id'] <1) return ['errcode'=>99004,'errmsg'=>'缺少总店id'];
        if(empty($_search['article_ids']) || count($_search['article_ids'])<1) return ['errcode'=>99004,'errmsg'=>'缺少文章id数组'];
        $data['list'] = [];
         foreach($_search['article_ids'] as $v){
            $res = Article::select('id','title','intro','img_json','content','group_ids','read_num','created_time','updated_time')
                    ->where('merchant_id',$_search['merchant_id'])
                    ->where('is_delete',1)
                    ->find((int)$v);
            if($res){
                $data['list'][]= $res;
            }
        }
        //查询所有分类
        $res = $this->getGroupByMerchantId(array('merchant_id'=>$_search['merchant_id']));
        if($res['errcode'] !== 0){
            return $res;
        }else{
            $group_name_list = $res['data']['list'];
        }
        if(count($data['list']) <1){
            $data['list'] = [];
        }elseif(count($group_name_list)<1){
            foreach($data['list'] as &$v){  //分组名称
                $v['group_name_str'] = '--'; 
            }
        }else{
            foreach($group_name_list as $k=>$v){
                $group_all[$v['id']] = $v['name'];                           //组成新的数组   $group_list[id]=>name
            } 
            //循环列表 添加分组名称
            foreach($data['list'] as &$v){
                // $article_group_ids = [];              
                $group_name_array = [] ;
            //将文章数组中的group_ids 分割成数组形式
                preg_match_all('/\d+/',$v->group_ids,$preg_group_list);
                    if(count($preg_group_list[0])>0){
                        $preg_group_list[0] = array_unique($preg_group_list[0]);   //去重
                        foreach($preg_group_list[0] as $vv){
                            if(array_key_exists(intval($vv),$group_all)){
                                $group_name_array[] = $group_all[intval($vv)];        //组装数组
                            }
                        }
                        // $v['group_name_array'] = $group_name_array;
                        $v['group_name_str'] = join($group_name_array,',');
                    }else{
                        // $v['group_name_array'] = ['--'];               //无分组文章
                        $v['group_name_str'] = '--'; 
                    }
            }//foreach结束
        }
        return (['errcode'=>0,'errmsg'=>'查询成功','data'=>$data]);
    }



    /**
    *查询门店有分组
    *@param $_search['merchant_id'] int 门店id 必选 
    *@param  $_search['page']  int 页码 (可选,默认为1)
    *@param  $_search['pagesize']  int 显示条数(可选,默认为10)
    *@param  $_search['is_all']  int 是否全部显示(仅当值为1时显示全部数据 且优先级高于分页参数)
    *@return array
    *@author renruiqi@dodoca.com
    */  
    public function getGroupByMerchantId(array $_search)
    {
        if(empty($_search['merchant_id']) || (int)$_search['merchant_id'] <1) return ['errcode'=>99004,'errmsg'=>'缺少总店id'];
        $page = (empty($_search['page']) || (int)$_search['page'] <1) ? 1 : (int)$_search['page'];
        $pagesize = (empty($_search['pagesize']) || (int)$_search['pagesize'] <1) ? 10 : (int)$_search['pagesize'];
        $offset = ($page-1)*$pagesize;                                              //偏移量
        $query= ArticleGroup::select('id','name','created_time')
                        ->where('merchant_id',$_search['merchant_id'])
                        ->where('is_delete',1);
        $data['count'] = $query->count();
        if(isset($_search['is_all'])&&(int)$_search['is_all'] == 1 ){
            $data['list'] = $query->orderBy('id','desc')->get();
        }else{
            $data['list'] = $query->offset($offset)                                        //前台分页数据
                        ->limit($pagesize)
                        ->orderBy('id','desc')
                        ->get();
        }
        return (['errcode'=>0,'errmsg'=>'查询成功','data'=>$data]);

    }


    /**
    *根据分组id数组返回对应分组信息
    *@param $_search['merchant_id'] int 门店id 必选 
    *@param $_search['article_group_ids'] array 文章id数组 必选 示例[1,2,3,4,5]
    *@return array
    *@author renruiqi@dodoca.com
    */
    public function getGroupByIds(array $_search)
    {
        if(empty($_search['merchant_id']) || (int)$_search['merchant_id'] <1) return ['errcode'=>99004,'errmsg'=>'缺少总店id'];
        if(empty($_search['article_group_ids']) || count($_search['article_group_ids'])<1) return ['errcode'=>99004,'errmsg'=>'缺少文章id数组'];
        $data['list'] = ArticleGroup::select('id','name','created_time')
                    ->where('merchant_id',$_search['merchant_id'])
                    ->where('is_delete',1)
                    ->whereIn('id',$_search['article_group_ids'])
                    ->orderBy('id','desc')
                    ->get();
        return (['errcode'=>0,'errmsg'=>'查询成功','data'=>$data]);
    }


    /**
    *文章阅读数加一   (数据库阅读数+1 有缓存修改缓存 无缓存不做操作)
    *@param $merchant_id   int  商户id 必选 
    *@param $article_id  int 文章id 必选
    */
    public function addReadNum($merchant_id = 0,$article_id = 0)
    {
        if((int)$merchant_id <1 || (int)$article_id<1 ) return array('errcode'=>17015,'errmsg'=>'参数格式错误');
        Article::where('id',(int)$article_id)
                ->where('merchant_id',(int)$merchant_id)
                ->increment('read_num');
        //查询缓存
        $key = CacheKey::get_article_by_id_key($article_id, $merchant_id);
        $data = Cache::get($key);
        if ($data) {
            $data->read_num += 1 ;
            Cache::put($key, $data, 60);
        }
        return ['errcode'=>0,'errmsg'=>'操作成功'];
    }

}




