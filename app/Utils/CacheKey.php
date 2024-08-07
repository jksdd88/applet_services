<?php
/**
 * @ 获取Cache键值，防止KEY名称冲突
 * @ time 2017-08-29
 */

namespace App\Utils;

class CacheKey
{

    static $prefix = 'cache_apple';

    //demo 获取用户id
    public static function get_demo_by_id_key($id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $id;
    }

    //通过ID获取商户信息
    public static function get_merchant_by_id_key($id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $id;
    }

    //demo 获取用户 by username
    public static function get_user_by_np_key($username, $password)
    {
        return self::$prefix . __FUNCTION__ . '_' . $username . '_' . $password;
    }


    //user
    public static function get_user_by_id($user_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $user_id;
    }

    //user_merchant
    public static function get_merchantuser_by_merchantid($user_id, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $user_id . '_' . $merchant_id;
    }

    //weixin
    public static function get_weixin_models($id, $table)
    {
        return self::$prefix . __FUNCTION__ . '_' . $table . '_' . $id;
    }

    public static function get_weixin_cache($key, $val = 'value')
    {
        return self::$prefix . __FUNCTION__ . '_' . $key . '_' . $val;
    }

    //
    public static function get_live_cache($key, $val = 'value')
    {
        return self::$prefix . __FUNCTION__ . '_' . $key . '_' . $val;
    }

    //通过ID获取一条优惠劵
    public static function get_coupon_by_id_key($id, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $id . '_' . $merchant_id;
    }

    //通过ID获取一条优惠劵
    public static function get_coupon_by_id($id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $id;
    }

    //获取一条优惠码
    public static function get_coupon_code_by_id_key($id, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $id . '_' . $merchant_id;
    }

    //优惠劵库存
    public static function get_coupon_stock_key($id, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $id . '_' . $merchant_id;
    }

    //通过ID获取一条买家信息
    public static function get_member_by_id_key($id, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $id . '_' . $merchant_id;
    }

    //通过ID获取一条商品信息
    public static function get_good_by_id_key($id, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $id . '_' . $merchant_id;
    }

    //购物车
    public static function get_cart_by_member_key($member_id, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $member_id . '_' . $merchant_id;
    }

    //购物车缓存数组数据的key
    public static function get_cart_by_goods_key($goods_id, $goods_sepc_id)
    {
        return 'cart_' . $goods_id . '_' . $goods_sepc_id;
    }

    //通过ID获取一条规格信息
    public static function get_goodspec_by_id_key($id, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $id . '_' . $merchant_id;
    }

    //通过商品ID获取所有商品规格信息
    public static function get_goodspec_by_goodsid_key($id, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $id . '_' . $merchant_id;
    }

    //通过商品id获取商品属性规格
    public static function get_data_by_goods_key($id, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $id . '_' . $merchant_id;
    }

    //通过商品id获取预约商品扩展信息
    public static function get_goodsappt_by_goodsid_key($id, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $id . '_' . $merchant_id;
    }

    //通过ID获取商品图片
    public static function get_goodimg_by_id_key($id, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $id . '_' . $merchant_id;
    }

    //获取商品库存key,$goods_id商品id，$goods_spec_id规格id,$date 预约商品日期2017-09-11(Y-m-d时间戳)
    public static function get_goods_stock_key($goods_id, $goods_spec_id = 0, $date = 0)
    {
//        if (!empty($goods_spec_id)) $goods_id = 0;
        return 'goods_stock_' . $goods_id . '_' . $goods_spec_id . '_' . $date;
    }

    //预约商品默认规格名称
    public static function getPropIdByName($prop_key, $goods_cat_id, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_appt' . $prop_key . '_' . $goods_cat_id . '_' . $merchant_id;
    }

    //获取一条拼团活动信息
    public static function get_fightgroup_by_id_key($id, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $id . '_' . $merchant_id;
    }

    //获取一条拼团阶梯信息
    public static function get_fightgroup_ladder_by_id_key($id, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $id . '_' . $merchant_id;
    }

    //获取一条拼团发起表信息
    public static function get_fightgroup_launch_by_id_key($id, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $id . '_' . $merchant_id;
    }

    //获取一条拼团子表信息
    public static function get_fightgroup_item_by_id_key($id, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $id . '_' . $merchant_id;
    }

    //获取拼团活动 普通/规格库存    库存key
    public static function get_fightgroup_stock_key($id, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $id . '_' . $merchant_id;
    }

