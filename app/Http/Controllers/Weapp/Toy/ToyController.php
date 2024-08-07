<?php

namespace App\Http\Controllers\Weapp\Toy;

use App\Models\ToyAssist;
use App\Models\WeixinInfo;
use App\Services\WeixinService;
use App\Utils\CacheKey;
use App\Utils\CommonApi;
use App\Utils\Encrypt;
use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Models\ToyGrabLog;
use App\Models\ToyMember;
use App\Models\ToyExchange;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\Region;
use App\Facades\Member;
use App\Services\ToyService;
use Illuminate\Support\Facades\Redis;

class ToyController extends Controller
{

    public function __construct(ToyService $toyService)
    {
        if (app()->isLocal()) {
            $this->merchant_id = 2;
            $this->member_id = 1;
        } else {
            $this->merchant_id = Member::merchant_id();
            $this->member_id = Member::id();
        }
        $this->toyService = $toyService;
    }

    /**
     * 兑换奖品
     *
     * @param int $merchant_id 商户id
     * @param int $member_id 会员id
     * @param int $type 1->三虚拟兑换一，2->六虚拟兑换一，3->九虚拟兑换一
     * @author denghongmei@dodoca.com
     *
     * @return \Illuminate\Http\Response
     */
    public function postExchange(Request $request)
    {
        if(date('Y-m-d H:i:s') >= '2018-03-15 00:00:00'){
            return ['errcode' => 1, 'errmsg' => '活动已结束'];
        }
        $key = CacheKey::get_toy_grab_limits_by_member_id_key($this->member_id, $this->merchant_id);

        if (!Redis::set($key, '1', 'EX', 3, 'NX')) {//限制每个会员每3秒访问一次
            return ['errcode' => 1, 'errmsg' => '兑换奖品过于频繁，请稍后重试。'];
        }
        $params = $request->all();
        $merchant_id = $this->merchant_id;
        $member_id = $this->member_id;
        $type = isset($params['type']) ? intval($params['type']) : 0;
        if (!$merchant_id) {
            return ['errcode' => 99001, 'errmsg' => '商户ID不存在'];
        }

        if (!$member_id) {
            return ['errcode' => 99001, 'errmsg' => '会员ID不存在'];
        }

        if (!$type) {
            return ['errcode' => 99001, 'errmsg' => '兑换类型不能为空'];
        }

        //判断是否可兑换
        $exp = ['member_id' => $member_id, 'type' => $type];
        $ifExchange = $this->toyService->ifExchange($exp, $merchant_id);

        if ($ifExchange) {
            $handleinfo = $this->handleinfo($merchant_id, $member_id, $params);
            if ($handleinfo['errcode'] == 0) {
                $create = $handleinfo['data'];
            } else {
                return $handleinfo;
            }
            //查询抓到的且未兑换的娃娃数量
            $toy_member_res = ToyMember::get_data_by_id($this->member_id, $this->merchant_id);
            //兑换规则
            $rule = [1 => 3, 2 => 6, 3 => 9, 4 => 1];
            //更新抓到的且未兑换的娃娃数量
            $update_data = [];
//            $update_data['toy_qty'] = intval($toy_member_res['toy_qty'] - $rule[$type]);
            $update_data['toy_qty'] = DB::raw('toy_qty - ' . $rule[$type]);

            if ($toy_member_res['toy_qty'] - $rule[$type] < 0) {
                return ['errcode' => 0, 'errmsg' => '拥有娃娃数量(抓到的且未兑换的)不能小于0'];
            }
            //事物控制  防止数据异常
            DB::connection('applet_cust')->beginTransaction();
            try {
                $member_update = ToyMember::update_data($toy_member_res['id'], $this->merchant_id, $update_data, false, true);
                if (empty($member_update)) throw new \Exception('toy_member更新数据失败，数据：' . json_encode($update_data, JSON_UNESCAPED_UNICODE));
                $exchange_insert = ToyExchange::insert_data($create);
                if (empty($exchange_insert)) throw new \Exception('exchange_insert表插入数据失败，数据：' . json_encode($create, JSON_UNESCAPED_UNICODE));
                DB::connection('applet_cust')->commit();
            } catch (\Exception $e) {
                DB::connection('applet_cust')->rollBack();
                //记录异常
                $except = [
                    'activity_id' => $toy_member_res['id'],
                    'data_type' => 'toy_postExchange',
                    'content' => '抓娃娃兑换奖品数据更新异常。line:' . $e->getLine() . ',msg:' . $e->getMessage(),
                ];
                CommonApi::errlog($except);
                return ['errcode' => -1, 'errmsg' => '兑换失败'];
            }
        } else {
            return ['errcode' => -1, 'errmsg' => '娃娃数量不足，不可兑换！'];
        }
        return ['errcode' => 0, 'errmsg' => '兑换成功'];

    }

