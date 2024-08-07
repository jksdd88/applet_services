<?php
/**
 * 门店控制器
 * changzhixian
 */

namespace App\Http\Controllers\Admin\Store;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\StoreService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

class StoreController extends Controller
{

    private $merchant_id;

    public function __construct(StoreService $StoreService)
    {
        $this->merchant_id = Auth::user()->merchant_id;
//        $this->merchant_id = 2;
        $this->StoreService = $StoreService;
    }


    /*
     * 门店列表
     * */
    public function getStoreList(Request $request) {


        /*$data_srevice = $this->StoreService->storeList(2,0,100);
        return $data_srevice;
        die;*/

        //参数
        $params['offset'] = $request['offset'];
        $params['limit'] = $request['limit'];
        $params['name'] = $request['name'];
        $params['enabled'] = $request['enabled'];
        $params['extract_enabled'] = $request['extract_enabled'];

        $rules = [
            'offset'        => 'integer|min:0',
            'limit'         => 'integer|min:1',
            'name'          => 'string',
            'enabled'         => 'integer|max:1|min:1',
            'extract_enabled' => 'integer|max:1|min:1'
        ];
        $messages = [
            'offset.integer'            => '非法的offset',
            'offset.min'                => '非法的offset',
            'limit.integer'             => '非法的limit',
            'limit.min'                 => '非法的limit',
            'name.string'               => '非法的name',
            'enabled.integer'           => '非法的enabled',
            'enabled.max'               => '非法的enabled',
            'enabled.min'               => '非法的enabled',
            'extract_enabled.integer'   => '非法的extract_enabled',
            'extract_enabled.max'       => '非法的extract_enabled',
            'extract_enabled.min'       => '非法的extract_enabled'
        ];
        $validator = Validator::make( $params ,$rules ,$messages );
        if($validator->fails()){
            $error = $validator->errors()->first();
            return ['errcode' => 10001, 'errmsg' => $error];
            exit;
        }

        $merchant_id = $this->merchant_id;//商户id
        $offsets = isset($params['offset']) ? $params['offset'] : 0;
        $limit = isset($params['limit']) ? $params['limit'] : 10;
        $offset = ($offsets - 1) * $limit;
        $name = $params['name'];
        $data_srevice = $this->StoreService->storeList($merchant_id,$name,$offset,$limit,$params['enabled'],$params['extract_enabled']);

        return ['errcode' => $data_srevice['errcode'], 'errmsg' => $data_srevice['errmsg'],'_count'=>$data_srevice['_count'],'data' => $data_srevice['data']];

    }
    /**
     * 新增门店
     * chang
     * 20171025 12:00
     */
    public function postStore(Request $request)
    {

        //参数
        $params['name'] = $request['name'];
        $params['mobile'] = $request['mobile'];
        $params['office_at'] = $request['office_at'];
        $params['img'] = $request['img'];

        $params['country'] = $request['country'];
        $params['province'] = $request['province'];
        $params['city'] = $request['city'];
        $params['district'] = $request['district'];
        $params['country_name'] = $request['country_name'];
        $params['province_name'] = $request['province_name'];
        $params['city_name'] = $request['city_name'];
        $params['district_name'] = $request['district_name'];

        $params['address'] = $request['address'];
        $params['contacts'] = $request['contacts'];
        $params['lng'] = $request['lng'];
        $params['lat'] = $request['lat'];
        $params['enabled'] = $request['enabled'];

        $params['info'] = $request['info'];
        $params['open_time'] = $request['open_time'];
        $params['close_time'] = $request['close_time'];
        $params['special_service'] = $request['special_service'];

        $params['is_delete'] = 1;

        $rules = [
            'name'              => 'required|string',
            'mobile'            => 'required|between:11,11',
            'office_at'         => 'required|string',
            'img'               => 'array',
            'country'           => 'required|integer',
            'province'          => 'required|integer',
            'city'              => 'required|integer',
            'district'          => 'required|integer',
            'country_name'      => 'required|string',
            'province_name'     => 'required|string',
            'city_name'         => 'required|string',
            'district_name'     => 'required|string',
            'address'           => 'required|string',
            'contacts'          => 'string',
            'lng'               => 'required|string',
            'lat'               => 'required|string',
            'enabled'           => 'required|integer',
            'info'              => 'string',
            'open_time'         => 'required|string',
            'close_time'        => 'required|string',
            'special_service'   => 'string',
        ];
        $messages = [
            'name.required'            => 'name为必填',
            'name.string'              => '非法的name',
            'mobile.required'          => 'mobile为必填',
            'mobile.between'           => '非法的mobile',
            'office_at.required'       => 'office_at为必填',
            'office_at.string'         => '非法的office_at',
            'img.array'               => '非法的img',
            'country.required'         => 'country为必填',
            'country.integer'          => '非法的country',
            'province.required'        => 'province为必填',
            'province.string'          => '非法的province',
            'city.required'            => 'city为必填',
            'city.string'              => '非法的city',
            'district.required'        => 'district为必填',
            'district.string'          => '非法的district',
            'country_name.required'    => 'country_name为必填',
            'country_name.string'      => '非法的country_name',
            'province_name.required'   => 'province_name为必填',
            'province_name.string'     => '非法的province_name',
            'city_name.required'       => 'city_name为必填',
            'city_name.string'         => '非法的city_name',
            'district_name.required'   => 'district_name为必填',
            'district_name.string'     => '非法的district_name',
            'address.required'   => 'address为必填',
            'address.string'     => '非法的address',
            'contacts.string'     => '非法的contacts',
            'lng.required'   => '请使用地图定位！',
            'lng.string'     => '请使用地图定位',
            'lat.required'   => '请使用地图定位！',
            'lat.string'     => '请使用地图定位',
            'enabled.required'   => 'enabled为必填',
            'enabled.integer'     => '非法的enabled',
            'info.string'     => '非法的enabled',
            'open_time.required'   => 'open_time为必填',
            'open_time.string'     => '非法的open_time',
            'close_time.required'   => 'close_time为必填',
            'close_time.string'     => '非法的close_time',
            'special_service.string'     => '非法的special_service',
        ];
        $validator = Validator::make( $params ,$rules ,$messages );
        if($validator->fails()){
            $error = $validator->errors()->first();
            return ['errcode' => 10001, 'errmsg' => $error];
            exit;
        }

        $params['merchant_id'] = $this->merchant_id;//商户id

        //验证门店名字
        $return = self::get_store_name($params['name']);
        if($return != 1){
            return ['errcode' => "10022", 'errmsg' => "门店名称已存在"];exit;
        }
        $params['img'] = !empty($params['img']) ? json_encode($params['img']) : null;
        $data_srevice = Store::insert_data($params);
        if($data_srevice > 0 ){
            return ['errcode' => "0", 'errmsg' => "新增门店成功"];
        }else{
            return ['errcode' => "1002", 'errmsg' => "新增门店失败"];
        }

    }

