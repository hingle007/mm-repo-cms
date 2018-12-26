<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Mojo\App\Controller\Valuation;

/**
 * Description of VAluation of stocks
 *
 * @author Amit
 */
use Mojo\Core\Base as Base;
use Mojo\App as App;
use Mojo\Core\Db as Db;

class Valuation extends App\Models\ValuationCheck\ValuationCheck {

    private $_post;
    private $filterMailID = [];

    const MAX_MAIL_LIMIT = 50;
    const FROM_EMAIL_ID = array('email' => 'tech@marketsmojo.com', 'name' => 'Tech'); //'tech@marketsmojo.com';
    const TO_EMAIL = array(
        array('email' => 'amit@marketsmojo.com', 'name' => 'Amit'),
//                            array('email' => 'tech@marketsmojo.com', 'name' => 'Tech'),
//                            array('email' => 'support@marketsmojo.com', 'name' => 'Support'),
    );
    const NON_DISPLAY_ITEMS = array('does not qualify', 'risky');

    private $_holding_n_insurance_stocks = array(
        658168, 371122, 103061, 848865, 129404, 280329, 950755, 972978, 506931, 712234, 663472, 485202, 468391, 274022, 1002626, 245716, 935945, 688217, 498418, 710320, 683998, 151780, 648578, 774131, 724375, 461234, 928757, 354807, 374193, 139834, 406579, 424719, 261671, 661429, 306806, 799299, 984417, 292539, 808289, 343883, 991100, 126982, 999997, 464586, 449669, 588736, 282398, 802538, 442477, 724048, 950688, 312348, 1002663, 1002823, 1002829, 1002851, 1002871, 1002872, 885158, 873791, 1002585, 324585, 467058, 232533
    );

    const UNSET_FIELD = array('_id', 'lastmodified', 'traded', 'mcap', 'stockid', 'remarks_2', 'remarks_3');
    const UNSET_SEARCHFIELD = array('stockid', 'short_name', 'previous', 'current');
    const FIELD_LIST = [
        "stockid" => 'StockId',
        "short_name" => 'Company',
        "changePercent" => 'Change(%)',
        "Ind_Name" => 'Ind Name',
        "moslInOut" => 'MOSL InOut',
        "moslCreatedAt" => 'MOSL InOutDate',
        "previous" => 'Previous',
        "current" => 'Current',
        "totalscore" => 'Total Score',
        "mcap" => 'Mcap',
        "resDate" => 'ResultDate',
        "debtChange" => "Debt",
        "cftScore" => 'CFT(Score)',
        "priceDataChange" => 'Is Data Change?',
    ];

    public function __construct() {
        parent::__construct();
        $this->_post = json_decode(file_get_contents('php://input'), true);
        if (empty($this->_post)) {
            $this->_post = [];
        }
    }

    /*
     * Author : Amit 
     * Last Change Date: 04/10/2018
     * Desc: This function gets data from model and merge data to create single array as response of API
     */

