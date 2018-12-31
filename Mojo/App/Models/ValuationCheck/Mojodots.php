<?php

namespace Mojo\App\Models\ValuationCheck;

use Mojo\Core\Base as Base;
use Mojo\App as App;
use Mojo\Core\Db as Db;


class Mojodots
{
    private $conn_mongo_r;
    private $conn_mongo_w;
    private $_connection;
    private $_redisMmcoreReadObj;
    private $_redisMmcoreWriteObj;
        
    public function __construct()
    {
       $this->_connection = new Db\Dbconfig('write');//create connection//liveprice_read
       $this->conn_mongo_r = new Base\MongoDb('mmcore_read');
       $this->conn_mongo_w = new Base\MongoDb("mmcore_write");    
       
       $redisMmcoreReadConfigure = App\App::$_registry["redis"]['mmcore_read'];
        $this->_redisMmcoreReadObj = new \Mojo\Lib\RedisClient($redisMmcoreReadConfigure['host'],
                                                            $redisMmcoreReadConfigure['port'], 
                                                            $redisMmcoreReadConfigure['timeout']
                                                        );
        $redisMmcoreReadConfigure = App\App::$_registry["redis"]['mmcore_write'];
        $this->_redisMmcoreWriteObj = new \Mojo\Lib\RedisClient($redisMmcoreReadConfigure['host'],
                                                            $redisMmcoreReadConfigure['port'], 
                                                            $redisMmcoreReadConfigure['timeout']
                                                        );
        
    }
    public function __destruct()
    {
        unset($this->conn_mongo_w);
        $this->_connection->closeConnection();
    }
    public function getQVUpdateStocksList($collectionName = ''){
        
        $MBDProjection = array();
        
        $MBDFilter = array('isupdate'=> 1);
        $MBDSort   = array('update_date'=> -1);
        $MBDLimit  = array('limit'=> 1);
        $result  = $this->conn_mongo_r->query($collectionName,$MBDProjection,$MBDFilter,array(),$MBDSort,$MBDLimit);
        $stocklist = array();
        if(!empty($result))
        {
            foreach($result as $v){
                $stocklist = $v['stockid'];
            }
        }    
        return $stocklist;
            
    }
    public function getMongoQVStockFinalGrade($collectionName = '', $stockids = array()){
        
        $MBDProjection          = array('stockid','finalgrade');
        $stockfinalGradelist    = array();
        if(!empty($stockids))
        {
                $MBDFilter = array('stockid'=> array('$in' => $stockids));
                $result  = $this->conn_mongo_r->query($collectionName,$MBDProjection,$MBDFilter);
                if(!empty($result)){
                    foreach($result as $v){
                        if(isset($v['stockid'])){
                             $stockfinalGradelist[$v['stockid']]['finalgrade'] = (isset($v['finalgrade']) && $v['finalgrade'] != '') ? $v['finalgrade'] : '';
                        }
                        else{
                            $stockfinalGradelist[] = $v;
                        }
                       
                    }
                }
        }
        return $stockfinalGradelist;
    }
    public function getRedisValuationRnkTxt($stockid = ''){
        /*
         * Data for dot summary
         */
        
        $dotSummaryData['stockid'] = $stockid;
        $dotSummaryData['valKey'] = "MM:STOCK:";
        $dotSummaryData['valFields'] = "DOT_SUMMARY";
        $dotSummaryResult= $this ->getRedisPipeData($dotSummaryData,'_redisMmcoreReadObj');
        $data = array_combine($stockid,$dotSummaryResult);
        
        return $data;
    }
    private function getRedisPipeData($pipeData = '', $redisdb = '_redisMmcoreReadObj'){
        $pipe = $this->$redisdb->pipeline();
        
        foreach($pipeData['stockid'] as $sid){
            $pipe->hget($pipeData['valKey'].$sid, $pipeData['valFields']);
        }
        $result = $pipe->exec();
        $detailsData = array();
        $i = 0;
        foreach ($result as $key => $value) {
            $temp = array();
            $temp= json_decode($value, true);
            if(isset($temp['valuation_ranktext'])){
                $detailsData[$i]['quality_rank']      = $temp['quality_rank'];
                $detailsData[$i]['stk_sect']          = $temp['stk_sect'];
                $detailsData[$i]['quality_ranktext']  = $temp['quality_ranktext'];
                $detailsData[$i]['valuation_rank']    = $temp['valuation_rank'];
                $detailsData[$i]['valuation_ranktext'] = $temp['valuation_ranktext'];
                $detailsData[$i]['fin_points']        = $temp['fin_points'];
                $detailsData[$i]['fin_ranktext']      = $temp['fin_ranktext'];
            } else {
                $detailsData[$i] = $temp;
            }
            $i++;
        } 
        return $detailsData;
    }
    public function updateAwsRedisValues($redisarr){
       
        $res = 0;
        $pip = $this->_redisMmcoreWriteObj->pipeline();
       
         $flag = 0;
        foreach($redisarr as $sid => $value){
//            if($flag <= 2){
                    $k   = 'MM:STOCK:' . $sid;
                    $pip->hset($k, 'DOT_SUMMARY', json_encode($redisarr[$sid]));
//            }
//           $flag++;
           
        }
        $res = $pip->exec();
        return $res;
        
    }
}
?>