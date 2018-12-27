<?php
namespace Mojo\App\Models\ValuationCheck;

use Mojo\Core\Base as Base;
use Mojo\App as App;
use Mojo\Core\Db as Db;

class Qvset {
    
    
    private $_conn_mongo_r;
    private $_connection;
    private $_mongo;
    private $conn_mongo_w = null;
    private $_redisMmcoreReadObj;
    private $_redisMmcoreWriteObj;
    
    private $_redis_prefix='MM:STOCK';
    
    private $_stock_fields = array(
        'valuation_meter'=>'VALUATION_METER',
        'valuation_details'=>'VALUATION_DETAILS',
        'price_dot_graph'=>'GRAPH',
        'industry_stocks'=>'INDUSTRY_STOCKS',
        'graph'=>'GRAPH',
        'peers'=>'PEERS',
        'fin_quarter_result'=>'FINTREND_QUARTER_RESULT',
        'ind_details'=>'DETAILS',
        'quality_details'=>'QUALITY_DETAILS',
        'dot_summary'=>'DOT_SUMMARY',
        'fin_mojo_pts'=>'FIN_MOJO_PTS',
        'fin_details'=>'FIN_DETAILS',
        'technicals'=>'technicals'
    );
    
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
        $this->_connection->closeConnection();
        unset($this->conn_mongo_r);        
    }
    public function getvaluation( $date,$stockid )
    {
        
        //Getting MongoDB valuation data
        $valmdb = $this->getvaluationmdb( $stockid );
        
        //Getting MySQL valuation data
        $valmysql = $this->getvalmysql( $date,$stockid );
        
        //Getting MySQL valuation meter data
        $valmeter = $this->getValuationMeter( $stockid );
       
        unset($stockid);//Unset because same variable name is used in bottom.
        
        $stockListWithMeterIssue = $this->findStocksHavingMeterIssue(array_keys($valmysql),$valmeter,$valmdb);
//        pr($stockListWithMeterIssue);
        
//        exit;
        $keysToreplace = array( 'pe', 'hist_pe', 'ev_ebidta', 'hist_ev_ebidta', 'peg_ratio', 'mcap', 'ranktext'  );
        foreach ( $valmysql as $stockid => $valdata )
        {
            //If MongoDB valuation data for corresponding stock not available skip
            if ( !isset( $valmdb[ $stockid ] ) )
            {
                continue;
            }
            //Mapping of MongDB stock data with Mysql stock data
            foreach ( $keysToreplace as $key )
            {
                if ( isset( $valmdb[ $stockid ] [ $key ] ) )
                {
                    $valmysql[ $stockid ] [ $key ]  = $valmdb[ $stockid ] [ $key ];
                }
            }
                        
            if ( isset( $valmdb[ $stockid ] [ 'errorcodes' ] ) )
            {
                $valmysql[ $stockid ] [ 'errorcodes' ]  = $valmdb[ $stockid ] [ 'errorcodes' ];
            }
            
            if ( isset( $valmdb[ $stockid ] [ 'highdiv' ] ) )
            {
                $valmysql[ $stockid ] [ 'highdiv' ]  = $valmdb[ $stockid ] [ 'highdiv' ];
            }
            
            
            #### Newl added
            if ( isset( $valmdb[ $stockid ] [ 'profit1yrgrowth' ] ) )
            {
                $valmysql[ $stockid ] [ 'profit1yrgrowth' ]  = $valmdb[ $stockid ] [ 'profit1yrgrowth' ];
            }
            
            if ( isset( $valmdb[ $stockid ] [ 'sales' ] ) )
            {
                $valmysql[ $stockid ] [ 'sales' ]  = $valmdb[ $stockid ] [ 'sales' ];
            }
            if ( isset( $valmdb[ $stockid ] [ 'bookvalue' ] ) )
            {
                $valmysql[ $stockid ] [ 'bookvalue' ]  = $valmdb[ $stockid ] [ 'bookvalue' ];
            }
            if ( isset( $valmdb[ $stockid ] [ 'annualebitda' ] ) )
            {
                $valmysql[ $stockid ] [ 'annualebitda' ]  = $valmdb[ $stockid ] [ 'annualebitda' ];
            }
            if ( isset( $valmdb[ $stockid ] [ 'annualebit' ] ) )
            {
                $valmysql[ $stockid ] [ 'annualebit' ]  = $valmdb[ $stockid ] [ 'annualebit' ];
            }
            
            if ( isset( $valmdb[ $stockid ] [ 'extremestatus' ] ) )
            {
                $valmysql[ $stockid ] [ 'extremestatus' ]  = $valmdb[ $stockid ] [ 'extremestatus' ];
            }

			//Added on 6 Mar 2018

			if ( isset( $valmdb[ $stockid ] [ 'v_ebit' ] ) )
            {
                $valmysql[ $stockid ] [ 'v_ebit' ]  = $valmdb[ $stockid ] [ 'v_ebit' ];
            }
			
			if ( isset( $valmdb[ $stockid ] [ 'v_ev2sales' ] ) )
            {
                $valmysql[ $stockid ] [ 'v_ev2sales' ]  = $valmdb[ $stockid ] [ 'v_ev2sales' ];
            }

			if ( isset( $valmdb[ $stockid ] [ 'v_npa2bookvalue' ] ) )
            {
                $valmysql[ $stockid ] [ 'v_npa2bookvalue' ]  = $valmdb[ $stockid ] [ 'v_npa2bookvalue' ];
            }

			if ( isset( $valmdb[ $stockid ] [ 'v_price2bookvalue' ] ) )
            {
                $valmysql[ $stockid ] [ 'v_price2bookvalue' ]  = $valmdb[ $stockid ] [ 'v_price2bookvalue' ];
            }

			if ( isset( $valmdb[ $stockid ] [ 'v_dividendyield' ] ) )
            {
                $valmysql[ $stockid ] [ 'v_dividendyield' ]  = $valmdb[ $stockid ] [ 'v_dividendyield' ];
            }

			if ( isset( $valmdb[ $stockid ] [ 'v_roa' ] ) )
            {
                $valmysql[ $stockid ] [ 'v_roa' ]  = $valmdb[ $stockid ] [ 'v_roa' ];
            }

			if ( isset( $valmdb[ $stockid ] [ 'v_ev2capemp' ] ) )
            {
                $valmysql[ $stockid ] [ 'v_ev2capemp' ]  = $valmdb[ $stockid ] [ 'v_ev2capemp' ];
            }

			if ( isset( $valmdb[ $stockid ] [ 'v_roce' ] ) )
            {
                $valmysql[ $stockid ] [ 'v_roce' ]  = $valmdb[ $stockid ] [ 'v_roce' ];
            }

			if ( isset( $valmdb[ $stockid ] [ 'v_roe' ] ) )
            {
                $valmysql[ $stockid ] [ 'v_roe' ]  = $valmdb[ $stockid ] [ 'v_roe' ];
            }
            
            if(!empty($valmeter[$stockid]))
            {
                $valmysql[ $stockid ]['meter'] = $valmeter[$stockid];
            }
        }
        //'ebit', 'ev2sales', 'npa2bookvalue', 'price2bookvalue', 'dividendyield', 'roa',  'ev2capemp', 'roce', 'roe'
       
        if ( count($valmysql) > 0 )
        {
        
//            $config = App\App::$_registry["redis"]['write'];
//            $redisObj = new \Mojo\Lib\RedisClient($config['host'], $config['port'],$config['timeout']);
            $subkey = $this->_stock_fields['valuation_details'];
                foreach($valmysql as $sid => $value){
                    
                    /* if meter is not in range with previous price then skip those stocks */
                    if(in_array($sid, $stockListWithMeterIssue)){
                        continue;
                    }                    
                    $key = $this->_redis_prefix.':'.$sid;
                    
                    $this->_redisMmcoreWriteObj->hSet($key,$subkey,json_encode($value));
                }
             unset($redisObj);
             Base\StatusCodes::successMessage(200,'Data set successfully');
            
        }
    }
    public function getvaluationmdb( $stockid )
    {
        $date = (int)date('Ymd');
        $newVal = array();
        //$date = 20171229;//REMOVE
        
        $COLLECTIONNAME = "valuation_data";
        $MDBFields = array( 'date', 'stockid', 'highdiv', 'peratio' , 'peratio_h', 'stockid', 'profit1yrgrowth', 'sales', 'bookvalue', 'annualebitda', 'annualebit', 'extremestatus', 'ev2ebidta' , 'ev2ebitda_h', 'peg', 'mcap', 'finalgrade', 'errorcodes',
		'ebit', 'ev2sales', 'npa2bookvalue', 'price2bookvalue', 'dividendyield', 'roa', 'ev2capemp', 'roce', 'roe'	
		);
        //$MDBCondn = array( 'date' => (int)$date  );//, 'stockid' => 399834 REMOVE STOCKID
        
        if ( !empty( $stockid ) )
            $MDBCondn = array( "stockid" => (int)$stockid );
        else
            $MDBCondn = array("stockid" => array('$ne' => 0)); // Select all stocks where stockid != 0
        $MDBSort = array( 'stockid' => 1 );
        $MDBLimit = array( 'limit' => 0 );
        
        $SResult = $this->conn_mongo_r->query($COLLECTIONNAME, $MDBFields, $MDBCondn, array(), $MDBSort, $MDBLimit);
        
        $finalResult = array();
        
        foreach ( $SResult as $value )
        {
            
            $sid = $value['stockid'];
            $newVal['dt'] = (isset($value['date'])) ? $value['date'] : "";
            
            $newVal['profit1yrgrowth'] = ( isset($value['profit1yrgrowth']) ? $value['profit1yrgrowth'] : "NA" );
            $newVal['sales'] = ( isset($value['sales']) && $value['sales'] != 'NA' ? numberFormat($value['sales'],2) : "" );
            $newVal['bookvalue'] = ( isset($value['bookvalue'])  && $value['bookvalue'] != 'NA' ? numberFormat($value['bookvalue'],2) : "" );
            $newVal['annualebitda'] = ( isset($value['annualebitda'])  && $value['annualebitda'] != 'NA' ? numberFormat($value['annualebitda'],2) : "" );
            $newVal['annualebit'] = ( isset($value['annualebit'])  && $value['annualebit'] != 'NA' ? numberFormat($value['annualebit'],2) : "" );
            $newVal['extremestatus'] = ( isset($value['extremestatus']) ? (int)$value['extremestatus'] : "" );
            
            
            $newVal['highdiv'] = ( isset($value['highdiv']) && $value['highdiv'] != 'NA' ? numberFormat($value['highdiv'],2) : "" );
            
            $newVal['pe'] = ( isset($value['peratio']) && $value['peratio'] != 'NA' ? numberFormat($value['peratio'],2) : "" );
            $newVal['hist_pe'] = ( isset($value['peratio_h']) && $value['peratio_h'] != 'NA' ? numberFormat($value['peratio_h']) : "" );
            $newVal['ev_ebidta'] = ( isset($value['ev2ebidta']) && $value['ev2ebidta'] != 'NA' ? numberFormat($value['ev2ebidta'],2) : "" );
            
            
            $newVal['hist_ev_ebidta'] = ( isset($value['ev2ebitda_h']) && $value['ev2ebitda_h'] != 'NA' ? numberFormat($value['ev2ebitda_h'],2) : "" );
            $newVal['peg_ratio'] = ( isset($value['peg']) && $value['peg'] != 'NA' ? numberFormat($value['peg']) : "" );
            $newVal['mcap'] = ( isset($value['mcap']) && $value['mcap'] != 'NA'  ? numberFormat($value['mcap'],2) : "" );
            
            //$newVal['ranktext'] = ( isset($value['finalgrade']) ? $value['finalgrade'] : "" );
            
            
            $newVal['ranktext'] = "Does not qualify";
            if ( isset( $value['finalgrade']  )  && $value['finalgrade'] !== ""  )
            {
                $newVal['ranktext'] = $value['finalgrade'];
            }           
            
            
            $newVal['errorcodes'] = ( isset($value['errorcodes']) ? $value['errorcodes'] : "" );

				 
			$newVal['v_ebit'] = ( isset($value['ebit']) ? $value['ebit'] : "" );
			$newVal['v_ev2sales'] = ( isset($value['ev2sales']) ? $value['ev2sales'] : "" );
			$newVal['v_npa2bookvalue'] = ( isset($value['npa2bookvalue']) ? $value['npa2bookvalue'] : "" );
			$newVal['v_price2bookvalue'] = ( isset($value['price2bookvalue']) ? $value['price2bookvalue'] : "" );
			$newVal['v_dividendyield'] = ( isset($value['dividendyield']) ? $value['dividendyield'] : "" );
			$newVal['v_roa'] = ( isset($value['roa']) ? $value['roa'] : "" );
			$newVal['v_ev2capemp'] = ( isset($value['ev2capemp']) ? $value['ev2capemp'] : "" );
			$newVal['v_roce'] = ( isset($value['roce']) ? $value['roce'] : "" );
			$newVal['v_roe'] = ( isset($value['roe']) ? $value['roe'] : "" );
            
            $finalResult[$sid] = $newVal;
                
                
        }
        
        return $finalResult;
    }
     public function getvalmysql( $date, $stockid )
    {    
        $stkcondn = "";
        if ( !empty($stockid) )
            $stkcondn = " stk_id=".(int)$stockid." and ";
        
        $query  = "select price_adj_book_val as book_adj_val,hist_price_adj_book_val as hist_book_adj_val,stk_id as stockid,ind_code,valuation_rk,valuation_sc,pe,hist_pe,ev_ebidta,hist_ev_ebidta,peg_ratio,eps_growth_2y,eps_growth_4y,
                    abv_growth_2y,abv_growth_4y,stock_return_1y,stock_return_3y,valuation_text1,valuation_text2,stock_price,mcap,net_profit,date as dt
                    from mojo_valuation_scorecard_v2  where ".$stkcondn." date >= ? ";//stk_id=399834 and REMOVE
        
        $params = array();
        
        $params[] = $date." 00:00:00";
        //$params[] = "2018-01-02 00:00:00";//REMOVE
        
//        echo $query;exit;
        $result = $this->_connection->query($query,$params);
        
        
        
        $finalResult = array();
        if(!empty($result)){
            foreach($result as $value){
                $sid = $value['stockid'];
                $newVal['ind_code'] = $value['ind_code'];
                $newVal['rank'] = $value['valuation_rk'];
                $newVal['ranktext'] = $value['valuation_sc'];
                $newVal['pe'] = numberFormat($value['pe'],2);
                $newVal['hist_pe'] = numberFormat($value['hist_pe']);
                $newVal['ev_ebidta'] = numberFormat($value['ev_ebidta'],2);
                $newVal['hist_ev_ebidta'] = numberFormat($value['hist_ev_ebidta'],2);
                $newVal['peg_ratio'] = numberFormat($value['peg_ratio']);
                $newVal['eps_growth_2y'] = numberFormat($value['eps_growth_2y'],2);
                $newVal['eps_growth_4y'] = numberFormat($value['eps_growth_4y'],2);
                $newVal['abv_growth_2y'] = numberFormat($value['abv_growth_2y']);
                $newVal['abv_growth_4y'] = numberFormat($value['abv_growth_4y'],2);
                $newVal['stock_return_1y'] = numberFormat($value['stock_return_1y'],2);
                $newVal['stock_return_3y'] = numberFormat($value['stock_return_3y'],2);
                $newVal['valuation_text1'] = $value['valuation_text1'];
                $newVal['valuation_text2'] = $value['valuation_text2'];
                $newVal['stock_price'] = numberFormat($value['stock_price']);
                $newVal['mcap'] = numberFormat($value['mcap'],2);
                $newVal['net_profit'] = numberFormat($value['net_profit'],2);
                $newVal['book_adj_val'] = (!empty($value['book_adj_val']))?numberFormat($value['book_adj_val'],2):0;
                $newVal['hist_book_adj_val'] = (!empty($value['hist_book_adj_val']))?numberFormat($value['hist_book_adj_val'],2):0;
                $newVal['dt'] = $value['dt'];
                $finalResult[$sid] = $newVal;
            }
        }
        return $finalResult;
        
    }
    public function getValuationMeter($stockid=''){
        $query = " select StockID as stockid, ValuationRank as val_rank, PriceFrom as pricefrom, PriceTo as priceto from mojo_valuation_scorecard_meter";
        $params = array();
        if(!empty($stockid)){
            $query .=" where StockID=?"; 
            $params[] = (int)$stockid;
        }
        $result = $this->_connection->query($query,$params);
        $finalResult = array();
        foreach($result as $value){
            $stockid = $value['stockid'];
            $rank = $value['val_rank'];
            $newVal['price_from'] = round($value['pricefrom']);
            $newVal['price_to'] = round($value['priceto']);
            switch ($rank){
                case 0 : $ranktext = 'Expensive';break;
                case 1 : $ranktext = 'Fair';break;
                case 2 : $ranktext = 'Attractive';break;
                case 3 : $ranktext = 'Very Attractive';break;
                case -1 : $ranktext = 'Very Expensive';break;
                case -2: $ranktext = 'Risky';break;
                case -2: $ranktext = 'Very Risky';break;
            }
            $newVal['ranktext'] = $ranktext;
            $finalResult[$stockid][] = $newVal;
        }
        return $finalResult;
    }
    public function findStocksHavingMeterIssue($stocklist = array(),$valuationMeterData = array(),$valuationData = array()){
        $issusesStock = array();
        if(!empty($stocklist)){
//            $config = App\App::$_registry["redis"]['read'];
//           $redisObj = new \Mojo\Lib\RedisClient($config['host'], $config['port'],$config['timeout']);
            
            $pipe = $this->_redisMmcoreReadObj->pipeline();
            foreach($stocklist as $id){
                $pipe->hget('MM:STOCK:'.$id,'PREVCLOSE_INFO');
            }
            $result = $pipe->exec();
            $skip_text = array('risky','does not qualify','very risky','ignore');
            if(!empty($result)){
                foreach ($result as $v){
                     $prevData =  json_decode($v,true);
                     $sid = isset($prevData['bse']['stockid'])?$prevData['bse']['stockid']:(isset($prevData['nse']['stockid'])?$prevData['nse']['stockid']:0);
                     if(!empty($sid)){
                         $price1D = isset($prevData['bse']['previousclose_1D'])?round($prevData['bse']['previousclose_1D']):(isset($prevData['nse']['previousclose_1D'])?round($prevData['nse']['previousclose_1D']):0);
                         $stockMeterData = isset($valuationMeterData[$sid])?$valuationMeterData[$sid]:array();
                         $valuationText = isset($valuationData[$sid]['ranktext'])?$valuationData[$sid]['ranktext']:"";
                         if(!empty($stockMeterData) && !empty($price1D)){
                             foreach($stockMeterData as $metersData){
                                 $priceFrom = isset($metersData['price_from'])?$metersData['price_from']:0;
                                 $priceTo = isset($metersData['price_to'])?$metersData['price_to']:0;
                                 $ranktext = isset($metersData['ranktext'])?$metersData['ranktext']:"";
                                 $correct = (in_array(strtolower($valuationText),$skip_text))?"-":0;                                 
                                        if($price1D >= $priceFrom && $price1D <= $priceTo && !in_array(strtolower($valuationText),$skip_text)){
                                            if(strtolower($valuationText) == strtolower($ranktext) ){
                                                $correct  =1;break;
                                            }
                                        }
                                 }                                 
                                 if($correct == 0 ){
                                     $issusesStock[] = $sid;
                                }
                         }
                     }
                }
            }
        }
        return $issusesStock;
    }
}
?>