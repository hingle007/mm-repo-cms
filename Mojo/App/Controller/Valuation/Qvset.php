<?php
namespace Mojo\App\Controller\Valuation;

/**
 * Description of VAluation of stocks
 *s
 * @author Amit
 */
use Mojo\Core\Base as Base;
use Mojo\App as App;
use Mojo\Core\Db as Db;

class Qvset extends App\Models\ValuationCheck\Qvset{
    
    CONST LAST_TRADED_DATE = 'HIST_PRICE';
    public function __construct() {
        parent::__construct();
        $this->_post = json_decode(file_get_contents('php://input'), true);
        if (empty($this->_post)) {
            $this->_post = [];
        }
    }
    public function valuation( $date = "" , $stockid = "" )
    {   
        global $holidays;
        if(in_array(date('D'),['Sat','Sun']) || in_array(date('Y-m-d'),$holidays)) {
            $param['sid'] = [1];
            $param['valKey'] = "INDEX";
            $param['valFields'] = self::LAST_TRADED_DATE;
             
            $result = $this ->getLastTradeDate($param);
            if(!empty($result)){
                $lastRec = end($result);
                $dt = !empty($lastRec['dt'])?date('Y-m-d',strtotime($lastRec['dt'])):$yesterday;
            }
        } 
       
        
        $date = (!empty($dt))?$dt:date("Y-m-d");
       $res = $this->getvaluation( $date,$stockid );
       pr($res);
       
    }
    private function getLastTradeDate($requiredData) {
        $formateData = array();
        $param['sid'] = $requiredData['sid'];
        $param['valKey'] = $requiredData['valKey'].":";
        $param['valFields'] = $requiredData['valFields'];
        $result = $this->getRedisPipeData($param);
        $formateData = isset($result[0])?json_decode($result[0],true):(!empty($result[$valFields])?json_decode($result[$valFields],true):array());
        return $formateData;
    }
    
}
?>