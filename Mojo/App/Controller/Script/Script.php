<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */


namespace Mojo\App\Controller\Script;

/**
 * Process current stock price comparison with previous 1d 1m 1w YTD
 *
 * @author Amit
 */

use Mojo\Core\Base as Base;
use Mojo\App as App;
use Mojo\Core\Db as Db; 
class Script extends App\Models\Script\Script{
    private $_post;
    private $_benchmark;
    private $alertPercentage = ['1D' => -20, '1W' => -50, '1M' => -60, 'YTD'=> -70];
    private $bmPercentage = ['1D' => -20, '1W' => -40, '1M' => -50, 'YTD'=> -60];
    CONST BENCHMARKPARAM = array('1D', '1W', '1M', 'YTD');
    CONST COLORCODE = array('cft'=>'antiquewhite', 'valuation'=>'aliceblue', 'quality'=>'beige', 'technical'=>'burlywood', 'quarter'=>'cadetblue', 'pos_neg'=>'burlywood');
    const MAX_MAIL_LIMIT = 50;
    const MOJO_SITE_URL = 'https://www.marketsmojo.com/';
    const FROM_EMAIL_ID = 'tech@marketsmojo.com';
    const TO_EMAIL = array(
                            array('email' => 'tech@marketsmojo.com', 'name' => 'Tech'),
//                            array('email' => 'support@marketsmojo.com', 'name' => 'Support'),
//                            array('email' => 'amit@marketsmojo.com', 'name' => 'Amit'),
                          );
    const TESTING_EMAIL = array(
                            array('email' => 'pradeep@marketsmojo.com', 'name' => 'Pradeep'),
                            array('email' => 'amit@marketsmojo.com', 'name' => 'Amit'),
                          );
    private $_holding_n_insurance_stocks = array(
        658168, 371122, 103061, 848865, 129404, 280329, 950755, 972978, 506931, 712234, 663472, 485202, 468391, 274022, 1002626, 245716, 935945, 688217, 498418, 710320, 683998, 151780, 648578, 774131, 724375, 461234, 928757, 354807, 374193, 139834, 406579, 424719, 261671, 661429, 306806, 799299, 984417, 292539, 808289, 343883, 991100, 126982, 999997, 464586, 449669, 588736, 282398, 802538, 442477, 724048, 950688, 312348, 1002663, 1002823, 1002829, 1002851, 1002871, 1002872, 885158, 873791, 1002585, 324585, 467058, 232533
    );
    CONST FEND_FIELD = array('52wk_low'=>'52wk_low', '52wk_high'=>'52wk_high', 'altm_low'=>'altm_low', 'altm_high'=>'altm_high', 'mcap'=>'mcap', 'vol'=>'vol');
    CONST AWS_BSE_FIELD = array('52wk_low'=>'bse_low', '52wk_high'=>'bse_high', 'altm_low'=>'bse_altm_low', 'altm_high'=>'bse_altm_high', 'mcap'=>'mcap', 'vol'=>'vol');
    CONST AWS_NSE_FIELD = array('52wk_low'=>'nse_low', '52wk_high'=>'nse_high', 'altm_low'=>'nse_altm_low', 'altm_high'=>'nse_altm_high', 'mcap'=>'mcap', 'vol'=>'vol');
    
    
    public function __construct() {
         parent::__construct();
         $this->_post = json_decode(file_get_contents('php://input'), true);
        if (empty($this->_post)) {
            $this->_post = [];
        }
     }

     /*
      * Author : Amit 
      * Last Change Date: 10/10/2018
      * Desc: This function gets data from redis and merge data to create  table and send mail
      */
    public function processStockData(){
        $data = $this->getRequiredData();
        $finalDataSet = array();
        $finalBMDataSet = array();
        $finalAlertStr = '';
        $finalBMAlertStr = '';
        $flag = 0;
        $bmflag = 0;
        $mailRes = array();
        
        $finalDataHeader  = '<table border="2">';
        $finalDataHeader .= '<tr><th>Type</th><th>Stock</th><th>Stock Name</th><th>Market Cap</th><th>Market Cap Grade</th><th>Trigger</th><th>Stock Return</th><th>Benchmark Return</th></tr>';

        $finalBMDataHeader  = '<table border="2">';
        $finalBMDataHeader .= '<tr><th>Type</th><th>Stock</th><th>Stock Name</th><th>Market Cap</th><th>Market Cap Grade</th><th>Trigger</th><th>Stock Return</th><th>Benchmark Return</th><th>Vs Benchmark</th></tr>';
        $tableEndTag = '</table>';
        if(!empty($data)){
            $priceDetailDT = '';
            if(!empty($data['benchMark'])){
                $this->_benchmark = $this->calculateBenchMark($data['benchMark']);
                unset($data['benchMark']);
            }
            foreach ($data as $key => $value) {
                //BSE Calculation
                $bseDT = !empty($value['BSE_data']['dt'])?date('Y-m-d', strtotime($value['BSE_data']['dt'])):"";
                $bseMcapClass = !empty($value['BSE_data']['mcap_class'])?$value['BSE_data']['mcap_class']:"";
                if(!empty($value['prevCloseBse']) && !empty($this->_benchmark[$bseMcapClass]['dt_min']) && $this->_benchmark[$bseMcapClass]['dt_min'] == $bseDT){
                    foreach ($value['prevCloseBse'] as $bkey => $prevCloseBse) {
                        $responsBse = $this -> formateDataSet($value['stockDet'], $bkey, $prevCloseBse, $value['BSE_data'],'BSE');
                        if(!empty($responsBse['dataset'])){
                            $finalDataSet[$bkey][$value['stockDet']['sid']] = $responsBse['dataset'];
                            $flag = 1;
                            $bmflag = 1;
                        }
                        
                        if(!empty($responsBse['bmDataset'])){
                            $finalBMDataSet[$bkey][$value['stockDet']['sid']] = $responsBse['bmDataset'];
                            $flag = 1;
                            $bmflag = 1;
                        }
                        
                    }
                }
                //NSE Calculation
                $nseDT = !empty($value['NSE_data']['dt'])?date('Y-m-d', strtotime($value['NSE_data']['dt'])):"";
                $nseMcapClass = !empty($value['NSE_data']['mcap_class'])?date('Y-m-d', strtotime($value['NSE_data']['mcap_class'])):"";
                if(!empty($value['prevCloseNse']) && !empty($this->_benchmark[$nseMcapClass]['dt_min']) && $this->_benchmark[$nseMcapClass]['dt_min'] == $nseDT){
                    foreach ($value['prevCloseNse'] as $nkey => $prevCloseNse) {
                        $responsNse = $this -> formateDataSet($value['stockDet'], $nkey, $prevCloseNse, $value['NSE_data'],'NSE');
                        if(!empty($responsNse['dataset'])){
                            $finalDataSet[$nkey][$value['stockDet']['sid']] = $responsNse['dataset'];
                            $flag = 1;
                            $bmflag = 1;
                        }
                        
                        if(!empty($responsNse['bmDataset'])){
                            $finalBMDataSet[$nkey][$value['stockDet']['sid']] = $responsNse['bmDataset'];
                            $flag = 1;
                            $bmflag = 1;
                        }
                    }    
                }
            }
            if(!empty($finalDataSet)){
                foreach (array_keys($this->alertPercentage) as $value) {
                    if(!empty($finalDataSet[$value])){
                        $finalAlertStr .= "<h3>Plese find below details of Price Alert for $value</h3>";
                        $finalAlertStr .= $finalDataHeader;
                        $finalAlertStr .=  implode('', $finalDataSet[$value]);
                        $finalAlertStr .= $tableEndTag;
                    }
                    
                }
            }
            
            if(!empty($finalBMDataSet)){
                foreach (array_keys($this->bmPercentage) as  $value) {
                    if(!empty($finalBMDataSet[$value])){
                        $finalBMAlertStr .= "<h3>Plese find below details of Price Alert (VS Benchmark) for $value</h3>";
                        $finalBMAlertStr .= $finalBMDataHeader;
                        $finalBMAlertStr .=  implode('', $finalBMDataSet[$value]);
                        $finalBMAlertStr .= $tableEndTag;
                    }
                    
                }
            }
        }
        if (!empty($flag)) {
            foreach (self::TO_EMAIL as $k => $v) {
                $mailData = array(
                    'subject' => "Price Alert (Drastic)",
                    'fromAddr' => array('email' => self::FROM_EMAIL_ID, 'name' => 'Tech'),
                    'toAddr' => $v,
                    'message' => $finalAlertStr,
                );
//                echo $finalAlertStr;
                $this -> sendEmail($mailData);
                $mailRes[] = 'Mail sent for Price Alert';
                
                
                if (!empty($bmflag)) {
                    $bmMailData = array(
                        'subject' => "Price Alert For Vs Benchmark",
                        'fromAddr' => array('email' => self::FROM_EMAIL_ID, 'name' => 'Tech'),
                        'toAddr' => $v,
                        'message' => $finalBMAlertStr,
                    );
//                    echo $finalBMAlertStr;
                    $this -> sendEmail($bmMailData); 
                    $mailRes[] = 'Mail sent for Price Alert(VS Benchmark)';
                }
            
            }
            /*
            * mcap and index
            */
            $mailRes[] = $this->mcapIndexCalc($data);
            Base\StatusCodes::successMessage(200, "success", $mailRes);
        }
        Base\StatusCodes::successMessage(200, "success","Whoops! No Data Found");
    }
    
