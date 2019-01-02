<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Mojo\App\Models\ValuationCheck;

/**
 * Description of Valuation Check
 *
 * @author Amit
 */
use Mojo\Core\Base as Base;
use Mojo\App as App;
use Mojo\Core\Db as Db;

class Valuationmeter {
    
    private $_mongoFrontend;
    private $_mongoWriteCore;
    private $_redisReadObj;
    private $_redisWriteObj;
    private $_redisWwwRead;
    private $_redisFrontendRead;
    private $_conn_mongo_r;
    private $_connection;
    private $_mongo;
    private $conn_mongo_w = null;
    
    private $_mongoWrite = null;
    const VALUATIONMETER_TEMP_COLLECTION = 'valuation_meter';
    private $stockData = array();
    
     public function __construct() {
        $this->conn_mongo_r = new Base\MongoDb("mmcore_read");
//        $this->conn_mongo_r = new Base\MongoDb('read'); //write
        $this->_mongoWrite = new Base\MongoDb("mmcore_write");
         $this->conn_mongo_w = new Base\MongoDb('mmcore_read');
        $this->_connection = new Db\Dbconfig('write');//create connection
//        $this->_mongoFrontendRead = new Base\MongoDb("mmfrontend_read");
     }
//     public function __destruct()
//    {
//        unset($this->conn_mongo_r);   
//        $this->_connection->closeConnection();
//        echo "</pre>";
//    }
    public function updateMeterFromTempModel($stockid = 0){
        $result  = $this->getValuationMeterFromMongo($stockid);
        $stocklist = $this->getValuationUpdateStocksList(1);
//        echo '<pre>';
//            pr($result);
//            pr($stocklist);
//            exit;
        if(!empty($result)){
                $scorelist  = array();
                $sqlinsbase = "insert into mojo_valuation_scorecard_meter ( StockID, ValuationRank, PriceFrom, PriceTo ) values ";
                foreach ($result as $sid => $data){
                    if(in_array($sid, $stocklist)){
                        continue;
                    }
                    if($this->_deleteMeterFromMySql($sid)){
                        foreach ($data as $v){
                            $scorelist[] = "( ".(int)$sid.", ".(int)$v['rank'].", ".(float)$v['price_from'].", ".(float)$v['price_to']." )";
                        }
                    }
                }
                if(!empty($scorelist)){
                    $sqlins = $sqlinsbase." ".implode ( ",", $scorelist );
                }
//                echo $sqlins;exit;
                $res = $this->_connection->execute($sqlins);
                if($res){
                    echo "Data updated Successfully";
                }else{
                    echo "Data updation failed";
                }
        }
     }
    public function getValuationMeterFromMongo($stockid = 0){
            $filter = array();
            if(!empty($stockid)){
                $filter['stockid'] = (int)$stockid;
            }
            $result = $this->conn_mongo_r->query(self::VALUATIONMETER_TEMP_COLLECTION,[],$filter);
            $newResult = array();
            if(!empty($result)){
                foreach($result as $v){
                    $sid = isset($v['stockid'])?$v['stockid']:0;
                    $priceFrom = isset($v['price_from'])?$v['price_from']:0;
                    $priceTo = isset($v['price_to'])?$v['price_to']:0;
                    $rank = isset($v['rank'])?$v['rank']:0;
                    $newResult[$sid][] = array(
                        'price_from'=>$priceFrom,
                        'price_to'=>$priceTo,
                        'rank'=>$rank
                    );
                }
            }
            unset($result);
            return $newResult;
    }
    /*if user updated from backend then only take those ids for update process*/
    public function getValuationUpdateStocksList($getUnselectedStockid  =0 ){
            $filter = array('update_date'=>date('Y-m-d'));
            $collection = 'valuation_update';
            $result  = $this->conn_mongo_w->query($collection,[],$filter);
            $stocklist = array();
            if(!empty($result[0])){               
               if($getUnselectedStockid == 1){
                   $stocklist = $result[0]['unselectedSid'];
               }else{
                   $stocklist = $result[0]['stockid'];
               }
            }
            return $stocklist;
    }
    private function _deleteMeterFromMySql($stockid =0){            
            if(!empty($stockid)){
                $sqldelete = "delete from mojo_valuation_scorecard_meter where StockID=".(int)$stockid;
//                echo $sqldelete;exit;
                if( $this->_connection->execute($sqldelete) ){
                    
                    return true;
                 }else{
                    return FALSE;
                 }
            }
        }
}