    /**
     * 新增门店查询此商户门店名字是否存在
     * chang
     * 20171113 12:00
     */
    public function postStoreName(Request $request)
    {

        //参数
        $params['name'] = $request['name'];

        $rules = [
            'name'              => 'required|string'
        ];
        $messages = [
            'name.required'            => 'name为必填',
            'name.string'              => '非法的name'
        ];
        $validator = Validator::make( $params ,$rules ,$messages );
        if($validator->fails()){
            $error = $validator->errors()->first();
            return ['errcode' => 10001, 'errmsg' => $error];
            exit;
        }

        $return = self::get_store_name($params['name']);
        if($return == 1){
            return ['errcode' => "0", 'errmsg' => "门店名称可以使用"];
        }else{
            return ['errcode' => "10022", 'errmsg' => "门店名称已存在"];
        }

    }


    /**
     * 获取我的门店名字是否可用（前端显示）
     * @author changzhixian@dodoca.com
     * @cdate 2017-11-13
     *$store_id 为编辑门店页面，验证门店名字需排除当前门店id($store_id)
     */
    protected function get_store_name($name,$store_id=0){

        $merchant_id = $this->merchant_id;//商户id
        $wheres = [
            [
                'column'   => 'merchant_id',
                'operator' => '=',
                'value'    => $merchant_id,
            ],
            [
                'column'   => 'is_delete',
                'operator' => '=',
                'value'    => 1,
            ],
            [
                'column'   => 'name',
                'operator' => '=',
                'value'    => $name,
            ]
        ];
        if(isset($store_id) && $store_id > 0){
            $wheres[] = [
                'column'   => 'id',
                'operator' => '<>',
                'value'    => $store_id,
            ];
        }
        //dd($wheres);

        $data_srevice = Store::get_data_list($wheres);//dd($data_srevice);
        if(empty($data_srevice)){
            return 1;//门店名称可以使用
        }else{
            return 2;//门店名称已存在
        }

    }

