<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017-12-04
 * Time: 下午 05:27
 */
namespace App\Http\Controllers\Super;


use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Response;
use App\Models\DesignTemplate;
use App\Models\DesignTemplatePage;
use App\Models\ShopDesign;
use App\Models\IndexPictures;
use Illuminate\Http\Request;


class TemplateController extends Controller
{
    protected $request;
    protected $params;

    public function __construct(Request $request) {
        $this->request = $request;
        $this->params = $request->all();
    }
    //首页装修列表
    public function designList(){
        $offset = isset($this->params['offset']) && $this->params['offset'] ? $this->params['offset'] : 0;
        $limit = isset($this->params['limit']) && $this->params['limit'] ? $this->params['limit'] : 20;
        $title = isset($this->params['title']) && $this->params['title'] ? $this->params['title'] : '';
        $query=ShopDesign::select('*');

        $query->where('is_delete','=',1);
        $query->whereIn('merchant_id',[14761,16344]);
        if($title){
            $query->where('title','like',"%".$title."%");
        }
        $count = $query->count();
        $query->skip($offset);
        $query->take($limit);
        $query->orderBy('id','desc');
        $list = $query->get();

        $industry = Config::get('industrycat') ? Config::get('industrycat') : '';

        $data['errcode'] = 0;
        $data['_count'] = $count;
        $data['data'] = $list;
        $data['industry'] =$industry;
        return Response :: json($data);
    }

    //整套页面模板列表
    public function desionTemplateList(){
        $offset = isset($this->params['offset']) && $this->params['offset'] ? $this->params['offset'] : 0;
        $limit = isset($this->params['limit']) && $this->params['limit'] ? $this->params['limit'] : 20;
        $catid = (isset($this->params['catid']) && (!empty($this->params['catid']))) ? $this->params['catid'] : 0;
        $name = (isset($this->params['name']) && (!empty($this->params['name']))) ? $this->params['name'] : '';

//        $industry = Config::get('industrycat') ? Config::get('industrycat') : '';
        $query=DesignTemplate::select('*');
        if(!empty($catid)) {
            $query->whereRaw("FIND_IN_SET(".$catid.",group_id)");
        }
        if($name){
            $query->where("name","like","%".$name."%");
        }
        $count = $query->count();
        $query->skip($offset);
        $query->take($limit);
        $query->orderBy('sort','asc');
        $list = $query->get();

        $data['errcode'] = 0;
        $data['_count'] = $count;
        $data['data'] = $list;
//        $data['industry'] =$industry;
        return Response :: json($data);
    }

    //整套模板详情
    public function desionDetail(){
        $id = isset($this->params['id']) && $this->params['id'] ? $this->params['id'] : '';
        if($id){
            $template=DesignTemplate::get_data_by_id($id);
            if($template["shop_design_ids"]){
                $shop_design=json_decode($template["shop_design_ids"]);
                if($shop_design){
                    $shop_design_detail=array();
                    foreach ($shop_design as $key=>$val){
                        $query=ShopDesign::select('id','title');
                        $query->where("id","=",$val);
                        $shop=$query->first();
                        if($shop){
                            $shop_design_detail[]=$shop;
                        }

                    }
                }
                $template["shop_design_ids"]=$shop_design_detail;
            }else{
                $template["shop_design_ids"]=array();
            }
        }
        $data['errcode'] = 0;
        $data['data'] = $template;
        return Response :: json($data);
    }

    //保存模板
    public function addDesion(){
        $id = isset($this->params['id']) && $this->params['id'] ? $this->params['id'] : '';
        $name = isset($this->params['name']) && $this->params['name'] ? $this->params['name'] : '';
        $image = isset($this->params['url']) && $this->params['url'] ? $this->params['url'] : '';
        $group_id = isset($this->params['group_id']) && $this->params['group_id'] ? $this->params['group_id'] : '';
        $shop_design_ids = isset($this->params['shop_design_ids']) && $this->params['shop_design_ids'] ? $this->params['shop_design_ids'] : '';
        $sort = isset($this->params['sort']) && $this->params['sort'] ? $this->params['sort'] : '';
        $edit_num = isset($this->params['edit_num']) && $this->params['edit_num'] ? $this->params['edit_num'] : 0;  //可编辑使用数
        $data=array();
        if(!$shop_design_ids){
            $data['shop_design_ids']="";
        }else{
            $data['shop_design_ids']="[".$shop_design_ids."]";
        }
        $data['name']=$name;
        $data['image']=$image;
        $data['group_id']=$group_id;
        $data['sort']=$sort;
        $data['edit_num'] = $edit_num;

        if($id){
            $data['updated_time']=date("Y-m-d H:i:s");
            DesignTemplate::update_data($id,$data);
        }else{
            $data['created_time']=date("Y-m-d H:i:s");
            DesignTemplate::insert_data($data);
        }
        $res['errcode'] = 0;
        $res['errmsg'] = 'ok';
        return Response :: json($res);
    }

