<?php
namespace Mojo\App\Models\ValuationCheck;

/**
 * Description of Valuation Check
 *
 * @author Amit
 */
use Mojo\Core\Base as Base;
use Mojo\App as App;
use Mojo\Core\Db as Db;

class Dot {
    
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
    
    public function __construct()
    {
        $this->_connection = new Db\Dbconfig('write');//create connection//liveprice_read
        $this->conn_mongo_r = new Base\MongoDb('mmcore_read');
        $this->conn_mongo_w = new Base\MongoDb("mmcore_write");       
    }

    public function __destruct()
    {
        $this->_connection->closeConnection();
        unset($this->conn_mongo_r);        
    }
    public function overwritemysql()
    {
        $mailObj = new \Mojo\Core\Base\Mail(SMTP_HOST, SMTP_PORT, SMTP_USERNAME, SMTP_PASSWORD);
        $fromAddr = array('email' => 'tech@marketsmojo.com', 'name' => 'Markets MOJO Alerts');
        $toAddr = array(
            ['email' => 'tech@marketsmojo.com', 'name' => 'MarketsMojo Tech']
            );
        $this->line_break = "<br/>";
        echo "####### VALUATION ######\n";
        $this->overwrite_data = '<html><body><b>Manual update Process started for quality and valuation </b>: '. date("Y-m-d H:i:s") . $this->line_break . "</b>";
        
        
        $this->updateFinalgradeManually();
        $this->overwrite_data .= '<b>Manual update Process End</b>'. date("Y-m-d H:i:s") . $this->line_break. $this->line_break. $this->line_break. $this->line_break;
        $this->overwrite_data .= '<b>Valuation Overwrite process started'  . date("Y-m-d H:i:s") . $this->line_break. $this->line_break. $this->line_break. $this->line_break;
        
        $this->overwrite_data .= 'Valuation Process started: '. date("Y-m-d H:i:s") . $this->line_break . "</b>";
       
        $vdata = $this->getqvdata( 'valuation' );  
        echo '<pre>';
        pr($vdata);
        exit;
        if(empty($vdata))
            $this->overwrite_data .= "No Data provided for valuation update.";
        $this->overwrite_data .= '*****Mysql update started*****' . $this->line_break;
        $this->setmysqlvdata( $vdata );
        $this->overwrite_data .= '*****Mysql update end*****'. $this->line_break;
        $this->overwrite_data .= '<b>Valuation Process End</b>'. date("Y-m-d H:i:s") . $this->line_break. $this->line_break. $this->line_break. $this->line_break;
        $this->overwrite_data .= '<b>Overwrite process End' . date("Y-m-d H:i:s") . '</b></body></html>';
       
        echo $this->overwrite_data;
        echo $mailObj->sendMailViaSMTP($fromAddr, $toAddr, '', "Valuation Overwrite Process", $this->overwrite_data);
        
        echo "\n\n\ ####### QUALITY ######\n";
        $this->overwrite_data = '<html><body><b>Quality Overwrite process started'  . date("Y-m-d H:i:s") . $this->line_break. $this->line_break. $this->line_break. $this->line_break;
        
        $this->overwrite_data .= 'Quality Process started</b>'. date("Y-m-d H:i:s") . $this->line_break;
        $qdata = $this->getqvdata( 'quality' );     
        if(empty($qdata))
            $this->overwrite_data .= "No Data provided for Quality update.";
        $this->overwrite_data .= '*****Mysql update started*****' . $this->line_break;
        $this->setmysqlqdata( $qdata );
        $this->overwrite_data .= '*****Mysql update end*****' . $this->line_break;
        $this->overwrite_data .= '<b>Quality Process End</b>'. date("Y-m-d H:i:s") . $this->line_break. $this->line_break. $this->line_break. $this->line_break;
		
        echo "\n\n\ ####### UPDATE STATUS 999 ######\n";
        $this->overwrite_data .= '<b>Update Status 999 process started</b>' . date("Y-m-d H:i:s"). $this->line_break;
	$this->update999();
        $this->overwrite_data .= '<b>Update Status 999 process End</b>' . date("Y-m-d H:i:s") . $this->line_break. $this->line_break. $this->line_break. $this->line_break;
        $this->overwrite_data .= '<b>Overwrite process End' . date("Y-m-d H:i:s") . '</b></body></html>';
        
        echo $mailObj->sendMailViaSMTP($fromAddr, $toAddr, '', "Quality Overwrite Process", $this->overwrite_data);
    }
    public function updateFinalgradeManually($section = array('quality','valuation')){
         echo '<pre>';
            $manual_change['quality'] = array(
                        658168 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        371122 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        103061 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        848865 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        129404 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        280329 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        950755 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        972978 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        506931 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        712234 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        663472 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        485202 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        468391 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        274022 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        1002626 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        245716 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        935945 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        688217 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        498418 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        710320 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        683998 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        151780 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        648578 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        774131 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        724375 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        461234 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        928757 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        354807 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        374193 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        139834 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        406579 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        424719 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        261671 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        661429 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        306806 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        799299 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        984417 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        292539 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        808289 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        343883 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        991100 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        126982 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        999997 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        464586 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        449669 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        588736 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        282398 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        802538 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        442477 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        724048 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        950688 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        312348 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        1002663 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        1002823 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        1002829 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        1002851 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        1002871 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        873791 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        1002585 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        324585 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        467058 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        232533 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        1002872 => array('dotcolor' => '', 'finalgrade' => '')
                );
                $manual_change['valuation'] = array(
                        658168 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        371122 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        103061 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        848865 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        129404 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        280329 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        950755 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        972978 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        506931 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        712234 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        663472 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        485202 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        468391 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        274022 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        1002626 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        245716 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        935945 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        688217 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        498418 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        710320 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        683998 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        151780 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        648578 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        774131 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        724375 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        461234 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        928757 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        354807 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        374193 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        139834 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        406579 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        424719 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        261671 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        661429 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        306806 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        799299 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        984417 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        292539 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        808289 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        343883 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        991100 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        126982 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        999997 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        464586 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        449669 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        588736 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        282398 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        802538 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        442477 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        724048 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        950688 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        312348 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        1002663 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        1002823 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        1002829 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        1002851 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        1002871 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        1002872 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        885158 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        873791 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        1002585 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        324585 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        467058 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify'),
                        232533 => array('dotcolor' => 'grey', 'finalgrade' => 'Does not qualify')
                );
            if(!empty($manual_change))
            {
                foreach($section as $sect)
                {
                    if($sect == "valuation")
                    {
                        $collection = "valuation_data";
                        $data = (!empty($manual_change['valuation']))?$manual_change['valuation']:array();
                    }
                    else
                    {
                        $collection = "quality_data";
                        $data = (!empty($manual_change['quality']))?$manual_change['quality']:array();
                    }
                    if(!empty($data))
                    {
                        $filter = array();
                        $documents = array();
                        $this->overwrite_data .=  "Manual process start: " . $sect . $this->line_break;
                        foreach($data as $key=>$value)
                        {
                            if(!empty($value))
                            {
                                $projection_temp = array();
                                foreach($value as $field=>$field_Value)
                                {
                                    $projection_temp[$field] = $field_Value;
                                }
                                $filter[] = array("stockid" => (int)$key);             
                                $documents[] = $projection_temp;
                            }
                        }
                       
                        echo $collection;
                        pr($filter);
                        pr($documents);
                        exit;
                        
                        if ( count($documents) > 0)
                        {
                            if ($this->conn_mongo_w->multiupdate($collection, $documents, $filter))
                                $this->overwrite_data .=  ":Sucess: " . $sect . $this->line_break;
                            else
                               $this->overwrite_data .=  ":Fail: " . $sect . $this->line_break;
                        }
                    }
                }
            }

            return;
    }
    public function getqvdata( $section )
    {   
        if ( $section == "valuation" )
        {
            $COLLECTIONNAME = "valuation_data";
            $MDBFields = array("stockid", "date", "finalgrade", "dotcolor" ,"peg","peratio","ev2ebidta","profit1yrgrowth","eps");
        }
        elseif ( $section == "quality" )
        {
            $COLLECTIONNAME = "quality_data";
            $MDBFields = array("stockid", "date", "finalgrade", "dotcolor" );
        }
        
        if ( $section == "quality" )
        {
            $MDBFields[] = "quarter";
        }
        	 
        $MDBCondn = array();

        $MDBSort = array("stockid" => -1);

        $MDBLimit = array( "limit" => 0 );

        $SResult = $this->conn_mongo_r->query($COLLECTIONNAME, $MDBFields, $MDBCondn, array(), $MDBSort, $MDBLimit);

        $qvdata = array();
        foreach ( $SResult as $res )
        {
            if ( isset($res['finalgrade']) && !empty($res['finalgrade']) )
            {
                $qvdata[ $res['stockid'] ] ['grade'] = $res['finalgrade'] ;
                if($section == 'valuation'){
                    $qvdata[ $res['stockid'] ] ['pe'] = numberFormat($res['peratio']);
                    $qvdata[ $res['stockid'] ] ['ev2ebidta'] = numberFormat($res['ev2ebidta']);
                    $qvdata[ $res['stockid'] ] ['profit1yrgrowth'] = numberFormat($res['profit1yrgrowth']);                    
                    $qvdata[ $res['stockid'] ] ['peg'] = numberFormat($res['peg']);
                    $qvdata[ $res['stockid'] ] ['eps'] = numberFormat($res['eps']);
                }                
            }
            else
            {
                $qvdata[ $res['stockid'] ] ['grade'] = "Does not qualify";
                if($section == 'valuation'){
                    $qvdata[ $res['stockid'] ] ['pe'] = numberFormat($res['peratio']);
                    $qvdata[ $res['stockid'] ] ['ev2ebidta'] = numberFormat($res['ev2ebidta']);
                    $qvdata[ $res['stockid'] ] ['profit1yrgrowth'] = numberFormat($res['profit1yrgrowth']);                    
                    $qvdata[ $res['stockid'] ] ['peg'] = numberFormat($res['peg']);
                    $qvdata[ $res['stockid'] ] ['eps'] = numberFormat($res['eps']);
                }
            }
            
            
            if ( $section === "quality" && isset( $res['dotcolor'] ) &&  trim($res['dotcolor']) === "red"  )
                $qvdata[ $res['stockid'] ] ['grade'] = "Below Average";
            elseif ( $section === "quality" && isset( $res['dotcolor'] ) &&  trim($res['dotcolor']) === "grey"  )
                $qvdata[ $res['stockid'] ] ['grade'] = "Does not qualify";    
                

            if ( $section == "quality" )
            {
                if ( isset($res['quarter']) && !empty($res['quarter']) )
                {
                    $qvdata[ $res['stockid'] ] ['quarter'] = $res['quarter'] ;
                }
                else
                {
                    $qvdata[ $res['stockid'] ] ['quarter'] = 999999 ;
                }
            }
            
        }
        
        return $qvdata;
    }
    ##################### VALUATION #######################################
    public function setmysqlvdata( $vdata )
    {
        if ( count($vdata) == 0  ){ "ERROR : No Data provided for valuation update."; return; }
        
        /* Multiple update in single query reference
         * UPDATE table_users
            SET cod_user = (case when user_role = 'student' then '622057'
                         when user_role = 'assistant' then '2913659'
                         when user_role = 'admin' then '6160230'
                    end),
        date = '12082014'
    WHERE user_role in ('student', 'assistant', 'admin') AND
          cod_office = '17389551';
         */
        
        $grade_map = [ "does not qualify" => -2 , "risky" => -2 , "very expensive" => -1,  "expensive" => 0 , "fair" => 1 , "attractive" => 2 , "very attractive" => 3 ];
        
        $cnt = 0;
        $casecondn = "";
        $casecondn_sm = "";
        
        $casecondn_v2 = "";
        $casecondn_sm_v2 = "";
        
        $stockidlist = array();
        $LotCount = 0;
        foreach ( $vdata as $stockid => $vdet )
        {
            $cnt++;
            
            $stockidlist[] = $stockid;
            
            $mdb_grade = -2;
            if ( isset( $grade_map [ strtolower( $vdet['grade'] ) ] ) )
                $mdb_grade = $grade_map [ strtolower( $vdet['grade'] ) ];
            
            
            $pe = $vdet['pe'];
            $ev2ebidta = $vdet['ev2ebidta'];
            $peg = $vdet['peg'];
            $profit1yrgrowth = $vdet['profit1yrgrowth'];
            $eps = $vdet['eps'];
            
            $casecondn .= " when stk_id = $stockid then '".$vdet['grade']."' ";
            
            $casecondn_sm .= " when STOCKID = $stockid then '".$vdet['grade']."' ";
            
            $casecondn_pe .= " when stk_id = $stockid then ".$pe." ";
            $casecondn_ev2ebidta .= " when stk_id = $stockid then ".$ev2ebidta." ";
            $casecondn_peg .= " when stk_id = $stockid then ".$peg." ";
            $casecondn_profit1yrgrowth .= " when stk_id = $stockid then ".$profit1yrgrowth." ";
            $casecondn_eps .= " when stk_id = $stockid then ".$eps." ";
            
            $casecondn_v2 .= " when stk_id = $stockid then '".$mdb_grade."' ";
            
            $casecondn_sm_v2 .= " when STOCKID = $stockid then '".$mdb_grade."' ";
            if($cnt == 1)
                $this->overwrite_data .= "<table style='border:1px solid #000'><tr><th>Stock Id</th><th>grade</th><th>pe</th><th>ev2ebidta</th><th>peg</th><th>profit 1Y growth</th><th>valuation_rk</th></tr>";
            $this->overwrite_data .= "<tr><td>".$stockid."</td><td>".$vdet['grade']."</td><td>".$pe."</td><td>".$ev2ebidta."</td><td>".$peg."</td><td>".$profit1yrgrowth."</td><td>".$mdb_grade."</td></tr>";
            
            if ( $cnt >= 100 )
            {
                $LotCount++;
                $this->overwrite_data .= "</table>". $this->line_break. $this->line_break;
                $sqlupdate =  "update mojo_valuation_scorecard_v2 set pe= ( case ".$casecondn_pe." end ),ev_ebidta=( case ".$casecondn_ev2ebidta." end ),peg_ratio=( case ".$casecondn_peg." end ),eps_growth_2y=( case ".$casecondn_profit1yrgrowth." end ),valuation_rk = ( case ".$casecondn_v2." end ) ,  valuation_sc = ( case ".$casecondn." end ) WHERE quarter = 888801 and stk_id in ( ".implode( ", ", $stockidlist )." )";
               //$sqlupdate =  "update mojo_valuation_scorecard_v2 set valuation_rk = ( case ".$casecondn_v2." end ) ,  valuation_sc = ( case ".$casecondn." end ) WHERE quarter = 888801 and stk_id in ( ".implode( ", ", $stockidlist )." )";
              //echo $sqlupdate;exit;
                echo "LOT : ".$LotCount." => ";                
                $res = $this->_connection->execute($sqlupdate);
                if( $res )
                {
                    $this->overwrite_data .= "SUCCESS: " . implode( ", ", $stockidlist ) . $this->line_break;
                    echo "SUCCESS";
                }
                else
                {
                    $this->overwrite_data .= "FAIL: " . implode( ", ", $stockidlist ) . $this->line_break;
                    echo "FAIL";
                }
                
                echo " => ";
                ######  MOJOSTOCKMASTER UPDATE #########
                $sqlupdate_sm =  "update mojostocksmaster set ValuationRank = ( case ".$casecondn_sm_v2." end ) , ValuationScoreText = ( case ".$casecondn_sm." end ) WHERE STOCKID in ( ".implode( ", ", $stockidlist )." )";
                if( $this->_connection->execute($sqlupdate_sm) )
                {
                    $this->overwrite_data .= "SUCCESS: " . implode( ", ", $stockidlist ) . $this->line_break;
                    echo "SUCCESS";
                }
                else
                {
                    $this->overwrite_data .= "FAIL: " . implode( ", ", $stockidlist ) . $this->line_break;
                    echo "FAIL";
                }
                ######  MOJOSTOCKMASTER UPDATE #########
                
                echo "\n<br>";
                $this->overwrite_data .= $this->line_break ."*****". $this->line_break;
                $cnt = 0;
                
                unset($casecondn); $casecondn = "";
                unset($casecondn_sm); $casecondn_sm = "";
                
                unset($casecondn_v2); $casecondn_v2 = "";
                unset($casecondn_sm_v2); $casecondn_sm_v2 = "";
                
                
                unset($stockidlist); $stockidlist = array();         
                unset($sqlupdate);
                unset($sqlupdate_sm);
            }
            
        }
        
        
        
        if ( count($stockidlist) > 0 )
        {
            $this->overwrite_data .= "</table>". $this->line_break. $this->line_break;
                
            $LotCount++;
            echo "LOT : ".$LotCount." => ";
            
            //$sqlupdate =  "update mojo_valuation_scorecard_v2 set valuation_rk = ( case ".$casecondn_v2." end ) , valuation_sc = ( case ".$casecondn." end ) WHERE quarter = 888801 and stk_id in ( ".implode( ", ", $stockidlist )." )";
            $sqlupdate =  "update mojo_valuation_scorecard_v2 set pe= ( case ".$casecondn_pe." end ),ev_ebidta=( case ".$casecondn_ev2ebidta." end ),peg_ratio=( case ".$casecondn_peg." end ),eps_growth_2y=( case ".$casecondn_profit1yrgrowth." end ),valuation_rk = ( case ".$casecondn_v2." end ) ,  valuation_sc = ( case ".$casecondn." end ) WHERE quarter = 888801 and stk_id in ( ".implode( ", ", $stockidlist )." )";
            if( $this->_connection->execute($sqlupdate) )
            { 
                $this->overwrite_data .= "SUCCESS: " . implode( ", ", $stockidlist ) . $this->line_break;
                echo "SUCCESS";
            }
            else
            {
                $this->overwrite_data .= "FAIL: " . implode( ", ", $stockidlist ) .  $this->line_break;
                echo "FAIL";
            }
            
            
            echo " => ";
            ######  MOJOSTOCKMASTER UPDATE #########
            $sqlupdate_sm =  "update mojostocksmaster set ValuationRank = ( case ".$casecondn_sm_v2." end ) , ValuationScoreText = ( case ".$casecondn_sm." end ) WHERE STOCKID in ( ".implode( ", ", $stockidlist )." )";
            if( $this->_connection->execute($sqlupdate_sm) )
            {
                $this->overwrite_data .= "SUCCESS: " . implode( ", ", $stockidlist ) . $this->line_break;
                echo "SUCCESS";
            }
            else
            {
                $this->overwrite_data .= "FAIL: " . implode( ", ", $stockidlist ) . $this->line_break;
                echo "FAIL";
            }
            ######  MOJOSTOCKMASTER UPDATE #########
                
        }
            
    }
    public function update999(){
		$SQL_LIST = [ 
		"update mojostocksmaster set ValuationRank=1 where ValuationRank=10 and ValuationScoreText!='Ignore' and status='Active' and sublisting='Active'", 
		"update mojostocksmaster set QualityRank=-99997 where QualityRank=-99999 and status='Active' and sublisting='Active'",
                "update mojo_quality_scorecard_v2 set quality_rk=-99997 where quality_rk=-99999 and quarter ='201803'",
		"update mojo_quality_scorecard_v2 set quality_rk=-99997 where quality_rk=-99999 and quarter ='201712'",
                    
                "update mojo_valuation_scorecard_v2 set valuation_rk=1 where valuation_rk=10 and valuation_sc!='Ignore' and quarter=888801",

"update mojo_quality_scorecard_v2 set quality_rk=-99997 where quality_rk=-99999 and quarter ='201706' and 
stock_id in 
(
100009, 106396, 114155, 136056, 137618, 168357, 179129, 181029, 197453, 200403, 215218, 226457, 249526, 251223, 267929, 287817, 290299, 291765, 291767, 296996, 331187, 334093, 339957, 351394, 386262, 395671, 396862, 401311, 404135, 410190, 422255, 425873, 429328, 439544, 494695, 495860, 500037, 540360, 544473, 618949, 692664, 694254, 697859, 697937, 709259, 712697, 735574, 748388, 750408, 752307, 755468, 762330, 765462, 767384, 770252, 775170, 799148, 814026, 816046, 833041, 839586, 861793, 882169, 894789, 895277, 899683, 919768, 928015, 940668, 955767, 966364, 970940, 976462, 999953, 999975, 1000712, 1000828, 1000920, 1002756, 1002826, 1002856
)",
	
"update mojo_quality_scorecard_v2 set quality_rk=-99997 where quality_rk=-99999 and quarter ='201709' and 
stock_id in 
( 107126, 122880, 128025, 128144, 129643, 134241, 135324, 136716, 140848, 142781, 145367, 148576, 149784, 158446, 160222, 173071, 178660, 195486, 200854, 203048, 210797, 212311, 231543, 233635, 238101, 239139, 242512, 243663, 246020, 250214, 256629, 261336, 265410, 267795, 268473, 273543, 285743, 297654, 314872, 335680, 337669, 337918, 343371, 346859, 348482, 356564, 359044, 361991, 373038, 397159, 398857, 400659, 404977, 407817, 413664, 416296, 422441, 429266, 432731, 442353, 446711, 456336, 461234, 464225, 469652, 472639, 475121, 516121, 534393, 549285, 550304, 551073, 554052, 556898, 564508, 566153, 566287, 579849, 588029, 599909, 604782, 608140, 610094, 633136, 634149, 638919, 640351, 650728, 653352, 655994, 656742, 658986, 664761, 669150, 670593, 674913, 676425, 677442, 680490, 682470, 683584, 690246, 694240, 697086, 706831, 707242, 716361, 726203, 726667, 731413, 736752, 747498, 755322, 758601, 778797, 780676, 786160, 791104, 807270, 812455, 820800, 825580, 831609, 834525, 836311, 836318, 839201, 844006, 847494, 861611, 863765, 865236, 867446, 879512, 889555, 908196, 914093, 941049, 960402, 971142, 981622, 986495, 999955, 999980, 999991, 1000215, 1000303, 1000507, 1000704, 1002596, 1002597, 1002602, 1002652, 1002662, 1002669, 1002692, 1002710, 1002723, 1002731, 1002738, 1002763, 1002786, 1002787, 1002792, 1002822, 1002837, 1002885 )"

		];

		foreach ( $SQL_LIST as $SQL )
		{
			 echo $SQL;
			 echo " => ";

			if( $this->_connection->execute($SQL) )
			{
                                $this->overwrite_data .= "SUCCESS:" . $SQL . $this->line_break. $this->line_break;
				echo "SUCCESS";
			}
			else
			{
                                $this->overwrite_data .= "FAIL:" . $SQL . $this->line_break. $this->line_break  ;
				echo "FAIL";
			}

			echo "\n\n";
		}
    }
}
?>

