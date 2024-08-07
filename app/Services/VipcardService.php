<?php

namespace App\Services;

/**
 * 会员卡服务类
 *
 * @package default
 * @author guoqikai
 **/
use App\Models\Goods;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\MemberUpdateLog;
use App\Jobs\WeixinMsgJob;
use Illuminate\Foundation\Bus\DispatchesJobs;

class VipcardService
{
    use DispatchesJobs;
	/**
     * 获取商品折扣
     *
     * @param string $merchant_id  商户ID
     * @param string $member_id  买家ID
     * @param string $goods_id  商品ID
     *
     * @return \Illuminate\Http\Response
     */
	public function goodsDiscount($param)
	{
		$merchant_id = isset($param['merchant_id']) ? intval($param['merchant_id']) : 0;
		$member_id   = isset($param['member_id']) ? intval($param['member_id']) : 0;
		$goods_id    = isset($param['goods_id']) ? intval($param['goods_id']) : 0;

		if(!$merchant_id){
			return ['errcode' => 99001, 'errmsg' => '商户ID不存在'];
		}

		if(!$member_id){
			return ['errcode' => 99001, 'errmsg' => '会员ID不存在'];
		}

		if(!$goods_id){
			return ['errcode' => 99001, 'errmsg' => '商品ID不存在'];
		}

		$member = Member::get_data_by_id($member_id, $merchant_id);

		if(!$member){
			return ['errcode' => 10004, 'errmsg' => '会员不存在'];
		}
		//会员卡ID
		$member_card_id = $member['member_card_id'];

		if($member_card_id > 0){
			$member_card = MemberCard::get_data_by_id($member_card_id, $merchant_id);

			if(!$member_card){
				return ['errcode' => 20001, 'errmsg' => '会员卡不存在'];
			}

			$goods = Goods::get_data_by_id($goods_id, $merchant_id);

			if($goods){
				//商品使用会员折扣
				if($goods['is_discount'] == 1){
					return ['errcode' => 0, 'data' => ['discount' => floatval($member_card['discount'])]];
				}
			}
		}

		return ['errcode' => 0, 'data' => ['discount' => 0]];
	}

    /**
     * 当前用户的会员卡是否支持包邮
     *
     * @param string $merchant_id  商户ID
     * @param string $member_id  买家ID
     *
     * @return \Illuminate\Http\Response
     */
    public function isPostageFree($param)
    {
        $merchant_id = isset($param['merchant_id']) ? intval($param['merchant_id']) : 0;
        $member_id   = isset($param['member_id']) ? intval($param['member_id']) : 0;

        if(!$merchant_id){
            return ['errcode' => 99001, 'errmsg' => '商户ID不存在'];
        }

        if(!$member_id){
            return ['errcode' => 99001, 'errmsg' => '会员ID不存在'];
        }

        $member = Member::get_data_by_id($member_id, $merchant_id);

        if(!$member){
            return ['errcode' => 10004, 'errmsg' => '会员不存在'];
        }
        //会员卡ID
        $member_card_id = $member['member_card_id'];

        if($member_card_id > 0){
            $member_card = MemberCard::get_data_by_id($member_card_id, $merchant_id);

            if(!$member_card){
                return ['errcode' => 20001, 'errmsg' => '会员卡不存在'];
            }

            if($member_card['is_postage_free'] == 0){
                return false;
            }

            if($member_card['is_postage_free'] == 1){
                return true;
            }
        }

        return false;
    }

    /**
     * 会员卡手动指派
     *
     * @param string $member_id  买家ID
     *
     * @return \Illuminate\Http\Response
     */
    public function allocation($param)
    {
        $merchant_id          = isset($param['merchant_id']) ? intval($param['merchant_id']) : 0;
        $member_ids           = isset($param['member_ids']) ? $param['member_ids'] : 0;
        $member_card_id       = isset($param['member_card_id']) ? intval($param['member_card_id']) : 0;
        $member_card_overtime = isset($param['member_card_overtime']) ? $param['member_card_overtime'] : '';

        if(!$merchant_id){
            return ['errcode' => 99001, 'errmsg' => '商户ID不存在'];
        }

        if(!$member_card_id){
            return ['errcode' => 99001, 'errmsg' => '会员等级不存在'];
        }

        if(!$member_ids){
            return ['errcode' => 99001, 'errmsg' => '会员ID不存在'];
        }

        if(!$member_card_overtime){
            return ['errcode' => 99001, 'errmsg' => '请输入会员卡过期时间'];
        }

        $member_card = MemberCard::get_data_by_id($member_card_id, $merchant_id);

        if(!$member_card || $member_card['is_delete'] == -1){
            return ['errcode' => 20018, 'errmsg' => '会员等级不存在'];
        }

        if($member_card['card_type'] != 2){
            return ['errcode' => 20017, 'errmsg' => '会员等级必须是手动升级类型的'];
        }

        if($member_ids){
            $wheres = [];
            if(is_array($member_ids)){
                $wheres[] = ['column' => 'id', 'operator' => 'in', 'value' => $member_ids];
            }else{
                $wheres[] = ['column' => 'id', 'operator' => '=', 'value' => $member_ids];
            }

            $data = [
                'member_card_id'       => $member_card_id,
                'member_card_overtime' => $member_card_overtime
            ];

            if(Member::update_data_by_where($member_ids, $merchant_id, $wheres, $data)){
                return ['errcode' => 0, 'errmsg' => '操作成功'];
            }
        }

        return ['errcode' => -1, 'errmsg' => '操作失败'];
    }