     /*
      * Author : Amit 
      * Last Change Date: 13/11/2018
      * Desc: This function gets data from redis and merge data to create  table and send mail
      */
    public function mcapIndexCalc($data = array()){
//        $data = $this->getRequiredData('mcapIndexCalc');
        $finalDataSet = array();
        $finalBMDataSet = array();
        $finalAlertStr = '';
        $finalBMAlertStr = '';
        $flag = 0;
        $bmflag = 0;
        $mailRes = array();
        $resp = 'No Data Found!!';
        
        $finalDataHeader  = '<table border="2">';
        $finalDataHeader .= '<tr><th>Type</th><th>Stock</th><th>Stock Name</th><th>Market Cap</th><th>Market Cap Grade</th><th>Trigger</th><th>Stock Return</th><th>Benchmark Return</th></tr>';

        $finalBMDataHeader  = '<table border="2">';
        $finalBMDataHeader .= '<tr><th>Type</th><th>Stock</th><th>Stock Name</th><th>Market Cap</th><th>Market Cap Grade</th><th>Trigger</th><th>Stock Return</th><th>Benchmark Return</th></tr>';
        $tableEndTag = '</table>';
        if(!empty($data)){
            if(!empty($data['mcapIndexData'])){
                $this->_benchmark = $this->calculateBenchMark($data['mcapIndexData']);
                unset($data['mcapIndexData']);
            }
            foreach ($data as $key => $value) {
                //BSE Calculation
                $bseDT = !empty($value['BSE_data']['dt'])?date('Y-m-d', strtotime($value['BSE_data']['dt'])):"";
                $bseMcapClass = !empty($value['BSE_data']['mcap'])?$this -> checkMcap($value['BSE_data']['mcap']):"";
                if(!empty($value['prevCloseBse']) && !empty($this->_benchmark[$bseMcapClass]['dt_min']) && $this->_benchmark[$bseMcapClass]['dt_min'] == $bseDT){
                    foreach ($value['prevCloseBse'] as $bkey => $prevCloseBse) {
                        $responsBse = $this -> formateDataSet($value['stockDet'], $bkey, $prevCloseBse, $value['BSE_data'],'BSE','mcapIndex');
                        if(!empty($responsBse['dataset'])){
                            $finalDataSet[$bkey][$value['stockDet']['sid']] = $responsBse['dataset'];
                            $flag = 1;
                            $bmflag = 1;
                        }
                        
                        
//                        if(!empty($responsBse['bmDataset'])){
//                            $finalBMDataSet[$bkey][$value['stockDet']['sid']] = $responsBse['bmDataset'];
//                            $flag = 1;
//                            $bmflag = 1;
//                        }
                        
                    }
                }
                //NSE Calculation
                $nseDT = !empty($value['NSE_data']['dt'])?date('Y-m-d', strtotime($value['NSE_data']['dt'])):"";
                $nseMcapClass = !empty($value['NSE_data']['mcap'])?$this -> checkMcap($value['NSE_data']['mcap']):"";
                if(!empty($value['prevCloseNse']) && !empty($this->_benchmark[$nseMcapClass]['dt_min']) && $this->_benchmark[$nseMcapClass]['dt_min'] == $nseDT){
                    foreach ($value['prevCloseNse'] as $nkey => $prevCloseNse) {
                        $responsNse = $this -> formateDataSet($value['stockDet'], $nkey, $prevCloseNse, $value['NSE_data'],'NSE','mcapIndex');
                        if(!empty($responsNse['dataset'])){
                            $finalDataSet[$nkey][$value['stockDet']['sid']] = $responsNse['dataset'];
                            $flag = 1;
                            $bmflag = 1;
                        }
                        
//                        if(!empty($responsNse['bmDataset'])){
//                            $finalBMDataSet[$nkey][$value['stockDet']['sid']] = $responsNse['bmDataset'];
//                            $flag = 1;
//                            $bmflag = 1;
//                        }
                    }    
                }
            }
            if(!empty($finalDataSet)){
                foreach (array_keys($this->alertPercentage) as $value) {
                    if(!empty($finalDataSet[$value])){
                        $finalAlertStr .= "<h3>Plese find below details of Price Alert for Market Cap and Index</h3>";
                        $finalAlertStr .= $finalDataHeader;
                        $finalAlertStr .=  implode('', $finalDataSet[$value]);
                        $finalAlertStr .= $tableEndTag;
                    }
                    
                }
            }
            
//            if(!empty($finalBMDataSet)){
//                foreach (array_keys($this->bmPercentage) as  $value) {
//                    if(!empty($finalBMDataSet[$value])){
//                        $finalBMAlertStr .= "<h3>Plese find below details of Price Alert for Market Cap and Index</h3>";
//                        $finalBMAlertStr .= $finalBMDataHeader;
//                        $finalBMAlertStr .=  implode('', $finalBMDataSet[$value]);
//                        $finalBMAlertStr .= $tableEndTag;
//                    }
//                    
//                }
//            }
        }
        if (!empty($flag)) {
            foreach (self::TO_EMAIL as $k => $v) {
                $mailData = array(
                    'subject' => "Price Alert for Market Cap and Index",
                    'fromAddr' => array('email' => self::FROM_EMAIL_ID, 'name' => 'Tech'),
                    'toAddr' => $v,
                    'message' => $finalAlertStr,
                );
//                echo $finalAlertStr;
                $this -> sendEmail($mailData);
                $resp = 'Mail sent for Mcap and Index!';
//                
                
//                if (!empty($bmflag)) {
//                    $bmMailData = array(
//                        'subject' => "Price Alert For Market Cap and Index",
//                        'fromAddr' => array('email' => self::FROM_EMAIL_ID, 'name' => 'Tech'),
//                        'toAddr' => $v,
//                        'message' => $finalBMAlertStr,
//                    );
//                    echo $finalBMAlertStr;
////                    $this -> sendEmail($bmMailData); 
//                    $mailRes[] = 'Mail sent for Market Cap and Index';
//                }
            }
            return $resp;
        }
        return $resp;
    }
    
