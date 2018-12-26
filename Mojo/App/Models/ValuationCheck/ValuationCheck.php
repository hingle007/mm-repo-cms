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

class ValuationCheck {
    
    private $_mongoFrontend;
    private $_mongoWriteCore;
    private $_redisReadObj;
    private $_redisWriteObj;
    private $_redisWwwRead;
    private $_redisFrontendRead;
    private $vcolor = [ "attractive" => "green", "very attractive" => "green", "expensive" => "red", "very expensive" => "red", "risky" => "red", "fair" => "yellow" ];
    
    CONST Valuation_Grade_Temp = "valuation_grade_temp";
    CONST Valuation_Data = "valuation_data";
    CONST PRE_VALUATION_DATA = "valuation_data_pre";
    CONST UPDATE_VALUATION = "valuation_update";
    CONST STOCK_DATA_KEYS = array('STOCK_DETAILS', 'PREVCLOSE', 'NSE', 'BSE');
    CONST STOCK_DATA_FIELDS = array('sid', 'short_name');
    CONST NSE_DATA = array('stock_status', 'cmp', 'mcap', 'mcap_class','dt');
    CONST BSE_DATA = array('stock_status', 'cmp', 'mcap', 'mcap_class','dt');
    CONST PREVCLOSE_NSE = array('1D', '1W', '1M', 'YTD');
    CONST PREVCLOSE_BSE = array('1D', '1W', '1M', 'YTD');
    CONST UNSETFIELD = array('_id','lastmodified');
    CONST FETCHFIELDS = ["stockid","finalgrade","totalscore","debt","date","debt2equity","debt2equity_h_max","debt2equity_h_min","debt2equity_historic","debtlatest","debtpremium","debtpremiumpast","debtscore","debtscorepast","dividendyieldebit","ebit1yrgrowth","ebit5yrgrowth","enterprisevalue","eps","ev2ebidta","ev2ebit_h","ev2ebit_h_max","ev2ebit_h_min","ev2ebitda_h","ev2ebitda_h_max","ev2ebitda_h_min","ev2sales","ev2sales_h","ev2sales_h_max","ev2sales_h_min","evbit2roce_h","evbit2roce_h_max","evbit2roce_h_min","evbitda2roce_h","evbitda2roce_h_max","evbitda2roce_h_min","grade1","grade2","grade3","grade4"];
    CONST NOCOMPARE = ["stockid","finalgrade","totalscore","debt"];
    
    
    

     public function __construct() {
        $this->_mongo = new Base\MongoDb("mmcore_read");
        $this->_mongoWriteCore = new Base\MongoDb('mmcore_write');
        
        $this->_mongoFrontend = new Base\MongoDb("mmfrontend_read");
        
        $redisReadConfigure = App\App::$_registry["redis"]['mmcore_read'];
        $this->_redisReadObj = new \Mojo\Lib\RedisClient($redisReadConfigure['host'],
                                                            $redisReadConfigure['port'], 
                                                            $redisReadConfigure['timeout']
                                                        );
        $redisWriteConfigure = App\App::$_registry["redis"]['mmcore_write'];
        $this->_redisWriteObj = new \Mojo\Lib\RedisClient($redisWriteConfigure['host'], 
                                                            $redisWriteConfigure['port'],
                                                            $redisWriteConfigure['timeout']
                                                          );
        $redisWwwRead = App\App::$_registry["redis"]['www_read'];
        $this->_redisWwwRead = new \Mojo\Lib\RedisClient($redisWwwRead['host'],
                                                $redisWwwRead['port'], 
                                            $redisWwwRead['timeout']);
        
        $redisFrontendRead = App\App::$_registry["redis"]['mmfrontend_read'];
        $this->_redisFrontendRead = new \Mojo\Lib\RedisClient($redisFrontendRead['host'],
                                                $redisFrontendRead['port'], 
                                            $redisFrontendRead['timeout']);
     }
    