    /**
     * 编辑门店
     * chang
     * 20171025 12:00
     */
    public function putStore(Request $request)
    {

        //参数
        $params['id'] = $request['id'];
        $params['name'] = $request['name'];
        $params['mobile'] = $request['mobile'];
        $params['office_at'] = $request['office_at'];
        $params['img'] = $request['img'];

        $params['country'] = $request['country'];
        $params['province'] = $request['province'];
        $params['city'] = $request['city'];
        $params['district'] = $request['district'];
        $params['country_name'] = $request['country_name'];
        $params['province_name'] = $request['province_name'];
        $params['city_name'] = $request['city_name'];
        $params['district_name'] = $request['district_name'];

        $params['address'] = $request['address'];
        $params['contacts'] = $request['contacts'];
        $params['lng'] = $request['lng'];
        $params['lat'] = $request['lat'];
        $params['enabled'] = $request['enabled'];

        $params['info'] = $request['info'];
        $params['open_time'] = $request['open_time'];
        $params['close_time'] = $request['close_time'];
        $params['special_service'] = $request['special_service'];

        $rules = [
            'id'           => 'required|integer',
            'name'              => 'required|string',
            'mobile'            => 'required|between:11,11',
            'office_at'         => 'required|string',
            'img'               => 'array',
            'country'           => 'required|integer',
            'province'          => 'required|integer',
            'city'              => 'required|integer',
            'district'          => 'required|integer',
            'country_name'      => 'required|string',
            'province_name'     => 'required|string',
            'city_name'         => 'required|string',
            'district_name'     => 'required|string',
            'address'           => 'required|string',
            'contacts'          => 'string',
            'lng'               => 'required|string',
            'lat'               => 'required|string',
            'enabled'           => 'required|integer',
            'info'              => 'string',
            'open_time'         => 'required|string',
            'close_time'        => 'required|string',
            'special_service'   => 'string',
        ];
        $messages = [
            'id.required'              => 'id为必填',
            'id.integer'               => '非法的id',
            'name.required'            => 'name为必填',
            'name.string'              => '非法的name',
            'mobile.required'          => 'mobile为必填',
            'mobile.between'           => '非法的mobile',
            'office_at.required'       => 'office_at为必填',
            'office_at.string'         => '非法的office_at',
            'img.array'                => '非法的img',
            'country.required'         => 'country为必填',
            'country.integer'          => '非法的country',
            'province.required'        => 'province为必填',
            'province.string'          => '非法的province',
            'city.required'            => 'city为必填',
            'city.string'              => '非法的city',
            'district.required'        => 'district为必填',
            'district.string'          => '非法的district',
            'country_name.required'    => 'country_name为必填',
            'country_name.string'      => '非法的country_name',
            'province_name.required'   => 'province_name为必填',
            'province_name.string'     => '非法的province_name',
            'city_name.required'       => 'city_name为必填',
            'city_name.string'         => '非法的city_name',
            'district_name.required'   => 'district_name为必填',
            'district_name.string'     => '非法的district_name',
            'address.required'         => 'address为必填',
            'address.string'           => '非法的address',
            'contacts.string'          => '非法的contacts',
            'lng.required'   => '请使用地图定位！',
            'lng.string'     => '请使用地图定位',
            'lat.required'   => '请使用地图定位！',
            'lat.string'     => '请使用地图定位',
            'enabled.required'         => 'enabled为必填',
            'enabled.integer'          => '非法的enabled',
            'info.string'              => '非法的enabled',
            'open_time.required'       => 'open_time为必填',
            'open_time.string'         => '非法的open_time',
            'close_time.required'      => 'close_time为必填',
            'close_time.string'        => '非法的close_time',
            'special_service.string'   => '非法的special_service',
        ];
        $validator = Validator::make( $params ,$rules ,$messages );
        if($validator->fails()){
            $error = $validator->errors()->first();
            return ['errcode' => 10001, 'errmsg' => $error];
            exit;
        }

        $params['merchant_id'] = $this->merchant_id;//商户id

        //验证门店名字
        $return = self::get_store_name($params['name'],$params['id']);
        if($return != 1){
            return ['errcode' => "10022", 'errmsg' => "门店名称已存在"];exit;
        }
        $params['img'] = !empty($params['img']) ? json_encode($params['img']) : null;//dd($params['img']);
        $data_srevice = Store::update_data($params['id'],$params['merchant_id'],$params);
        if($data_srevice > 0 ){
            return ['errcode' => "0", 'errmsg' => "编辑门店成功"];
        }else{
            return ['errcode' => "1002", 'errmsg' => "编辑门店失败"];
        }
    }

