<?php

namespace App\Http\Controllers\Super;

use App\Models\ApiDoc;
use App\Models\ApiMenu;
use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Session;

class OpenDocController extends Controller
{

    private $request;


    public function __construct(Request $request)
    {
        $this->request = $request;
    }


    /**
     * @anme 菜单
     *
    */
    public function menuList(){
        $is_api =  substr($this->request->path(),0,3);
        $did = $is_api == 'api' ? 1 : 0;
        $list = ApiMenu::getDataList($did);
        foreach ($list as $k => $v) {
            $list[$k]['children'] = ApiMenu::getDataList($did,$v['id']);
        }
        return Response::json(['errcode'=>0,'errmsg'=>'ok','data'=>$list]);
    }

    public function addMenu(){
        $pid = (int)$this->request->input('pid',0);
        $sort = (int)$this->request->input('sort',0);
        $title = $this->request->input('title','');
        if(empty($title)){
            return Response::json(['errcode'=>1,'errmsg'=>'请填写菜单名称哦']);
        }
        ApiMenu::insertData(['pid'=>$pid,'sort'=>$sort,'title'=>$title]);
        return Response::json(['errcode'=>0,'errmsg'=>'添加成功']);
    }

    public function delMenu(){
        $id = (int)$this->request->input('id',0);
        if($id < 1){
            return Response::json(['errcode'=>1,'errmsg'=>'ID有问题，请确认。']);
        }
        ApiMenu::updateData('id',$id,['is_delete'=>-1]);
        ApiDoc::updateData('mid',$id,['is_delete'=>-1]);
        return Response::json(['errcode'=>0,'errmsg'=>'删除成功']);
    }

    public function editMenu(){
        $id = (int)$this->request->input('id',0);
        $pid = (int)$this->request->input('pid');
        $sort = (int)$this->request->input('sort');
        $title = $this->request->input('title');
        if($id < 1){
            return Response::json(['errcode'=>1,'errmsg'=>'ID有问题，请检查']);
        }
        $data = [];
        if(isset($pid) && $pid >= 0){
            $data['pid'] = $pid;
        }
        if(isset($sort) && $sort >= 0){
            $data['sort'] = $sort;
        }
        if(isset($title) && !empty($title)){
            $data['title'] = $title;
        }
        if(empty($data)){
            return Response::json(['errcode'=>2,'errmsg'=>'更新内容不能为空哦']);
        }
        $check = ApiMenu::getDataOneId($id);
        if(!isset($check['id'])){
            return Response::json(['errcode'=>3,'errmsg'=>'ID不存在','id'=>$id]);
        }
        ApiMenu::updateData('id',$id,$data);
        return Response::json(['errcode'=>0,'errmsg'=>'修改成功']);
    }

    /**
     * @anme 文档
     *
     */
    public function docDetails(){
        $mid = (int)$this->request->get('mid',0);
        if($mid < 1){
            return Response::json(['errcode'=>1,'errmsg'=>'MID参数不能为空哦']);
        }
        $response =  ApiDoc::getDataOne('mid',$mid);
        return Response::json(['errcode'=>0,'errmsg'=>'ok','data'=>$response ]);

    }

    public function addDoc(){
        $mid = (int)$this->request->input('mid',0);
        $title = $this->request->input('title','');
        $text = $this->request->input('text','');
        if($mid < 0 || empty($title) || empty($text)){
            return Response::json(['errcode'=>1,'errmsg'=>'参数有误']);
        }
        $mcheck = ApiMenu::getDataOneId($mid);
        if(!isset($mcheck['id'])){
            return Response::json(['errcode'=>2,'errmsg'=>'菜单不存在' ]);
        }
        if($mcheck['pid'] == 0){
            return Response::json(['errcode'=>3,'errmsg'=>'请在二级菜单添加文档' ]);
        }
        $check = ApiDoc::getDataOne('mid',$mid);
        if(isset($check['id'])){
            ApiDoc::updateData('id',$check['id'],['mid'=>$mid,'title'=>$title,'text'=>$text]);
        }else{
            $check['id'] = ApiDoc::insertData(['mid'=>$mid,'title'=>$title,'text'=>$text]);
        }
        ApiMenu::updateData('id',$mid,['did'=>$check['id']]);
        ApiMenu::updateData('id',$mcheck['pid'],['did'=>1]);
        return Response::json(['errcode'=>0,'errmsg'=>'添加成功' ]);
    }

    public function delDoc(){
        $id = (int)$this->request->input('id',0);
        if($id < 0){
            return Response::json(['errcode'=>1,'errmsg'=>'ID不能为空']);
        }
        ApiDoc::updateData('id',$id,['is_delete'=>-1]);
        return Response::json(['errcode'=>0,'errmsg'=>'删除成功' ]);
    }

    public function editDoc(){
        $id = (int)$this->request->input('id',0);
        $title = $this->request->input('title');
        $text = $this->request->input('text');
        if($id < 1){
            return Response::json(['errcode'=>1,'errmsg'=>'ID不能为空哦']);
        }
        $data = [];
        if(isset($title) && !empty($title)){
            $data['title'] = $title;
        }
        if(isset($text) && !empty($text)){
            $data['text'] = $text;
        }
        if(empty($data)){
            return Response::json(['errcode'=>2,'errmsg'=>'更新内容不能为空哦']);
        }
        $check = ApiDoc::getDataOne('id',$id);
        if(!isset($check['id'])){
            return Response::json(['errcode'=>3,'errmsg'=>'ID有问题']);
        }
        ApiDoc::updateData('id',$check['id'],$data);
        return Response::json(['errcode'=>0,'errmsg'=>'修改成']);
    }

}