	/**
     * 会员卡自动升级
     *
     * @param string $member_id  买家ID
     *
     * @return \Illuminate\Http\Response
     */
	public function autoUpgrade($member_id)
	{
		if(!$member_id) return;
        $member = Member::where('id', $member_id)->first();

        if($member){

            $merchant_id            = $member['merchant_id'];
            $current_member_card_id = $member['member_card_id'];

            if($member['member_card_id'] > 0 && strtotime($member['member_card_overtime']) > time()){
                return;
            }
            
            //会员 -> 累计消费金额
            $member_total_amount    = $member['total_amount'];
            //会员 -> 累计购买次数
            //$member_purchased_count = $member['purchased_count'];
            //会员 -> 累积积分
            //$member_total_credit    = $member['total_credit'];

            $levels = [];

            //累计消费金额
            if($member_total_amount > 0){
                $amount_limit_level = MemberCard::select('id', 'card_name', 'card_level')
                    ->where('merchant_id', $merchant_id)
                    ->where('is_delete', 1)
                    ->where('card_type', 1)
                    ->where(function ($query) use ($member_total_amount){
                        $query->where('amount_limit', '>', 0)->where('amount_limit', '<=', $member_total_amount);
                    })
                    ->orderBy('card_level', 'desc')
                    ->first();

                if($amount_limit_level){
                    $levels[] = $amount_limit_level->toArray();
                }
            }

            //累计成功交易笔数
            /*if($member_purchased_count > 0){
                $trade_limit_level = MemberCard::select('id', 'card_name', 'card_level')
                    ->where('merchant_id', $merchant_id)
                    ->where('is_delete', 1)
                    ->where('card_type', 1)
                    ->where(function ($query) use ($member_purchased_count){
                        $query->where('trade_limit', '>', 0)->where('trade_limit', '<=', $member_purchased_count);
                    })
                    ->orderBy('card_level', 'desc')
                    ->first();

                if($trade_limit_level){
                    $levels[] = $trade_limit_level->toArray();
                }
            }*/
            

            //累计积分
            /*if($member_total_credit > 0){
                $points_limit_level = MemberCard::select('id', 'card_name', 'card_level')
                    ->where('merchant_id', $merchant_id)
                    ->where('is_delete', 1)
                    ->where('card_type', 1)
                    ->where(function ($query) use ($member_total_credit){
                        $query->where('points_limit', '>', 0)->where('points_limit', '<=', $member_total_credit);
                    })
                    ->orderBy('card_level', 'desc')
                    ->first();

                if($points_limit_level){
                    $levels[] = $points_limit_level->toArray();
                }
            }*/
            
            if($levels){
                $card_level = [];
                foreach($levels as $level){
                    $card_level[] = $level['card_level'];
                }
                //使用会员卡级别从大到小排序
                array_multisort($card_level, SORT_DESC, $levels);

                $new_level_card_id = $levels[0]['id'];

                if($current_member_card_id != $new_level_card_id){
                	
                	$wheres = [
                		[
							'column'   => 'id',
							'operator' => '=',
							'value'    => $member_id
                		],
                		[
							'column'   => 'member_card_id',
							'operator' => '=',
							'value'    => $current_member_card_id
                		]
                	];

                    $data = [
                        'member_card_id'       => $new_level_card_id,
                        'member_card_overtime' => '0000-00-00'
                    ];
                    
                    
                    if(Member::update_data_by_where($member_id, $merchant_id, $wheres, $data)){

                        $log_data = [
                            'merchant_id'         => $merchant_id,
                            'member_id'           => $member_id,
                            'last_member_card_id' => $current_member_card_id,
                            'next_member_card_id' => $new_level_card_id,
                            'type'                => 1
                        ];

                        MemberUpdateLog::insert_data($log_data);
                        //发送模板消息
                        $this->dispatch(new WeixinMsgJob(['type' => 'upgrade', 'member_id' => $member_id]));
                    }
                }
            }else{
                //打回默认会员
                $wheres = [
                    [
                        'column'   => 'id',
                        'operator' => '=',
                        'value'    => $member_id
                    ],
                    [
                        'column'   => 'member_card_id',
                        'operator' => '=',
                        'value'    => $current_member_card_id
                    ]
                ];

                $data = [
                    'member_card_id'       => 0,
                    'member_card_overtime' => '0000-00-00'
                ];
                
                
                if(Member::update_data_by_where($member_id, $merchant_id, $wheres, $data)){

                    $log_data = [
                        'merchant_id'         => $merchant_id,
                        'member_id'           => $member_id,
                        'last_member_card_id' => $current_member_card_id,
                        'next_member_card_id' => 0,
                        'type'                => 1
                    ];

                    MemberUpdateLog::insert_data($log_data);
                    //发送模板消息
                    $this->dispatch(new WeixinMsgJob(['type' => 'upgrade', 'member_id' => $member_id]));
                }
            }
        }
	}
} // END class 
