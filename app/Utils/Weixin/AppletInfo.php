<?php
/**
 * Created by PhpStorm.
 * User: wangshiliang@dodoca.com
 * Date: 2018/8/10
 * Time: 15:25
 * Desc: 小程序信息设置
 * Link:https://open.weixin.qq.com/cgi-bin/showdocument?action=dir_list&t=resource/res_list&verify=1&id=21528465979XX32V&token=&lang=zh_CN
 */

namespace App\Utils\Weixin;


class AppletInfo  extends Weixin
{

    public function __construct()
    {
    }

    /**
     * @name 独立账户获取access_token
     * @return array
     */
    public function getAccessToken($appid,$secret){
        return $this->mxCurl('/cgi-bin/token',['grant_type'=>'client_credential','appid'=>$appid,'secret'=>$secret],false);
    }

    /**
     * @name 获取帐号基本信息
     * @return array
     */
    public function getInfo(){
        return $this->mxCurl('/cgi-bin/account/getaccountbasicinfo',[],false);
    }

    /**
     * @name 修改搜索状态
     * @param int $status  1表示不可搜索，0表示可搜索
     * @return array
     */
    public function changeSearch($status){
        return $this->mxCurl('/wxa/changewxasearchstatus',json_encode(['status'=>$status]));
    }

    /**
     * @name 查看搜索状态
     * @return array
     */
    public function getSearch(){
        return $this->mxCurl('/wxa/getwxasearchstatus',[],false);
    }
}