    //获取拼团活动 发起的团   可用名额key
    public static function get_fightgroup_launch_numsless_key($id, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $id . '_' . $merchant_id;
    }

    //获取拼团库存表主键id
    public static function get_fightgroup_stock_id_by_ids_key($merchant_id, $fightgroup_id, $goods_id, $spec_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $merchant_id . '_' . $fightgroup_id . '_' . $goods_id . '_' . $spec_id;
    }

    //获取一条优惠劵可用商品信息
    public static function get_coupon_goods($merchant_id, $coupon_id, $goods_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $merchant_id . '_' . $coupon_id . '_' . $goods_id;
    }

    //通过id获取一条运费模板信息
    public static function get_shipment_byid_key($id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $id;
    }

    //通过id获取一条运费区域信息
    public static function get_shipment_area_key($id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $id;
    }

    //通过id获取一条运费区域地区关联数据
    public static function get_shipment_area_region_key($id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $id;
    }

    //通过运费模板id 获取运费区域信息
    public static function get_shipment_area_list_key($shipment_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $shipment_id;
    }

    //通过运费区域id 获取运费区域地区关联数据
    public static function get_shipment_region_list_key($shipment_area_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $shipment_area_id;
    }

    public static function get_member_card_by_id_key($id, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $id . '_' . $merchant_id;
    }

    //个人中心通过手机号获取验证码
    public static function get_member_sms_by_mobile_key($mobile, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $mobile . '_' . $merchant_id;
    }

    //发送验证码60秒限制缓存
    public static function get_member_sms_sixty_by_mobile_key($mobile, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $mobile . '_' . $merchant_id;
    }

    //通过商品id 获取商品配送区域排除数据
    public static function get_goods_region_exclude_bygoodsid($goods_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $goods_id;
    }

    //查询日历 星期 法定节假日
    public static function get_search_calendar_key($date)
    {
        return self::$prefix . __FUNCTION__ . '_' . $date;
    }

    //查询服务工作人员
    public static function get_apptstaff_by_id_key($id, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $id . '_' . $merchant_id;
    }

    //秒杀
    public static function get_seckill_by_id_key($id, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $id . '_' . $merchant_id;
    }

    //查询会员趋势
    public static function get_member_daily_by_id_key($day, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $day . '_' . $merchant_id;
    }

    //查询订单趋势
    public static function get_order_daily_by_id_key($day, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $day . '_' . $merchant_id;
    }

    //交易趋势
    public static function get_trade_daily_by_id_key($day, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $day . '_' . $merchant_id;
    }

    //订单商品评论
    public static function get_order_comment_by_id_key($id, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $id . '_' . $merchant_id;
    }

    //小程序端发送图片验证码
    public static function get_member_captcha_key($merchant_id, $member_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $merchant_id . '_' . $member_id;
    }

    //商品库存缓存标签
    public static function get_tags_goods_stock($goods_id, $goods_spec_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $goods_id . '_' . $goods_spec_id;
    }

    //获取物流信息
    public static function get_logistic_ket($logis_code, $logis_no)
    {
        return self::$prefix . __FUNCTION__ . '_' . $logis_code . '_' . $logis_no;
    }

    //获取全国行政数据
    public static function get_region_key($region_key)
    {
        return self::$prefix . __FUNCTION__ . '_' . $region_key;
    }

    //获取订单到处excel时间限定值
    public static function get_order_export_time($merchant_id)
    {
        return 'order_export_task_' . $merchant_id;

    }
//--------权限 start-----------------------------
    //获取管理员的权限缓存
    public static function get_user_privs_key($merchant_id, $uid)
    {
        return self::$prefix . __FUNCTION__ . '_' . $merchant_id . '_' . $uid;
    }

    //通过priv.code获取priv.id
    public static function get_PrivId_by_PrivCode($priv_code)
    {
        return self::$prefix . __FUNCTION__ . '_' . $priv_code;
    }

    //通过priv.id获取priv.code
    public static function get_PrivCode_by_PrivId($priv_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $priv_id;
    }

    //role_id对应的priv_id数组
    public static function get_RolePriv_by_RoleId($role_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $role_id;
    }

    //user_id对应的role_id数组
    public static function get_UserRole_by_UserId($user_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $user_id;
    }

    //版本拥有的权限缓存
    public static function get_VersionPriv_by_VersionId($version_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $version_id;
    }

    //获取用户权限
    public static function get_UserPriv_by_UserId($uid, $version_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $version_id;
    }