    //单页面模板列表
    public function TemplatePageList(){
        $offset = isset($this->params['offset']) && $this->params['offset'] ? $this->params['offset'] : 0;
        $limit = isset($this->params['limit']) && $this->params['limit'] ? $this->params['limit'] : 20;
        $catid = (isset($this->params['catid']) && (!empty($this->params['catid']))) ? $this->params['catid'] : 0;
        $name = (isset($this->params['name']) && (!empty($this->params['name']))) ? $this->params['name'] : '';
//        $industry = Config::get('industrycat') ? Config::get('industrycat') : '';
        $query=DesignTemplatePage::select('*');
        if(!empty($catid)) {
            $query->whereRaw("FIND_IN_SET(".$catid.",group_id)");
        }
        if($name){
            $query->where("template_name","like","%".$name."%");
        }

        $count = $query->count();
        $query->skip($offset);
        $query->take($limit);
        $query->orderBy('sort','asc');
        $list = $query->get();

        $data['errcode'] = 0;
        $data['_count'] = $count;
        $data['data'] = $list;
//        $data['industry'] =$industry;
        return Response :: json($data);
    }

    //模板详情
    public function desionPageDetail(){
        $id = isset($this->params['id']) && $this->params['id'] ? $this->params['id'] : '';
        if($id){
            $template=DesignTemplatePage::get_templatepage_id($id);
            if($template['shop_design_id']){
                $shop_design_detail=array();
                $query=ShopDesign::select('id','title');
                $query->where("id","=",$template['shop_design_id']);
                $shop=$query->first();
                if($shop){
                    $shop_design_detail[]=$shop;
                }
                $template['shop_design_id']=$shop_design_detail;
            }
        }
        $data['errcode'] = 0;
        $data['data'] = $template;
        return Response :: json($data);
    }
    //保存模板
    public function addDesionPage(){

        $id = isset($this->params['id']) && $this->params['id'] ? $this->params['id'] : '';
        $name = isset($this->params['name']) && $this->params['name'] ? $this->params['name'] : '';
        $image = isset($this->params['url']) && $this->params['url'] ? $this->params['url'] : '';
        $image_show = isset($this->params['show_url']) && $this->params['show_url'] ? $this->params['show_url'] : '';
        $group_id = isset($this->params['group_id']) && $this->params['group_id'] ? $this->params['group_id'] : '';
        $shop_design_ids = isset($this->params['shop_design_ids']) && $this->params['shop_design_ids'] ? $this->params['shop_design_ids'] : '';
        $sort = isset($this->params['sort']) && $this->params['sort'] ? $this->params['sort'] : '';
        $edit_num = isset($this->params['edit_num']) && $this->params['edit_num'] ? $this->params['edit_num'] : 0;  //可编辑使用数
        $data=array();
        $data['template_name']=$name;
        $data['template_image']=$image;
        $data['template_show_img']=$image_show;
        $data['group_id']=$group_id;
        $data['sort']=$sort;
        $data['edit_num']=$edit_num;
        $data['shop_design_id']=$shop_design_ids;
        if($id){
            $data['updated_time']=date("Y-m-d H:i:s");
            DesignTemplatePage::update_data($id,$data);
        }else{
            $data['created_time']=date("Y-m-d H:i:s");
            DesignTemplatePage::insert_data($data);
        }
        $res['errcode'] = 0;
        $res['errmsg'] = 'ok';
        return Response :: json($res);
    }
    //删除模板
    public function deleteDesion(){
        $id = isset($this->params['id']) && $this->params['id'] ? $this->params['id'] : '';
        $type = isset($this->params['type']) && $this->params['type'] ? $this->params['type'] : '';
        if($type && $id){
            if($type==1){ //整套模板
                DesignTemplate::where('id',$id)->delete();
            }elseif ($type==2){ //单页模板
                DesignTemplatePage::where('id',$id)->delete();
            }
        }
        $res['errcode'] = 0;
        $res['errmsg'] = 'ok';
        return Response :: json($res);
    }



