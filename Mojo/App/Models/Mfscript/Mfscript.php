<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Mojo\App\Models\Mfscript;

/**
 * Description of Valuation Check
 *
 * @author Amit
 */
use Mojo\Core\Base as Base;
use Mojo\App as App;

class Mfscript {
    private $_mongo;
    private $_mongoFrontendRead;
    private $_redisReadObj;
    private $_redisMmcoreReadObj;
    private $_redisFrontendRead;
    CONST STOCK_DATA_KEYS = array('STOCK_DETAILS', 'PREVCLOSE', 'NSE', 'BSE');
    CONST STOCK_DATA_FIELDS = array('sid', 'short_name');
    CONST BENCHMARK_DATA_KEYS = array('PRICE_DETAILS', 'PREVIOUS_CLOSE');
    CONST NSE_DATA = array('stock_status', 'cmp', 'mcap', 'mcap_class','dt');
    CONST BSE_DATA = array('stock_status', 'cmp', 'mcap', 'mcap_class','dt');
    CONST PREVCLOSE_NSE = array('1D', '1W', '1M', 'YTD');
    CONST PREVCLOSE_BSE = array('1D', '1W', '1M', 'YTD');
    CONST BENCHMARKPARAM = array('1D', '1W', '1M', 'YTD');
    CONST CAPARRAY = array('Micro Cap' => 19,'Small Cap' => 19,'Mid Cap' => 18,'Large Cap' => 1,);
    CONST MCAPINDEXARRAY = array('Nifty' => 123,'Nifty Next 50' => 125,'Nifty Mid Cap' => 130,'Nifty Small Cap' => 145,);
    CONST MCAPNAMEARRAY = array('Nifty' => 'Mega Cap','Nifty Next 50' => 'Large Cap','Nifty Mid Cap' => 'Mid Cap','Nifty Small Cap' => 'Small Cap',);
    CONST STOCK_DAY_PRICE = "stock_day_price";
    CONST FEND_FIELD = array('52wk_low', '52wk_high', 'altm_low', 'altm_high', 'mcap', 'vol','dt');
    CONST CORE_MF_SCHEME_MASTER = 'mf_scheme_master';
    CONST CORE_MF_DAILY_NAV = 'mf_daily_nav';
    CONST FRONTEND_MF_FE_DAILY_NAV = 'mf_fe_daily_nav';

     public function __construct() {
        $this->_mongo = new Base\MongoDb("mmcore_write");
        $this->_mongoFrontendRead = new Base\MongoDb("mmfrontend_read");
        $redisReadConfigure = App\App::$_registry["redis"]['www_read'];
        $this->_redisReadObj = new \Mojo\Lib\RedisClient($redisReadConfigure['host'],
                                                $redisReadConfigure['port'], 
                                            $redisReadConfigure['timeout']);
        
        $redisMmcoreReadConfigure = App\App::$_registry["redis"]['mmcore_read'];
        $this->_redisMmcoreReadObj = new \Mojo\Lib\RedisClient($redisMmcoreReadConfigure['host'],
                                                            $redisMmcoreReadConfigure['port'], 
                                                            $redisMmcoreReadConfigure['timeout']
                                                        );
        
        //$redisFrontendRead = App\App::$_registry["redis"]['mmfrontend_read'];
        //$this->_redisFrontendRead = new \Mojo\Lib\RedisClient($redisFrontendRead['host'],$redisFrontendRead['port'],$redisFrontendRead['timeout']);
     }
    protected function getAllSchemeMaster($mongodata){
         $data   = array();
         $scheme = array();
         # Fetch all scheme ids
         if(!empty($mongodata['asect_name'])){
             $filter['asect_name'] = $mongodata['asect_name'];
         }
        $data = $this->_mongo->query(self::CORE_MF_SCHEME_MASTER, $mongodata['projection'], $filter);
        if(!empty($data)){
            foreach($data as $key => $value){
                if(isset($value['schemeid'])){
                   $scheme[] =  $value['schemeid'];
                }
            }
        }
        return $scheme; 
    }
    protected function getCoreNavData($schemeids) {
        echo '<pre>';
        $data      = array();
        $schdata   = array();
        $navdata   = array();
        $filter   = array();
        $projection = ['navdate','navrs','schemeid'];
        
        $schemeidskey       = array_values($schemeids);
        $filter['schemeid'] = ['$in' => $schemeidskey];
        
        $data = $this->_mongo->query(self::CORE_MF_DAILY_NAV, $projection, $filter);
       
        if(!empty($data)){
            
            foreach($data as $key => $value){
                
               $tempdata =  mongo_to_normal_date($value['navdate'],'Y-m-d');
                if(isset($value['schemeid'])){
                   $schdata[$value['schemeid']]['navdate'] =  $tempdata;
                   $schdata[$value['schemeid']]['navrs']   =  $value['navrs'];
                }
            }
        }
        else{
            $schdata[$value['schemeid']]['navdate'] =  '--';
            $schdata[$value['schemeid']]['navrs']   =  '--';
        }
        return $schdata;
     }
    protected function getFrontendNavData($schemeids) {
         
        $data      = array();
        $schdata   = array();
        $navdata   = array();
        $filter   = array();
        $projection = ['navdate','navrs','schemeid'];
        
        $schemeidskey       = array_values($schemeids);
        $filter['schemeid'] = ['$in' => $schemeidskey];
        
        $data = $this->_mongoFrontendRead->query(self::FRONTEND_MF_FE_DAILY_NAV, $projection, $filter);
       
        if(!empty($data)){
            
            foreach($data as $key => $value){
                
               $tempdata =  mongo_to_normal_date($value['navdate'],'Y-m-d');
                if(isset($value['schemeid'])){
                   $schdata[$value['schemeid']]['navdate'] =  $tempdata;
                   $schdata[$value['schemeid']]['navrs']   =  $value['navrs'];
                }
            }
        }
        else{
            $schdata[$value['schemeid']]['navdate'] =  '--';
            $schdata[$value['schemeid']]['navrs']   =  '--';
        }
        return $schdata;
     }
}

