<?php
/**
 * Created by PhpStorm.
 * User: lizhenheng
 * Date: 2017-12-26
 * Time: 下午 05:21
 */
namespace App\Utils\Weixin;



class Statics extends Weixin
{

    public function __construct()
    {
        $this->errcode = false;
    }

    /**
     * @param $data
     * @return array
     * 概况趋势(日报表)
     * 获取 累计用户数,转发次数,转发人数
     * 结束日期，限定查询1天数据，end_date允许设置的最大值为昨日
     * ['begin_date'=>20170313,'end_date'=>20170313]
     */
    public function getweanalysisappiddailysummarytrend($data){
        return $this->mxCurl('/datacube/getweanalysisappiddailysummarytrend',json_encode($data));
    }

    /**
     * @param $data
     * @return array
     * 访问趋势(日报表)
     *获取 打开次数,访问次数,访问次数,新用户数,人均停留时长 (浮点型),次均停留时长 (浮点型),平均访问深度 (浮点型)
     * 结束日期，限定查询1天数据，end_date允许设置的最大值为昨日
     * ['begin_date'=>20170313,'end_date'=>20170313]
     */
    public function getweanalysisappiddailyvisittrend($data){
        return $this->mxCurl('/datacube/getweanalysisappiddailyvisittrend',json_encode($data));
    }

    /**
     * @param $data
     * @return array
     * 访问趋势(周趋势)
     * 获取 打开次数,访问次数,访问次数,新用户数,人均停留时长 (浮点型),次均停留时长 (浮点型),平均访问深度 (浮点型)
     * 开始日期，为周一日期
     * 结束日期，为周日日期，限定查询一周数据
     * ['begin_date'=>20170313,'end_date'=>20170313]
     */
    public function getweanalysisappidweeklyvisittrend($data){
        return $this->mxCurl('/datacube/getweanalysisappidweeklyvisittrend',json_encode($data));
    }

    /**
     * @param $data
     * @return array
     * 访问趋势(月趋势)
     * 获取 打开次数,访问次数,访问次数,新用户数,人均停留时长 (浮点型),次均停留时长 (浮点型),平均访问深度 (浮点型)
     * 开始日期，为自然月第一天
     * 结束日期，结束日期，为自然月最后一天，限定查询一个月数据
     * ['begin_date'=>20170313,'end_date'=>20170313]
     */
    public function getweanalysisappidmonthlyvisittrend($data){
        return $this->mxCurl('/datacube/getweanalysisappidmonthlyvisittrend',json_encode($data));
    }

    /**
     * @param $data
     * @return array
     * 访问分布(日报表)
     * 获取 访问来源分布,访问时长分布,访问深度的分布
     * 结束日期，限定查询1天数据，end_date允许设置的最大值为昨日
     * ['begin_date'=>20170313,'end_date'=>20170313]
     */
    public function getweanalysisappidvisitdistribution($data){
        return $this->mxCurl('/datacube/getweanalysisappidvisitdistribution',json_encode($data));
    }


    /**
     * @param $data
     * @return array
     * 访问留存(日报表)
     * 获取新增用户留存,活跃用户留存
     * 结束日期，限定查询1天数据，end_date允许设置的最大值为昨日
     * ['begin_date'=>20170313,'end_date'=>20170313]
     */
    public function getweanalysisappiddailyretaininfo($data){
        return $this->mxCurl('/datacube/getweanalysisappiddailyretaininfo',json_encode($data));
    }

    /**
     * @param $data
     * @return array
     * 访问留存(周报表)
     * 获取新增用户留存,活跃用户留存
     * 开始日期，为周一日期
     * 结束日期，为周日日期，限定查询一周数据
     * ['begin_date'=>20170313,'end_date'=>20170313]
     */
    public function getweanalysisappidweeklyretaininfo($data){
        return $this->mxCurl('/datacube/getweanalysisappidweeklyretaininfo',json_encode($data));
    }

    /**
     * @param $data
     * @return array
     * 访问留存(月报表)
     * 获取新增用户留存,活跃用户留存
     * 开始日期，为自然月第一天
     * 结束日期，为自然月最后一天，限定查询一个月数据
     * ['begin_date'=>20170313,'end_date'=>20170313]
     */
    public function getweanalysisappidmonthlyretaininfo($data){
        return $this->mxCurl('/datacube/getweanalysisappidmonthlyretaininfo',json_encode($data));
    }

    /**
     * @param $data
     * @return array
     * 访问页面(日报表)
     * 获取 页面路径,访问次数,访问人数,次均停留时长,进入页次数,退出页次数,转发次数,转发人数
     * 结束日期，限定查询1天数据，end_date允许设置的最大值为昨日
     * ['begin_date'=>20170313,'end_date'=>20170313]
     * 注意：目前只提供按 (访问次数)page_visit_pv 排序的 top200
     */
    public function getweanalysisappidvisitpage($data){
        return $this->mxCurl('/datacube/getweanalysisappidvisitpage',json_encode($data));
    }

    /**
     * @param $data
     * @return array
     * 用户画像
     * 获取 新用户,活跃用户
     * 结束日期，开始日期与结束日期相差的天数限定为0/6/29，分别表示查询最近1/7/30天数据，end_date允许设置的最大值为昨日
     * ['begin_date'=>20170313,'end_date'=>20170313]
     * 注意：目前只提供按 (访问次数)page_visit_pv 排序的 top200
     */
    public function getweanalysisappiduserportrait($data){
        return $this->mxCurl('/datacube/getweanalysisappiduserportrait',json_encode($data));
    }


}