    public function continuousFallLastTenD(){
        ini_set('memory_limit','1024M');
        $data = $this->getLastTenDData();
        $stockDetails = !empty($data['stockDetails'])?$data['stockDetails']:array();
        $tenthDate = !empty($data['TenthHistDate'])?$data['TenthHistDate']:'';
        unset($data['stockDetails'],$data['TenthHistDate']);
        $projection = ['stockid','date','price'];
        $finalArr  = 'Plese find below details of stocks losing continuously for last 10 trading days.<br><table border="2">';
        $finalArr .= '<tr><th>#</th><th>Stock</th><th>Stock Name</th><th>Market Cap</th><th>Market Cap Grade</th><th>Trigger</th><th>Stock Return</th><th>Benchmark Return</th></tr>';
        $i = 1;
        $mailFlag = 0;
        foreach ($data as $key => $value) {
            if(!empty($value)){
                if($key == 'common'){
                    $key = 'bse';
                }
                $mongoData['date'] = $tenthDate;
                $mongoData['exch'] = $key;
                $mongoData['value'] = array_keys($value);
                $mongoData['projection'] = $projection;
                $priceData = $this -> getPriceData($mongoData);
                $sidArray = array_column($priceData, 'stockid');
                $chunkwiseData = array();
                foreach(array_flip($sidArray) as $keyD => $valD){
                    $chunkwiseData = array_keys($sidArray,$keyD);
                    $chkPriceFall = $this -> chkPriceFall($chunkwiseData, $priceData);
                    if($chkPriceFall){
                        $trData['index'] = $i;
                        $trData['sid'] = $stockDetails[$keyD]['stockDet']['sid'];
                        $trData['short_name'] = $stockDetails[$keyD]['stockDet']['short_name'];
                        $stData = '';
                        if($key == 'bse'){
                            $stData = 'BSE_data';
                        } else if($key == 'nse'){
                            $stData = 'NSE_data';
                        }
                        $trData['mcap'] = !empty($stockDetails[$keyD][$stData]['mcap'])?$stockDetails[$keyD][$stData]['mcap']:'';
                        $trData['mcap_class'] = !empty($stockDetails[$keyD][$stData]['mcap_class'])?$stockDetails[$keyD][$stData]['mcap_class']:'';
                        $trData['trigger'] = 'Continuous Fall 10 Days';
                        $trData['sReturn'] = 'Stock Return in last 10 days';
                        $trData['bReturn'] = 'Benchmark Return in last 10 days';
                        $finalArr .= $this->createTr($trData);
                        $i++;
                        $mailFlag = 1;
                    }
                }
            }
            
        }
        $finalArr .= '</table>';
        if (!empty($mailFlag)) {
            foreach (self::TO_EMAIL as $k => $v) {
                $mailData = array(
                    'subject' => "Stocks losing continuously for last 10 trading days",
                    'fromAddr' => array('email' => self::FROM_EMAIL_ID, 'name' => 'Tech'),
                    'toAddr' => $v,
                    'message' => $finalArr,
                );
//                echo $finalArr;
                $this -> sendEmail($mailData); 
            }
            Base\StatusCodes::successMessage(200, "success", "Mail Sent Successfully!");
        }
        Base\StatusCodes::successMessage(200, "success","Whoops! No Data Found");
    }
    