    /**
     * 门店开启/关闭
     * chang
     * 20171025 12:00
     */
    public function putEnabled(Request $request)
    {

        //参数
        $params['id'] = $request['id'];
        $params['enabled'] = $request['enabled'];

        $rules = [
            'id'           => 'required|integer',
            'enabled'           => 'required|integer'
            ];
        $messages = [
            'id.required'              => 'id为必填',
            'id.integer'               => '非法的id',
            'enabled.required'         => 'enabled为必填',
            'enabled.integer'          => '非法的enabled'
        ];
        $validator = Validator::make( $params ,$rules ,$messages );
        if($validator->fails()){
            $error = $validator->errors()->first();
            return ['errcode' => 10001, 'errmsg' => $error];
            exit;
        }

        $params['merchant_id'] = $this->merchant_id;//商户id
        $data_srevice = Store::update_data($params['id'],$params['merchant_id'],$params);
        if($data_srevice > 0 ){
            return ['errcode' => "0", 'errmsg' => "成功"];
        }else{
            return ['errcode' => "1002", 'errmsg' => "失败"];
        }
    }

    /**
     * 门店详情
     * chang
     * 20171025 12:00
     */
    public function getStore(Request $request)
    {

        //参数
        $params['id'] = $request['id'];

        $rules = [
            'id'           => 'required|integer'
        ];
        $messages = [
            'id.required'              => 'id为必填',
            'id.integer'               => '非法的id'
        ];
        $validator = Validator::make( $params ,$rules ,$messages );
        if($validator->fails()){
            $error = $validator->errors()->first();
            return ['errcode' => 10001, 'errmsg' => $error];
            exit;
        }

        $params['merchant_id'] = $this->merchant_id;//商户id
        $data = Store::get_data_by_id($params['id'],$params['merchant_id']);

        if($data){
            $data['img'] = (is_null($data['img']) || empty($data['img']) ) ? [] : json_decode($data['img'],true);
            $data['office_at_str'] = explode(',',$data['office_at']);

            if(is_array($data['office_at_str'])){
                foreach($data['office_at_str'] as $k=>$v){
                    $office_at_int[] = (int)$v;
                }
            }
            $data['office_at'] = $office_at_int;
            $data['special_service'] = explode(',',$data['special_service']);
            $data['open_time_close_time'] = [$data['open_time'],$data['close_time']];
            $data['country_id_district_id'] = [$data['province'],$data['city'],$data['district']];
            return ['errcode' => "0", 'errmsg' => "成功",'data' => $data];
        }else{
            return ['errcode' => "1002", 'errmsg' => "失败",'data' => ""];
        }
    }





}