    //获取用户权限+用户角色权限
    public static function get_UserprivRolePriv_by_Id($user_id, $version_id)
    {
        return self::$prefix . __FUNCTION__ . 'user_privs_' . $user_id . '_' . $version_id;
    }

    //获取priv
    public static function get_priv_by_id($priv_id)
    {
        return self::$prefix . __FUNCTION__ . 'priv_' . $priv_id;
    }
//--------权限 end-----------------------------
    //修改手机号缓存
    public static function get_verify_mobile_message_key($uid)
    {
        return self::$prefix . __FUNCTION__ . '_' . $uid;
    }

    //商户登录缓存
    public static function get_merchant_login_key($merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $merchant_id;
    }

    //获取省市区
    public static function get_region_list_by_parentid_key($parent_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $parent_id;
    }

    //获取区域名称
    public static function get_region_title_by_id_key($id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $id;
    }

    //获取商户配置表
    public static function merchant_setting($merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $merchant_id;
    }

    //获取一条门店信息
    public static function get_store_by_id_key($id, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $id . '_' . $merchant_id;
    }

    //获取行业分类 
    public static function get_industry_key($industry_key)
    {
        return self::$prefix . __FUNCTION__ . '_' . $industry_key;
    }

    //获取行业分类
    public static function get_industry_list_by_parentid_key($parent_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $parent_id;
    }

    //获取行业分类 
    public static function get_industry_title_by_id_key($id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $id;
    }

    //获取一条文章记录
    public static function get_article_by_id_key($id, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $id . '_' . $merchant_id;
    }

    //获取商城信息
    public static function get_shop_by_merchant_id_key($merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $merchant_id;
    }

    //待付款订单消息模板发送状态
    public static function wait_pay_msg($id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $id;
    }

    //发送消息模板
    public static function wait_apptnotice_msg($id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $id;
    }


    //超级表单
    public static function get_forminfo_by_id_key($id, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $id . '_' . $merchant_id;
    }

    //超级表单
    public static function get_formfeedback_by_id_key($id, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $id . '_' . $merchant_id;
    }

    //根据超级表单id查询分组名称->ren
    public static function get_formcate_by_formid_key($formid, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $formid . '_' . $merchant_id;
    }

    //获取公告版本号
    public static function get_announce_by_id_key()
    {
        return self::$prefix . __FUNCTION__ . '_announce';
    }


    //小程序端注册小程序图片验证码key
    public static function get_register_member_captcha_key($merchant_id, $random)
    {
        return self::$prefix . __FUNCTION__ . '_' . $merchant_id . '_' . $random;
    }

    //小程序端注册小程序短信验证码key
    public static function get_register_member_sms_by_mobile_key($mobile, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $mobile . '_' . $merchant_id;
    }


    /*****************  分销Start  *****************/
    //通过member_id获取推客信息
    public static function get_distrib_partner_byid_key($member_id, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $member_id . '_' . $merchant_id;
    }

    //通过merchant_id获取推客信息
    public static function get_distrib_setting_by_merchant_id_key($merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $merchant_id;
    }

    //通过order_id获取distrib_order_goods
    public static function get_distrib_order_goods_list_key($order_id, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $order_id . '_' . $merchant_id;
    }

    //通过order_id获取distrib_order
    public static function get_distrib_order_key($order_id, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $order_id . '_' . $merchant_id;
    }

    //通过order_id获取distrib_order_goods
    public static function get_distrib_order_goods_data_key($order_id, $merchant_id, $goods_id, $spec_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $order_id . '_' . $merchant_id . '_' . $goods_id . '_' . $spec_id;
    }

    //通过order_id获取distrib_order_detail
    public static function get_distrib_order_detail_list_key($order_id, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $order_id . '_' . $merchant_id;
    }

    //通过order_id获取distrib_order_detail
    public static function get_distrib_order_detail_key($order_id, $member_id, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $order_id . '_' . $member_id . '_' . $merchant_id;
    }

    //通过member_id获取与推客关系
    public static function get_distrib_buyer_relation_byid_key($member_id, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $member_id . '_' . $merchant_id;
    }

    //获取一条推广活动信息
    public static function get_distrib_activity_by_id_key($id, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $id . '_' . $merchant_id;
    }
    //通过member_id获取与推客关系
    public static function get_distrib_member_first_record_key($member_id, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $member_id . '_' . $merchant_id;
    }

    /*****************  分销End  *****************/

    //角色拥有的权限缓存
    public static function get_SuperRolePriv_by_RoleId($super_role_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $super_role_id;
    }

    //获取一条新用户有礼活动
    public static function get_newusergift_by_id_key($id, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $id . '_' . $merchant_id;
    }


