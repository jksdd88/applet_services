<?php
/**
 * 用户行为
 * @author 王禹
 * @package  App\Services;
 */
namespace App\Services;

use App\Models\MemberBehavior;
use App\Models\Goods;
use App\Models\KnowledgeContent;
use App\Models\KnowledgeColumn;
use App\Models\DistribPartner;
use App\Models\WeixinInfo;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;

class MemberBehaviorService
{
    /**
     * 行为采集
     * @author 王禹
     *
     */
    static function collection($member_id, $merchant_id, $weapp_id, $appid, $type, $type_id, $chat_group_id,
                               $distrib_member_id, $share_member_id)
    {
        $data['member_id'] = $member_id;
        $data['merchant_id'] = $merchant_id;
        $data['wxinfo_id'] = $weapp_id;
        $data['appid'] = $appid;
        $data['type'] = $type;
        $data['type_id'] = $type_id;
        $data['chat_group_id'] = $chat_group_id;

        //查询分享者是否是推客
        if($share_member_id){
            $share_is_partner = DistribPartner::get_data_by_memberid($share_member_id, $merchant_id);
            if ($share_is_partner && in_array($share_is_partner['status'], [1, 2])) {
                $distrib_member_id = $share_member_id;
            }
        }

        $data['distrib_member_id'] = $distrib_member_id;

        $wheres[] = array('column' => 'member_id', 'operator' => '=', 'value' => $member_id);
        $wheres[] = array('column' => 'merchant_id', 'operator' => '=', 'value' => $merchant_id);
        $wheres[] = array('column' => 'type', 'operator' => '=', 'value' => $type);

        switch ($type)
        {
            case 1:

                $cache_key  = CacheKey::member_visit_behavior($member_id, $merchant_id, $weapp_id,
                    date('Y-m-d').'_'.floor(date('H')/3));//三小时只算一次

                if(empty(Cache::get($cache_key)))
                {
                    Cache::put($cache_key, 1, 240);

                    //行为次数
                    (int)$count = MemberBehavior::get_count($wheres);

                    $wxinfo = WeixinInfo::get_one('id', $weapp_id, 1);
                    switch ($count)
                    {
                        case 0:
                            $behavior_content = ' <span>首次</span> 进入了'.$wxinfo['nick_name'].'小程序';
                            break;

                        default:
                            $behavior_content = ' <span>第'.($count+1).'次</span> 进入了'.$wxinfo['nick_name'].'小程序';
                            break;
                    }

                }
                else
                {
                    return;
                }

                break;

            case 2:
                $wxinfo = WeixinInfo::get_one('id', $weapp_id, 1);
                $behavior_content = ' <span>转发</span> 了'.$wxinfo['nick_name'].'小程序';
                break;

            case 3:
                if($chat_group_id){
                    $behavior_content = '正在<span id="gid">'.$chat_group_id.'</span>群 <span>查看</span> ';
                }else{
                    $behavior_content = '正在 <span>查看</span> ';
                }
                
                $good_info = Goods::get_data_by_id($type_id,$merchant_id);
                $behavior_content .= empty($good_info['title']) ? '商品' : $good_info['title'];

                break;

            case 4:
                if($chat_group_id){
                    $behavior_content = '正在<span id="gid">'.$chat_group_id.'</span>群 <span>查看</span> ';
                }else{
                    $behavior_content = '正在 <span>查看</span> ';
                }

                $knowledge_content_info = KnowledgeContent::get_data_by_id($type_id,$merchant_id);
                $behavior_content .= empty($knowledge_content_info['name']) ? '专栏' : $knowledge_content_info['name'];

                break;

            case 5:
                if($chat_group_id){
                    $behavior_content = '正在<span id="gid">'.$chat_group_id.'</span>群 <span>查看</span> ';
                }else{
                    $behavior_content = '正在 <span>查看</span> ';
                }

                $knowledge_column_info = KnowledgeColumn::get_data_by_id($type_id,$merchant_id);
                $behavior_content .= empty($knowledge_column_info['name']) ? '课程内容' : $knowledge_column_info['name'];

                break;

            case 6:

                $behavior_content = ' <span>转发</span> 了商品';

                $good_info = Goods::get_data_by_id($type_id,$merchant_id);
                $behavior_content .= empty($good_info['title']) ? '商品' : $good_info['title'];
                break;

            default:
                return;
        }

        $data['behavior_content'] = $behavior_content;

        MemberBehavior::insert_data($data);

        //消息模板
//        $behavior_content = preg_replace("/<(\/?span.*?)>/si","",$behavior_content);
//        (new WeixinWorkMsgService())->sendText($merchant_id,$clerk_info['userid'],$cler_member_info['name'].$behavior_content);
    }


}