     protected function getValuationData($sdate, $edate){
        $data = array();
        
        $mongoData['collection'] = 'stock_screener';
        $mongoData['projection'] = ['sid','mcap','result'];
        $valuationCurrentData = $this ->getMcapRes($mongoData);
        /*
         * Data from redis for valuatio_data
         */
        $mongoData['projection'] = self::FETCHFIELDS;
        $mongoData['startDate'] = convertDateToMongo($sdate);
        $mongoData['endDate'] = convertDateToMongo($edate);
        $valuationPreData = $this ->getMongoData($mongoData, self::Valuation_Data);
        $data['valuationPreData'] = array();
        if(!empty($valuationPreData)){
            foreach ($valuationPreData as $key => $value) { 
                if(!empty($value['stockid'])){
                    $data['valuationPreData'][$value['stockid']] = $value;
                }    
            }
        }
        $stockid = array_keys($data['valuationPreData']);
//       $stockid = array_slice(array_keys($data['valuationPreData']),0,50);
        /*
         * Data from valuatiion_data_pre
         */
        $valPreData['projection'] = self::FETCHFIELDS;
        $valuationBeforeUpdate = $this ->getMongoData($valPreData, self::PRE_VALUATION_DATA);
        $data['valuationBeforeUpdate'] = array();
        if(!empty($valuationPreData)){
            foreach ($valuationBeforeUpdate as $key => $value) { 
                if(!empty($value['stockid'])){
                    $currentDebt = !empty($data['valuationPreData'][$value['stockid']]['debt'])?ucfirst($data['valuationPreData'][$value['stockid']]['debt']):'';
                    $previousDebt = !empty($value['debt'])?ucfirst($value['debt']):'';        
                    $data['valuationBeforeUpdate'][$value['stockid']] = $previousDebt != $currentDebt?$previousDebt.'->'.$currentDebt:'-';
                    $data['multiFieldComparison'][$value['stockid']] = $this->multiFieldCoparison($value,$data['valuationPreData'][$value['stockid']]);
                }    
            }
        }
        /*
         * Data from redis for datails
         */
        $pipeData['stockid'] = $stockid;
        $pipeData['valKey'] = "MM:STOCK:";
        $pipeData['valFields'] = "DETAILS";
        $data['detailsData'] = $this ->getRedisPipeData($pipeData);       
        /*
         * Data for dot summary
         */
        
        $dotSummaryData['stockid'] = $stockid;
        $dotSummaryData['valKey'] = "STOCK:";
        $dotSummaryData['valFields'] = "DOT_SUMMARY";
        $dotSummaryResult= $this ->getRedisPipeData($dotSummaryData);
        $data['dotSummary'] = array_combine($stockid,$dotSummaryResult);
        $data['dotSummary'] = array_map(array($this,"cftRequiredData"),$data['dotSummary']);
        /*
         * Data for Valuation Meter
         */
        
        $valuationMeter['stockid'] = $stockid;
        $valuationMeter['valKey'] = "STOCK:";
        $valuationMeter['valFields'] = "VALUATION";
        $valuationMeterResult= $this ->getRedisPipeData($valuationMeter,'_redisFrontendRead');
        $data['valuationMeter'] = array();
        if(!empty($valuationMeterResult)){
            $data['valuationMeter'] = array_combine($stockid,$valuationMeterResult);
            foreach ($data['valuationMeter'] as $meterKey => $meterValue) {
                $data['valuationMeter'][$meterKey] = !empty($meterValue['meter'])?$meterValue['meter']:array();
            }
            
        }
        /*
         * Data from mongo for current valuation
         */
        $mongoData['projection'] = ['stockid','finalgrade','totalscore'];
        $mongoData['startDate'] = convertDateToMongo($sdate);
        $mongoData['endDate'] = convertDateToMongo($edate);
        $valuationCurrentData = $this ->getMongoData($mongoData, self::Valuation_Grade_Temp);
        $data['valuationCurrentfinalgrade'] = !empty($valuationCurrentData)?array_column($valuationCurrentData,'finalgrade','stockid'):array();
        $data['valuationCurrentTotalscore'] = !empty($valuationCurrentData)?array_column($valuationCurrentData,'totalscore','stockid'):array();
        
        
        $mongoData['collection'] = 'stock_screener';
        $mongoData['projection'] = ['sid','mcap','result'];
        $resMcapData = $this ->getMcapRes($mongoData);
        $data['resMcapData'] = array();
        if(!empty($resMcapData)){
            foreach ($resMcapData as $k => $val) { 
                if(!empty($val['sid'])){
                    $data['resMcapData'][$val['sid']] = $val;
                }    
            }
        }
        
        $moslData['collection'] = 'mosl_model_portfolio_entry_exit';
        $moslData['projection'] = ['stockid','type','created_at'];
        $moslData['filter'] = [
           'stockid' => ['$in'=> $stockid]
       ];
        $moslRes = $this ->getMcapRes($moslData);
        $data['moslInOut'] = array_column($moslRes,'type','stockid');
        $data['moslCreatedAt'] = array_column($moslRes,'created_at','stockid');
        
        /*
         * Data from last 7 days
        */
        $last7thDay = date('Y-m-d', strtotime('-6 day'));
        $unselected['collection'] = self::UPDATE_VALUATION;
        $unselected['projection'] = ['update_date','unselectedSid'];
        $unselected['filter'] = [
            'update_date' => ['$gte'=> $last7thDay]
        ];
        $unselectedData = $this ->getMongoData($unselected, self::UPDATE_VALUATION);
        $unselectSid = array();
        foreach (array_column($unselectedData, 'unselectedSid') as $unsKey => $unsValue) {
            $unselectSid = array_merge($unselectSid,$unsValue);
        }
        $data['unselectSid'] = array_unique($unselectSid);
        
        /*Change Percentage data*/
        $pipeData['stockid'] = $stockid;
        $pipeData['valKey'] = "STOCK:";
        $pipeData['valFields'] = self::STOCK_DATA_KEYS;
        $pipeData['NSE_DATA'] = self::NSE_DATA;
        $pipeData['BSE_DATA'] = self::BSE_DATA;
        $pipeData['PREVCLOSE_NSE'] = self::PREVCLOSE_NSE;
        $pipeData['PREVCLOSE_BSE'] = self::PREVCLOSE_BSE;
        $pipeData['STOCK_DATA_FIELDS'] = self::STOCK_DATA_FIELDS;
        $data['changePercentageData'] = $this ->getRedisMultiKeyPipeData($pipeData);
        return($data);

    }
    