    //意见反馈
    public static function get_feedback_by_id($id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $id;
    }

    //节日营销主表
    public static function get_holiday_marketing_by_id($id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $id;
    }

    //节日营销--标签表
    public static function get_holiday_marketing_tag_by_id($id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $id;
    }

    //节日营销--设置营销活动
    public static function get_holiday_marketing_activity_by_id($id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $id;
    }

    //节日营销--商家活动管理
    public static function get_holiday_marketing_merchant_by_id($id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $id;
    }

    //抓娃娃tag
    public static function get_toy_tag_by_merchant_id_id_key($merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $merchant_id;
    }

    //抓娃娃金额
    public static function get_toy_money_by_member_id_key($merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $merchant_id;
    }

    //抓娃娃今日抓去次数
    public static function get_toy_grab_today_times_by_id_key($member_id, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $member_id . '_' . $merchant_id . '_' . date('Y-m-d');
    }

    //抓娃娃频率限制
    public static function get_toy_grab_limits_by_member_id_key($member_id, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $member_id . '_' . $merchant_id . '_' . date('Y-m-d H:i:s');
    }

    //兑换娃娃频率限制
    public static function get_toy_exchange_limits_by_member_id_key($member_id, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $member_id . '_' . $merchant_id;
    }

    //抓娃娃助力列表
    public static function get_toy_assist_list_by_member_id_key($member_id, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $member_id . '_' . $merchant_id;
    }

    //抓娃娃兑换记录列表
    public static function get_toy_exchange_list_by_member_id_key($member_id, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $member_id . '_' . $merchant_id;
    }

    //抓娃娃分享助力小程序码
    public static function get_toy_wxacode_by_member_id_key($member_id, $merchant_id, $appid, $scene)
    {
        return self::$prefix . __FUNCTION__ . '_' . $member_id . '_' . $merchant_id . '_' . $appid . '_' . $scene;
    }


    //年会投票:投票主题
    public static function get_vote_by_vote_id($vote_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $vote_id;
    }

    //年会投票:投票选项
    public static function get_vote_detail_by_vote_detail_id($vote_id, $vote_detail_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $vote_id . '_' . $vote_detail_id;
    }

    //年会投票:商户下的所有投票选项
    public static function get_all_vote_details_by_vote_id($vote_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $vote_id;
    }

    //年会投票:会员投票详情
    public static function get_vote_member_by_vote_id($vote_id, $member_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $vote_id . '_' . $member_id;
    }

    //意见反馈：后台回复
    public static function get_feedback_reply_by_id_key($id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $id;
    }

    //新注册的50个 1.上海地区 2.免费版商家 开通客服
    public static function get_cache_data_of_custserviceWithFree()
    {
        return self::$prefix . __FUNCTION__;
    }
    
    
    //通过商品id获取虚拟商品扩展信息
    public static function get_goods_virtual_by_goodsid_key($id, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $id . '_' . $merchant_id;
    }
    

    //提现频率限制
    public static function get_withdraw_limits_by_member_id_key($member_id, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $member_id . '_' . $merchant_id . '_' . date('Y-m-d H:i:s');
    }

    //知识收费->根据内容id获取信息
    public static function get_k_content_by_id( $id,$merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' .$id. '_' . $merchant_id ;
    }
    //知识收费->根据专栏获取信息
    public static function get_k_column_by_id( $id,$merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' .$id. '_' . $merchant_id ;
    }

    
    //通过小程序id获取小程序设置信息
    public static function get_weixin_setting_by_infoid_key($id, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $id . '_' . $merchant_id;
    }

    //根据小程序ID获取分享卡片设置
    public static function get_sharedata_by_wxinfoid($merchant_id, $wxinfo_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $merchant_id . '_' . $wxinfo_id;
    }

    //自定义分享卡片缓存
    public static function share_card_custom_key($merchant_id, $wxinfo_id)
    {
        return self::$prefix . __FUNCTION__ . '_imagick_' . $merchant_id . '_' . $wxinfo_id;
    }

    //商品分享卡片缓存
    public static function share_card_goods_key($goods_id, $merchant_id, $alone_id)
    {
        return self::$prefix . __FUNCTION__ . '_imagick_' . $goods_id . '_' . $merchant_id . '_' . $alone_id;
    }

    //推广二维码卡片缓存
    public static function distrib_qrcode_card_key($merchant_id, $weapp_id, $member_id, $type)
    {
        return self::$prefix . __FUNCTION__ . '_imagick_' . $merchant_id . '_' . $weapp_id .'_'. $member_id . '_' . $type;
    }