    public function processValuationData() {
        
        $sdate = !empty($this->_post['sdate']) ? date('Y-m-d 00:00:00', strtotime($this->_post['sdate'])) : date('Y-m-d 00:00:00');
        $edate = !empty($this->_post['edate']) ? date('Y-m-d  23:59:59', strtotime($this->_post['edate'])) : date('Y-m-d  23:59:59');
        $filterStatus = !empty($this->_post['filterStatus']) ? $this->_post['filterStatus'] : 'Filtered';

        $data = $this->getValuationData($sdate, $edate);
        $finalArray = array();
//        pr($data['changePercentageData']);exit;
        $changePerData = $data['changePercentageData'];
        foreach ($data['valuationPreData'] as $key => $value) {
            $tableType = 'currentData';
            if (in_array($key, $data['unselectSid'])) {
                $tableType = 'last7DayData';
            }
            $changePercent = 0;
            /* if any of below is empty that stock id will not display */
            if (!empty($changePerData[$key]['prevCloseBse']['1D']) && !empty($changePerData[$key]['BSE_data']['cmp'])) {
                $changePercent = $this->calculateStockData($changePerData[$key]['prevCloseBse']['1D'], $changePerData[$key]['BSE_data']['cmp']);
            }
            if (!empty($changePerData[$key]['prevCloseNse']['1D']) && !empty($changePerData[$key]['NSE_data']['cmp'])) {
                $changePercentNse = $this->calculateStockData($changePerData[$key]['prevCloseNse']['1D'], $changePerData[$key]['NSE_data']['cmp']);
                if ($changePercentNse > 10) {
                    $changePercent = $changePercentNse;
                }
            }
            $tenthDay = date('Y-m-d', strtotime('-10 day'));
            if (
                    !empty($data['valuationCurrentfinalgrade'][$key]) &&
                    trim(strtolower($value['finalgrade'])) != trim(strtolower($data['valuationCurrentfinalgrade'][$key])) &&
                    !in_array($key, $this->_holding_n_insurance_stocks) &&
                    !in_array(strtolower($value['finalgrade']), self::NON_DISPLAY_ITEMS)
//                    ($changePercent > 10 || strtotime($tenthDay) < strtotime($data['resMcapData'][$key]['result']) || $filterStatus == 'Default')
            ) {
                $finalArray[$key]['stockid'] = (int) $key;
                $finalArray[$key]['company_name'] = !empty($data['detailsData'][$key]['short_name']) ? strtolower($data['detailsData'][$key]['short_name']) : '';
                $finalArray[$key]['short_name'] = !empty($data['detailsData'][$key]['short_name']) ? "<a href='https://www.marketsmojo.com/Stocks?StockId=" . $key . "' target='_blank'>" . $data['detailsData'][$key]['short_name'] . "</a>" : "";
                $finalArray[$key]['changePercent'] = !empty($changePercent) ? numberFormat($changePercent) . '%' : "";
                $finalArray[$key]['Ind_Name'] = !empty($data['detailsData'][$key]['Ind_Name']) ? $data['detailsData'][$key]['Ind_Name'] : "";
                $finalArray[$key]['moslInOut'] = !empty($data['moslInOut'][$key]) ? $data['moslInOut'][$key] : "-";
                $finalArray[$key]['moslCreatedAt'] = !empty($data['moslCreatedAt'][$key]) ? date("d M, Y", strtotime($data['moslCreatedAt'][$key])) : "-";

                $dotColorData = getDotColor(array('valuation_ranktext' => $value['finalgrade']), 'v');
                $prevcolor = isset($dotColorData['valuation_clr']) ? $dotColorData['valuation_clr'] : 'grey';
                $finalArray[$key]['previous'] = '<p class="cell_' . $prevcolor . '" align="center">' . $value['finalgrade'] . '</p>';
                $finalArray[$key]['prev_data'] = $value['finalgrade'];

                $currentGrade = !empty($data['valuationCurrentfinalgrade'][$key]) ? $data['valuationCurrentfinalgrade'][$key] : '';
                $dotColorData = getDotColor(array('valuation_ranktext' => $currentGrade), 'v');
                $currentcolor = isset($dotColorData['valuation_clr']) ? $dotColorData['valuation_clr'] : 'grey';
                $finalArray[$key]['current'] = '<p class="cell_' . $currentcolor . '" align="center">' . $currentGrade . '</p>';
                $finalArray[$key]['curr_data'] = $data['valuationCurrentfinalgrade'][$key];
                
                $finalArray[$key]['totalscore'] = !empty($data['valuationCurrentTotalscore'][$key]) ? numberFormat($data['valuationCurrentTotalscore'][$key]) : '';
                $finalArray[$key]['mcap'] = !empty($data['resMcapData'][$key]['mcap']) ? numberFormat($data['resMcapData'][$key]['mcap']) : "";
                $finalArray[$key]['resDate'] = !empty($data['resMcapData'][$key]['result']) ? date("d M, Y", strtotime($data['resMcapData'][$key]['result'])) : "";

                $finalArray[$key]['cftScore'] = !empty($data['dotSummary'][$key]) ? $data['dotSummary'][$key] : "";
                $finalArray[$key]['debtChange'] = !empty($data['valuationBeforeUpdate'][$key]) ? $data['valuationBeforeUpdate'][$key] : "";
                $finalArray[$key]['priceDataChange'] = !empty($data['multiFieldComparison'][$key]) ? $data['multiFieldComparison'][$key] : "";
                $finalArray[$key]['valuationMeter'] = !empty($data['valuationMeter'][$key]) ? $data['valuationMeter'][$key] : "";
                $finalArray[$tableType][$key] = $finalArray[$key];
                unset($finalArray[$key]);
            }
        }
        $tableHeader = array();
        foreach (self::FIELD_LIST as $headerKey => $headerVal) {
            $width = (10 * strlen($headerVal)) + 30;
            $tableHeader[] = array('field' => $headerKey, 'header' => $headerVal, 'width' => $width);
            if(in_array($headerKey, self::UNSET_SEARCHFIELD)){
                $dataSet['searchFilter'][$headerKey] = !empty($searchFilter[$headerKey])?$searchFilter[$headerKey]:'';
            }    
        }
        $finalArrayRes = array();
        $this->_post['currPrevName'] = 'current';
        if (!empty($this->_post['currPrevName']) && $this->_post['currPrevName'] == 'current') {
            $finalArrayRes = !empty($finalArray['currentData']) ? array_values($finalArray['currentData']) : array();
        } else if (!empty($this->_post['currPrevName']) && $this->_post['currPrevName'] == 'pending') {
            $finalArrayRes = !empty($finalArray['last7DayData']) ? array_values($finalArray['last7DayData']) : array();
        }
        
        $searchFilter = array();
        $searchFilter['stockid'] = !empty($this->_post['filterStockId'])?$this->_post['filterStockId']:"";
        $searchFilter['company_name'] = !empty($this->_post['filterShort_name'])?strtolower($this->_post['filterShort_name']):"";
        $searchFilter['prev_data'] = !empty($this->_post['filterPrevious'])?$this->_post['filterPrevious']:"";
        $searchFilter['curr_data'] = !empty($this->_post['filterCurrent'])?$this->_post['filterCurrent']:"";
        if(!empty(array_filter($searchFilter))){
            $filterdKey = array();
            $like = ['company_name'];
            foreach ($searchFilter as $key => $value) {
                if(!empty($value) && !empty($key)){
                    $temp = array();
                    $columnData = array_column($finalArrayRes,$key);
                    $temp = $this->string_in_array($value,  $columnData,$like,$key);
                    if(empty($temp)){
                        $filterdKey = array();
                        break;
                    }
                    if(!empty($filterdKey)){
                        $filterdKey = array_intersect($filterdKey, $temp);
                    } else {
                        $filterdKey = $temp;
                    }
                }    
            }
            $filterdFinalArray = array();
            if(!empty($filterdKey)){
                foreach ($filterdKey as $filterdkey => $filterdval) {
                    $filterdFinalArray[] = $finalArrayRes[$filterdval];
                }
            }
            $finalArrayRes = $filterdFinalArray;
        }
        
            
        $dataSet['rowCount'] = count($finalArrayRes);
        $dataSet['allStockId'] = array_column($finalArrayRes, 'stockid');
        $dataSet['lastmodified'] = ''; //$data['lastmodified'];
        $dataSet['lastUpdatedBy'] = ''; //$data['lastUpdatedBy'];
        $dataSet['tableHeader'] = $tableHeader;
        if(empty($this->_post['pagenum'])){
            $pageNum = 1;
        } else {
            $pageNum = $this->_post['pagenum'];
        }
        $startRec = ($pageNum - 1)*300;
        $endRec = $pageNum*300;
        
        $finalArrayRes = array_slice($finalArrayRes, $startRec, $endRec);
        $dataSet['data'] = array_values($finalArrayRes);
        $dataSet['currentPage'] = array_column($finalArrayRes, 'stockid');
        

        Base\StatusCodes::successMessage(200, "success", $dataSet);
    }
    
