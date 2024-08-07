<?php

namespace App\Http\Controllers\Super;

use App\Http\Controllers\Controller;
use App\Services\SuperAttachmentGroupService;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Response;

class AttachmentGroupController extends Controller{
    
    public function __construct(SuperAttachmentGroupService $superattachmentgroupservice){
        $this->service = $superattachmentgroupservice;
    }

    /**
    *@author renruiqi@qq.com
    *@param pid int 可选 父级分类id 默认0
    *@param type int 可选 类型   0普通1素材默认0
    */
    public function getGroups(Request $request){
        $service_rt = $this->service->getGroups($request::all());
        return Response::json($service_rt);
    }

    public function postGroups(Request $request){
        $service_rt = $this->service->postGroups($request::All());
        return Response::json($service_rt);
    }

    public function deleteGroup($id){
        $service_rt = $this->service->deleteGroup($id);
        return Response::json($service_rt);
        
    }




    //修改分组
    public function putGroups(Request $request){
        $service_rt = $this->service->postGroups($request::All());
        // $service_rt = $this->service->putGroups($request::all(),(int)$id);
        return Response::json($service_rt);
    }






    public function postSetGroup(Request $request){
        $service_rt = $this->service->postSetGroup($request::all());
        return Response::json($service_rt);
    }

}
?>