    /**
     * 处理兑换信息
     * @param int $merchant_id 商户id
     * @param int $member_id 会员id
     * @param array $params 详细信息
     * @return array
     * @author: denghongmei@dodoca.com
     */
    public function handleinfo($merchant_id, $member_id, $params)
    {
        $create = [];
        $create['merchant_id'] = $merchant_id;
        $create['member_id'] = $member_id;
        $create['consignee'] = isset($params['consignee']) ? $params['consignee'] : '';
        $create['mobile'] = isset($params['mobile']) ? $params['mobile'] : '';
        $create['type'] = isset($params['type']) ? intval($params['type']) : 0;

        if (!$create['consignee']) {
            return ['errcode' => 99001, 'errmsg' => '请填写收货人姓名'];
        }

        if (!$create['mobile']) {
            return ['errcode' => 99001, 'errmsg' => '请填写收货人手机号'];
        }

        if (!$create['type']) {
            return ['errcode' => 99001, 'errmsg' => '兑换类型不能为空'];
        }

        //兑换非优惠券等实物奖品
        if ($create['type'] < 4) {
            $create['country'] = 1;
            $create['country_name'] = Region::get_title_by_id($create['country']);

            $province = isset($params['province']) ? $params['province'] : '';
            $city = isset($params['city']) ? $params['city'] : '';
            $district = isset($params['district']) ? $params['district'] : '';
            $create['address'] = isset($params['address']) ? $params['address'] : '';
            $create['remark'] = isset($params['remark']) ? $params['remark'] : '';

            if (!$province || !$city || !$district || !$create['address']) {
                return ['errcode' => 99001, 'errmsg' => '请填写完整收货地址信息'];
            }
            //截取'市'
            $sub_string = mb_substr($province, -1);
            if ($sub_string == '市') {
                $province = mb_substr($province, 0, -1);
            }

            $provincedata = Region::get_data_by_title($province, $create['country']);
            if (!$provincedata) {
                return ['errcode' => 110001, 'errmsg' => '省份不存在！'];
            }
            $create['province'] = $provincedata['id'];
            $create['province_name'] = Region::get_title_by_id($create['province']);

            if (!in_array($create['province'], array(900000, 710000, 810000, 820000))) {
                if (!preg_match('#^13[\d]{9}$|^14[0-9]{1}\d{8}$|^15[^4]{1}\d{8}$|^17[0-9]{1}\d{8}$|^18[\d]{9}$#', $create['mobile'])) {
                    return array('errcode' => -1, 'errmsg' => '手机格式错误！');
                }
            }

            $citydata = Region::get_data_by_title($city, $create['province']);
            if (!$citydata) {
                return ['errcode' => 110001, 'errmsg' => '城市不存在！'];
            }
            $create['city'] = $citydata['id'];
            $create['city_name'] = Region::get_title_by_id($create['city']);

            $districtdata = Region::get_data_by_title($district, $create['city']);
            if (!$districtdata) {
                return ['errcode' => 110001, 'errmsg' => '区域不存在！'];
            }
            $create['district'] = $districtdata['id'];
            $create['district_name'] = Region::get_title_by_id($create['district']);
        }

        return ['errcode' => 0, 'data' => $create];
    }

