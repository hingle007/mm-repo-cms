<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */


namespace Mojo\App\Controller\Mfscript;

/**
 * Process current stock price comparison with previous 1d 1m 1w YTD
 *
 * @author Amit
 */

use Mojo\Core\Base as Base;
use Mojo\App as App;
use Mojo\Core\Db as Db; 
class Mfscript extends App\Models\Mfscript\Mfscript{
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
//                            array('email' => 'pradeep@marketsmojo.com', 'name' => 'Pradeep'),
                            array('email' => 'harshal@marketsmojo.com', 'name' => 'Harshal'),
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
      * Author : Hashal 
      * Last Change Date: 18/12/2018
      * Desc: This function fetch all mutual funds data comparison from MMfrontend and Core MongoDB
      */
    public function checkMfNavData(){
//        echo '<pre>';
        $mongodata              = array();
        $masterSchemeIdArr      = array();
        $matchmfCoreNavData          = array();
        $mfFrontendNavData      = array();
        $mfFrontendRedisNavData      = array();
        $finalResultsetArr      = array();
        
        $match      = array();
        
        $projection           = ['schemeid','asect_name'];
        # Fetch All Scheme id from Scheme master [MM-core DB ]
        # Only those records where asect_name = 'Equity'
       
        
        $mongodata['projection'] = $projection;
        $mongodata['asect_name']     = 'Equity';
        
        $masterSchemeIdArr  = $this->getAllSchemeMaster($mongodata);
        
        /*Core Nav Data*/
        $mfCoreNavData      = $this->getCoreNavData($masterSchemeIdArr);
        
        /*Frontend Nav Data*/
        $mfFrontendNavData  = $this->getFrontendNavData($masterSchemeIdArr);
        
        /*Frontend Redis Nav Data*/
        $mfFrontendRedisNavData  = $this->getFrontendRedisNavData($masterSchemeIdArr);
        
//        echo count($mfCoreNavData).'-- '.count($mfFrontendNavData).'--'.count($mfFrontendRedisNavData);
//        print_r($mfCoreNavData);
//        print_r($mfFrontendNavData);
        $flag = 'false';
        foreach($masterSchemeIdArr as $schemeid){
            $finalResultsetArr[$schemeid]['frontend']['navdate'] = '--';
            $finalResultsetArr[$schemeid]['core']['navdate'] = '--';
            $finalResultsetArr[$schemeid]['frontend']['navrs'] = '--';
            $finalResultsetArr[$schemeid]['core']['navrs'] = '--';
            $finalResultsetArr[$schemeid]['redis_frontend']['navdate'] = '--';
            $finalResultsetArr[$schemeid]['redis_frontend']['navrs'] = '--';
            
            
            /*mongocore vs mongofrontend comparison*/
            if(isset($mfCoreNavData[$schemeid]['navdate']) && isset($mfFrontendNavData[$schemeid]['navdate']) && $mfCoreNavData[$schemeid]['navdate'] != '--' && $mfFrontendNavData[$schemeid]['navdate'] != '--'){
                if($mfCoreNavData[$schemeid]['navdate'] != $mfFrontendNavData[$schemeid]['navdate']){
                   $finalResultsetArr[$schemeid]['frontend']['navdate'] =  $mfFrontendNavData[$schemeid]['navdate'];
                   $finalResultsetArr[$schemeid]['core']['navdate']     =  $mfCoreNavData[$schemeid]['navdate'];
                   $flag = 'true';
                   
                }
                # Match array
//                else{
//                    $match[$schemeid]['frontend']['navdate'] =  $mfFrontendNavData[$schemeid]['navdate'];
//                   $match[$schemeid]['core']['navdate']     =  $mfCoreNavData[$schemeid]['navdate'];
//                }
            }
            else{
//                $finalResultsetArr[$schemeid]['frontend']['navdate'] =  (isset($mfFrontendNavData[$schemeid]['navdate'])) ? $mfFrontendNavData[$schemeid]['navdate'] : '--';
//                $finalResultsetArr[$schemeid]['core']['navdate']     =  (isset($mfCoreNavData[$schemeid]['navdate'])) ? $mfCoreNavData[$schemeid]['navdate'] : '--';
            }
//            print_r($finalResultsetArr);
//            print_r($match);
//            exit;
            if(isset($mfCoreNavData[$schemeid]['navrs']) && isset($mfFrontendNavData[$schemeid]['navrs']) && $mfCoreNavData[$schemeid]['navrs'] != '--' && $mfFrontendNavData[$schemeid]['navrs'] != '--'){
                if($mfCoreNavData[$schemeid]['navrs'] != $mfFrontendNavData[$schemeid]['navrs']){
                   $finalResultsetArr[$schemeid]['frontend']['navrs'] =  $mfFrontendNavData[$schemeid]['navrs'];
                   $finalResultsetArr[$schemeid]['core']['navrs']     =  $mfCoreNavData[$schemeid]['navrs'];
                  $flag = 'true';
                   
                }
                # Match array
//                else{
//                   $match[$schemeid]['frontend']['navrs'] =  $mfFrontendNavData[$schemeid]['navrs'];
//                   $match[$schemeid]['core']['navrs']     =  $mfCoreNavData[$schemeid]['navrs'];
//                }
            }
            else{
//                $finalResultsetArr[$schemeid]['frontend']['navrs'] =  (isset($mfFrontendNavData[$schemeid]['navrs'])) ? $mfFrontendNavData[$schemeid]['navrs'] : '--';
//                $finalResultsetArr[$schemeid]['core']['navrs']     =  (isset($mfCoreNavData[$schemeid]['navrs'])) ? $mfCoreNavData[$schemeid]['navrs'] : '--';
            }
//            echo  $flag;exit;
            /*core vs frontend redis comparison*/
            if(isset($mfCoreNavData[$schemeid]['navdate']) && isset($mfFrontendRedisNavData[$schemeid]['navdate']) && $mfCoreNavData[$schemeid]['navdate'] != '--' && $mfFrontendRedisNavData[$schemeid]['navdate'] != '--'){
                if($mfCoreNavData[$schemeid]['navdate'] != $mfFrontendRedisNavData[$schemeid]['navdate']){
                   $finalResultsetArr[$schemeid]['redis_frontend']['navdate'] =  $mfFrontendRedisNavData[$schemeid]['navdate'];
                   $finalResultsetArr[$schemeid]['core']['navdate']     =  $mfCoreNavData[$schemeid]['navdate'];
                   $flag = 'true';
                }
                # Match array
//                else{
//                   $match[$schemeid]['redis_frontend']['navdate'] =  $mfFrontendRedisNavData[$schemeid]['navdate'];
//                   $match[$schemeid]['core']['navdate']     =  $mfCoreNavData[$schemeid]['navdate'];
//                }
            }
            else{
//                $finalResultsetArr[$schemeid]['redis_frontend']['navdate'] =  (isset($mfFrontendRedisNavData[$schemeid]['navdate'])) ? $mfFrontendRedisNavData[$schemeid]['navdate'] : '--';
//                $finalResultsetArr[$schemeid]['core']['navdate']     =  (isset($mfCoreNavData[$schemeid]['navdate'])) ? $mfCoreNavData[$schemeid]['navdate'] : '--';
            }
//            echo '<pre>';echo $mfFrontendRedisNavData[$schemeid]['navdate'];
//            print_r($finalResultsetArr);
//            exit;
            if(isset($mfCoreNavData[$schemeid]['navrs']) && isset($mfFrontendRedisNavData[$schemeid]['navrs']) && $mfCoreNavData[$schemeid]['navrs'] != '--' && $mfFrontendRedisNavData[$schemeid]['navrs'] != '--'){
                if($mfCoreNavData[$schemeid]['navrs'] != $mfFrontendRedisNavData[$schemeid]['navrs']){
                   $finalResultsetArr[$schemeid]['redis_frontend']['navrs'] =  $mfFrontendRedisNavData[$schemeid]['navrs'];
                   $finalResultsetArr[$schemeid]['core']['navrs']     =  $mfCoreNavData[$schemeid]['navrs'];
                   $flag = 'true';
                }
                # Match array
//                else{
//                   $match[$schemeid]['redis_frontend']['navrs'] =  $mfFrontendRedisNavData[$schemeid]['navrs'];
//                   $match[$schemeid]['core']['navrs']     =  $mfCoreNavData[$schemeid]['navrs'];
//                }
            }
            else{
//                $finalResultsetArr[$schemeid]['redis_frontend']['navrs'] =  (isset($mfFrontendRedisNavData[$schemeid]['navrs'])) ? $mfFrontendRedisNavData[$schemeid]['navrs'] : '--';
//                $finalResultsetArr[$schemeid]['core']['navrs']     =  (isset($mfCoreNavData[$schemeid]['navrs'])) ? $mfCoreNavData[$schemeid]['navrs'] : '--';
            }
            
        }
//        echo count($finalResultsetArr);
//        print_r($finalResultsetArr);
//        exit;
            # Create Table
            $tabletag  = '<table border="2">';
            $tableHeader = '<tr> '
                            . '<th></th> '
                            . '<th>Scheme</th>'
                            . ' <th>Core NavDate</th>'
                             . ' <th>Core NavRs</th>'
                            . ' <th>Frontend NavDate</th>'
                            . ' <th>Frontend NavRs</th>'
                            . ' <th>Frontend Redis NavDate</th>'
                            . ' <th>Frontend Redis NavRs</th>'
                            . '</tr>';
            $tddata = '';
            $i = 1;
            foreach($finalResultsetArr as $schemeid => $value)
            {
                if($finalResultsetArr[$schemeid]['core']['navdate'] == '--' && $finalResultsetArr[$schemeid]['core']['navrs'] == '--' && $finalResultsetArr[$schemeid]['frontend']['navdate'] == '--' && $finalResultsetArr[$schemeid]['frontend']['navrs'] == '--' && $finalResultsetArr[$schemeid]['redis_frontend']['navdate'] == '--' && $finalResultsetArr[$schemeid]['redis_frontend']['navrs'] == '--' )
                {
                    continue;
                }
                else{
                    if($finalResultsetArr[$schemeid]['frontend']['navdate'] == '--' && $finalResultsetArr[$schemeid]['frontend']['navrs'] == '--' && $finalResultsetArr[$schemeid]['redis_frontend']['navdate'] == '--' && $finalResultsetArr[$schemeid]['redis_frontend']['navrs'] == '--'){
                              continue;
                     }
                     else{
                         $blankData = '<td>--</td>';
                        $tddata .= '<tr>';
                        $tddata .= '<td>'.$i.'</td>';
                        $tddata .= '<td>'.$schemeid.'</td>';
                        $tddata .= (isset($value['core']['navdate'])) ? '<td>'.$value['core']['navdate'].'</td>' :  $blankData;
                        $tddata .= (isset($value['core']['navrs'])) ? '<td>'.$value['core']['navrs'].'</td>' :  $blankData;
                        $tddata .= (isset($value['frontend']['navdate'])) ? '<td>'.$value['frontend']['navdate'].'</td>' :  $blankData;
                        $tddata .= (isset($value['frontend']['navrs'])) ? '<td>'.$value['frontend']['navrs'].'</td>' :  $blankData;
                        $tddata .= (isset($value['redis_frontend']['navdate'])) ? '<td>'.$value['redis_frontend']['navdate'].'</td>' :  $blankData;
                        $tddata .= (isset($value['redis_frontend']['navrs'])) ? '<td>'.$value['redis_frontend']['navrs'].'</td>' :  $blankData;

                        $tddata .= '</tr>';
                        $i++;
                     }
                    
                }
            }
            $tableEndTag = '</table>';
            $tableBody = $tabletag.$tableHeader.$tddata.$tableEndTag;
            
//           echo $tableBody;
//            exit;
           if($flag == 'true'){
            /*Send Mail*/
            $finalMailMessage = "";
            $finalMailMessage .= "<h3>Please check mismatch of MF Data in MMCore v/s Frontend v/s Frontend Redis.</h3>";
            $finalMailMessage .= $tableBody;


            foreach (self::TESTING_EMAIL as $k => $v) {
                    $mailData = array(
                        'subject' => "Navdate and Navrs Mismatch (Core MF v/s Frontend MF)",
                        'fromAddr' => array('email' => self::FROM_EMAIL_ID, 'name' => 'Tech'),
                        'toAddr' => $v,
                        'message' => $finalMailMessage,
                    );
    //                echo $finalAlertStr;
                    $this->sendEmail($mailData);	
                    $res[] = 'Mail sent to '.$v['email'];
            }
    //        print_r($res);
            Base\StatusCodes::successMessage(200, "success", $res);
        }
    }
    private function sendEmail($mailData){
        $subject = $mailData['subject'];
        $message = $mailData['message'];
        $mailObj = new Base\Mail(SMTP_HOST, SMTP_PORT, SMTP_USERNAME, SMTP_PASSWORD);
        $fromAddr = $mailData['fromAddr'];
        $toAddr = $mailData['toAddr'];
        $mailObj->sendMailViaSMTP($fromAddr, $toAddr, '', $subject, $message);
    }
}