    /**
     * 获取当前所有有效轮播图
     *
     * Author:zhangyu1@dodoca.com
     *
     */
    public function getAllPictures(){

        $query=IndexPictures::select('*');

        $query->where('is_delete','=',1);

        $count = $query->count();

        $query->orderby('sort','asc');

        $list = $query->get();

        return Response::json(['errcode'=>0,'_count'=>$count,'errmsg'=>'获取数据 成功','data'=>$list]);

    }

    /**
     *  添加或编辑图片
     *
     *  Author:zhangyu1@dodoca.com
     *
     */
    public function AddPictures(){

        $id = isset($this->params['id']) && $this->params['id'] ? $this->params['id'] : '';

        $pic_url = isset($this->params['pic_url']) && $this->params['pic_url'] ? $this->params['pic_url'] : '';

        $url = isset($this->params['url']) && $this->params['url'] ? $this->params['url'] : '';

        $color = isset($this->params['color']) && $this->params['color'] ? $this->params['color'] : '';

        $data=array();

        $data['url']=$url;

        $data['pic_url']=$pic_url;

        $data['color']=$color;

        if($id){

            if($pic_url){

                $update_data['pic_url']=$pic_url;
            }
            if($url){

                $update_data['url']=$url;
            }
            if($color){

                $update_data['color']=$color;
            }

            IndexPictures::update_data($id,$update_data);

        }else{

            if($pic_url == ''){

                $res['errcode'] = 10001;

                $res['errmsg'] = '图片地址未传';

                return Response :: json($res);
            }

            $maxsort = IndexPictures::select('sort')->where('is_delete',1)->orderby('sort','desc')->first();

            if(!empty($maxsort)){

                if($maxsort->sort >=5){

                    $res['errcode'] = 10002;

                    $res['errmsg'] = '最多5个轮播图';

                    return Response :: json($res);
                }
            }

            if(!empty($maxsort->sort)){

                $data['sort'] = $maxsort->sort + 1;

            }else{

                $data['sort'] = 1;
            }

            IndexPictures::insert_data($data);
        }

        $res['errcode'] = 0;

        $res['errmsg'] = 'ok';

        return Response :: json($res);
    }

    public function ChangeSort(){

        $id = isset($this->params['id']) && $this->params['id'] ? $this->params['id'] : '';

        $action = isset($this->params['action'])  && $this->params['action'] ? $this->params['action'] : '';

        $current_sort = IndexPictures::select('sort')->where('id',$id)->first();

        if($action == 'ahead'){   //前进

            $current_data['sort'] = $current_sort->sort - 1;

            $ahead_sort = IndexPictures::select('sort','id')->where('sort',$current_sort->sort - 1)->first();

            $ahead_data['sort'] = $ahead_sort->sort + 1;

            $cur_res = IndexPictures::update_data($id,$current_data);

            $other_res = IndexPictures::update_data($ahead_sort->id,$ahead_data);

        }elseif($action == 'recede'){  //后退

            $current_data['sort'] = $current_sort->sort + 1;

            $recede_sort = IndexPictures::select('sort','id')->where('sort',$current_sort->sort + 1)->first();

            $recede_data['sort'] = $recede_sort->sort - 1;

            $cur_res = IndexPictures::update_data($id,$current_data);

            $other_res = IndexPictures::update_data($recede_sort->id,$recede_data);

        }

        if($cur_res && $other_res){

            $res['errcode'] = 0;

            $res['errmsg'] = 'success';

        }else{
            $res['errcode'] = 10001;

            $res['errmsg'] = 'error';
        }

        return Response :: json($res);
    }


    public function DeletePicture(){

        $id = isset($this->params['id']) && $this->params['id'] ? $this->params['id'] : '';

        if($id){

            $data['is_delete'] = -1;

            $data['sort'] = 0;

            IndexPictures::update_data($id,$data);

        }

        $res['errcode'] = 0;

        $res['errmsg'] = 'ok';

        return Response :: json($res);

    }


}