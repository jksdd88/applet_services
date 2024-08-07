<?php

namespace App\Http\Controllers\Admin\Attachment;

use App\Http\Controllers\Controller;
use App\Services\AttachmentGroupService;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Response;

class AttachmentGroupController extends Controller{
    
    public function __construct(AttachmentGroupService $attachmentgroupservice){
        $this->service = $attachmentgroupservice;
    }

    public function getGroups(){
        $service_rt = $this->service->getGroups();
        return Response::json($service_rt);
    }

    public function postGroups(Request $request){
        $service_rt = $this->service->postGroups($request::All());
        return Response::json($service_rt);
    }

    public function putGroups(Request $request,$id){
        $service_rt = $this->service->putGroups($request,$id);
        return Response::json($service_rt);
    }

    public function deleteGroup($id){
        $service_rt = $this->service->deleteGroup($id);
        return Response::json($service_rt);
        
    }

    public function postSetGroup(Request $request){
        $service_rt = $this->service->postSetGroup($request);
        return Response::json($service_rt);
    }

    
}
?>