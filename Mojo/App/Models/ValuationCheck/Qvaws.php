<?php

namespace Mojo\App\Models\ValuationCheck;

use Mojo\Core\Base as Base;
use Mojo\App as App;
use Mojo\Core\Db as Db;


class Qvaws
{
    private $_conn_mongo_r;
    private $_conn_mongo_w;
    private $_connection;
    
    private $StockList;
    private $subsectors = array();
    private $ind_banks = [ 43, 44 ];
    private $ind_finance = [ 35, 32, 31, 27, 22, 36, 38, 39, 40, 41, 33, 23, 28, 30, 34, 29 ];
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
    public function awsdot()
    {
//        echo '<pre>';
        $this->getStockList("","","","");
        
        $StockIDs = array_keys( $this->StockList );
	
        if ( count($StockIDs) === 0 )
        {
            echo "No Records";
            return;
        }
        
        $alldotdetails = array();
        
        
        
        
        
        $COLLECTIONNAME = "quality_data";

        $MDBFields = array( 'stockid', 'dotcolor', 'finalgrade', 'finalscore'  );

        $MDBCondn = array( "stockid" => array ( '$in' => $StockIDs  ) );
                
        $MDBSort = array( "stockid" => 1 );

        $MDBLimit = array("limit" => 0 );

        $SResult = $this->conn_mongo_w->query($COLLECTIONNAME, $MDBFields, $MDBCondn, array(), $MDBSort, $MDBLimit);
       
        
        $qualitydotdetails = array();
        
        foreach ( $SResult as $sres )
        {
            $singlequalitydotdet = array();
                    
            $singlequalitydotdet[ 'sid' ] = $sres['stockid'];
            
            
            if ( isset($sres['dotcolor']) && $sres['dotcolor'] == "grey" )
                $singlequalitydotdet[ 'q_txt' ] = "Does not qualify";
            elseif ( isset($sres['dotcolor']) && $sres['dotcolor'] == "red" )
                $singlequalitydotdet[ 'q_txt' ] = "Below Average";
            elseif ( isset( $sres['finalgrade'] ) )
                $singlequalitydotdet[ 'q_txt' ] = $sres['finalgrade'];
            else
                $singlequalitydotdet[ 'q_txt' ] = "NA";
            
           
            $singlequalitydotdet[ 'q_rank' ] = -999999;//Not Available
            
            
            if ( isset( $sres['finalscore'] ) )
                $singlequalitydotdet[ 'q_score' ] = $sres['finalscore'];
            else
                $singlequalitydotdet[ 'q_score' ] = -999999;
            
            $qualitydotdetails[ $sres['stockid'] ] = $singlequalitydotdet;
        }
        
        if ( count($qualitydotdetails) < 3000 )
        {
            echo "Error : Please check the quality count ". count($qualitydotdetails);
            return;
        }
        
        #######################################################################
//        pr($qualitydotdetails);
       
        
        
        $valuationdotdetails = array();
        
        $COLLECTIONNAME = "valuation_data";

        $MDBFields = array( 'stockid', 'totalscore', 'finalgrade' );

        $MDBCondn = array( "stockid" => array ( '$in' => $StockIDs  ) );
                
        $MDBSort = array( "stockid" => 1 );

        $MDBLimit = array("limit" => 0 );

        $VResult = $this->conn_mongo_w->query($COLLECTIONNAME, $MDBFields, $MDBCondn, array(), $MDBSort, $MDBLimit);
       
        foreach ( $VResult as $vres )
        {
            $singlvaluationdotdet = array();
           
            //$singlvaluationdotdet[ 'sid' ] = $vres['stockid'];
            
            if ( isset( $vres['finalgrade'] ) )
            $singlvaluationdotdet[ 'v_txt' ] = $vres['finalgrade'];
            else
            $singlvaluationdotdet[ 'v_txt' ] = "NA";            
            
            $singlvaluationdotdet[ 'v_rank' ] = -999999;//Not Available
            
            
            if ( isset( $vres['totalscore'] ) )
                $singlvaluationdotdet[ 'v_score' ] = $vres['totalscore'];
            else
                $singlvaluationdotdet[ 'v_score' ] = -999999;
            
            $valuationdotdetails[ $vres['stockid'] ] = $singlvaluationdotdet;
            
        }
//        pr($qualitydotdetails);
//        pr($valuationdotdetails);
//        exit;
        if ( count($valuationdotdetails) < 3000 )
        {
            echo "Error : Please check the valuation count ". count($valuationdotdetails);
            return;
        }
        
        foreach ( $StockIDs as $sid )
        {
            $dotdetails [ 'sid' ] = $sid;
            
            $dotdetails [ 'q_txt' ] = (isset($qualitydotdetails [ $sid ] [ 'q_txt' ])) ? $qualitydotdetails [ $sid ] [ 'q_txt' ] : 'NA' ;
            $dotdetails [ 'q_rank' ] = (isset($qualitydotdetails [ $sid ] [ 'q_rank' ])) ? $qualitydotdetails [ $sid ] [ 'q_rank' ] : 'NA' ;
            $dotdetails [ 'q_score' ] = (isset($qualitydotdetails [ $sid ] [ 'q_score' ])) ? $qualitydotdetails [ $sid ] [ 'q_score' ] : 'NA' ;
            
            $dotdetails [ 'v_txt' ] = (isset($valuationdotdetails [ $sid ] [ 'v_txt' ])) ? $valuationdotdetails [ $sid ] [ 'v_txt' ] : 'NA' ;
            $dotdetails [ 'v_rank' ] = (isset($valuationdotdetails [ $sid ] [ 'v_rank' ] )) ? $valuationdotdetails [ $sid ] [ 'v_rank' ]  : 'NA';
            $dotdetails [ 'v_score' ] = ($valuationdotdetails [ $sid ] [ 'v_score' ]) ? $valuationdotdetails [ $sid ] [ 'v_score' ] : 'NA' ;
            
            $alldotdetails[] = $dotdetails;
        }
       
        
        if ( count($alldotdetails) < 3000 )
        {
            echo "Error : Please check the count ". count($alldotdetails);
            return;
        }
        
        
        echo $DOT_KEY = "MM:ALL_DOT_DETAILS";
        echo "\n";  
        
//        $config = App\App::$_registry["redis"]['write'];
//        
//        $redisObj = new \Mojo\Lib\RedisClient($config['host'], $config['port'],$config['timeout']);
        
//        echo json_encode ( $alldotdetails );
//        exit;
        
        if ( $this->_redisMmcoreWriteObj->set( $DOT_KEY , json_encode ( $alldotdetails ) ) )
        echo "SuCCESS";
            else
        echo "FAIL";
           
        
        unset ( $redisObj );
        
    }
    public function getStockList($stockid , $limit = 10000, $sectorid = "", $sec = "" )
    {
        
        $COLLECTIONNAME = "stock_master";

        $MDBCondn = array(
            '$and' => [
            ["status" => "Active"], ["sublisting" => "Active"],
            ['$or' => array(["bse_traded" => array('$gte' => 1)], ["nse_traded" => array('$gte' => 1)])],
            [ "subsect_id" => array( '$gt' => 0 ) ]
            ]);
        
        
        $MDBSort = array("stockid" => 1);

        $MDBLimit = array("limit" => $limit);

        $VResult = $this->conn_mongo_w->query($COLLECTIONNAME, array(), $MDBCondn, array
            (), $MDBSort, $MDBLimit);
        
        //echo count($VResult);exit;
        $StockList = array();
        
        foreach ($VResult as $vr) {
            
            
            if ( stripos( $vr['comp_name'] , "dvr" ) === false ) {} else
            {
                continue; 
            }
            
           
           
            $StockList[$vr['stockid']]['stockid'] = (int)$vr['stockid'];
            $StockList[$vr['stockid']]['comp_name'] = $vr['comp_name'];
            $StockList[$vr['stockid']]['ind_code'] = (int)$vr['ind_code'];
            $StockList[$vr['stockid']]['ind_name'] = (string)$vr['ind_name'];
            $StockList[$vr['stockid']]['subsect_id'] = (int)$vr['subsect_id'];
            
        }
        
        $this->StockList = $StockList;
                        
        unset($VResult);
        unset($COLLECTIONNAME);
        unset($MDBCondn);
        unset($MDBSort);
        unset($MDBLimit);

        return $StockList;
    }
    
}
?>