    public function compareCFTData(){
        ini_set('memory_limit', '-1');
        global $holidays;
        if (in_array(date('Y-m-d'), $holidays)) {
            $res = 'Today is holidays, Enjoy!';
            Base\StatusCodes::successMessage(200, "success", $res);
        }
        $data = $this->getCFTRequiredData();
        if(!empty($data['dotSummaryFend']) && !empty($data['dotSummaryAWS'])){
            $trdata = '';
            $i = 1;
            foreach($data['dotSummaryFend'] as $key => $value) {
                if(!in_array($key, $this->_holding_n_insurance_stocks) && !empty($value) && !empty($data['dotSummaryAWS'][$key]) && !in_array($key, $this->_holding_n_insurance_stocks)) //&& $this->compareEachRec($value,$data['dotSummaryAWS'][$key]) == false)   
                {
                    $data['technicalsAWS'][$key] = !empty($data['technicalsAWS'][$key])?$data['technicalsAWS'][$key]:array();
                    $data['FIN_DETAILS'][$key] = !empty($data['FIN_DETAILS'][$key])?$data['FIN_DETAILS'][$key]:array();
                    $data['FIN_MOJO_PTS'][$key] = !empty($data['FIN_MOJO_PTS'][$key])?$data['FIN_MOJO_PTS'][$key]:array();
                    $data['FIN_POS_NEG'][$key] = !empty($data['FIN_POS_NEG'][$key])?$data['FIN_POS_NEG'][$key]:array();
                    $data['FIN_DETAILS_AWS'][$key] = !empty($data['FIN_DETAILS_AWS'][$key])?$data['FIN_DETAILS_AWS'][$key]:array();
                    
                    $checkMismatch = $this->compareEachRec($value,$data['dotSummaryAWS'][$key],$data['technicalsAWS'][$key],$data['FIN_DETAILS'][$key],$data['FIN_MOJO_PTS'][$key],$data['FIN_DETAILS_AWS'][$key],$data['FIN_POS_NEG'][$key]);
                    if(!empty($checkMismatch)){
                        $trData = array();
                        $trData[] = $i++;
                        
                        /*Stock Details*/
//                        $trData[] = !empty($data['stockdetails'][$key]['sid'])?$data['stockdetails'][$key]['sid']:"-";
                        $trData[] = !empty($data['stockdetails'][$key]['short_name'])?'<a href="'.self::MOJO_SITE_URL.'Stocks?StockId='.$data['stockdetails'][$key]['sid'].'" target="_blank">'. $data['stockdetails'][$key]['short_name'] .'</a>':"-";
                        
                        /*CFT*/
                        $trData[] = !empty($checkMismatch['f_txt'])?[$checkMismatch['f_txt'],self::COLORCODE['cft']]:["-",self::COLORCODE['cft']];
                        $trData[] = !empty($checkMismatch['f_pts'])?[$checkMismatch['f_pts'],self::COLORCODE['cft']]:["-",self::COLORCODE['cft']];
                        $trData[] = !empty($checkMismatch['fin_ranktext'])?[$checkMismatch['fin_ranktext'],self::COLORCODE['cft']]:["-",self::COLORCODE['cft']];
                        $trData[] = !empty($checkMismatch['fin_points'])?[$checkMismatch['fin_points'],self::COLORCODE['cft']]:["-",self::COLORCODE['cft']];
                        
                        /*Valuation*/
                        $trData[] = !empty($checkMismatch['v_txt'])?[$checkMismatch['v_txt'],self::COLORCODE['valuation']]:["-",self::COLORCODE['valuation']];
                        $trData[] = !empty($checkMismatch['valuation_ranktext'])?[$checkMismatch['valuation_ranktext'],self::COLORCODE['valuation']]:["-",self::COLORCODE['valuation']];
                        
                        /*Quality*/
                        $trData[] = !empty($checkMismatch['q_txt'])?[$checkMismatch['q_txt'],self::COLORCODE['quality']]:["-",self::COLORCODE['quality']];
                        $trData[] = !empty($checkMismatch['quality_ranktext'])?[$checkMismatch['quality_ranktext'],self::COLORCODE['quality']]:["-",self::COLORCODE['quality']];
                        
                        /*Technical*/
                        $trData[] = !empty($checkMismatch['fendtech_txt'])?[$checkMismatch['fendtech_txt'],self::COLORCODE['technical']]:["-",self::COLORCODE['technical']];
                        $trData[] = !empty($checkMismatch['AWStech_txt'])?[$checkMismatch['AWStech_txt'],self::COLORCODE['technical']]:["-",self::COLORCODE['technical']];
                        
                        /*fin points last quater comparison*/
                        $trData[] = !empty($checkMismatch['fend_result_date'])?[$checkMismatch['fend_result_date'],self::COLORCODE['quarter']]:["-",self::COLORCODE['quarter']];
                        $trData[] = !empty($checkMismatch['fend_points'])?[$checkMismatch['fend_points'],self::COLORCODE['quarter']]:["-",self::COLORCODE['quarter']];
                        $trData[] = !empty($checkMismatch['AWS_result_date'])?[$checkMismatch['AWS_result_date'],self::COLORCODE['quarter']]:["-",self::COLORCODE['quarter']];
                        $trData[] = !empty($checkMismatch['AWS_points'])?[$checkMismatch['AWS_points'],self::COLORCODE['quarter']]:["-",self::COLORCODE['quarter']];
                        
                        /*Compare Positive/negative data*/
                        $trData[] = !empty($checkMismatch['fend_pos_neg'])?[$checkMismatch['fend_pos_neg'],self::COLORCODE['pos_neg']]:["-",self::COLORCODE['pos_neg']];
                        $trData[] = !empty($checkMismatch['AWS_pos_neg'])?[$checkMismatch['AWS_pos_neg'],self::COLORCODE['pos_neg']]:["-",self::COLORCODE['pos_neg']];
                        
                        
                        $trdata .= $this->prepareTr($trData);
                    }
                }
            }
            if(!empty($trdata)){
                $subject = 'Alert : Mismatch in quality/valuation/financial trend grades data';
                $bodyText = '<h3>Please check mismatch in quality/valuation/financial trend grades data.</h3>';
                $tabletag  = '<table border="2">';
                $cft = 'style="background:'.self::COLORCODE['cft'].'"';
                $valuation = 'style="background:'.self::COLORCODE['valuation'].'"';
                $quality = 'style="background:'.self::COLORCODE['quality'].'"';
                $technical = 'style="background:'.self::COLORCODE['technical'].'"';
                $quarter = 'style="background:'.self::COLORCODE['quarter'].'"';
                $pos_neg = 'style="background:'.self::COLORCODE['pos_neg'].'"';
                $tableHeader = '<tr> '
                                . '<th></th> <th>Stock</th>'
                                . ' <th colspan="4" '.$cft.'>CFT</th>'
                                . ' <th colspan="2" '.$valuation.'>Valuation</th>'
                                . ' <th colspan="2" '.$quality.'>Quality</th>'
                                . ' <th colspan="2" '.$technical.'>Technical</th>'
                                . ' <th colspan="4" '.$quarter.'>Quaterly Finpoints</th>'
                                . ' <th colspan="2" '.$pos_neg.'>Quaterly Positive/Negative</th>'
                            . '<tr>'
                            . '<tr> '
                                . '<th>#</th> <th>Stock Name</th>'
                                . ' <th '.$cft.'>Site Fin Text</th> <th '.$cft.'>Site Fin Points</th> <th '.$cft.'>Core Fin Text</th> <th '.$cft.'>Core Fin Points</th>'
                                . ' <th '.$valuation.'>Site Valuation Grade</th>  <th '.$valuation.'>Core Valuation Grade</th> '
                                . ' <th '.$quality.'>Site Quality Grade</th>  <th '.$quality.'>Core Quality Grade</th>'
                                . ' <th '.$technical.'>Site Technical</th> <th '.$technical.'>Core Technical</th>'
                                . ' <th '.$quarter.'>Site Quaterly Result </th> <th '.$quarter.'>Site Quaterly Fin Points</th> <th '.$quarter.'>Core Quaterly Result</th> <th '.$quarter.'>Core Quaterly Fin Points</th>'
                                . ' <th '.$pos_neg.'>Site Quaterly Positive/Negative</th> <th '.$pos_neg.'>Core Quaterly Positive/Negative</th>'
                            . '<tr>';
                $tableBody = $trdata;
                $tableEndTag = '</table>';
                $finalText = $bodyText.$tabletag.$tableHeader.$tableBody.$tableEndTag;

                foreach (self::TESTING_EMAIL as $k => $v) {
                    $mailData = array(
                        'subject' => $subject,
                        'fromAddr' => array('email' => self::FROM_EMAIL_ID, 'name' => 'Tech'),
                        'toAddr' => $v,
                        'message' => $finalText,
                    );
//                    echo $finalText;
                    $this -> sendEmail($mailData);
                    $res[] = 'Mail sent to '.$v['email'];
                }
                Base\StatusCodes::successMessage(200, "success", $res);
            }
        }
        Base\StatusCodes::successMessage(200, "success","Whoops! No Data Found");
    }
    
    private function compareEachRec($frontEndData, $AWSData,$technicalData,$FIN_DETAILS,$FIN_MOJO_PTS,$FIN_DETAILS_AWS,$FIN_POS_NEG){
        $data = array();
        /*CFT Comparison*/
        if(
            (!empty($frontEndData['f_pts']) && empty($AWSData['fin_points']))
            || (!empty($AWSData['fin_points']) && empty($frontEndData['f_pts']))
            || (!empty($frontEndData['f_txt']) && empty($AWSData['fin_ranktext']))
            || (!empty($AWSData['fin_ranktext']) && empty($frontEndData['f_txt']))
            || ($frontEndData['f_txt'] != $AWSData['fin_ranktext'])       
            || ($frontEndData['f_pts'] != $AWSData['fin_points'])     
        ){  
            if($frontEndData['f_txt'] != 'Does not qualify') {
                $data['f_txt'] = !empty($frontEndData['f_txt'])?$frontEndData['f_txt']:'-';
                $data['f_pts'] = !empty($frontEndData['f_pts'])?$frontEndData['f_pts']:'-';
                $data['fin_ranktext'] = !empty($AWSData['fin_ranktext'])?$AWSData['fin_ranktext']:'-';
                $data['fin_points'] = !empty($AWSData['fin_points'])?$AWSData['fin_points']:'-';
            }
        }
        /*Valuation Comparison*/
        if(
            (!empty($frontEndData['v_txt']) && empty($AWSData['valuation_ranktext']))
            || (!empty($AWSData['valuation_ranktext']) && empty($frontEndData['v_txt']))
            || ($frontEndData['v_txt'] != $AWSData['valuation_ranktext'])     
        ){
            if($frontEndData['v_txt'] != 'Does not qualify') {
                $data['v_txt'] = !empty($frontEndData['v_txt'])?$frontEndData['v_txt']:'-';
                $data['valuation_ranktext'] = !empty($AWSData['valuation_ranktext'])?$AWSData['valuation_ranktext']:'-';
            }
        }
        /*Quality Comparison*/
        if(
            (!empty($frontEndData['q_txt']) && empty($AWSData['quality_ranktext']))
            || (!empty($AWSData['quality_ranktext']) && empty($frontEndData['q_txt']))
            || ($frontEndData['q_txt'] != $AWSData['quality_ranktext']) 
        ){
            if($frontEndData['q_txt'] != 'Does not qualify') {
                $data['q_txt'] = !empty($frontEndData['q_txt'])?$frontEndData['q_txt']:'-';
                $data['quality_ranktext'] = !empty($AWSData['quality_ranktext'])?$AWSData['quality_ranktext']:'-';
            }
        }
        /*Technical Comparison*/
        if(
            (!empty($frontEndData['tech_txt']) && empty($technicalData['tech_txt']))
            || (!empty($technicalData['tech_txt']) && empty($frontEndData['tech_txt']))
            || ($frontEndData['tech_txt'] != $technicalData['tech_txt'])    
        ){  
            if($frontEndData['tech_txt'] != 'Does not qualify') {
                $data['fendtech_txt'] = !empty($frontEndData['tech_txt'])?$frontEndData['tech_txt']:'-';
                $data['AWStech_txt'] = !empty($technicalData['tech_txt'])?$technicalData['tech_txt']:'-';
            }    
        }
        /*fin points last quater comparison*/
        if(
            (!empty($FIN_DETAILS['result_date']) && empty($FIN_MOJO_PTS['result_date']))
            || (!empty($FIN_MOJO_PTS['result_date']) && empty($FIN_DETAILS['result_date']))
            || (!empty($FIN_DETAILS['points']) && empty($FIN_MOJO_PTS['points']))
            || (!empty($FIN_MOJO_PTS['points']) && empty($FIN_DETAILS['points']))    
            || (!empty($FIN_DETAILS['result_date']) && !empty($FIN_MOJO_PTS['result_date']) && ($FIN_DETAILS['result_date'] != $FIN_MOJO_PTS['result_date']))
            || (!empty($FIN_DETAILS['points']) && !empty($FIN_MOJO_PTS['points']) && ($FIN_DETAILS['points'] != $FIN_MOJO_PTS['points']))             
        ){  
            $data['fend_result_date'] = !empty($FIN_DETAILS['result_date'])?$FIN_DETAILS['result_date']:'-';
            $data['fend_points'] = !empty($FIN_DETAILS['points'])?$FIN_DETAILS['points']:'-';
            $data['AWS_result_date'] = !empty($FIN_MOJO_PTS['result_date'])?$FIN_MOJO_PTS['result_date']:'-';
            $data['AWS_points'] = !empty($FIN_MOJO_PTS['points'])?$FIN_MOJO_PTS['points']:'-';
        }
        /*Compare Positive/negative data*/
        if(
            (!empty($FIN_POS_NEG) && empty($FIN_DETAILS_AWS))
            || (!empty($FIN_DETAILS_AWS) && empty($FIN_POS_NEG))
            || ($FIN_DETAILS_AWS != $FIN_POS_NEG)    
        ){  
            if($frontEndData['tech_txt'] != 'Does not qualify') {
                $data['fend_pos_neg'] = !empty($FIN_POS_NEG)?$FIN_POS_NEG:'-';
                $data['AWS_pos_neg'] = !empty($FIN_DETAILS_AWS)?$FIN_DETAILS_AWS:'-';
            }    
        }
        
        
        return $data; 
    }
    