    //推广素材缓存
    public static function distrib_poster_card_key($activity_id, $merchant_id, $weapp_id, $member_id)
    {
        return self::$prefix . __FUNCTION__ . '_imagick_' . $activity_id . '_' . $merchant_id . '_' . $weapp_id . '_' . $member_id;
    }


    //通过ID获取一条砍价活动信息
    public static function get_bargain_by_id_key($id, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $id . '_' . $merchant_id;
    }

    //通过ID获取一条直播信息
    public static function get_live_by_id_key($id, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $id . '_' . $merchant_id;
    }

	//通过直播id获取关联商品
	public static function get_live_goods_by_id_key($id, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $id . '_' . $merchant_id;
    }
	
    //通过ID获取一条直播观众信息
    public static function get_live_viewer_by_id($member_id, $live_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $member_id . '_' . $live_id;
    }
	
	//会员观看录播状态
    public static function live_viewer_by_memer_id($member_id, $live_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $member_id . '_' . $live_id;
    }
	
	//会员点赞数量
    public static function live_praise($live_id)
    {
        return self::$prefix . __FUNCTION__  . '_' . $live_id;
    }
	
	//会员点赞标记
    public static function live_viewer_praise($member_id, $live_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $member_id . '_' . $live_id;
    }
	
	//购买动态
    public static function buy_msg($type)
    {
        return self::$prefix . __FUNCTION__ . '_' . $type;
    }

    //记录直播在线人数
    public static function live_online_number($live_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $live_id;
    }
    //记录进入直播间的会员
    public static function live_online_member($live_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $live_id;
    }
    //记录录播在线人数
    public static function record_online_number($record_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $record_id;
    }
    //记录观看录播的会员
    public static function record_online_member($record_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $record_id;
    }
	
    //录播观众表
    public static function get_live_record_viewer_by_id($member_id, $record_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $member_id.'_'.$record_id;
    }

    //通过商品id获取商品分销信息
    public static function get_distrib_goods_by_id_key($id, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $id . '_' . $merchant_id;
    }

    //开发接口open api
    public static function get_open_api($key,$type = 'openApi'){
        return self::$prefix . __FUNCTION__ . '_' . $key . '_' . $type;
    }
    
    //通过商户id获取推客码设置
    public static function get_distrib_code_by_merchantid_key($merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $merchant_id;
    }
	
	//获取包裹字表（商品）
	public static function get_goods_by_package_id($package_id, $order_id) {
		return self::$prefix . __FUNCTION__ . '_' . $package_id.'_'.$order_id;
	}
	
	//物流公司
	public static function get_delivery_by_id($id) {
		return self::$prefix . __FUNCTION__ . '_' . $id;
	}



	//达达物流
    public static function get_dada_express($key,$type = 'dada'){
        return self::$prefix . __FUNCTION__ . '_' . $type. '_' . $key;
    }
    
    //广告投放推广计划
    public static function get_campaign_by_id_key($id, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $id . '_' . $merchant_id;
    }
    
    //广告投放创意
    public static function get_campaign_adcreative_by_campaign_id_key($campaign_id, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $campaign_id . '_' . $merchant_id;
    }
    
    //广告内容/广告组表
    public static function get_campaign_adgroup_by_campaign_id_key($id, $merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $id . '_' . $merchant_id;
    }
    
    //广告资质提交/广告账号
    public static function get_campaign_advertiser_by_id_key($merchant_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $merchant_id;
    }
    
    //广告微信
    public static function get_campaign_wx_industry_by_wx_id_key($wx_industry_id)
    {
        return self::$prefix . __FUNCTION__ . '_' . $wx_industry_id;
    }

	//优化权限
	public static function get_all_priv() {
	    return self::$prefix . __FUNCTION__ . '_priv';
	}

    //直播卡片
    public static function live_qrcode_card_key($merchant_id, $weapp_id, $member_id, $type,$live_id)
    {
        return self::$prefix . __FUNCTION__ . '_live_imagick_' . $merchant_id . '_' . $weapp_id .'_'. $member_id . '_' . $type. '_' . $live_id;
    }

    //用户访问小程序周期
    public static function member_visit_behavior($member_id, $merchant_id, $weapp_id, $date_flag)
    {
        return self::$prefix . __FUNCTION__ . '_' . $member_id . '_' . $merchant_id .'_'. $weapp_id . '_' . $date_flag;
    }
}