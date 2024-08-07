<?php
/**
 * Created by PhpStorm.
 * User: wangshiliang@dodoca.com
 * Desc: 小程序版本
 * link:https://open.weixin.qq.com/cgi-bin/showdocument?action=dir_list&t=resource/res_list&verify=1&id=open1506504150_nMMh6
 * Date: 2017/12/7
 * Time: 15:06
 */

namespace App\Utils\Weixin;

class AppletVersion extends Weixin
{

    public function __construct($componentToken)
    {
        parent::setAccessToken($componentToken);
    }


    /**
     * @name 获取草稿箱内的所有临时代码草稿
     * @return array  template_list : [ create_time:int开发者上传草稿时间;user_version:string模版版本号，开发者自定义字段;user_desc:sting 模版描述开发者自定义字段; draft_id:int 草稿id]
     */
    public function getTemplateDraftList(){
        return $this->mxCurl('/wxa/gettemplatedraftlist', [],false);
    }

    /**
     * @name 获取代码模版库中的所有小程序代码模版
     * @return array template_list: [ create_time:int 被添加为模版的时间 ; user_version:string 模版版本号，开发者自定义字段; user_desc:sting 模版描述 开发者自定义字段; template_id     	模版id]
     */
    public function getTemplateList(){
        return $this->mxCurl('/wxa/gettemplatelist', [],false);
    }

    /**
     * @name 将草稿箱的草稿选为小程序代码模版
     * @param  int $draft_id 草稿ID
     * @deprecated  85064	找不到草稿 85065	模版库已满
     * @return array
     */
    public function addToTemplate($draft_id){
        return $this->mxCurl('/wxa/addtotemplate', json_encode([ 'draft_id' => $draft_id ]));
    }

    /**
     * @name 获取代码模版库中的所有小程序代码模版
     * @param  int $template_id 草稿ID
     * @deprecated
     * @return  array
     */
    public function deleteTemplate($template_id){
        return $this->mxCurl('/wxa/deletetemplate', json_encode([ 'template_id' => $template_id ]));
    }


}