    private function compareMcapEachRec($frontEndBseData, $frontEndNseData,$AWSBseData,$AWSNseData){
        $data = array();
        /*BSE Comparison*/
        $bseStatus = 0;
        $bsetemp = array();
        $bseDT = !empty($frontEndBseData['dt'])?date('Y-m-d', strtotime($frontEndBseData['dt'])):"";
        if($bseDT == date('Y-m-d')){
            foreach (self::FEND_FIELD as $key => $value) {
                if(
                    (!empty($frontEndBseData[$key]) && empty($AWSBseData[self::AWS_BSE_FIELD[$key]]))
                    || (!empty($AWSBseData[self::AWS_BSE_FIELD[$key]]) && empty($frontEndBseData[$key]))
                    || (empty($AWSBseData['mcap']) && empty($frontEndBseData['mcap']))
                    || (empty($AWSBseData['vol']) && empty($frontEndBseData['vol']))        
                    || (!in_array($key, ['mcap','vol']) && !empty($frontEndBseData[$key]) && !empty($AWSBseData[self::AWS_BSE_FIELD[$key]]) && (numberFormat($frontEndBseData[$key]) !== numberFormat($AWSBseData[self::AWS_BSE_FIELD[$key]])))  
                ){
                   $bseStatus = 1;
                }
                if(!empty($bseStatus)){
                    $bsetemp[$key] = !empty($frontEndBseData[$key])?$frontEndBseData[$key]:'-';
                    $bsetemp['AWS_'.self::AWS_BSE_FIELD[$key]] = !empty($AWSBseData[self::AWS_BSE_FIELD[$key]])?$AWSBseData[self::AWS_BSE_FIELD[$key]]:'-';
                    $bseStatus = 0;
                }
            }
            $data['BSE'] = $bsetemp;
        }
        
        /*NSE Comparison*/
        $nseStatus = 0;
        $nsetemp = array();
        $nseDT = !empty($frontEndNseData['dt'])?date('Y-m-d', strtotime($frontEndNseData['dt'])):"";
        if($nseDT == date('Y-m-d')){
            foreach (self::FEND_FIELD as $key => $value) {
                if(
                    (!empty($frontEndNseData[$key]) && empty($AWSNseData[self::AWS_NSE_FIELD[$key]]))
                    || (!empty($AWSNseData[self::AWS_NSE_FIELD[$key]]) && empty($frontEndNseData[$key]))
                    || (empty($AWSNseData['mcap']) && empty($frontEndNseData['mcap']))
                    || (empty($AWSNseData['vol']) && empty($frontEndNseData['vol']))        
                    || (!in_array($key, ['mcap','vol']) &&  !empty($frontEndNseData[$key]) && !empty($AWSNseData[self::AWS_NSE_FIELD[$key]]) && (numberFormat($frontEndNseData[$key]) !== numberFormat($AWSNseData[self::AWS_NSE_FIELD[$key]])))  
                ){
                   $nseStatus = 1;
                }
                if(!empty($nseStatus)){
                    $nsetemp[$key] = !empty($frontEndNseData[$key])?$frontEndNseData[$key]:'-';
                    $nsetemp['AWS_'.self::AWS_NSE_FIELD[$key]] = !empty($AWSNseData[self::AWS_NSE_FIELD[$key]])?$AWSNseData[self::AWS_NSE_FIELD[$key]]:'-';
                    $nseStatus = 0;
                }
                
            }
            $data['NSE'] = $nsetemp;
        }
        return $data; 
    }
    