    /**
     * 兑换记录
     *
     * @param int $merchant_id 商户id
     * @param int $member_id 会员id
     * @author denghongmei@dodoca.com
     *
     * @return \Illuminate\Http\Response
     */

    public function getRecord(Request $request)
    {
        $merchant_id = $this->merchant_id;
        $member_id = $this->member_id;
        if (!$merchant_id) {
            return ['errcode' => 99001, 'errmsg' => '商户ID不存在'];
        }

        if (!$member_id) {
            return ['errcode' => 99001, 'errmsg' => '会员ID不存在'];
        }

        $data = ToyExchange::get_data_list($member_id, $merchant_id);

        return ['errcode' => 0, 'errmsg' => '获取数据成功', 'data' => $data];
    }

    /**
     * 抓娃娃结果
     * @return array
     * @author: tangkang@dodoca.com
     * @throws \Exception
     */
    public function getGrab()
    {
        $key = CacheKey::get_toy_grab_limits_by_member_id_key($this->member_id, $this->merchant_id);

        if (!Redis::set($key, '1', 'EX', 1, 'NX')) {//限制每个会员每秒访问一次
            return ['errcode' => 1, 'errmsg' => '抓取频率过于频繁，请稍后重试。'];
        }/*else{
            \Log::info('');
        }*/

        $toy_member_res = ToyMember::get_data_by_id($this->member_id, $this->merchant_id);
        if (empty($toy_member_res)) return ['errcode' => 1, 'errmsg' => '获取用户信息失败'];
        $ToyService = new ToyService();
        $param = [
            'member_id' => $this->member_id,
        ];
        $ifGrab_res = $ToyService->ifGrab($param, $this->merchant_id);
        if (!$ifGrab_res) return ['errcode' => 0, 'errmsg' => '今日抓取次数已用完', 'data' => $ifGrab_res];

        $count = ToyGrabLog::grab_times_today($this->member_id, $this->merchant_id);

        $update_data = [];
        if ($count >= $ToyService::MAX_FREE_TIMES) {//使用助力
            $update_data['grab_qty'] = DB::raw('grab_qty + 1');
        }
        $res = $ToyService->getGrabResult($param, $this->merchant_id);
        $log_data = [
            'merchant_id' => $this->merchant_id,
            'member_id' => $this->member_id,
            'result' => (int)$res,
        ];
        DB::connection('applet_cust')->beginTransaction();
        try {
            $log_res = ToyGrabLog::insert_data($log_data);
            if (empty($log_res)) throw new \Exception('ToyGrabLog表插入数据失败，数据：' . json_encode($log_data, JSON_UNESCAPED_UNICODE));
            if ($res) {//抓到
                $update_data['toy_qty_total'] = DB::raw('toy_qty_total + 1');
                $update_data['toy_qty'] = DB::raw('toy_qty + 1');
            }
            $update_data['grab_qty_total'] = DB::raw('grab_qty_total + 1');
            $member_update = ToyMember::update_data($toy_member_res['id'], $this->merchant_id, $update_data, $res);
            if (empty($member_update)) throw new \Exception('toy_member表更新抓取次数数据失败，数据：' . json_encode($update_data, JSON_UNESCAPED_UNICODE));
            DB::connection('applet_cust')->commit();
        } catch (\Exception $e) {
            DB::connection('applet_cust')->rollBack();
            //记录异常
            $except = [
                'activity_id' => $this->member_id,
                'data_type' => 'toy_getGrab',
                'content' => '抓娃娃数据更新异常。line:' . $e->getLine() . ',msg:' . $e->getMessage(),
            ];
            CommonApi::errlog($except);
            return ['errcode' => 1, 'errmsg' => '抓取失败', 'data' => []];
        }

        return ['errcode' => 0, 'errmsg' => 'OK', 'data' => ['result' => (int)$res]];
    }

