<?php namespace App\Http\Controllers\Admin\User;

use App\Http\Controllers\Controller;
use App\Services\SystemMessageService;
use App\Services\SystemMessageReadService;
use Illuminate\Http\Request;

class SystemMessageController extends Controller
{
    public function __construct(SystemMessageService $systemMessage,SystemMessageReadService $systemMessageRead)
    {
        $this->systemMessage = $systemMessage;
        $this->systemMessageRead = $systemMessageRead;
    }

    public function getSystemMessage(Request $request){
        $data = $request->all();
        return $this->systemMessage->getSystemMsg($data);
    }

    public function readSystemMessage($id){
        return $this->systemMessageRead->addMessageRead($id);
    }

    public function getNotReadCount(){
        return $this->systemMessage->getNotReadCount();
    }

}