    public function compareMCAPData(){
        ini_set('memory_limit', '-1');
        global $holidays;
        if (in_array(date('Y-m-d'), $holidays)) {
            $res = 'Today is holidays, Enjoy!';
            Base\StatusCodes::successMessage(200, "success", $res);
        }
        $data = $this->getMCAPRequiredData();
        if(!empty($data['stockdetails'])){
            $trdataBSE = '';
            $trdataNSE = '';
            $i = 1;
            $j = 1;
            foreach($data['stockdetails'] as $key => $value) {
                $data['Fend_BSE_Data'][$key] = !empty($data['Fend_BSE_Data'][$key])?$data['Fend_BSE_Data'][$key]:array();
                $data['Fend_NSE_Data'][$key] = !empty($data['Fend_NSE_Data'][$key])?$data['Fend_NSE_Data'][$key]:array();
                $data['AWS_BSE_Data'][$key] = !empty($data['AWS_BSE_Data'][$key])?$data['AWS_BSE_Data'][$key]:array();
                $data['AWS_NSE_Data'][$key] = !empty($data['AWS_NSE_Data'][$key])?$data['AWS_NSE_Data'][$key]:array();
                $checkMcapMismatch = array();
                if(!in_array($key, $this->_holding_n_insurance_stocks)){
                    $checkMcapMismatch = $this->compareMcapEachRec($data['Fend_BSE_Data'][$key],$data['Fend_NSE_Data'][$key],$data['AWS_BSE_Data'][$key],$data['AWS_NSE_Data'][$key]);
                }
                if(!empty($checkMcapMismatch['BSE'])){
                    $trDataBSE = array();
                    $trDataBSE[] = $i++;

                    /*Stock Details*/
                    $trDataBSE[] = !empty($value)?'<a href="'.self::MOJO_SITE_URL.'Stocks?StockId='.$key.'" target="_blank">'. $value .'</a>':"-";

                    /*CFT*/
                    foreach (self::FEND_FIELD as $BSEkey => $BSEvalue) {
                        $trDataBSE[] = !empty($checkMcapMismatch['BSE'][$BSEvalue])?[$checkMcapMismatch['BSE'][$BSEvalue],'orange']:["-",'orange'];
                        $trDataBSE[] = !empty($checkMcapMismatch['BSE']['AWS_'.self::AWS_BSE_FIELD[$BSEvalue]])?[$checkMcapMismatch['BSE']['AWS_'.self::AWS_BSE_FIELD[$BSEvalue]],'yellow']:["-",'yellow'];
                    }
                    $trdataBSE .= $this->prepareTr($trDataBSE);
                }
                
                if(!empty($checkMcapMismatch['NSE'])){
                    $trDataNSE = array();
                    $trDataNSE[] = $j++;

                    /*Stock Details*/
                    $trDataNSE[] = !empty($value)?'<a href="'.self::MOJO_SITE_URL.'Stocks?StockId='.$key.'" target="_blank">'. $value .'</a>':"-";

                    /*CFT*/
                    foreach (self::FEND_FIELD as $NSEkey => $NSEvalue) {
                        $trDataNSE[] = !empty($checkMcapMismatch['NSE'][$NSEvalue])?[$checkMcapMismatch['NSE'][$NSEvalue],'orange']:["-",'orange'];
                        $trDataNSE[] = !empty($checkMcapMismatch['NSE']['AWS_'.self::AWS_NSE_FIELD[$NSEvalue]])?[$checkMcapMismatch['NSE']['AWS_'.self::AWS_NSE_FIELD[$NSEvalue]],'yellow']:["-",'yellow'];
                    }
                    $trdataNSE .= $this->prepareTr($trDataNSE);
                }
            }
        }    
        if(!empty($trdataBSE) || !empty($trdataNSE)){
            $subject = 'Alert : Mismatch in MCAP/Volume data';
            $finalText = '';
            if(!empty($trdataBSE)){
                $bodyText = '<h3>Please check mismatch in Site BSE & Core BSE data.</h3>';
                $tabletag  = '<table border="2">';
                $siteColor = 'style="background:orange"';
                $coreColor = 'style="background:yellow"';
                $tableHeader =  '<tr> '
                                . '<th>#</th> <th>Stock Name</th>'
                                . ' <th '.$siteColor.'>Site 52wk low</th> <th '.$coreColor.'>Core 52wk low</th>'
                                . '<th '.$siteColor.'>Site 52wk high</th> <th '.$coreColor.'>Core 52wk high</th>'
                                . '<th '.$siteColor.'>Site alltime low</th> <th '.$coreColor.'>Core all time low</th> '
                                . '<th '.$siteColor.'>Site alltime high</th> <th '.$coreColor.'>Core all time high</th>'
                                . '<th '.$siteColor.'>Site MCAP</th> <th '.$coreColor.'>Core MCAP</th>'
                                . '<th '.$siteColor.'>Site Volume</th> <th '.$coreColor.'>Core Volume</th>'
                                . '<tr>';
                $tableBody = $trdataBSE;
                $tableEndTag = '</table>';
                $finalText .= $bodyText.$tabletag.$tableHeader.$tableBody.$tableEndTag;
            }
            if(!empty($trdataNSE)){
                $bodyText = '<h3>Please check mismatch in Site NSE & Core NSE data.</h3>';
                $tabletag  = '<table border="2">';
                $siteColor = 'style="background:orange"';
                $coreColor = 'style="background:yellow"';
                $tableHeader =  '<tr> '
                                . '<th>#</th> <th>Stock Name</th>'
                                . ' <th '.$siteColor.'>Site 52wk_low</th> <th '.$coreColor.'>Core 52wk_low</th>'
                                . '<th '.$siteColor.'>Site 52wk_high</th> <th '.$coreColor.'>Core 52wk_high</th>'
                                . '<th '.$siteColor.'>Site altm_low</th> <th '.$coreColor.'>Core altm_low</th> '
                                . '<th '.$siteColor.'>Site altm_high</th> <th '.$coreColor.'>Core altm_high</th>'
                                . '<th '.$siteColor.'>Site mcap</th> <th '.$coreColor.'>Core mcap</th>'
                                . '<th '.$siteColor.'>Site vol</th> <th '.$coreColor.'>Core vol</th>'
                                . '<tr>';
                $tableBody = $trdataNSE;
                $tableEndTag = '</table>';
                $finalText .= $bodyText.$tabletag.$tableHeader.$tableBody.$tableEndTag;
            }

            foreach (self::TESTING_EMAIL as $k => $v) {
                $mailData = array(
                    'subject' => $subject,
                    'fromAddr' => array('email' => self::FROM_EMAIL_ID, 'name' => 'Tech'),
                    'toAddr' => $v,
                    'message' => $finalText,
                );
//                echo $finalText;
                $this -> sendEmail($mailData);
                $res[] = 'Mail sent to '.$v['email'];
            }
            Base\StatusCodes::successMessage(200, "success", $res);
        }
            
        Base\StatusCodes::successMessage(200, "success","Whoops! No Data Found");
    }
    
    private function chkPriceFall($chunkwiseData, $priceData){
        $res = 1;
        foreach ($chunkwiseData as $key => $value) {
            if($key === 0){
                $prev = !empty($priceData[$value]['price'])?$priceData[$value]['price']:'';
                $res = 0;
            } else {
                $res = 1;
                $curr = !empty($priceData[$value]['price'])?$priceData[$value]['price']:'';
                
                if($prev <= $curr){
                    $res = 0;
                    break;
                }
                $prev = $curr;
            }
        } 
        return $res;
    }
    
    private function calculateBenchMark($benchmarkData){
        
        $dataSet  = array();
        foreach ($benchmarkData as $eachKey => $eachValueSet) {
            if(!empty($eachValueSet['previousClose'])){
                $temp = array();
            foreach (self::BENCHMARKPARAM as $key => $value) {
                    if(!empty($eachValueSet['previousClose'][$value])){
                        $prev = (float)$eachValueSet['previousClose'][$value];
                        $curr = (float)$eachValueSet['priceDetails']['cmp'];
                        $temp[$value] = $this->calculateStockData($prev,$curr);
                }
            }
                $temp['dt_min'] = !empty($eachValueSet['priceDetails']['dt_min'])?date('Y-m-d', strtotime($eachValueSet['priceDetails']['dt_min'])):"";
                $dataSet[$eachKey] = $temp;
        }
        }
        return $dataSet;
    }
    
    private function calculateStockData($previousData,$currentCMP){
        return @((((float)$currentCMP - (float)$previousData)/(float)$previousData)*100);
    }
    