    public function string_in_array($searchVal, $haystack,$like,$key)
    {
        $temp = array();
        if(!empty($searchVal) && !empty($haystack)){
            if(in_array($key, $like)){
                $searchVal = strtolower($searchVal);
                $temp = array_keys(preg_grep("/$searchVal/", $haystack));
            } else {
                $temp = array_keys(array_intersect($haystack, [$searchVal]));
            }
        }   
        return $temp;
    }

    /*
     * Author : Amit 
     * Last Change Date: 04/10/2018
     * Desc: Updates finalgrade and total score based on stockid
     */

    public function updateValuationData() {
        if (!empty($this->_post)) {
            $update_sets = array();
            $filter = array();
            $this->_post['message'] = "Please check below updates on Valuation.<br><table border='1'><tr><td>StockID</td><td>Company Name</td><td>Previous Status</td><td>Current Status</td><td>TotalScore</td></tr>";
            $userid = '';
            if (!empty($this->_post['_userid'])) {
                $userid = $this->_post['_userid'];
            }
            $allStock = array();
            if (!empty($this->_post['_allStockId'][0])) {
                $allStock = $this->_post['_allStockId'][0];
            }
            $currStock = array();
            if (!empty($this->_post['_currPage'][0])) {
                $currStock = $this->_post['_currPage'][0];
            }
            foreach ($this->_post['_stockId'] as $value) {

                if (!empty($value['current']) && !empty($value['totalscore'])) {
                    $matches = array();
                    preg_match('/>(.*?)\</s', $value['current'], $matches);
                    if (empty($matches[1])) {
                        continue;
                    }
                    $update_sets[] = array("finalgrade" => $matches[1], "totalscore" => (float) $value['totalscore']);
                    $filter[] = array("stockid" => (int) $value['stockid']);
                    $this->_post['message'] .= "<tr><td><a href='https://www.marketsmojo.com/Stocks?StockId=" . $value['stockid'] . "'>" . $value['stockid'] . "</a></td><td>" . $value['short_name'] . "</td><td>" . $value['previous'] . "</td><td>" . $value['current'] . "</td><td>" . $value['totalscore'] . "</td></tr>";
                }
            }

            $this->_post['message'] .= "</table>";
            $data = $this->updateValuationRec($update_sets, $filter, $userid, $allStock,$currStock);

            if (!empty($data)) {
                foreach (self::TO_EMAIL as $k => $v) {
                    $mailData = array(
                        'subject' => "Valuation Update Details",
                        'fromAddr' => self::FROM_EMAIL_ID,
                        'toAddr' => $v,
                        'message' => $this->_post['message'],
                    );
//                    pr($mailData);exit;
                    $this->sendEmail($mailData);
                }
                Base\StatusCodes::successMessage(200, "success", "Updated Successfully!");
            }
        }
        Base\StatusCodes::errorMessage(706, "Whoop! Somthing  went wrong");
    }

    private function sendEmail($mailData) {
        $subject = $mailData['subject'];
        $message = $mailData['message'];
        $mailObj = new Base\Mail(SMTP_HOST, SMTP_PORT, SMTP_USERNAME, SMTP_PASSWORD);
        $fromAddr = $mailData['fromAddr'];
        $toAddr = $mailData['toAddr'];
        $mailObj->sendMailViaSMTP($fromAddr, $toAddr, '', $subject, $message);
    }

    private function calculateStockData($previousData, $currentCMP) {
        return @((((float) $currentCMP - (float) $previousData) / (float) $previousData) * 100);
    }

    public function dumpValuationData() {
        global $holidays;
        if (!in_array(date('Y-m-d'), $holidays)) {
            $res = $this->dumpPreValuationData();
        } else {
            $res = 'Today is holidays, Enjoy!';
        }
        Base\StatusCodes::successMessage(200, "success", $res);
    }
    
    public function priceCompareData(){
        $data = array();
//        $data = $this->price_compare_data('137473');
        if(!empty($this->_post)){
            $data = $this->price_compare_data($this->_post);
        }
//            pr($data);exit;
        Base\StatusCodes::successMessage(200, "success", $data);
    }

}
