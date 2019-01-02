<?php
namespace Mojo\App\Controller\Valuation;

use Mojo\Core\Base as Base;
use Mojo\App as App;
use Mojo\Core\Db as Db;

class Mojodots extends App\Models\ValuationCheck\Mojodots{
    
    
    public function __construct() {
        parent::__construct();
        $this->_post = json_decode(file_get_contents('php://input'), true);
        if (empty($this->_post)) {
            $this->_post = [];
        }
        
        
    }
    public function setDotSummary($stockid=''){
       
        # Fetch Latest Stockids(updated_date) from Valuationmeter
        $collectionName = 'valuation_update';
        $valuationStockIds = $this->getQVUpdateStocksList($collectionName);
        unset($collectionName);
        
        # Fetch finalgrade of all above stocids from valuation_data mongo
        $collectionName = 'valuation_data';
        $mongoStockFGradeList = $this->getMongoQVStockFinalGrade($collectionName,$valuationStockIds);
      
        #Fetch all Redis DOT_SUMMARY Of above stocks #
        $redisStockDotSummary = $this->getRedisValuationRnkTxt(array_keys($mongoStockFGradeList));
//        pr($redisStockDotSummary);
//        exit;
        
        #Compare Mongo finalgrade with Redis valuation_rnktxt. If same skip else replace redis value to mongo #
        $newRedisUpdateArray = array();
        if(!empty($mongoStockFGradeList)){
            foreach($mongoStockFGradeList as $sid => $value){
                if(isset($redisStockDotSummary[$sid])){
                    if($redisStockDotSummary[$sid]['valuation_ranktext'] != $mongoStockFGradeList[$sid]['finalgrade']){
                        
                        
                        $newRedisUpdateArray[$sid]['quality_rank']      = $redisStockDotSummary[$sid]['quality_rank'];
                        $newRedisUpdateArray[$sid]['stk_sect']          = $redisStockDotSummary[$sid]['stk_sect'];
                        $newRedisUpdateArray[$sid]['quality_ranktext']  = $redisStockDotSummary[$sid]['quality_ranktext'];
                        $newRedisUpdateArray[$sid]['valuation_rank']    = $redisStockDotSummary[$sid]['valuation_rank'];
                      
                        $newRedisUpdateArray[$sid]['valuation_ranktext'] = $mongoStockFGradeList[$sid]['finalgrade'];
                        
                        $newRedisUpdateArray[$sid]['fin_points']        = $redisStockDotSummary[$sid]['fin_points'];
                        $newRedisUpdateArray[$sid]['fin_ranktext']      = $redisStockDotSummary[$sid]['fin_ranktext'];
                    }
                }
            }
        }
//        $allDotarr[] = $newRedisUpdateArray;
//        pr($newRedisUpdateArray);
//        exit;
        
        # Update in Redis #
        
        $updateredis = $this->updateAwsRedisValues($newRedisUpdateArray);
        if(!empty($updateredis))
            echo json_encode($updateredis);
        else
            echo 'No Records to update';
//        Base\StatusCodes::successMessage(200,'Data set successfully');
    }
    
}
?>