    protected function price_compare_data($sid){
        $data = array();
        
        $mongoData['projection'] = self::FETCHFIELDS;
        $mongoData['filter'] = [
                'stockid' => (int)$sid
            ];
        $valuationPreData = $this ->getMongoData($mongoData, self::Valuation_Data);
        $data['valuationPreData'] = array();
        if(!empty($valuationPreData)){
            foreach ($valuationPreData as $key => $value) { 
                if(!empty($value['date'])){
                    $value['date'] = date('d M, Y', strtotime((string)$value['date']));
                }
                if(!empty($value['stockid'])){
                    $data['valuationPreData'][$value['stockid']] = $value;
                }    
            }
        }
        $stockid = array_keys($data['valuationPreData']);
//       $stockid = array_slice(array_keys($data['valuationPreData']),0,50);
        /*
         * Data from valuatiion_data_pre
         */
        $valPreData['projection'] = self::FETCHFIELDS;
        $valPreData['filter'] = [
                'stockid' => (int)$sid
            ];
        $valuationBeforeUpdate = $this ->getMongoData($valPreData, self::PRE_VALUATION_DATA);
        $finalArr = array();
        if(!empty($valuationPreData)){
            foreach ($valuationBeforeUpdate as $key => $value) { 
                if(!empty($value['date'])){
                    $value['date'] = date('d M, Y', strtotime((string)$value['date']));
                }
                if(!empty($value['stockid'])){
//                    
//                    $currentDebt = !empty($data['valuationPreData'][$value['stockid']]['debt'])?ucfirst(substr($data['valuationPreData'][$value['stockid']]['debt'],0,1)):'';
//                    $previousDebt = !empty($value['debt'])?ucfirst(substr($value['debt'],0,1)):'';        
                    $finalArr['dataset'] = $this->priceFieldCoparison($value,$data['valuationPreData'][$value['stockid']]);
                }    
            }
        }
        $finalArr['dataset'] = array_values($finalArr['dataset']);
        $tableHeader = array();
        foreach (self::FETCHFIELDS as $headerKey => $headerVal) {
            
            if(
                !in_array($headerVal, self::NOCOMPARE)
            ){
                if($key == 'date'){
                    $width = (30 * strlen($headerVal)) + 30;
                    $tableHeader[] = array('field' => $headerVal, 'header' => $headerVal, 'width' => $width);
                } else {
                    $width = (strlen($headerVal)) + 30;
                    $tableHeader[] = array('field' => $headerVal, 'header' => $headerVal, 'width' => $width); 
                }
                
            }
        }
        $finalArr['dataHeader'] = $tableHeader;
        return($finalArr);

    }
    