    /**
     * 获取当前用户游戏信息
     * @return array
     * @author: tangkang@dodoca.com
     */
    public function getMember()
    {
        $ToyService = new ToyService();

        $toy_member_res = ToyMember::get_data_by_id($this->member_id, $this->merchant_id);

        //获取每日三次抓取机会
        if (empty($toy_member_res)) {//初次登入
            $insert_data = [
                'merchant_id' => $this->merchant_id,
                'member_id' => $this->member_id,
                'give_times_time' => date('Y-m-d H:i:s')
            ];
            ToyMember::insert_data($insert_data);
            $toy_member_res['toy_qty'] = 0;//拥有娃娃数量
            $toy_member_res['can_grab_times_today'] = $ToyService::MAX_FREE_TIMES;//今日剩余抓去次数
            $toy_member_res['if_give_times'] = 1;//恭喜您获得3次抓取机会
        } else {
            $param = [
                'member_id' => $this->member_id,
            ];
            $ifGrab_res = $ToyService->ifGrab($param, $this->merchant_id);
            $toy_member_res['can_grab_times_today'] = $ifGrab_res;

            if (date('Y-m-d', strtotime($toy_member_res['give_times_time'])) == date('Y-m-d')) {
                $toy_member_res['if_give_times'] = 0;//已获取过抓取机会
            } else {
                $update_data = [
                    'give_times_time' => date('Y-m-d H:i:s')
                ];
                ToyMember::update_data($toy_member_res['id'], $this->merchant_id, $update_data);
                $toy_member_res['if_give_times'] = 1;//恭喜您获得3次抓取机会
            }
        }
        $member = Member::get();
        $toy_member_res['name'] = $member['name'];
        $toy_member_res['avatar'] = $member['avatar'];
        return ['errcode' => 0, 'errmsg' => 'OK', 'data' => $toy_member_res];
    }

    /**
     * 助力
     * @return array
     * @author: tangkang@dodoca.com
     */
    public function postAssist()
    {
        $member_id_tem = request()->get('member_id_tem', 0);
        if (!is_numeric($member_id_tem)) {
            $member_id_tem = encrypt($member_id_tem, 'D');
        }
        if (empty($member_id_tem)) return ['errcode' => 1, 'errmsg' => '助力失败，member_id_tem参数获取失败'];
        if ($member_id_tem == $this->member_id) {
            return ['errcode' => 0, 'errmsg' => '给自己助力不增加抓取次数'];
        }
        $member = Member::get();
        $toy_member_res = ToyMember::get_data_by_id($member_id_tem, $this->merchant_id);
        if (empty($toy_member_res)) return ['errcode' => 1, 'errmsg' => '被助力用户信息获取失败'];
        $create_data = [
            'merchant_id' => $this->merchant_id,
            'member_id' => $member_id_tem,//主力给tem
            'assist_member_id' => $this->member_id,//他来助力tem，tem+1
            'is_delete' => 1,
        ];
        $res_assist = ToyAssist::where($create_data)->first(['id']);
        if (!empty($res_assist)) return ['errcode' => 0, 'errmsg' => '已助力过该用户'];
        $create_data['assist_name'] = $member['name'];
        $create_data['assist_avatar'] = $member['avatar'];
        $update_data = [
            'assist_qty' => DB::raw('assist_qty + 1'),
        ];
        DB::connection('applet_cust')->beginTransaction();
        try {
            $res = ToyAssist::insert_data($create_data);//新增助力记录
            if (empty($res)) throw new \Exception('ToyAssist表插入数据失败（助力），数据：' . json_encode($create_data, JSON_UNESCAPED_UNICODE));
            $member_update_res = ToyMember::update_data($toy_member_res['id'], $this->merchant_id, $update_data);
            if (empty($member_update_res)) throw new \Exception('ToyMember更新数据失败（助力），数据：' . json_encode($update_data, JSON_UNESCAPED_UNICODE));
            DB::connection('applet_cust')->commit();
        } catch (\Exception $e) {
            DB::connection('applet_cust')->rollBack();
            //记录异常
            $except = [
                'activity_id' => $this->member_id,
                'data_type' => 'toy_postAssist',
                'content' => '抓娃娃助力失败。line:' . $e->getLine() . ',msg:' . $e->getMessage(),
            ];
            CommonApi::errlog($except);
            return ['errcode' => 1, 'errmsg' => '抓娃娃助力失败', 'data' => []];
        }
        return ['errcode' => 0, 'errmsg' => '助力成功'];
    }

