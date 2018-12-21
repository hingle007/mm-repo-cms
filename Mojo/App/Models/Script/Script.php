<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Mojo\App\Models\Script;

/**
 * Description of Valuation Check
 *
 * @author Amit
 */
use Mojo\Core\Base as Base;
use Mojo\App as App;

class Script {
    private $_mongo;
    private $_mongoRead;
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
    CONST QUALITY_COLLECTIONNAME = 'quality_data';
    CONST VALUATION_COLLECTIONNAME = 'valuation_data';

     public function __construct() {
        $this->_mongo = new Base\MongoDb("mmcore_write");
        $this->_mongoRead = new Base\MongoDb("mmcore_read");
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
     
     protected function getRequiredMfData(){ 
        $finalSet = array(); 
        $filter = [
            'primary_fund'=>'T'
        ];
        $projection =  ['schemeid','scheme_name'];
        $schemeData = $this->_mongoRead->query('mf_scheme_master',$projection,$filter);
        $schemeid = array_column($schemeData, 'scheme_name', 'schemeid');
        $finalSet['schemeid'] = $schemeid;
        $schemeid = array_keys($schemeid);
        if(!empty($schemeid)){
            foreach (array_chunk($schemeid, 1000) as $key => $value) {
                $data = array();
                $filter = [
                    'schemeid'=>['$in'=>$value]
                ];
                $projection =  ['schemeid','stockid'];
                $data = $this->_mongoRead->query('mf_scheme_portfolio',$projection,$filter);
                foreach ($data as $eachKey => $eachValue) {
                    if(!empty($eachValue['stockid'])){
                        $finalSet['mongo'][$eachValue['schemeid']][] = $eachValue['stockid'];
                    }    
                }
                
                $MSPAWS['stockid'] = $value;
                $MSPAWS['valKey'] = "MM:SCHEME:";
                $MSPAWS['valFields'] = "MF_SCHEME_PORTFOLIO";
                $MSPAWSRes= $this ->getRedisPipeDataSinglKey($MSPAWS ,'_redisMmcoreReadObj');
                $MSPAWSRes = array_combine($value,$MSPAWSRes);
                foreach ($MSPAWSRes as $awskey => $awsvalue) {
                    if(!empty($awsvalue)){
                        $temp = array();
                        $temp = array_column($awsvalue, 'stockid');

    //                    pr($temp);exit;
                        $finalSet['awsRedis'][$awskey] =!empty($temp)?array_filter($temp):[];
                    }
                }
            }
        }    
        return $finalSet;
     }
    
     protected function getCFTRequiredData(){
         $data = array();
         
        /*
         * Data from redis for datails
         */
        $redisData['valKey'] = "";
        $redisData['valFields'] = "ALLSTOCKS";
        $allStock = $this ->getRedisData($redisData);
        $stockid = array_values($allStock);
//        $stockid = array_slice(array_values($allStock),0,40);
//        $stockid = array('399834');
        /*
         * Data for frontend dot summary
         */
        $dotSummaryFend['stockid'] = $stockid;
        $dotSummaryFend['valKey'] = "STOCK:";
        $dotSummaryFend['valFields'] = "DOT_SUMMARY";
        $data['dotSummaryFend']= $this ->getRedisPipeDataSinglKey($dotSummaryFend);
        /*
         * Data for AWS Redis dot summary
         */
        $dotSummaryAWS['stockid'] = $stockid;
        $dotSummaryAWS['valKey'] = "MM:STOCK:";
        $dotSummaryAWS['valFields'] = "DOT_SUMMARY";
        $dotSummaryAWSRes= $this ->getRedisPipeDataSinglKey($dotSummaryAWS ,'_redisMmcoreReadObj');
        $data['dotSummaryAWS'] = array_combine($stockid,$dotSummaryAWSRes);
        
        /*
         * Data for frontend finpoints quater data
         */
        $finParamFend['stockid'] = $stockid;
        $finParamFend['valKey'] = "STOCK:";
        $finParamFend['valFields'] = "FIN_DETAILS";
        $finParamFendRes = $this ->getRedisPipeDataSinglKey($finParamFend);
        $finParamFendRes = array_combine($stockid,$finParamFendRes);
        $data['FIN_DETAILS'] = array();
        foreach (array_filter($finParamFendRes) as $finkey => $finvalue) {
            $data['FIN_DETAILS'][$finkey] = end($finvalue);
        }
        
        /*
         * Data for AWS Redis finpoints quater data
         */
        $finParamAWS['stockid'] = $stockid;
        $finParamAWS['valKey'] = "MM:STOCK:";
        $finParamAWS['valFields'] = "FIN_MOJO_PTS";
        $finParamAWSRes= $this ->getRedisPipeDataSinglKey($finParamAWS,'_redisMmcoreReadObj');
        $finParamAWSRes = array_combine($stockid,$finParamAWSRes);
        $data['FIN_MOJO_PTS'] = array();
        foreach (array_filter($finParamAWSRes) as $finkey => $finvalue) {
            $data['FIN_MOJO_PTS'][$finkey] = end($finvalue);
        }
        /*
         * Data for frontend FIN_POS_NEG
         */
        $FIN_POS_NEG_Fend['stockid'] = $stockid;
        $FIN_POS_NEG_Fend['valKey'] = "STOCK:";
        $FIN_POS_NEG_Fend['valFields'] = "FIN_POS_NEG";
        $FIN_POS_NEG_FendRes = $this ->getRedisPipeDataSinglKey($FIN_POS_NEG_Fend);
        $FIN_POS_NEG_FendRes = array_combine($stockid,$FIN_POS_NEG_FendRes);
        $data['FIN_POS_NEG'] = array();
        foreach (array_filter($FIN_POS_NEG_FendRes) as $fnkey => $fnvalue) {
            $data['FIN_POS_NEG'][$fnkey] = !empty($fnvalue['quarter'])?$fnvalue['quarter']:"";
        }
        
        /*
         * Data for AWS Redis finpoints quater data
         */
        $FIN_DETAILS_AWS['stockid'] = $stockid;
        $FIN_DETAILS_AWS['valKey'] = "MM:STOCK:";
        $FIN_DETAILS_AWS['valFields'] = "FIN_DETAILS";
        $FIN_DETAILS_AWS_Res = $this ->getRedisPipeDataSinglKey($FIN_DETAILS_AWS,'_redisMmcoreReadObj');
        $FIN_DETAILS_AWS_Res = array_combine($stockid,$FIN_DETAILS_AWS_Res);
        $data['FIN_DETAILS_AWS'] = array();
        foreach (array_filter($FIN_DETAILS_AWS_Res) as $inkey => $invalue) {
            $allKey = array();
            $allKey =  array_keys($invalue);
            $data['FIN_DETAILS_AWS'][$inkey] = end($allKey);
        }
        /*
         * Data for AWS Redis Technical data
         * hget MM:STOCKHIST:399834 technicals
         */
        $technicalsAWS['stockid'] = $stockid;
        $technicalsAWS['valKey'] = "MM:STOCKHIST:";
        $technicalsAWS['valFields'] = "technicals";
        $technicalsAWSRes= $this ->getRedisPipeDataSinglKey($technicalsAWS ,'_redisMmcoreReadObj');
        $combinedSid = array_combine($stockid,$technicalsAWSRes);
        $finalTechSet = array();
        if(!empty($combinedSid)){
            foreach ($combinedSid as $tkey => $tvalue) {
                if(!empty($tvalue)){
                    $finalTechSet[$tkey] = $this->getTechDot($tvalue);
                }    
            }
        }
        $data['technicalsAWS'] = $finalTechSet;
        /*
         * Data for Stock Details
         */
        $dotSummaryAWS['stockid'] = $stockid;
        $dotSummaryAWS['valKey'] = "STOCK:";
        $dotSummaryAWS['valFields'] = "STOCK_DETAILS";
        $data['stockdetails']= $this ->getRedisPipeDataSinglKey($dotSummaryAWS ,'_redisReadObj');
        
        return($data);
        
     }
     
     protected function getMCAPRequiredData(){
         $data = array();
         
        /*
         * Data from redis for datails
         */
        $redisData['valKey'] = "";
        $redisData['valFields'] = "ALLSTOCKS";
        $allStock = $this ->getRedisData($redisData);
        $stockid = array_values($allStock);
//        $stockid = array_slice(array_values($allStock),0,40);
//        $stockid = array('399834');
        
        /*
         * Data for frontend BSE
         */
        $BSE_Param_Fend['stockid'] = $stockid;
        $BSE_Param_Fend['valKey'] = "STOCK:";
        $BSE_Param_Fend['valFields'] = "BSE";
        $BSEDataRes = $this ->getRedisPipeDataSinglKey($BSE_Param_Fend);
        $BSEDataRes = array_combine($stockid,$BSEDataRes);
        $data['Fend_BSE_Data'] =  array();
        if(!empty($BSEDataRes)){
            foreach ($BSEDataRes as $FBkey => $FBvalue) {
                $temp = array();
                foreach (self::FEND_FIELD as $bkey => $bvalue) {
                    $temp[$bvalue] = !empty($FBvalue[$bvalue])?$FBvalue[$bvalue]:"";
                }
                $data['Fend_BSE_Data'][$FBkey] = $temp;
            }
            
        }
        /*
         * Data for frontend BSE
         */
        $NSE_Param_Fend['stockid'] = $stockid;
        $NSE_Param_Fend['valKey'] = "STOCK:";
        $NSE_Param_Fend['valFields'] = "NSE";
        $NSEDataRes = $this ->getRedisPipeDataSinglKey($NSE_Param_Fend);
        $NSEDataRes = array_combine($stockid,$NSEDataRes);
        $data['Fend_NSE_Data'] =  array();
        if(!empty($NSEDataRes)){
            foreach ($NSEDataRes as $FNkey => $FNvalue) {
                $temp = array();
                foreach (self::FEND_FIELD as $nkey => $nvalue) {
                    $temp[$nvalue] = !empty($FNvalue[$nvalue])?$FNvalue[$nvalue]:"";
                }
                $data['Fend_NSE_Data'][$FNkey] = $temp;
            }
            
        }
        /*
         * Data for AWS Redis PRICE_INFO
         */
        $PRICE_INFO_AWS['stockid'] = $stockid;
        $PRICE_INFO_AWS['valKey'] = "MM:STOCK:";
        $PRICE_INFO_AWS['valFields'] = "PRICE_INFO";
        $PRICE_INFO_AWS_Res= $this ->getRedisPipeDataSinglKey($PRICE_INFO_AWS ,'_redisMmcoreReadObj');
        $PRICE_INFO_AWS_Res = array_combine($stockid,$PRICE_INFO_AWS_Res);
        $data['AWS_BSE_Data'] =  array();
        $data['AWS_NSE_Data'] =  array();
        if(!empty($PRICE_INFO_AWS_Res)){
            foreach ($PRICE_INFO_AWS_Res as $Akey => $Avalue) {
                $fieldArray = array('mcap','vol');
                $tempB = array();
                $tempN = array();
                    foreach ($fieldArray as $b_key => $b_value) {
                        $tempB[$b_value] = !empty($Avalue['bse'][$b_value])?$Avalue['bse'][$b_value]:"";
                    }
                
                    foreach ($fieldArray as $n_key => $n_value) {
                        $tempN[$n_value] = !empty($Avalue['nse'][$n_value])?$Avalue['nse'][$n_value]:"";
                    }
                $data['AWS_BSE_Data'][$Akey] = $tempB;
                $data['AWS_NSE_Data'][$Akey] = $tempN;
            }
            
        }
        
        /*
         * Data for AWS Redis PREVCLOSE_INFO
         */
        $PREVCLOSE_INFO_AWS['stockid'] = $stockid;
        $PREVCLOSE_INFO_AWS['valKey'] = "MM:STOCK:";
        $PREVCLOSE_INFO_AWS['valFields'] = "PREVCLOSE_INFO";
        $PREVCLOSE_INFO_AWS_Res= $this ->getRedisPipeDataSinglKey($PREVCLOSE_INFO_AWS ,'_redisMmcoreReadObj');
        $PREVCLOSE_INFO_AWS_Res = array_combine($stockid,$PREVCLOSE_INFO_AWS_Res);
        if(!empty($PREVCLOSE_INFO_AWS_Res)){
            foreach ($PREVCLOSE_INFO_AWS_Res as $Akey => $Avalue) {
                $BSEfieldArray = array('bse_low','bse_high','bse_altm_high','bse_altm_low');
                $NSEfieldArray = array('nse_low','nse_high','nse_altm_high','nse_altm_low');
                $tempB = array();
                $tempN = array();
                    foreach ($BSEfieldArray as $b_key => $b_value) {
                        $tempB[$b_value] = !empty($Avalue['bse'][$b_value])?$Avalue['bse'][$b_value]:"";
                    }
                
                    foreach ($NSEfieldArray as $n_key => $n_value) {
                        $tempN[$n_value] = !empty($Avalue['nse'][$n_value])?$Avalue['nse'][$n_value]:"";
                    }
                $data['AWS_BSE_Data'][$Akey] = !empty($data['AWS_BSE_Data'][$Akey])?array_merge($data['AWS_BSE_Data'][$Akey],$tempB):$tempB;
                $data['AWS_NSE_Data'][$Akey] = !empty($data['AWS_NSE_Data'][$Akey])?array_merge($data['AWS_NSE_Data'][$Akey],$tempN):$tempN;
            }
            
        }
        
        /*
         * Data for Stock Details
         */
        $stockDetails['stockid'] = $stockid;
        $stockDetails['valKey'] = "STOCK:";
        $stockDetails['valFields'] = "STOCK_DETAILS";
        $data['stockdetails']= $this ->getRedisPipeDataSinglKey($stockDetails,'_redisReadObj');
        $data['stockdetails'] = array_column($data['stockdetails'], 'short_name','sid');
        return($data);
        
     }
     
     protected function getRequiredData(){
        $data = array();
        
//       Data from redis for ALLSTOCKS
       $redisData['valKey'] = "";
       $redisData['valFields'] = "ALLSTOCKS";
       $allStock = $this ->getRedisData($redisData);
       $stockid = array_values($allStock);
//       $stockid = array_slice(array_values($allStock),0,40);
//        $stockid = array('399834');
        
//       Data from redis for stock datails
        $pipeData['stockid'] = $stockid;
        $pipeData['valKey'] = "STOCK:";
        $pipeData['valFields'] = self::STOCK_DATA_KEYS;
        $pipeData['NSE_DATA'] = self::NSE_DATA;
        $pipeData['BSE_DATA'] = self::BSE_DATA;
        $pipeData['PREVCLOSE_NSE'] = self::PREVCLOSE_NSE;
        $pipeData['PREVCLOSE_BSE'] = self::PREVCLOSE_BSE;
        $pipeData['STOCK_DATA_FIELDS'] = self::STOCK_DATA_FIELDS;
        $data = $this ->getRedisPipeData($pipeData);
        
        $benchMarkParam['stockid'] = self::CAPARRAY;
        $benchMarkParam['valKey'] = "INDEX:";
        $benchMarkParam['valFields'] = self::BENCHMARK_DATA_KEYS;
        $benchMarkParam['type'] = 'benchmark';
        $benchMarkData = $this ->getRedisPipeData($benchMarkParam);
        if(!empty($benchMarkData)){
            $data['benchMark'] = $this->calculateBenchmark($benchMarkData,self::CAPARRAY);
        }
//        if(!empty($type) && $type == 'mcapIndexCalc'){
            $mcapIndexParam['stockid'] = self::MCAPINDEXARRAY;
            $mcapIndexParam['valKey'] = "INDEX:";
            $mcapIndexParam['valFields'] = self::BENCHMARK_DATA_KEYS;
            $mcapIndexParam['type'] = 'benchmark';
            $mcapIndexData = $this ->getRedisPipeData($benchMarkParam);
            if(!empty($benchMarkData)){
                $data['mcapIndexData'] = $this->calculateBenchmark($benchMarkData,self::MCAPINDEXARRAY);
            }
//        }
        return $data;
    }
    
    
    protected function getLastTenDData(){
        $data = array();
        
//       Data from redis for ALLSTOCKS
       $redisData['valKey'] = "";
       $redisData['valFields'] = "ALLSTOCKS";
       $allStock = $this ->getRedisData($redisData);
       $stockid = array_values($allStock);
//       $stockid = array_slice(array_values($allStock),0,40);
//        $stockid = array(1003058);
       if(!empty($stockid)){
            $pipeData['stockid'] = $stockid;
            $pipeData['valKey'] = "STOCK:";
            $pipeData['valFields'] = self::STOCK_DATA_KEYS;
            $pipeData['NSE_DATA'] = self::NSE_DATA;
            $pipeData['BSE_DATA'] = self::BSE_DATA;
            $pipeData['STOCK_DATA_FIELDS'] = array('sid', 'short_name','scripcode','symbol');
            $data['stockDetails'] = $this ->getRedisPipeData($pipeData);
            $stockDet = array_column($data['stockDetails'],'stockDet');
            if(!empty($data['stockDetails'])){
                $bseSid = array_filter(array_column($stockDet, 'scripcode','sid'));
                $nseSid = array_filter(array_column($stockDet, 'symbol','sid'));
                $data['common'] = array_intersect_key($bseSid,$nseSid);
                $data['bse'] = array_diff_key($bseSid,$data['common']);
                $data['nse'] = array_diff_key($nseSid,$data['common']);
            }
            
            $histPriceParam['stockid'] = array(1);
            $histPriceParam['valKey'] = "INDEX:";
            $histPriceParam['valFields'] = 'HIST_PRICE';
            $histPriceParam['type'] = 'HIST_PRICE';
            $histPriceData = $this ->getRedisPipeData($histPriceParam);
            if(!empty($histPriceData)){
                $histPriceData = $histPriceData[0];
                $histPrice = isset($histPriceData[0])?json_decode($histPriceData[0],true):json_decode($histPriceData['HIST_PRICE'],true);
                $data['TenthHistDate'] = !empty($histPrice)?key(array_slice($histPrice, -10, 1, true)):'';
            }
       }
        return $data;

    }
    
    private function    calculateBenchmark($benchMarkData = '',$capKeys){
        $dataset = array();
        $capKeys = array_keys($capKeys);
        if(!empty($benchMarkData)){
            foreach ($benchMarkData as $key => $eachDataSet) {
                $dataset[$capKeys[$key]]['priceDetails'] = isset($eachDataSet[0])?json_decode($eachDataSet[0],true):json_decode($eachDataSet['PRICE_DETAILS'],true);
                $dataset[$capKeys[$key]]['previousClose'] = isset($eachDataSet[1])?json_decode($eachDataSet[1],true):json_decode($eachDataSet['PREVIOUS_CLOSE'],true);  
        } 
            
        } 
        return $dataset;
    }
    
    
    private function getRedisData($redisData = '',$type = ''){
       if(!empty($redisData)){
            $valKey = $redisData['valKey'];
            $valFields = $redisData['valFields'];
            if(!empty($type)){
                $stockData = $this->_redisReadObj->hGet($valKey, $valFields);
            } else {
                $stockData = $this->_redisReadObj->GET($valFields);
            }
            
            $data = !empty($stockData)?json_decode($stockData, true):array();
            return $data;
       }    
    }
    
    private function getRedisPipeDataSinglKey($pipeData = '', $dbRef = '_redisReadObj'){
        $pipe = $this->$dbRef->pipeline();
        foreach($pipeData['stockid'] as $sid){
            $pipe->hget($pipeData['valKey'].$sid, $pipeData['valFields']);
        }
        $result = $pipe->exec();
        $detailsData = array();
        foreach ($result as $key => $value) {
            $temp = array();
            $temp= json_decode($value, true);
            if(!empty($temp['sid'])){
                $detailsData[$temp['sid']] = $temp;
            } else {
                $detailsData[] = $temp;
            }
            
        } 
        return $detailsData;
    }
    
    private function getRedisPipeData($pipeData = ''){
        $pipe = $this->_redisReadObj->pipeline();
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

                $detailsData[$stockDetails['sid']] = $temp;
            } 
        }
//        pr($detailsData);exit;
        return $detailsData;
    }
    
    protected function getPriceData($mongoData){
        $data = array();
        if(!empty($mongoData['value'])){
            $filter['stockid'] = ['$in' => $mongoData['value']];
        }
        if(!empty($mongoData['exch'])){
            $filter['exch'] = $mongoData['exch'];
        }
        if(!empty($mongoData['date'])){
            $filter['date'] = ['$gte' => $mongoData['date']];
        }
        $data = $this->_mongo->query(self::STOCK_DAY_PRICE, $mongoData['projection'], $filter);
        return $data;
    }
    
    protected function getTechnicalStatus($tech_score,$error_flag)
    {
         $tech_status = $tech_clr = "";

         if($error_flag == '1' || ($tech_score == "" && $tech_score!= 0))
         {
             $tech_status = "Does not qualify";
             $tech_clr = "Grey";
         }
         else
         {

             if ( $tech_score > 1 )
             {
                 $tech_status = "Bullish";
                 $tech_clr = "Green";
             }
             elseif ( $tech_score > 0.1 && $tech_score <= 1 )
             {
                 $tech_status = "Mildly Bullish";
                 $tech_clr = "Green";
             }
             elseif ( $tech_score > -0.1 && $tech_score <= 0.1 )
             {
                 $tech_status = "Sideways";
                 $tech_clr = "Yellow";
             }
             elseif ( $tech_score > -1 && $tech_score <= -0.1 )
             {
                 $tech_status = "Mildly Bearish";
                 $tech_clr = "Red";
             }
             elseif ( $tech_score <= -1 )
             {
                 $tech_status = "Bearish";
                 $tech_clr = "Red";
             }

         }

         $result['tech_status'] = $tech_status;
         $result['tech_clr'] = $tech_clr;

         return $result;

    }
   /*
    * Author: Nayana Rane
    * return technical dot info
    * Input: $redis_data: STOCKHIST: MOMENTUM key
    *       
    */
    private function getTechDot($redis_data)
    {    
         $final_dot = array();
         if(isset($redis_data) && !empty($redis_data))
         {
             $tech_score = (isset($redis_data) && !isset($redis_data['momentum']['code']) && isset($redis_data['momentum']['fdv']) && $redis_data['momentum']['fdv']!='' && $redis_data['momentum']['fdv']!=null)?numberFormat($redis_data['momentum']['fdv']):'';

             //if error present then technical dot does not qualify
             $error = (isset($redis_data['momentum']['code']))?1:0;

             //get technical dot text and color
             $technical_dot = $this->getTechnicalStatus($tech_score,$error);
             $final_dot['tech_score'] =  $tech_score;
             $final_dot['tech_txt'] = $technical_dot['tech_status'];
             $final_dot['tech_clr'] = $technical_dot['tech_clr'];

         }
         else
         {
             $final_dot['tech_score'] =  '';
             $final_dot['tech_txt'] = '';
             $final_dot['tech_clr'] = '';
         }

         return $final_dot;
    }
    private function getRankDirecion($curr_rank, $prev_rank)
    {
        if($curr_rank < $prev_rank)
            $dir = 1;
        else if($curr_rank > $prev_rank)
            $dir = -1;
        else
            $dir = 0;
        
        return $dir;
    }
    protected function getAllRedisStockData()
    {
        $data = array();
//       Data from redis for ALLSTOCKS
        $redisData['valKey'] = "";
        $redisData['valFields'] = "ALLSTOCKS";
        $allStock = $this ->getRedisData($redisData);
        $stockid = array_values($allStock);
        return $stockid;
    }
    protected function getRedisDotSummary($stockid,$fieldName)
    {
       /*
         * Data for AWS Redis dot summary
         */
        $dotSummaryAWSRes = array();
        $dotSummaryAWS['stockid'] = $stockid;
        $dotSummaryAWS['valKey'] = "MM:STOCK:";
        $dotSummaryAWS['valFields'] = "DOT_SUMMARY";
        $dotSummaryAWSRes= $this ->getRedisQtextValue($dotSummaryAWS ,'_redisMmcoreReadObj',$fieldName);
        
        return $dotSummaryAWSRes;
    }
    private function getRedisQtextValue($pipeData = '', $dbRef = '_redisReadObj', $fieldName = ''){
        $detailsData = array();
        $pipe = $this->$dbRef->pipeline();
        foreach($pipeData['stockid'] as $sid){
            $pipe->hget($pipeData['valKey'].$sid, $pipeData['valFields']);
        }
        $result = $pipe->exec();
        foreach ($result as $key => $value) {
            $temp = array();
            $temp= json_decode($value, true);
            if(!empty($temp[$fieldName])){
                $detailsData[] = $temp[$fieldName];
            } else {
                $detailsData[] = '';
            }
        } 
        $FIN_DETAILS_AWS_Res = array_combine($pipeData['stockid'],$detailsData);
//        echo '<pre>';
//        echo count($FIN_DETAILS_AWS_Res)." --- ".count($pipeData['stockid']);
//        print_r($FIN_DETAILS_AWS_Res);
//        exit;
        return $FIN_DETAILS_AWS_Res;
    }
    protected function getMongoStockData($collectionType)
    {       
        $stockGradeArray = array();
        if($collectionType == 'quality')
            $collectionName = self::QUALITY_COLLECTIONNAME;
	elseif($collectionType == 'valuation')
            $collectionName = self::VALUATION_COLLECTIONNAME;
			
	if(!empty($filter)){
            //$filter['stockid'] = ['$in' => $mongoData['value']];
        }
        else{
            $filter = [];
        }
	$mongoData['projection'] = ['stockid','finalgrade'];	
        $qualityCurrentData = $this->_mongo->query($collectionName, $mongoData['projection'], $filter);
      
        if(!empty($qualityCurrentData)){
           foreach($qualityCurrentData as $key => $value){
               if(!empty($value['finalgrade'])){
                 $stockGradeArray[$value['stockid']] =  (isset($value['finalgrade'])) ? $value['finalgrade'] : "";
               }
           }
       }
       return $stockGradeArray;
    }
    protected function getMongoFinalgradeValue($mongoData) {
        $stockGradeArray = array();
        if(!empty($mongoData)){
            $filter['stockid'] = ['$in' => $mongoData];
        }
	$mongoData['projection'] = ['stockid','finalgrade'];	
        $valuationCurrentData = $this->_mongo->query('quality_data', $mongoData['projection'], $filter);
      
        if(!empty($valuationCurrentData)){
           foreach($valuationCurrentData as $key => $value){
               if(!empty($value['finalgrade'])){
                 $stockGradeArray[$value['stockid']] =  $value['finalgrade'];
               }
           }
       }
       return $stockGradeArray;
    }
    protected function getStockDetails($stock_ids) {
        /*
         * Data for Stock Details
         */
        $stockDetails['stockid'] = $stock_ids;
        $stockDetails['valKey'] = "STOCK:";
        $stockDetails['valFields'] = "STOCK_DETAILS";
        $data['stockdetails']= $this ->getRedisPipeStockDetailData($stockDetails,'_redisReadObj');
        //$data['stockdetails'] = array_column($data['stockdetails'], 'short_name','sid');
        return($data);
    }
    private function getRedisPipeStockDetailData($pipeData = '', $dbRef = '_redisReadObj'){
        $pipe = $this->$dbRef->pipeline();
        foreach($pipeData['stockid'] as $sid){
            $pipe->hget($pipeData['valKey'].$sid, $pipeData['valFields']);
        }
        $result = $pipe->exec();
        $detailsData = array();
        foreach ($result as $key => $value) {
            $temp = array();
            $temp= json_decode($value, true);
            if(!empty($temp['sid'])){
                $detailsData[$temp['sid']]['short_name'] = $temp['short_name'];
                $detailsData[$temp['sid']]['symbol']     = $temp['symbol'];
            } else {
                $detailsData[] = $temp;
            }
        } 
        return $detailsData;
    }
    protected function getRedisLastQuaterData($stockid) {
        
        /*
         * Data for AWS Redis finpoints with points
         */
        $finParamAWS['stockid'] = $stockid;
        $finParamAWS['valKey'] = "MM:STOCK:";
        $finParamAWS['valFields'] = "FIN_MOJO_PTS";
        $finParamAWSRes= $this ->getRedisPipeFinDataPoints($finParamAWS,'_redisMmcoreReadObj');
        $finParamAWSRes = array_combine($stockid,$finParamAWSRes);
//        echo '<pre>';
//        print_r($finParamAWSRes);
//        exit;
        return $finParamAWSRes;
    }
    private function getRedisPipeFinDataPoints($pipeData = '', $dbRef = '_redisReadObj'){
        $pipe = $this->$dbRef->pipeline();
        foreach($pipeData['stockid'] as $sid){
            $pipe->hget($pipeData['valKey'].$sid, $pipeData['valFields']);
        }
        $result = $pipe->exec();
        $detailsData = array();
        
        foreach ($result as $key => $value) {
            $temp = array();
            $temp= json_decode($value, true);
            if(!empty($temp)){
                end($temp);
                $lastelement = key($temp);
                $detailsData[$key]['last_qtr_date'] = $temp[$lastelement]['result_date'];
                $detailsData[$key]['points'] = ($temp[$lastelement]['points'] != 0 || $temp[$lastelement]['points'] != '0') ? $temp[$lastelement]['points'] : '--';
            } else {
                $detailsData[$key]['last_qtr_date'] = 'NA';
                $detailsData[$key]['points'] = 'NA';
            }
            
        } 
        return $detailsData;
    }
    protected function getMongoLastQuaterData($stockData)
    {
        $lastqtrdata = '';
        $mongoData   = array();
        $resultdata  = array();
        $filter      = array();
        
        $mongoData['projection'] = ['stockid','result_date','points'];
               
        foreach ($stockData as $sid => $value) {
            
            if(isset($value['last_qtr_date']) && $value['last_qtr_date'] != 'NA' && $value['last_qtr_date'] != ''){
                
                
//                $filter['stockid']     = 399834;
//                $filter['result_date'] = $stockData['399834']['last_qtr_date'];
//                $aggregate[] = ['$group' => ['result_date' =>]];
//                $valuationCurrentData = $this->_mongo->query('stock_fin_data', $mongoData['projection'], $filter);
                
                $filter['stockid']     = $sid;
                $filter['result_date'] = $value['last_qtr_date'];
                $valuationCurrentData = $this->_mongo->query('stock_fin_data', $mongoData['projection'], $filter);
               
                if(!empty($valuationCurrentData)){
                    $pts = 0;
                     foreach($valuationCurrentData as $mkey => $mvalue){
                         $pts += $mvalue['points'];
                         
                         $resultdata[$mvalue['stockid']]['result_date'] = $mvalue['result_date'];
                         $resultdata[$mvalue['stockid']]['points'] = ($pts != 0 || $pts != '0') ? $pts : '--';
                     }
                }
                else{
                    $resultdata[$sid]['result_date'] = 'NA';
                    $resultdata[$sid]['points'] = 'NA';
                }
            }
            else{
                $resultdata[$sid]['result_date'] = 'NA';
                $resultdata[$sid]['points'] = 'NA';
            }
            
        }
        return $resultdata;
    }   
}