    private function formateDataSet($stockDet, $trigger, $prevCloseData, $currentData, $type, $capIndex = ''){
        $data = array();
        if(!empty($capIndex) && $capIndex == 'mcapIndex'){
            $bseMcapClass = $this -> checkMcap($currentData['mcap']);
        }    
        $returnStock = $this -> calculateStockData($prevCloseData,$currentData['cmp']);
        if(!empty($this->alertPercentage[$trigger]) && $this->alertPercentage[$trigger] >= $returnStock){
            $dataSet  = '';
            $dataSet .= '<tr>';
            $dataSet .= '<td>'. $type .'</td>';
            $dataSet .= '<td><a href="'.self::MOJO_SITE_URL.'Stocks?StockId='.$stockDet['sid'].'" target="_blank">'. $stockDet['sid'] .'</td>';
            $dataSet .= '<td>'. $stockDet['short_name'] .'</td>';
            $dataSet .= !empty($currentData['mcap'])?'<td>'.$currentData['mcap'].'</td>':"<td></td>";
            if(!empty($capIndex) && $capIndex == 'mcapIndex'){
                $dataSet .= !empty(parent::MCAPNAMEARRAY[$bseMcapClass])?'<td>'.parent::MCAPNAMEARRAY[$bseMcapClass].'</td>':"<td></td>";
            } else {
            $dataSet .= !empty($currentData['mcap_class'])?'<td>'.$currentData['mcap_class'].'</td>':"<td></td>";
            }
            $dataSet .= '<td>'. $trigger .'</td>';
            $dataSet .= '<td>'. numberFormat($returnStock) .'</td>';
            if(!empty($capIndex) && $capIndex == 'mcapIndex'){
                $dataSet .= '<td>'. numberFormat($this->_benchmark[$bseMcapClass][$trigger]) .'</td>';
                $vsBenchMark = ((float)$returnStock - (float)$this->_benchmark[$bseMcapClass][$trigger]);
            } else {
                $dataSet .= '<td>'. numberFormat($this->_benchmark[$currentData['mcap_class']][$trigger]) .'</td>';
                $vsBenchMark = ((float)$returnStock - (float)$this->_benchmark[$currentData['mcap_class']][$trigger]);
            }
            
            if($vsBenchMark <= $this->bmPercentage[$trigger]){
                $bmDataset  = $dataSet;
                $bmDataset .= '<td>'. numberFormat($vsBenchMark) .'</td>';
                $bmDataset .= '</tr>';
                $data['bmDataset'] = $bmDataset;
            }
            $dataSet .= '</tr>';
            $data['dataset'] = $dataSet;
        }
        return $data;
    }
    
    
    private function checkMcap($mcap){
        switch (true) {
    
            case ($mcap > 60000):
            return 'Nifty';
            break;

            case ($mcap > 20000 && $mcap <= 60000):
            return 'Nifty Next 50';
            break;

            case ($mcap > 5000 && $mcap <= 20000):
            return 'Nifty Mid Cap';
            break;
            
            case ($mcap < 5000):
            return 'Nifty Small Cap';
            break;
        
            default:
            return 0;
            break;
        }
    }
    private function createTr($trData = ''){
        $dataSet  = '';
        if(!empty($trData)){
            $dataSet .= '<tr>';
            $dataSet .= '<td>'. $trData['index'] .'</td>';
            $dataSet .= '<td>'. $trData['sid'] .'</td>';
            $dataSet .= '<td>'. $trData['short_name'] .'</td>';
            $dataSet .= '<td>'.$trData['mcap'].'</td>';
            $dataSet .= '<td>'.$trData['mcap_class'].'</td>';
            $dataSet .= '<td>'. $trData['trigger'] .'</td>';
            $dataSet .= '<td>'. $trData['sReturn'] .'</td>';
            $dataSet .= '<td>'. $trData['bReturn'] .'</td>';
            $dataSet .= '</tr>';
        }
        return $dataSet;
    }
    
    private function prepareTr($trData = ''){
        $dataSet  = '';
        if(!empty($trData)){
            $dataSet .= '<tr>';
            foreach($trData as $val){
                if(is_array($val))
                    $dataSet .= '<td style="background:'.$val[1].'">'. $val[0] .'</td>';
                else 
                    $dataSet .= '<td>'. $val .'</td>';
                    
            }
            $dataSet .= '</tr>';
        }
        return $dataSet;
    }
     