    protected function cftRequiredData($data)
    {
        $f_pts = !empty($data['f_pts'])?'('.$data['f_pts'].')':'';
        $f_txt = !empty($data['f_txt'])?$data['f_txt']:'';
        return $f_txt.$f_pts;
    }    
    
    protected function multiFieldCoparison($preData,$postData)
    {
        $status = 'No';
        foreach ($preData as $key => $value) {
             if(
                    !in_array($key, self::NOCOMPARE)
                    && ($key != 'date')
                    && (
                            (empty($value) && !empty($postData[$key]))
                            ||(empty($postData[$key]) && !empty($value))
                            ||!empty($value) && !empty($postData[$key])

                       )
            ){
                if(is_numeric($value) != false && !is_nan($value) && is_numeric($postData[$key]) != false && !is_nan($postData[$key]) && (numberFormat($value) !== numberFormat($postData[$key]))){
                    $status = 'Yes';
                    break;
                }
            }    
        }
        return $status;
    }
    
    protected function priceFieldCoparison($preData,$postData)
    {
       $data['pre'] = array();
       $data['post'] = array();
        foreach ($preData as $key => $value) {
            if(
                !in_array($key, self::NOCOMPARE)
            ){ 
                if($key == 'date'){
                    $data['pre'][$key] = !empty($value)?$value:"";
                    $data['post'][$key] = !empty($postData[$key])?$postData[$key]:"";
                } else {
                    $data['pre'][$key] = !empty($value)?numberFormat($value):"";
                    $data['post'][$key] = !empty($postData[$key])?numberFormat($postData[$key]):""; 
                }
                
                
            }
        }
        return($data); 
    }
    
    protected function updateValuationRec($update_sets,$filter, $userid='',$allStock= array(),$currStock= array()){
        $result = array(); 
        if(!empty($filter) && !empty($update_sets)){
            $unselectted_stockId = array_map('intval',array_diff($allStock, array_column($filter,'stockid')));
            $last7thDay = date('Y-m-d', strtotime('-10 day'));
            $projection = ['update_date','unselectedSid'];
            $datefilter = [
                'update_date' => ['$gte'=> $last7thDay] 
            ];
            $unSid = $this->_mongo->query('valuation_update', $projection, $datefilter);
            $modifiedSid = array();
            foreach (array_column($unSid, 'unselectedSid') as $unsKey => $unsValue) {
                $modifiedSid = array_merge($modifiedSid,$unsValue);
            }
            $modifiedSid = array_unique($modifiedSid);
            if(!empty($modifiedSid)){
                $unselectted_stockId = array_merge($unselectted_stockId,$modifiedSid);
            }
            $mongoData['projection'] = ['stockid',"finalgrade","totalscore"];
            $mongoData['filter'] = [
                'stockid' => ['$nin'=> $unselectted_stockId]
            ];
            $collectionName = self::Valuation_Grade_Temp;    
            $sData = $this -> getStockData($mongoData, $collectionName);
            
            $vd_update_sets = array();
            $vd_filter = array();
            foreach ($sData as $skey => $svalue) {
                if(!in_array($svalue['stockid'], $unselectted_stockId)){
                    $vd_update_sets[] = array("finalgrade"=>$svalue['finalgrade'],"totalscore"=>(float)$svalue['totalscore']);        
                    $vd_filter[] = array("stockid"=>(int)$svalue['stockid']); 
                }
            }
            $result['mongoRes'] = $this->_mongoWriteCore->multiUpdate(self::Valuation_Data, $vd_update_sets, $vd_filter);  //mongo Update
            
            $sidList = array_column($filter, 'stockid');
            $currStock = array_slice($currStock, 0, 300);
            $unselectedSid = array_values(array_diff($currStock,$sidList));
            $valuationCurrData['update_date'] = date('Y-m-d');
            $valuationCurrData['userid'] = array($userid);
            $valuationCurrData['isupdate'] = 1;
            $valuationCurrData['isExecution'] = 0;
            $filter = [
                'update_date' => $valuationCurrData['update_date'], 
            ];
            $valuationPrevData = $this->_mongo->query('valuation_update', [], $filter);
            
            if(!empty($valuationPrevData[0]['stockid'])){
                $valuationCurrData['stockid'] = array_unique(array_merge($valuationPrevData[0]['stockid'], $sidList));
                $valuationCurrData['stockid'] = array_values($valuationCurrData['stockid']);
                $valuationCurrData['unselectedSid'] = array_unique(array_merge($valuationPrevData[0]['unselectedSid'], $unselectedSid));
                $valuationCurrData['userid'] = array_unique(array_merge($valuationPrevData[0]['userid'],$valuationCurrData['userid']));
            } else {
                $valuationCurrData['stockid'] =  $sidList;
                $valuationCurrData['unselectedSid'] = $unselectedSid;
                $valuationCurrData['userid'] = $valuationCurrData['userid'];
            }
//            pr($valuationCurrData);exit;
            $result['mongoup'] = $this->_mongoWriteCore->update('valuation_update', $valuationCurrData, $filter, $action='upsert');
            
        }
        return($result);
    }
    