    /**
     * 助力列表
     * @return array
     * @author: tangkang@dodoca.com
     */
    public function getAssist()
    {
        $data = ToyAssist::get_data_list($this->member_id, $this->merchant_id);
        return ['errcode' => 0, 'errmsg' => '列表', 'data' => $data];
    }

    /**
     * 获取分享的小程序码
     * @author: tangkang@dodoca.com
     */
    public function getWxacode()
    {
        $appid = request('appid', 0);
        if (empty($appid)) return ['errcode' => 1, 'errmsg' => 'appid参数不能为空'];
//        $Encrypt = new Encrypt;
//        $member_id_tem = $Encrypt->encode($this->member_id);
        $member_id_tem = $this->member_id;
        $scene = 'member_id_tem=' . $member_id_tem;
        $tag_key = CacheKey::get_toy_tag_by_merchant_id_id_key($this->merchant_id);
        $key = CacheKey::get_toy_wxacode_by_member_id_key($this->member_id, $this->merchant_id, $appid, $scene);
        $code_res = Cache::tags($tag_key)->get($key);
        if (empty($code_res)) {
            $WeixinService = new WeixinService();
            $code_res = $WeixinService->limitQrcode($appid, $scene);
            if ($code_res['errcode'] == 0) {
                Cache::tags($tag_key)->put($key, $code_res, 10080);
            }
        }

        return $code_res;
    }

    /**
     * 查看已抓中娃娃的缓存中的金额价值
     * @return mixed
     * @author: tangkang@dodoca.com
     */
    public function getCacheMoney()
    {
        $merchant_id = request('merchant_id', 0);
        if (empty($merchant_id)) return 'merchant_id参数不能为空';
        $tag_key = CacheKey::get_toy_tag_by_merchant_id_id_key($merchant_id);
        $key = CacheKey::get_toy_money_by_member_id_key($merchant_id);
        $money_cache = Cache::tags($tag_key)->get($key);

        $total_toy = ToyMember::where('merchant_id', $merchant_id)
            ->where('toy_qty_total', '>=', ToyService::$exchange_rules['ten'])
            ->where('is_delete', 1)
            ->limit(500)->get(['toy_qty_total']);
        $money = 0;//大于等于5000，概率为0
        foreach ($total_toy as $value) {
            $remainder = floor($value['toy_qty_total'] / ToyService::$exchange_rules['ten']);
            $money += ($remainder * 10);
        }

        $data['merchant_id'] = $merchant_id;
        $data['cache'] = '娃娃价值金额（缓存）：' . $money_cache;
        $data['mysql'] = '娃娃价值金额（mysql）：' . $money;
        return $data;
    }

    /**
     * 清抓娃娃所有缓存
     * @author: tangkang@dodoca.com
     */
    public function flushToyCache()
    {
        $merchant_id = request('merchant_id', 0);
        $tag_key = CacheKey::get_toy_tag_by_merchant_id_id_key($merchant_id);
        Cache::tags($tag_key)->flush();
        echo '所有抓娃娃缓存清理成功！';
    }
}
