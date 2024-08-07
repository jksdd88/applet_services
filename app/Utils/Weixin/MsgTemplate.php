<?php
/**
 * Created by PhpStorm.
 * User: wangshiliang@dodoca.com
 * Desc: 消息模板
 * link: https://mp.weixin.qq.com/debug/wxadoc/dev/api/notice.html
 * Date: 2017/10/19
 * Time: 16:57
 */

namespace App\Utils\Weixin;


class MsgTemplate
{

    const WEIXIN_API_CGI = 'https://api.weixin.qq.com/cgi-bin/';

    protected $access_token;

    public $http_response;

    public function __construct()
    {

    }

    public function setAccessToken($access_token){
        $this->access_token = $access_token;
        return $this;
    }

    /**
     * @name 获取小程序模板库标题列表
     * @param  $offset int 分页起始
     * @param  $count int  每页数量
     * @return array  { "errcode":0,"errmsg":"ok","list":[{"id":"AT0002","title":"购买成功通知"},],"total_count":599}
     */
    public function libraryTemplate($offset, $count=20){
        return $this->mxCurl('wxopen/template/library/list',['offset'=>$offset,'count'=>$count]);
    }

    /**
     * @name 获取模板库某个模板标题下关键词库
     * @param  $id string  模板id
     * @return array { "errcode":0,"errmsg":"ok","id": "AT0002","title": "购买成功通知","keyword_list":[{"keyword_id": 3,"name": "购买地点","example": "TIT造舰厂"},]}
     */
    public function getTemplate($id){
        return $this->mxCurl('wxopen/template/library/get',['id'=>$id]);
    }

    /**
     * @name 组合模板并添加至帐号下的个人模板库
     * @param $id string  模板id
     * @param $keyword_id_list array 模板关键词id
     * @return array { "errcode":0,"errmsg":"ok","template_id": "wDYzYZVxobJivW9oMpSCpuvACOfJXQIoKUm0PY397Tc"}
     */
    public function addTemplate($id,$keyword_id_list){
        return $this->mxCurl('wxopen/template/add',['id'=>$id,'keyword_id_list'=>$keyword_id_list]);
    }

    /**
     * @name 获取帐号下已存在的模板列表
     * @param  $offset int 分页起始
     * @param  $count int  每页数量
     * @return array { "errcode":0,"errmsg":"ok","list": [ "template_id": "wDYzYZVxobJivW9oMpSCpuvACOfJXQIoKUm0PY397Tc","title": "购买成功通知","content": "购买地点{{keyword1.DATA}}\n购买时间{{keyword2.DATA}}\n物品名称{{keyword3.DATA}}\n","example": "购买地点：TIT造舰厂\n购买时间：2016年6月6日\n物品名称：咖啡\n"]}
     */
    public function listTemplate($offset,$count){
        return $this->mxCurl('wxopen/template/list',['offset'=>$offset,'count'=>$count]);
    }

    /**
     * @name 删除帐号下的某个模板
     * @param $template_id string 模板id
     * @return array  { "errcode":0,"errmsg":"ok"}
     */
    public function delTemplate($template_id){
        return $this->mxCurl('wxopen/template/del',['template_id'=>$template_id]);
    }

    /**
     * @name 发送模板消息
     * @param $openid string 接收者（用户）的 openid
     * @param $template_id string 所需下发的模板消息的id
     * @param $form_id int  表单提交场景下，为 submit 事件带上的 formId；支付场景下，为本次支付的 prepay_id
     * @param $data array 模板内容，不填则下发空模板
     * @param $page string 点击模板卡片后的跳转页面，仅限本小程序内的页面。支持带参数,（示例index?foo=bar）。该字段不填则模板无跳转。
     * @param $color string 模板内容字体的颜色，不填默认黑色 {"keyword1": {"value": "339208499","color": "#173177"}}
     * @param $emphasis_keyword string 模板需要放大的关键词，不填则默认无放大 "emphasis_keyword": "keyword1.DATA"
     * @return array { "errcode":0,"errmsg":"ok"}
     */
    public function sendMsg($openid,$template_id,$form_id,$data,$page='',$color='',$emphasis_keyword=''){//https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=ACCESS_TOKEN
        return $this->mxCurl('message/wxopen/template/send',['touser'=>$openid,'template_id'=>$template_id,'form_id'=>$form_id,'data'=>$data,'page'=>$page]);
    }

    private function mxCurl($url , $data , $is_post = true){
        $response = ( new Http())->mxCurl(static::WEIXIN_API_CGI.$url.'?access_token='.$this->access_token,json_encode($data),$is_post );
        $this->http_response = json_encode($response);
        if($response['errcode'] == 0 ){
            return  json_decode($response['data'],true) ;
        }else{
            return $response;
        }
    }
}