    private function getRedisData($redisData = ''){
       if(!empty($redisData)){
            $valKey = $redisData['valKey'];
            $valFields = $redisData['valFields'];
            $valuationPreData = $this->_redisReadObj->hGet($valKey, $valFields);
            $data = !empty($valuationPreData)?json_decode($valuationPreData, true):array();
            return $data;
       }    
    }
    
    private function updateRedisData($sidList = array(), $redisFetchedData = array(), $dataToUpdate = array(), $redisdb = '_redisWriteObj'){
       $res = 0;
       if(!empty($sidList) && !empty($redisFetchedData) && !empty($dataToUpdate)){
            $pip = $this->$redisdb->pipeline();
            foreach($sidList as $key => $sid){  
                if (!empty($sid) && !empty($redisFetchedData[$key]) && !empty($dataToUpdate[$key]['finalgrade'])){
                    if($redisdb != '_redisWriteObj'){
                        $k = 'STOCK:' . $sid;
                        $redisFetchedData[$key]['v_txt'] = $dataToUpdate[$key]['finalgrade'];
                        $redisFetchedData[$key]['v_clr'] = $this -> vcolor[strtolower($dataToUpdate[$key]['finalgrade'])];
                    } else {
                        $k = 'MM:STOCK:' . $sid;
                        $redisFetchedData[$key]['valuation_ranktext'] = $dataToUpdate[$key]['finalgrade'];
                    }
                    $pip->hset($k, 'DOT_SUMMARY', json_encode($redisFetchedData[$key]));
                }
            }
            $res = $pip->exec();
       }  
       return $res;
    }
    
    private function getRedisPipeData($pipeData = '', $redisdb = '_redisReadObj'){
        $pipe = $this->$redisdb->pipeline();
        foreach($pipeData['stockid'] as $sid){
            $pipe->hget($pipeData['valKey'].$sid, $pipeData['valFields']);
        }
        $result = $pipe->exec();
        $detailsData = array();
        foreach ($result as $key => $value) {
            $temp = array();
            $temp= json_decode($value, true);
            if(!empty($temp['stockid'])){
                $detailsData[$temp['stockid']] = $temp;
            } else {
                $detailsData[] = $temp;
            }
        } 
        return $detailsData;
    }
    
    private function getMongoData($mongoData = '', $collectionName){
       $startDate = !empty($mongoData['startDate'])?convertDateToMongo($mongoData['startDate']):"";
       $endDate = !empty($mongoData['startDate'])?convertDateToMongo($mongoData['endDate']):"";

       $filter = [];
       if(!empty($mongoData['filter'])){
        $filter = $mongoData['filter'];
       }
       $valuationCurrentData = $this->_mongo->query($collectionName, $mongoData['projection'], $filter);
       return $valuationCurrentData;
    }
    
    private function getMcapRes($mongoData = ''){
       $filter = [];
       if(!empty($mongoData['filter'])){
        $filter = $mongoData['filter'];
       }
       $data = $this->_mongoFrontend->query($mongoData['collection'], $mongoData['projection'], $filter);
       return $data;
    }
    