    private function sendEmail($mailData){
        $subject = $mailData['subject'];
        $message = $mailData['message'];
        $mailObj = new Base\Mail(SMTP_HOST, SMTP_PORT, SMTP_USERNAME, SMTP_PASSWORD);
        $fromAddr = $mailData['fromAddr'];
        $toAddr = $mailData['toAddr'];
        $mailObj->sendMailViaSMTP($fromAddr, $toAddr, '', $subject, $message);
    }
    public function checkFinalGrade(){ 
//       ini_set('display_errors','Off');
        $qualityDifferenceArray   = array();
        $valuationDifferenceArray = array();
        $stockDetails = array();
        $finalMailMessage = "";
        # Fetch All Redis stock id's from key ALLSTOCKS
        $allStockData = $this->getAllRedisStockData();
//        $allStockData = array_slice(array_values($allStockData),0,40);
        /* Quality */
        # Fetch Stock quality_ranktext details of Redis
        $allQualityRedisSummaryData = $this->getRedisDotSummary($allStockData,'quality_ranktext'); # Pass all stock id's
	 # Fetch All Mongo Stock Data with finalgrade 
        $allQualityMongoStockData = $this->getMongoStockData('quality');
        
        /*Valuation*/
        $allValuationRedisSummaryData = $this->getRedisDotSummary($allStockData,'valuation_ranktext'); # Pass all stock id's
        $allValuationMongoStockData   = $this->getMongoStockData('valuation');

        /* Loop Mongo stock Data(finalgrade) and compare with redis data (quality_ranktext)
         * If finalgrade = does not qaulify and not present in above $_holding_n_insurance_stocks stocks then skip 
         * that loop. 
         * Fetch those stocks where finalgrade != quality_ranktext (Redis)
         */
        $mainArray = array();
        foreach($allStockData as $sid){
//            if((!in_array($sid, $this->_holding_n_insurance_stocks)) && ($allQualityMongoStockData[$sid] != 'Does not qualify' || $allValuationMongoStockData[$sid] != 'Does not qualify') ){
            if((!in_array($sid, $this->_holding_n_insurance_stocks))){
                if(isset($allQualityMongoStockData[$sid]) && isset($allValuationMongoStockData[$sid])){
                    
                    /*Set default values to make all stock count equal*/
                    $qualityDifferenceArray[$sid]['redis']      = '--';
                    $qualityDifferenceArray[$sid]['mongo']      = '--';
                    $valuationDifferenceArray[$sid]['redis']    = '--';
                    $valuationDifferenceArray[$sid]['mongo']    = '--';
                    $finalFinDataArray[$sid]['redis']           = '--';
                    $finalFinDataArray[$sid]['mongo']           = '--';
                    
                   
                    /*Quality*/
                        if(isset($allQualityMongoStockData[$sid]) && isset($allQualityRedisSummaryData[$sid]) && ($allQualityRedisSummaryData[$sid] != '') && ($allQualityMongoStockData[$sid])!= ''){
                            
                            if($allQualityMongoStockData[$sid] != 'Does not qualify'){
                                
                                if($allQualityMongoStockData[$sid] != $allQualityRedisSummaryData[$sid]){
                                    $qualityDifferenceArray[$sid]['redis'] =  $allQualityRedisSummaryData[$sid];
                                    $qualityDifferenceArray[$sid]['mongo'] =  $allQualityMongoStockData[$sid];
                                }
                            }
                        }
                        else{
                                 //$qualityDifferenceArray[$sid]['redis'] =  (isset($allQualityRedisSummaryData[$sid])) ? $allQualityRedisSummaryData[$sid] : '';
                                 //$qualityDifferenceArray[$sid]['mongo'] =  (isset($allQualityMongoStockData[$sid])) ? $allQualityMongoStockData[$sid] : '';
                        }

                    /*Valuation*/
                        if(isset($allValuationMongoStockData[$sid]) && isset($allValuationRedisSummaryData[$sid]) && ($allValuationRedisSummaryData[$sid] != '') && ($allValuationMongoStockData[$sid])!= ''){
                            if($allValuationMongoStockData[$sid] != 'Does not qualify'){
                                if($allValuationMongoStockData[$sid] != $allValuationRedisSummaryData[$sid]){
                                    $valuationDifferenceArray[$sid]['redis'] =  $allValuationRedisSummaryData[$sid];
                                    $valuationDifferenceArray[$sid]['mongo'] =  $allValuationMongoStockData[$sid];
                                }
                            }
                        }
                        else{
                                 //$valuationDifferenceArray[$sid]['redis'] =  (isset($allValuationRedisSummaryData[$sid])) ? $allValuationRedisSummaryData[$sid] : '';
                                // $valuationDifferenceArray[$sid]['mongo'] =  (isset($allValuationMongoStockData[$sid])) ? $allValuationMongoStockData[$sid] : '';
                        } 
                }
            }
        }
        
                    /*Financial Trends*/
                    $finalFinDataArray   = $this->checkFinancialPointsData($allStockData);    

//        echo '<pre>';
//        echo count($valuationDifferenceArray)." --- ".count($qualityDifferenceArray); exit;
//        print_r($qualityDifferenceArray);
//        print_r($qualityDifferenceArray);
//        exit;
        #Fetch all stock ids present in a key and fetch Stock details #
        $stockIds = array_keys($qualityDifferenceArray);
        $stockDetails = $this->getStockDetails($stockIds);
        
        # Create Table
        $tabletag  = '<table border="2">';
        $tableHeader = '<tr> '
                        . '<th></th> '
                        . '<th>Stock</th>'
                        . ' <th>Short Name</th>'
                        . ' <th>Core Quality Grade</th>'
                        . ' <th>AWS Quality Grade</th>'
                        . ' <th>Core Valuation Grade</th>'
                        . ' <th>AWS Valuation Grade</th>'
                        . ' <th>Core Financial Grade</th>'
                        . ' <th>AWS Financial Grade</th>'
                        . '</tr>';
        $tddata = '';
        $i = 1;
        foreach($stockDetails['stockdetails'] as $stock_id => $description)
        {
            if($qualityDifferenceArray[$stock_id]['mongo'] == '--' && $qualityDifferenceArray[$stock_id]['redis'] == '--' && $valuationDifferenceArray[$stock_id]['mongo'] == '--' && $valuationDifferenceArray[$stock_id]['redis'] == '--' && (!isset($finalFinDataArray[$stock_id]['redis']) || $finalFinDataArray[$stock_id]['redis'] == '--' || $finalFinDataArray[$stock_id]['redis'] == '' )&&  (!isset($finalFinDataArray[$stock_id]['mongo']) || $finalFinDataArray[$stock_id]['mongo'] == '--' || $finalFinDataArray[$stock_id]['mongo'] == '')){
                continue;
            }
            else{
                $blankData = '<td>--</td>';
                $tddata .= '<tr>';
                $tddata .= '<td>'.$i.'</td>';
                $tddata .= '<td><a href="'.self::MOJO_SITE_URL.'Stocks?StockId='.$stock_id.'" target="_blank">'.$stock_id.'</a></td>';
                $tddata .= '<td><a href="'.self::MOJO_SITE_URL.'Stocks?StockId='.$stock_id.'" target="_blank">'.$description['short_name'].'</a></td>';
                $tddata .= (isset($qualityDifferenceArray[$stock_id]['mongo'])) ? '<td>'.$qualityDifferenceArray[$stock_id]['mongo'].'</td>' :  $blankData;
                $tddata .= (isset($qualityDifferenceArray[$stock_id]['redis'])) ? '<td>'.$qualityDifferenceArray[$stock_id]['redis'].'</td>'  : $blankData;
                $tddata .= (isset($valuationDifferenceArray[$stock_id]['mongo'])) ? '<td>'.$valuationDifferenceArray[$stock_id]['mongo'].'</td>'  : $blankData;
                $tddata .= (isset($valuationDifferenceArray[$stock_id]['redis'])) ? '<td>'. $valuationDifferenceArray[$stock_id]['redis'].'</td>'  : $blankData;
                $tddata .= (isset($finalFinDataArray[$stock_id]['mongo']) && $finalFinDataArray[$stock_id]['mongo'] != '') ? '<td>'.$finalFinDataArray[$stock_id]['mongo'].'</td>'  : $blankData;
                $tddata .= (isset($finalFinDataArray[$stock_id]['redis']) && $finalFinDataArray[$stock_id]['redis'] != '') ? '<td>'.$finalFinDataArray[$stock_id]['redis'].'</td>'  : $blankData;
                $tddata .= '</tr>';
                $i++;
            }
        }
        $tableEndTag = '</table>';
        $tableBody = $tabletag.$tableHeader.$tddata.$tableEndTag;
//       echo $tableBody;exit;
        
       /*Send Mail*/
        $finalMailMessage .= "<h3>Please check mismatch in AWS Redis & Core(MongoDB) Quality/Valuation data.</h3>";
        $finalMailMessage .= $tableBody;
        
        
        foreach (self::TO_EMAIL as $k => $v) {
                $mailData = array(
                    'subject' => "Grade Mismatch(AWS Redis v/s Core MongoDB)",
                    'fromAddr' => array('email' => self::FROM_EMAIL_ID, 'name' => 'Tech'),
                    'toAddr' => $v,
                    'message' => $finalMailMessage,
                );
//                echo $finalAlertStr;
                $this -> sendEmail($mailData);	
                $res[] = 'Mail sent to '.$v['email'];
 
        }			
		
//        print_r($res);
		 Base\StatusCodes::successMessage(200, "success", $res);
        
//       echo $tableBody;exit;
    }
    public function checkFinancialPointsData($allStockData){
//        echo '<pre>';
        $redis_Fin_DataArray = array();
        $mongo_Fin_DataArray = array();
        $finalFinDataArray   = array();
        
        /* Fetch All Stocksids */
//        $allStockData = $this->getAllRedisStockData();
        
        /*Redis Fetch Last quater date with points of all stocks */
        $redis_Fin_DataArray = $this->getRedisLastQuaterData($allStockData);
        
        /*Mongo Fetch Last quater data points  */
        $mongo_Fin_DataArray = $this->getMongoLastQuaterData($redis_Fin_DataArray);
        
//        echo count($redis_Fin_DataArray).'---'.count($mongo_Fin_DataArray);
//        print_r($redis_Fin_DataArray);
//        print_r($mongo_Fin_DataArray);
//        exit;
        foreach($redis_Fin_DataArray as $sid => $valuset){
            if($redis_Fin_DataArray[$sid]['points'] == 'NA' || empty($redis_Fin_DataArray[$sid])){
                continue;    
            }
            else{
                if(isset($mongo_Fin_DataArray[$sid]['points']) && $mongo_Fin_DataArray[$sid]['points'] != 'NA'){
                
                    if($mongo_Fin_DataArray[$sid]['points'] != $redis_Fin_DataArray[$sid]['points']){

                        $finalFinDataArray[$sid]['redis'] = $redis_Fin_DataArray[$sid]['points'];
                        $finalFinDataArray[$sid]['mongo'] = $mongo_Fin_DataArray[$sid]['points'];
                        $finalFinDataArray[$sid]['last_qtr_date'] = $valuset['last_qtr_date'];
                    }
                }
                else{
                        $finalFinDataArray[$sid]['redis'] = $redis_Fin_DataArray[$sid]['points'];
                        $finalFinDataArray[$sid]['mongo'] = '--';
                        $finalFinDataArray[$sid]['last_qtr_date'] = $valuset['last_qtr_date'];
                }
            }
            
        }
        return $finalFinDataArray;
    } 
    
}

