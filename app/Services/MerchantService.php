<?php
/**
 * 商户服务类
 * 
 */
namespace App\Services;

use App\Models\Merchant;
use App\Models\MerchantSetting;
use App\Models\LiveBalance;
use App\Models\MerchantBalance;

class MerchantService {
    
    /**
     * 获取商户版本信息
     * @author wangshen@dodoca.com
	 * @cdate 2017-11-28
     * 
     * @param int $merchant_id  商户id
     */
    public static function getMerchantVersion($merchant_id){
        
        //商户id
        $merchant_id = isset($merchant_id) ? (int)$merchant_id : 0;
        
        
        $merchant_info = Merchant::get_data_by_id($merchant_id);
        
        
        if(!$merchant_info){
            return ['errcode' => 99001,'errmsg' => '未查询到商户信息'];
        }
        
        
        
        //版本
        $version_id = $merchant_info['version_id'];
        
        $version_config = config('version');
        $version_name = isset($version_config[$version_id]['name'])?$version_config[$version_id]['name']:'';
        
        //细分行业
        $industry_sign_id = $merchant_info['industry_sign'];
        $industry_sign_config = config('industrysign');
        $industry_sign_name = isset($industry_sign_config[$industry_sign_id]['name'])?$industry_sign_config[$industry_sign_id]['name']:'';
        
        $data = [
            'version_id' => $version_id,
            'version_name' => $version_name,
            'industry_sign_id' => $industry_sign_id,
            'industry_sign_name' => $industry_sign_name,
            'is_expired' => 0,
        ];
        
        //过期
        if( $merchant_info['version_id']==5 && date('Y-m-d H:i:s')>=date('Y-m-d H:i:s',strtotime('+1 month',strtotime($merchant_info['created_time']))) ){
            $data['is_expired'] = 1;
        }else if( date('Y-m-d H:i:s')>=$merchant_info['expire_time'] && !in_array($merchant_info['version_id'], array(1,5)) ){
            $data['is_expired'] = 1;
        }
        
        return ['errcode' => 0,'errmsg' => '获取商户版本信息成功','data' => $data];
    }

     /**
     * 商家直播余额变动
     * @param 
	 	$data = array(
			'merchant_id'	=>	'', 	//商户id
     		'ctype'			=>	'',		//余额类型：1 直播包，2 录播包 3 云存储
     		'type'			=>	'',		//扣除类型：1创建直播扣除，2删除直播归还，配置config/varconfig.php
			'type_id'		=>	'',		//关联业务表id
     		'sum'			=>	'',		//变动金额
     		'memo'			=>	'',		//备注
		);
     * @return bool|float
     */
    public static function changeLiveMoney($data){
		if(!isset($data['merchant_id']) || !isset($data['ctype']) || !in_array($data['ctype'],array(1,2,3)) || !isset($data['type']) || !isset($data['sum'])) {
			return false;
		}
		
        $merchatSetInfo = MerchantSetting::get_data_by_id($data['merchant_id']);
        if(!$merchatSetInfo){
            return false;
        }
		
		$field = '';
		if($data['ctype']==1) {	//直播包
			/*$changeM = $u_data['live_balance']	= $merchatSetInfo['live_balance']+$data['sum'];
			if($data['sum']<0) {
				$where = "live_balance-".abs($data['sum'])."=".$changeM." and live_balance-".abs($data['sum']).">0";
			} else {
				$where = "live_balance+".abs($data['sum'])."=".$changeM;
			}*/
			$field = 'live_balance';
		} else if($data['ctype']==2) {	//录播包
			/*$changeM = $u_data['live_record']	= $merchatSetInfo['live_record']+$data['sum'];
			if($data['sum']<0) {
				$where = "live_record-".abs($data['sum'])."=".$changeM." and live_record-".abs($data['sum']).">0";
			} else {
				$where = "live_record+".abs($data['sum'])."=".$changeM;
			}*/
			$field = 'live_record';
		} else if($data['ctype']==3) {	//云存储
			/*$changeM = $u_data['live_store']	= $merchatSetInfo['live_store']+$data['sum'];
			if($data['sum']<0) {
				$where = "live_store-".abs($data['sum'])."=".$changeM." and live_store-".abs($data['sum']).">0";
			} else {
				$where = "live_store+".abs($data['sum'])."=".$changeM;
			}*/
			$field = 'live_store';
		}
		
		if($data['sum']>0) {
			$result =  MerchantSetting::increment_data($merchatSetInfo['id'],$merchatSetInfo['merchant_id'],$field,abs($data['sum']));
		} else {
			$result =  MerchantSetting::decrement_data($merchatSetInfo['id'],$merchatSetInfo['merchant_id'],$field,abs($data['sum']));
		}
        if(!$result){
            return false;
        }
		
        //余额变化记录
        $create = array(
            'merchant_id'   => $data['merchant_id'],
            'ctype'     	=> $data['ctype'],
            'sum'     		=> $data['sum'],
            'balance'      	=> $merchatSetInfo[$field]+$data['sum'],	//$changeM,
            'type'        	=> $data['type'],
            'type_id'       => isset($data['type_id']) ? $data['type_id'] : 0,
            'memo'    		=> $data['memo'],
        );
        $rid = LiveBalance::insert_data($create);
		return ['insert_id'=>$rid,'balance'=>$create['balance']];
    }
    
    
    
    /**
     * 商家余额变动
     * @author wangshen@dodoca.com
	 * @cdate 2018-4-28
	 * 
     * @param
     *    $data = array(
     *        'merchant_id'	=>	'', 	//商户id
     *        'type'		=>	'',		//变化类型，配置config/varconfig.php
     *        'type_id'		=>	'',		//关联业务表id
     *        'sum'			=>	'',		//变动金额
     *        'memo'		=>	'',		//备注
     *    );
     * @return bool|float
     * 
     */
    public static function changeMerchantBalance($data){
        if(!isset($data['merchant_id']) || !isset($data['type']) || !isset($data['sum'])){
            return false;
        }
        
        //商户表信息
        $merchant_info = Merchant::get_data_by_id($data['merchant_id']);
        
        if(!$merchant_info){
            return false;
        }
        
    
        $field = 'balance';
    
        if($data['sum']>0) {
            $result =  Merchant::increment_data($data['merchant_id'],$field,abs($data['sum']));
        } else {
            $result =  Merchant::decrement_data($data['merchant_id'],$field,abs($data['sum']));
        }
        if(!$result){
            return false;
        }
        
    
        //余额变化记录
        $create = array(
            'merchant_id'   => $data['merchant_id'],
            'sum'     		=> $data['sum'],
            'balance'      	=> $merchant_info[$field] + $data['sum'],	//变化后余额
            'type'        	=> $data['type'],
            'type_id'       => isset($data['type_id']) ? $data['type_id'] : 0,
            'memo'    		=> $data['memo'],
            'balance_sn'    => self::get_balance_sn('D'),  //交易单号
        );
        return MerchantBalance::insert_data($create);
    }
    
    
    /**
     * 获取点点币交易单号
     * @author wangshen@dodoca.com
     * @cdate 2018-5-3
     * prefix 前缀
     */
    public static function get_balance_sn($prefix='E') {
        $balance_sn = $prefix.date('Ymdhis').str_pad(mt_rand(1,99999),5,'0',STR_PAD_LEFT);
        $balance = MerchantBalance::select('balance_sn')->where(array('balance_sn' => $balance_sn))->first();
        if(!$balance) {
            return $balance_sn;
        }
        return self::get_balance_sn($prefix);
    }

}