    private function getRedisMultiKeyPipeData($pipeData = ''){
        $pipe = $this->_redisWwwRead->pipeline();
        foreach($pipeData['stockid'] as $sid){
            $key = $pipeData['valKey'] . $sid;
            $pipe->hmget($key,$pipeData['valFields']);           
        }
        $result = $pipe->exec();
        if(!empty($pipeData['type'])){
            return $result;
        }
        $detailsData = array();
        if(!empty($result)){
            foreach ($result as $key => $v) {
                $temp = array();
                $stockDetails = isset($v[0])?json_decode($v[0],true):(!empty($v['STOCK_DETAILS'])?json_decode($v['STOCK_DETAILS'],true):array());
//                $temp['stockDet']['sid'] = !empty($stockDetails['sid'])?$stockDetails['sid']:'';
//                $temp['stockDet']['sname'] = !empty($stockDetails['short_name'])?$stockDetails['short_name']:'';
                
                $temp['stockDet'] = array();
                if(!empty($stockDetails) && !empty($pipeData['STOCK_DATA_FIELDS'])){
                    foreach($pipeData['STOCK_DATA_FIELDS'] as $stfK => $stfV){
                        $temp['stockDet'][$stfV] = !empty($stockDetails[$stfV])?$stockDetails[$stfV]:"";
                    }
                }
                
                $PREVCLOSE = isset($v[1])?json_decode($v[1],true):(!empty($v['PREVCLOSE'])?json_decode($v['PREVCLOSE'],true):array());
                $temp['prevCloseBse'] = array();
                if(!empty($PREVCLOSE['bse']) && !empty($pipeData['PREVCLOSE_BSE'])){
                    foreach($pipeData['PREVCLOSE_BSE'] as $bseK => $bseV){
                        $temp['prevCloseBse'][$bseV] = !empty($PREVCLOSE['bse'][$bseV])?$PREVCLOSE['bse'][$bseV]:"";
                    }
                }

                $temp['prevCloseNse'] = array();
                if(!empty($PREVCLOSE['nse']) && !empty($pipeData['PREVCLOSE_NSE'])){
                    foreach($pipeData['PREVCLOSE_NSE'] as $nseK => $nseV){
                        $temp['prevCloseNse'][$nseV] = !empty($PREVCLOSE['nse'][$nseV])?$PREVCLOSE['nse'][$nseV]:"";
                    }
                }

                $NSE_data = isset($v[2])?json_decode($v[2],true):(!empty($v['NSE'])?json_decode($v['NSE'],true):array());
                $temp['NSE_data'] = array();
                if(!empty($NSE_data) && !empty($pipeData['NSE_DATA'])){
                    foreach($pipeData['NSE_DATA'] as $nseDK => $nseDV){
                        if($nseDV == 'cmp')
                            $temp['NSE_data'][$nseDV] = !empty($NSE_data[$nseDV])?$NSE_data[$nseDV]:0;
                        else 
                            $temp['NSE_data'][$nseDV] = !empty($NSE_data[$nseDV])?$NSE_data[$nseDV]:"";
                    }
                }

                $BSE_data = isset($v[3])?json_decode($v[3],true):(!empty($v['BSE'])?json_decode($v['BSE'],true):array());
                $temp['BSE_data'] = array();
                if(!empty($BSE_data) && !empty($pipeData['BSE_DATA'])){
                    foreach($pipeData['BSE_DATA'] as $bseDK => $bseDV){
                        if($bseDV == 'cmp')
                            $temp['BSE_data'][$bseDV] = !empty($BSE_data[$bseDV])?$BSE_data[$bseDV]:0;
                        else 
                            $temp['BSE_data'][$bseDV] = !empty($BSE_data[$bseDV])?$BSE_data[$bseDV]:"";
                    }
                }
                if(!empty($stockDetails['sid'])){
                    $detailsData[$stockDetails['sid']] = $temp;
                } else {
                    $detailsData[] = $temp;
                }
                
            } 
        }
//        pr($detailsData);exit;
        return $detailsData;
    }
    
    protected function getStockData($mongoData = '', $collectionName){
        $filter = [];
//        if(!empty($mongoData['filter'])){
//         $filter = $mongoData['filter'];
//        }
        $data = $this->_mongo->query($collectionName, $mongoData['projection'], $filter);
       return $data;
    }
    
    protected function dumpPreValuationData(){
        $result = 0;
        $mongoData['projection'] = [];
        $valuationPreData = $this ->getMongoData($mongoData, self::Valuation_Data);
        if(!empty($valuationPreData)){
            foreach ($valuationPreData as $value) {
                foreach (self::UNSETFIELD as $k => $val) {
                    unset($value[$val]);
                }
                $update_sets[] = $value;        
                $filter[] = array("stockid"=>(int)$value['stockid']); 
            }
            $result = $this->_mongoWriteCore->multiUpdate(self::PRE_VALUATION_DATA, $update_sets, $filter);  //mongo Update
        }
        return $result;
    }
    
    
}

