<?php
/**
 * Created by PhpStorm.
 * User: wangshiliang@dodoca.com
 * Desc: 公众账号模板消息
 * link: https://mp.weixin.qq.com/wiki?t=resource/res_main&id=mp1433751277
 * Date: 2018/1/3
 * Time: 20:11
 */

namespace App\Utils\Weixin;


class OfficialMsgTemplate
{
    const WEIXIN_API_CGI = 'https://api.weixin.qq.com/cgi-bin';
    private $access_token ;

    public function __construct()
    {
    }

    /**
     * @name 配置 $access_token
     * @return void
     */
    public function setConfig($access_token){
        $this->access_token = $access_token;
    }

    /**
     * @name 设置所属行业
     * @param $industry_id1 int 公众号模板消息所属行业编号
     * @param $industry_id2 int 公众号模板消息所属行业编号
     * @link  行业ID字典  https://mp.weixin.qq.com/wiki?t=resource/res_main&id=mp1433751277
     * @return array  ["errcode" =>"" ,"errmsg"=>'"]
     */
    public function setIndustry($industry_id1 = 1 ,$industry_id2 = 2){
        return $this->mxCurl('/template/api_set_industry', json_encode(['industry_id1'=>$industry_id1,'industry_id2'=>$industry_id2]));
    }

    /**
     * @name 获取设置的行业信息
     * @return array  ["primary_industry"=>["first_class"=>"IT科技","second_class"=>"互联网|电子商务"], ,"secondary_industry"=>["first_class"=>"IT科技","second_class"=>"IT软件与服务"]]
     */
    public function getIndustry(){
        return $this->mxCurl('/template/get_industry', [], false);
    }

    /**
     * @name 生成模板ID
     * @param $template_no string 模板编号
     * @return array  ["errcode":0,"errmsg":"ok","template_id":""]
     */
    public function addTemplate($template_no){
        return $this->mxCurl('/template/api_add_template', json_encode(['template_id_short'=>$template_no]));
    }

    /**
     * @name 模板列表
     * @return array  ["template_list":[{"template_id":"模板ID","title":"模板标题","primary_industry":"模板所属行业的一级行业","deputy_industry":"模板所属行业的二级行业","content":"模板内容","example":"模板示例"}],]
     */
    public function listTemplate(){
        return $this->mxCurl('/template/get_all_private_template', [],false);
    }

    /**
     * @name 删除模板
     * @param $template_id  string 模板编号
     * @return array  ["errcode":0,"errmsg":"ok"]
     */
    public function delTemplate($template_id){
        return $this->mxCurl('/template/del_private_template', json_encode(['template_id'=>$template_id]));
    }

    /**
     * @name 删除模板
     * @param $openid string openid
     * @param $template_id  string 模板编号
     * @param $data  array 内容
     * @param $url string 链接
     * @param $appid string 小程序 appid
     * @param $pagepath string 小程序 地址
     * @return array  ["errcode":0,"errmsg":"ok","msgid":200228332]
     */
    public function sendMsg($openid,$template_id,$data,$url = '',$appid = '',$pagepath = ''){
        foreach ($data as $k => $v) {
            $data[$k] = ['value'=>$v,'color'=>'#173177'];
        }
        $content = ['touser'=>$openid,'template_id'=>$template_id,'url'=>$url,'data'=>$data];
        if(!empty($appid) && !empty($pagepath)){
            $content['miniprogram'] = ['appid'=>$appid, 'pagepath'=>$pagepath];
        }
        return $this->mxCurl('/message/template/send', json_encode($content));
    }



    private function mxCurl($url , $data , $is_post = true){
        $url = static::WEIXIN_API_CGI.$url.'?access_token='. $this->access_token;
        $response = ( new Http())->mxCurl($url,$data,$is_post);
        $this->http_response = json_encode($response);
        if($response['errcode'] == 0 ){
            return json_decode($response['data'],true);
        }else{
            return $response;
        }
    }
}