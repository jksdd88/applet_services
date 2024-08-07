<?php

namespace App\Http\Controllers\Admin\Delivery;

use App\Models\Goods;
use App\Models\Region;
use App\Models\Shipment;
use App\Models\ShipmentArea;
use App\Models\ShipmentAreaRegion;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class ShipmentController extends Controller
{

    public function __construct() {
        $this->merchant_id = Auth::user()->merchant_id;
        //$this->merchant_id  = 1;
    }

    /**
     * 运费模版列表
     * @return mixed
     */
    public function getShipments(Request $request){
        $params = $request->all();
        $offset = isset($params['offset']) ? $params['offset'] : 0;
        $limit = isset($params['limit']) ? $params['limit'] : 10;
        $wheres = array(
            array('column'=>'merchant_id', 'value'=>$this->merchant_id, 'operator'=>'='),
            array('column'=>'is_delete', 'value'=>1, 'operator'=>'=')
        );
        $data['_count'] = Shipment::get_data_count($wheres);
        $shipments = array();
        if($data['_count'] > 0){
            $tmpShipments = Shipment::get_data_list($wheres,'*',$offset,$limit);
            if($tmpShipments)
            {
                $regions = Region::where('id','>',0)->get()->toArray();
                foreach($tmpShipments as &$_shipment)
                {
                    $areaWhere = array(
                        array('column'=>'merchant_id', 'value'=>$this->merchant_id, 'operator'=>'='),
                        array('column'=>'shipment_id', 'value'=>$_shipment['id'], 'operator'=>'='),
                        array('column'=>'is_delete', 'value'=>1, 'operator'=>'='),
                    );
                    $_shipment['areas'] = ShipmentArea::get_data_list($areaWhere,'*',0,100);
                    $area_regions_tmp = [];
                    foreach($_shipment['areas'] as $ks=>$vs){
                        $areaRegionWhere = array(
                            array('column'=>'merchant_id', 'value'=>$this->merchant_id, 'operator'=>'='),
                            array('column'=>'shipment_area_id', 'value'=>$vs['id'], 'operator'=>'='),
                            array('column'=>'is_delete', 'value'=>1, 'operator'=>'='),
                        );
                        $area_regions_tmp[] = ShipmentAreaRegion::get_data_list($areaRegionWhere,'*',0,100);
                    }
                    $_shipment['area_regions'] = $area_regions_tmp;
                }
                foreach($tmpShipments as $k=> $_shipments){
                    if(isset($_shipments['areas']) && isset($_shipments['area_regions'])){
                        $areas = $_shipments['areas'];
                        $area_regions = $_shipments['area_regions'];
                        unset($_shipments['areas']);
                        unset($_shipments['area_regions']);
                        $shipments[$k] = $_shipments;
                        foreach($areas as $_areas){
                            $shipments[$k]['areas'][$_areas['id']] = $_areas;
                            $dot = '';
                            $region_name = '';
                            foreach($area_regions as $_area_regions){
                                foreach($_area_regions as $_area_regions_v) {
                                    if ($_areas['id'] == $_area_regions_v['shipment_area_id']) {
                                        //print_r($_regions);
                                        foreach ($regions as $_regions) {
                                            if ($_regions['id'] == $_area_regions_v['region_id']) {
                                                $region_name .= $dot . $_regions['title'];
                                                $dot = ',';
                                            }
                                        }
                                    }
                                }

                            }
                            $shipments[$k]['areas'][$_areas['id']]['regions']['region_name'] = $region_name;
                        }
                    }
                }

            }
        }
        //print_r($tmp);exit;
        $data['data'] = $shipments;
        return Response::json(['errcode'=>0,'_count'=>$data['_count'],'data'=>$data['data']]);
    }

    /**
     * 添加运费模版
     */
    public function postShipment(Request $request){
        $param = $request->all();
        return $this->addShipment($param);
    }

    /**
     * 读取运费模版
     */
    public function getShipment($id){
        $_shipments = $this->shipmentInfo($id);
        return Response::json(['errcode'=>0,'data'=>$_shipments]);
    }

    /**
     * 删除运费模版
     */
    public function deleteShipment($id)
    {
        //判断是否有商品在使用
        $isuse = Goods::where('shipment_id',$id)->where('merchant_id',$this->merchant_id)->count();
        if($isuse)
        {
            return Response::json(['errcode'=>1,'errmsg'=>'该运费模板已被商品使用，不允许删除']);
        }
        $info = Shipment::where('id',$id)->where('merchant_id',$this->merchant_id)->first();
        if(!$info || $info['is_delete']==-1){
            return array(['errcode'=>2,'errmsg'=>'数据不存在或已删除']);
        }
        if(Shipment::where('id',$id)->update(array('is_delete'=>-1))){
            $areas = ShipmentArea::where('shipment_id',$info['id'])->where('merchant_id',$this->merchant_id)->get();
            if($areas){
                foreach ($areas as $k=>$v){
                    if($v['id']){
                        ShipmentAreaRegion::where('shipment_area_id',$v['id'])->where('merchant_id',$this->merchant_id)->update(array('is_delete'=>-1));
                        ShipmentArea::where('id',$v['id'])->where('merchant_id',$this->merchant_id)->update(array('is_delete'=>-1));
                    }
                }
            }
            return Response::json(['errcode'=>0,'errmsg'=>'操作成功']);
        }
        return Response::json(['errcode'=>3,'errmsg'=>'操作失败']);
    }

    /**
     * 修改运费模版
     */
    public function putShipment($id,Request $request)
    {
        $data = $request->all();
        $name = isset($data['name']) ? trim($data['name']) : '';
        if(!$name){
            return Response::json(['errcode'=>0,'errmsg'=>'名称不能为空']);
        }
        $shipment = Shipment::get_data_by_id($id);
        if(!$shipment || $shipment['is_delete']!=1)
        {
            return Response::json(['errcode'=>1,'errmsg'=>'数据不存在']);
        }
        if($shipment['merchant_id']!=$this->merchant_id){
            return Response::json(['errcode'=>2,'errmsg'=>'非法请求']);
        }
        $areas = ShipmentArea::where('shipment_id',$shipment['id'])->where('merchant_id',$this->merchant_id)->get();
        if($areas){
            foreach ($areas as $k=>$v){
                if($v['id']){
                    ShipmentAreaRegion::where('shipment_area_id',$v['id'])->where('merchant_id',$this->merchant_id)->update(array('is_delete'=>-1));
                    ShipmentArea::where('id',$v['id'])->where('merchant_id',$this->merchant_id)->update(array('is_delete'=>-1));
                }
            }
        }
        if(isset($data['areas']) && $data['areas'])
        {
            foreach ($data['areas'] as $areas) {
                $areasData = array(
                    'merchant_id'=>$this->merchant_id,
                    'shipment_id' => $shipment->id,
                    'start_standard'=>$areas['start_standard'],
                    'start_fee'=>$areas['start_fee'],
                    'add_standard'=>$areas['add_standard'],
                    'add_fee'=>$areas['add_fee'],
                    'is_delete'=>1
                );
                $shipment_areas = ShipmentArea::insert_data($areasData);

                if(isset($areas['regions']['region_id'])){
                    $regions = explode(',',$areas['regions']['region_id']);
                    foreach($regions as $_regions){
                        $regionsData = array(
                            'merchant_id'=>$this->merchant_id,
                            'region_id' => $_regions,
                            'shipment_area_id'=>$shipment_areas,
                            'is_delete'=>1
                        );
                        ShipmentAreaRegion::insert_data($regionsData);
                    }

                }
            }
        }
        $shipmentData = array(
            'name' => $name,
            'valuation_type' => isset($data['valuation_type']) ? $data['valuation_type'] : '',
            'start_standard' => isset($data['start_standard']) ? $data['start_standard'] : '',
            'start_fee' => isset($data['start_fee']) ? $data['start_fee'] : '',
            'add_standard' => isset($data['add_standard']) ? $data['add_standard'] : '',
            'add_fee' => isset($data['add_fee']) ? $data['add_fee'] : '',
            'listorder' => isset($data['listorder']) ? $data['listorder'] : '',
            'is_delete'=>1
        );
        $result = Shipment::update_data($id,$shipmentData);
        if($result){
            return Response::json(['errcode'=>0,'errmsg'=>'操作成功']);
        }else{
            return Response::json(['errcode'=>3,'errmsg'=>'操作失败']);
        }
    }

    /**
     * 复制运费模板
     */
    public function copyShipment($id)
    {
        $shipment = $this->shipmentInfo($id);
        if($shipment) {
            $shipment['name'] = $shipment['name'] . ' ' . date('YmdHis');
            return $this->addShipment($shipment);
        }
        return Response::json(['errcode'=>1,'errmsg'=>'操作失败']);
    }

    //运费模版详情
    private function shipmentInfo($id){
        $_shipments = Shipment::get_data_by_id($id);
        if($_shipments){
            $areaWhere = array(
                array('column'=>'merchant_id', 'value'=>$this->merchant_id, 'operator'=>'='),
                array('column'=>'shipment_id', 'value'=>$_shipments['id'], 'operator'=>'='),
                array('column'=>'is_delete', 'value'=>1, 'operator'=>'='),
            );
            $_shipments['areas'] = ShipmentArea::get_data_list($areaWhere,'*',0,100);
            $area_regions_tmp = [];
            foreach($_shipments['areas'] as $ks=>$vs){
                $areaRegionWhere = array(
                    array('column'=>'merchant_id', 'value'=>$this->merchant_id, 'operator'=>'='),
                    array('column'=>'shipment_area_id', 'value'=>$vs['id'], 'operator'=>'='),
                    array('column'=>'is_delete', 'value'=>1, 'operator'=>'='),
                );
                $area_regions_tmp[] = ShipmentAreaRegion::get_data_list($areaRegionWhere,'*',0,100);
            }
            $_shipments['area_regions'] = $area_regions_tmp;

            $_shipments = $_shipments->toArray();
            $regions = Region::all()->toArray();
            if(isset($_shipments['areas']) && isset($_shipments['area_regions'])){
                $areas = $_shipments['areas'];
                $area_regions = $_shipments['area_regions'];
                unset($_shipments['areas']);
                unset($_shipments['area_regions']);

                foreach($areas as $area_key=>$_areas){
                    $_shipments['areas'][] = $_areas;
                    $dot = $dun = $region_name = $region_id = '';
                    foreach($area_regions as $_area_regions){
                        foreach($_area_regions as $_area_regions_v) {
                            if ($_areas['id'] == $_area_regions_v['shipment_area_id']) {
                                foreach ($regions as $_regions) {
                                    if ($_regions['id'] == $_area_regions_v['region_id']) {
                                        $region_id .= $dun . $_area_regions_v['region_id'];
                                        $region_name .= $dot . $_regions['title'];
                                        $dun = ',';
                                        $dot = '、';
                                    }
                                }
                            }
                        }
                    }
                    $_shipments['areas'][$area_key]['regions']['region_id'] = $region_id;
                    $_shipments['areas'][$area_key]['regions']['region_name'] = $region_name;
                }
            }
        }
        return $_shipments;
    }

    //添加运费模版
    private function addShipment($param){
        $name = isset($param['name']) ? trim($param['name']) : '';
        if(!$name){
            return Response::json(['errcode'=>0,'errmsg'=>'名称不能为空']);
        }
        $data = array(
            'merchant_id'=>$this->merchant_id,
            'name'=>$name,
            'valuation_type'=>isset($param['valuation_type']) ? $param['valuation_type'] : '',
            'start_standard'=>isset($param['start_standard']) ? $param['start_standard'] : '',
            'start_fee'=>isset($param['start_fee']) ? $param['start_fee'] : '',
            'add_standard'=>isset($param['add_standard']) ? $param['add_standard'] : '',
            'add_fee'=>isset($param['add_fee']) ? $param['add_fee'] : '',
            'listorder'=>isset($param['listorder']) ? $param['listorder'] : 1,
            'is_delete'=>1
        );
        //print_r($param);exit;
        $shipment = Shipment::insert_data($data);
        $areas_arr = isset($param['areas']) && $param['areas'] ? $param['areas'] : '';
        //添加地区运费模板
        if($areas_arr)
        {
            foreach ($param['areas'] as $areas) {
                $areasData = array(
                    'merchant_id'=>$this->merchant_id,
                    'shipment_id' => $shipment,
                    'start_standard'=>$areas['start_standard'],
                    'start_fee'=>$areas['start_fee'],
                    'add_standard'=>$areas['add_standard'],
                    'add_fee'=>$areas['add_fee'],
                    'is_delete'=>1
                );
                $shipment_areas = ShipmentArea::insert_data($areasData);

                if(isset($areas['regions']['region_id'])){
                    $regions = explode(',',$areas['regions']['region_id']);
                    foreach($regions as $_regions){
                        $regionsData = array(
                            'merchant_id'=>$this->merchant_id,
                            'region_id' => $_regions,
                            'shipment_area_id'=>$shipment_areas,
                            'is_delete'=>1
                        );
                        ShipmentAreaRegion::insert_data($regionsData);
                    }

                }
            }
        }
        if($shipment){
            $result = $data;
            $result['areas'] = $areas_arr;
            return Response::json(['errcode'=>0,'data'=>$result]);
        }else{
            return Response::json(['errcode'=>0,'errmsg'=>'操作失败']);
        }
    }
}
