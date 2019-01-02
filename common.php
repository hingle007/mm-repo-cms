<?php
if(!function_exists("getUserId")) {
    function getUserId()
    {
//        $_session = getSession();
//        $userid = $_session->getData("userid");
        $request = json_decode(file_get_contents("php://input"),true);
        if(is_array($request) && isset($request["userid"])){
            $userid = $request["userid"];
        }else{
            $userid = 0;
        }
        return $userid;
    }
}
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
if (!function_exists('getController')) {

    function getController($classname) {
        $path = explode('\\', $classname);
        $controller = $path[count($path) - 1];
        return $controller;
    }

}

if (!function_exists('getLoginHash')) {
    /**
         * @author Mayur Takawale
         * Generate hash string for auto login
         * 
         * @param int $userid
         * @param array $data
         */
        function getLoginHash($userid, $data = array()) {
            $data = serialize($data);
            $stringForHash = $userid .'||' . $data;
            return base64_encode(encrypt_string($stringForHash));
        }
    }

if (!function_exists(('writeLog'))) {

    function writeLog($fileName, $path, $mode, $message) {
        $fh = fopen($path . DS . $fileName, $mode);
        fwrite($fh, $message);
        fclose($fh);
    }

}

if (!function_exists(('numberFormat'))) {

    function numberFormat($number, $decimals = 2, $thousands_sep = '') {
        $finalNumber = number_format($number, $decimals, '.', $thousands_sep);
        return $finalNumber;
    }

}

//show 500 when coding error
if (!function_exists('fatalErrorShutdownHandler')) {

    function fatalErrorShutdownHandler() {
        $last_error = error_get_last();
        switch ($last_error['type']) {
            case E_ERROR:
            case E_USER_ERROR:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_PARSE:
                \Mojo\Core\Base\StatusCodes::errorMessage(500);
        }
    }

}

if (!function_exists('getHeaders')) {

    function getHeaders() {
        if (!is_array($_SERVER)) {
            return array();
        }
        $headers = array();
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', (strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }

}

if (!function_exists('getHeaderToken')) {

    function readToken() {
        $headers = getHeaders();
        $token = isset($headers['x-mm-auth']) ? $headers['x-mm-auth'] : '';
        return $token;
    }

}

if (!function_exists('pr')) {

    function pr($val) {
        echo "<pre>";
        print_r($val);
        echo "</pre>";
    }

}
/*
 * Desc: write logs in system
 * System logs function
 * @param $module_name - name of module
 * @param $msg - actual message that wanted to be shown
 * @pram $type - general/cron, for processes has to be pass type as "cron" else default "general"
 * @return true/false
 */
if (!function_exists('systemLog')) {

    function systemLog($module_name = '', $msg = '', $type = 'general') {
        $err_msg = "UNKNOWN";
        switch ($type) {
            case 'general':
                openlog($module_name, LOG_PID | LOG_PERROR, LOG_LOCAL0);
                $ipaddress = getClientIP();
                $err_msg = $module_name . " : " . date('Y-m-d H:i:s') . " : " . $ipaddress . ":" . $_SERVER['HTTP_USER_AGENT'] . ":" . $msg;
                break;
            case 'cron':
                openlog($module_name, LOG_PID | LOG_PERROR, LOG_CRON);
                $err_msg = $module_name . " : " . $msg;
                break;
            default :
                openlog($module_name, LOG_PID | LOG_PERROR, LOG_LOCAL0);
                break;
        }
        syslog(LOG_INFO, $err_msg);
        closelog();
    }

}
/*
 * get client ip address
 * @return ip address of client
 */
if (!function_exists('getClientIP')) {

    function getClientIP() {
        $ipaddress = '';
        if (isset($_SERVER['HTTP_CLIENT_IP']))
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        else if (isset($_SERVER['HTTP_X_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        else if (isset($_SERVER['HTTP_X_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        else if (isset($_SERVER['HTTP_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        else if (isset($_SERVER['HTTP_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        else if (isset($_SERVER['REMOTE_ADDR']))
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        else
            $ipaddress = 'UNKNOWN IP';
        return $ipaddress;
    }

}
/* create guid */
if (!function_exists("getGUID")) {

    function getGUID() {
        if (function_exists('com_create_guid')) {
            return com_create_guid();
        } else {
            mt_srand((double) microtime() * 10000); //optional for php 4.2.0 and up.
            $charid = strtoupper(md5(uniqid(rand(), true)));
            $hyphen = chr(45); // "-"
            $uuid = substr($charid, 0, 8) . $hyphen
                    . substr($charid, 8, 4) . $hyphen
                    . substr($charid, 12, 4) . $hyphen
                    . substr($charid, 16, 4) . $hyphen
                    . substr($charid, 20, 12);
            return $uuid;
        }
    }

}
/*
 * Func: validatePassword()
 * @param $password
 * @pasram $cpassword
 * Desc : Validate password
 */
if (!function_exists("validatePassword")) {

    function validatePassword($password, $cpassword) {
        $res = true;
        if (!empty($password) && ($password == $cpassword)) {
            if (strlen($password) < '8') {
                $res = "Your Password must contain at least 8 characters!";
            }/* elseif (!preg_match("#[0-9]+#", $password)) {
                $res = "Your password must contain at least 1 number!";
            } elseif (!preg_match("#[A-Z]+#", $password)) {
                $res = "Your password must contain at least 1 capital letter!";
            } elseif (!preg_match("#[a-z]+#", $password)) {
                $res = "Your password must contain at least 1 lowercase letter!";
            }*/
        } else {
            $res = false;
        }
        return $res;
    }

}

if (!function_exists('array_in_string')) {

    function array_in_string($str, array $arr) {

        foreach ($arr as $arr_value) { //start looping the array
            if (strpos($str, $arr_value) !== false) {

                return true; //if $arr_value is found in $str return true
            }
        }
        return false; //else return false
    }

}

if (!function_exists('stock_status_message')) {


    function stock_status_message($stockId) {

        $message = '';

        if ($stockId < 0 || empty($stockId)) {

            if ($stockId == -9995) {
                $message = 'amalgamated';
            } else if ($stockId == -9998) {
                $message = 'suspended';
            } else if ($stockId == -9997) {
                $message = 'delisted';
            } else if ($stockId == -9996) {
                $message = 'merged';
            } else if ($stockId == -9999) {
                $message = 'inactive';
            } else {
                $message = 'not valid';
            }

            $message = 'Cannot be added because the stock is ' . ucwords($message);
        }

        return $message;
    }

}

/*
 * Author:Nayana 
 * Decode in json format 
 * Input: $val = array or variable   
 * Output: reurn json decoded array
 */
if (!function_exists(('jsonDecode'))) {

    function jsonDecode($var, $index) {
        if (is_array($var)) {
            for ($i = 0; $i < count($var); $i++) {
                $index[$i] = ((strpos($index[$i], "_LIVE") !== FALSE) ? str_replace("_LIVE", "", $index[$i]) : $index[$i]);
                $result[$index[$i]] = json_decode($var[$i], true);
            }
        } else {
            $index = ((strpos($index, "_LIVE") !== FALSE) ? str_replace("_LIVE", "", $index) : $index);
            $result[$index] = json_decode($var, true);
        }

        return $result;
    }

}

if (!function_exists(('calculate_chgp'))) {

    function calculate_chgp($val1, $val2) {
        return (is_numeric($val1) && is_numeric($val2) && $val2 != 0 && !empty($val2)) ? numberFormat(((($val1 - $val2) / $val2) * 100), 2) : '';
    }

}

if (!function_exists('gettimeDiff')) {

    function gettimeDiff($date1, $date2) {
        $dt1 = new DateTime($date1);
        $dt2 = new DateTime($date2);
        $interval = $dt1->diff($dt2);
        $mins = $interval->format('%i');
        $hrs = $interval->format('%h');
        return array('min' => $mins, 'hrs' => $hrs);
    }

}

if (!function_exists('show_error')) {

    function show_error($type, $message, $mailFrequency = 2) {
        if (!empty($type) && !empty($message)) {
            $subject = ENVIRONMENT . " : " . $type;
            if (ENVIRONMENT == 'development') {
                @mail('pradip@marketsmojo.com', $subject, $message);
            } else {
                $type = str_replace(" ", "_", $type);
                $date = date('Y-m-d H:i:s');
                if ($type != 'Redis-connection-error') {
                    $config = Mojo\App\App::$_registry["redis"]['www_write'];
                    $redisObj = new \Mojo\Lib\RedisClient($config['host'], $config['port'], $config['timeout']);
                    $sendMail = 0;
//every 5 minute
                    if ($mailFrequency == 1) {
                        $lastSenttime = $redisObj->get('MM:SENDERROR_TIME_5MIN_' . $type);
                        if (!empty($lastSenttime)) {
                            $timeData = gettimeDiff($lastSenttime, $date);
                            $min = $timeData['min'];
                            if ($min > 5) {
                                $redisObj->set('MM:SENDERROR_TIME_5MIN_' . $type, $date);
                                $sendMail = 1;
                            }
                        } else {
                            $redisObj->set('MM:SENDERROR_TIME_5MIN_' . $type, $date);
                            $sendMail = 1;
                        }
                    } elseif ($mailFrequency == 2) {
//every 1 hr
                        $lastSenttime = $redisObj->get('MM:SENDERROR_TIME_1HR_' . $type);
                        if (!empty($lastSenttime)) {
                            $timeData = gettimeDiff($lastSenttime, $date);
                            $min = $timeData['min'];
                            $hrs = $timeData['hrs'];
                            if ($min > 60 || $hrs >= 1) {
                                $lastSenttime = $redisObj->set('MM:SENDERROR_TIME_1HR_' . $type, $date);
                                $sendMail = 1;
                            }
                        } else {
                            $redisObj->set('MM:SENDERROR_TIME_1HR_' . $type, $date);
                            $sendMail = 1;
                        }
                    } elseif ($mailFrequency == 3) {
//once in a day
                        $lastSenttime = $redisObj->get('MM:SENDERROR_TIME_1D_' . $type);
                        if (!empty($lastSenttime)) {
                            $timeData = gettimeDiff($lastSenttime, $date);
                            $hrs = $timeData['hrs'];
                            if ($hrs >= 24) {
                                $lastSenttime = $redisObj->set('MM:SENDERROR_TIME_1D_' . $type, $date);
                                $sendMail = 1;
                            }
                        } else {
                            $redisObj->set('MM:SENDERROR_TIME_1D_' . $type, $date);
                            $sendMail = 1;
                        }
                    }
                    unset($redisObj);
                } else {
                    $sendMail = 1;
                }
                if ($sendMail > 0) {
                    $mailObj = new Mojo\Core\Base\Mail(SMTP_HOST, SMTP_PORT, SMTP_USERNAME, SMTP_PASSWORD);
                    $fromAddr = array('email' => 'tech@marketsmojo.com', 'name' => 'Error');
                    $toAddr = array('email' => 'tech@marketsmojo.com', 'name' => 'Tech Team');
                    $mailObj->sendMailViaSMTP($fromAddr, $toAddr, '', $subject, $message);
                    unset($mailObj);
                }
//@mail('pradip@marketsmojo.com',$subject, $message);
            }
        }
    }

}

if (!function_exists('getDirection')) {

    function getDirection($change) {
        if ($change > 0) {
            $dir = 1;
        } elseif ($change < 0) {
            $dir = -1;
        } else {
            $dir = 0;
        }
        return $dir;
    }

}

if (!function_exists('IND_money_format')) {

    function IND_money_format($money, $decimal_required = 1) {
        $exploded_string = explode('.', $money);
        $sign = "";
        if ($money < 0) {
            $sign = "-";
        }
        $money = $exploded_string[0];
        
        // echo $money; die();
        $len = strlen(abs($money));
        $m = "";
        $money = strrev($money);
        for ($i = 0; $i < $len; $i++) {
            if (( $i == 3 || ($i > 3 && ($i - 1) % 2 == 0) ) && $i != $len) {
                $m .= ',';
            }
            $m .= $money[$i];
        }
        $m .= $sign;
        $exploded_string[1] = !empty($exploded_string[1]) ? $exploded_string[1] : '00';
        
        if (strlen($exploded_string[1]) < 2) {
            $exploded_string[1] .= "0";
        } 

        return ($decimal_required) ? strrev($m) . "." . $exploded_string[1] : strrev($m);
    }

}

if (!function_exists('calculate_number')) {

    function calculate_number($number) {
        $output = array(
            'num' => NULL,
            'label' => ''
        );

        if ($number >= 1000 && $number < 100000 || $number <= -1000 && $number > -100000) {
            $number = numberFormat($number / 1000);
            $output['num'] = number_format($number, 2);
            $output['label'] = 'k';
        } else if ($number >= 100000 && $number < 10000000 || $number <= -100000 && $number > -10000000) {
            $number = number_format(($number / 100000), 2);
            $output['num'] = $number;
            $output['label'] = 'lacs';
        } else if ($number >= 10000000 || $number <= -10000000) {
            $number = number_format(($number / 10000000), 2);
            $output['num'] = $number;
            $output['label'] = 'cr';
        } else {
            $output['num'] = $number;
        }

        return $output;
    }

}


if (!function_exists('is_login')) {

    function is_login($redirect = true) {
        switch (CLIENT_NAME) {
            case "KOTAK":
                return \Mojo\App\Controller\Premium\Kotak::isLogin($redirect);
                break;

            default:
                $user = new \Mojo\App\Controller\Premium\Premium();

                if ($user->isLogin($redirect)) {
                    return true;
                }
                break;
        }
        

        return false;
    }

}

if (!function_exists('getSession')) {

    function getSession() {
        $config = \Mojo\App\App::$_registry["session"];
        return \Mojo\Core\Base\Session::getSession($config);
    }

}

if (!function_exists('get_user_details')) {

    /**
     * Below are list of keys exists in user details with their data type
     * userid int
     * display_name string
     * phone string
     * email string
     * image string
     * accounts array
     */
    function get_user_details($key = "") {
        $user = new \Mojo\App\Controller\Premium\Premium();
        return $user->getUserData($key);
    }

}
if (!function_exists(('isHoliday'))) {

    function isHoliday($date) {

        global $holidays;
        if (in_array($date, $holidays)) {
            return true;
        }
        return false;
    }

}

if (!function_exists('get_acc_details')) {
  
    function get_acc_details($acc_id = "") {
        $user = new \Mojo\App\Controller\Premium\Premium();
        $account_list = $user->getUserData('accounts');
        if (!empty($acc_id)) {
            foreach ($account_list as $key => $value) {
                if ($value['acc_id'] == $acc_id) {
                    return $value;
                }
            }
        }

        return $account_list;
    }

}

if (!function_exists('calculate_returns')) {

    function calculate_returns($currPrice, $prevCloseArr, $intervalArr = []) {
        $output = [];

        if (!is_array($intervalArr) || empty($intervalArr)) {
            $intervalArr = ['1D', '1W', '1M', '3M', '6M', '9M', 'YTD', '1Y', '2Y', '3Y', '4Y', '5Y', '10Y'];
        }

        foreach ($intervalArr as $interval) {
            if (isset($prevCloseArr[$interval]) && $prevCloseArr[$interval] > 0) {

                $price = $prevCloseArr[$interval];
                $chg = ($currPrice - $price);
                $chgp = ($price != 0) ? (($chg / $price) * 100) : 0;

                $output[$interval] = [
                    'prev_close' => (float) numberFormat($price, 2),
                    'chg' => (float) numberFormat($chg, 2),
                    'chgp' => (float) numberFormat($chgp, 2),
                    'dir' => getDirection($chg),
                ];
            } else {
                $output[$interval] = [
                    'prev_close' => 0,
                    'chg' => 0,
                    'chgp' => 0,
                    'dir' => 0,
                ];
            }
        }

        return $output;
    }

}

if ( !function_exists("typeConversion")) {
    
    function typeConversion($type, $value) {
        switch ($type) {
            case "int":
                return (int)$value;
                 break;

            case "double":
                return (double)$value;
                break;
            
            case "boolean":
                return (boolean)$value;
                break;
            
            case "mongodate":
                if(!empty(trim($value)) && $value != 'NULL') {
                    $utcdatetime = new \MongoDB\BSON\UTCDateTime(strtotime($value)*1000);
                    return $utcdatetime;
                }
                return $value;
                break;
            
            case "date":
                if(!empty(trim($value)) && $value != 'NULL') {
                    return date("Y-m-d",strtotime($value));
                }
                return $value;
                break;
                
            case "datetime":
                return (boolean)$value;
                break;    
                
            default:
                return $value;
                break;
        }
    }
}


if (!function_exists("mongo_to_normal_date")) {
    
    function mongo_to_normal_date($mongoDate , $format = "Y-m-d H:i:s") {
        $dt = $mongoDate->toDateTime()->format('Y-m-d H:i:s');
        $orig_date = new \DateTime($dt, new \DateTimeZone('UTC'));
        $orig_date->setTimezone(new \DateTimeZone('Asia/Kolkata'));
        return $orig_date->format($format);
    }
}


if (!function_exists(('userLog'))) {

    function userLog($action, $data = array()) {
        $log = new \Mojo\App\Models\Premium\PremiumModel();
        $log->userLog($action, $data);
    }

}

if (!function_exists(('getStockUrl'))) {

    function getStockUrl($sid, $exchange=0) {
        
        return (!empty($sid))?"/Stocks?StockId=" . $sid . "&Exchange=" .$exchange:"javascript:void(0)";
    }

}

if (!function_exists(('cURLdownload_file'))) {

    function cURLdownload_file($url, $localFile, $OPTIONAL_HEADERS) {

        $ch = curl_init($url);
        $fp = fopen($localFile, "w");

        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $OPTIONAL_HEADERS);
        curl_exec($ch);
        $CURLINFO_HTTP_CODE = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if (!preg_match("'[2][0-9]{2}'", $CURLINFO_HTTP_CODE)) {
            unlink($localFile);
        }
    }

}



if (!function_exists(('setRedirectUrl'))) {

    function setRedirectUrl() {
        $redirectUrl = $_GET['redirect'];
        if (strpos($redirectUrl, "?") > -1) {
            foreach ($_GET as $key => $val) {
                if ($key != "redirect") {
                    $redirectUrl .= "&".$key."=".$val;
                }
            }
        }
        setcookie("redirect_url", $redirectUrl, 0, '/');
        return $redirectUrl;
    }

}


if (!function_exists(('getRedirectUrl'))) {

    function getRedirectUrl() {
        $redirectUrl = "";
        if (!empty($_COOKIE['redirect_url'])) {
            $redirectUrl = $_COOKIE['redirect_url'];
            setcookie("redirect_url", null, -1, '/');
        }
        return $redirectUrl;
    }

}


if (!function_exists("encrypt_string")) {

    function encrypt_string($str) {
        $ciphertext = openssl_encrypt($str, CIPHER, SECRET_KEY, NULL, IV);

        return $ciphertext;
    }

}

if (!function_exists("decrypt_string")) {

    function decrypt_string($ciphertext) {
        $str = openssl_decrypt($ciphertext, CIPHER, SECRET_KEY, NULL, IV);

        return $str;
    }

}


if (!function_exists('isPaid')) {

    function isPaid($redirect = false) {
        $user = new \Mojo\App\Controller\Premium\Premium();

        if ($user->isPaid($redirect)) {
            return true;
        }
        return false;
    }

}
if(!function_exists(('sendMail'))){
    function sendMail($mail_obj,$from,$to,$subject,$body,$replyto=array()) {
       $res = $mail_obj->sendMailViaSMTP($from, $to, $replyto,$subject, $body);
       return $res;
    }
}


if (!function_exists('isMobile')) {
    /**
     * Check whether device is mobile browser, app or desktop 
     * @param boolean $deviceType set when you want device type in return
     * @return mixed
     *      when device type flag is set return device name else return boolean
     */
    function isMobile($deviceType = false) {
        $device = "desktop";
        $ismobile = false;
        if (preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i", $_SERVER["HTTP_USER_AGENT"])) {
            $device = "mobile_web";
            $ismobile = true;
        }
        
        if (strpos($_SERVER["HTTP_USER_AGENT"],"mm-app") > -1 ) {
            $device = "mobile_app";
            $ismobile = true;
        }
        
        if ($deviceType) {
            return $device;
        }
        
        return $ismobile;
    }
}

if (!function_exists('track_visit')) {

    function track_visit($data) {
        $premiumModel = new \Mojo\App\Models\Premium\PremiumModel();
        return $premiumModel->trackVisit($data);
    }

}

if (!function_exists('check_accessiblity')) {
    function check_accessiblity() {
        global $userIdsForReportsArr;
        is_login();
        $userId = getSession()->getData("userid");
        if (!in_array($userId, $userIdsForReportsArr)) {
            echo "You are not authorized to access";
            exit;
            return;
        }
    }
}

if (!function_exists('getIpCountry')) {
    function getIpCountry($ip) {
        $ip_data = @json_decode(file_get_contents("http://www.geoplugin.net/json.gp?ip=".$ip));    
        if($ip_data && $ip_data->geoplugin_countryName != null){
            $result['country'] = $ip_data->geoplugin_countryCode;
            $result['city'] = $ip_data->geoplugin_city;
        }
        return $result;

    }
}

if (!function_exists('convertDateToMongo')) {
    
        function convertDateToMongo($date) {
            $dt = new \MongoDB\BSON\UTCDateTime(strtotime($date) * 1000);
    
            return $dt;
        }
    
    }
    
 if (!function_exists('logData')) {
    
        function logData($old,$new,$event,$module,$userid,$mongo) {
            $collection = "eventlogger";
            $old = json_encode($old);
            $new = json_encode($new);
            $arr['olddata'] = $old;
            $arr['newdata'] = $new;
            $arr['module'] = $module;
            $arr['event'] = $event;
            $arr['updatedby'] = (int) $userid;
            $arr['updatedon'] = (int) date('YmdHis');
            $arr['updatedtime'] = date('Y-m-d H:i:s');
            $mongo->insert($collection,[$arr],false);
            
        }
    
    }   
    
    if (!function_exists(('getDotColor'))) {

    function getDotColor($dot_summary, $opt = 0) {
        $dot = array();
        if ($opt === 0 || $opt == 'q') {
            $quality = array('excellent' => 'green',
                'good' => 'green',
                'average' => 'orange',
                'below average' => 'red',
                'does not qualify' => 'grey');
            $dot['quality_clr'] = isset($quality[strtolower($dot_summary['quality_ranktext'])]) ? $quality[strtolower($dot_summary['quality_ranktext'])] : 'grey';
        }
        if ($opt === 0 || $opt == 'v') {
            $valuation = array('very attractive' => 'green',
                'attractive' => 'green',
                'fair' => 'orange',
                'expensive' => 'red',
                'very expensive' => 'red',
                'risky' => 'red',
                'very risky' => 'red',
                'does not qualify' => 'grey');

            $dot['valuation_clr'] = isset($valuation[strtolower($dot_summary['valuation_ranktext'])]) ? $valuation[strtolower($dot_summary['valuation_ranktext'])] : 'grey';
        }
        if ($opt === 0 || $opt == 'f') {
            if ($dot_summary['fin_points'] == -99999 || $dot_summary['fin_points'] === '') {
                $dot['fin_trend_clr'] = 'grey';
            } elseif ($dot_summary['fin_points'] > 29) {
                $dot['fin_trend_clr'] = 'green';
            } elseif ($dot_summary['fin_points'] > 19) {
                $dot['fin_trend_clr'] = 'green';
            } elseif ($dot_summary['fin_points'] > 5) {
                $dot['fin_trend_clr'] = 'green';
            } elseif ($dot_summary['fin_points'] > -6) {
                $dot['fin_trend_clr'] = 'orange';
            } elseif ($dot_summary['fin_points'] > -20) {
                $dot['fin_trend_clr'] = 'red';
            } else {
                $dot['fin_trend_clr'] = 'red';
            }
        }

        return $dot;
    }

}
if (!function_exists("mailalert")) {
    function mailalert( $subject ,$message,$fromAddr=array(), $toAddr=array() )
    { 
        $subject = ENVIRONMENT . "-" . $subject;
        $mailObj = new \Mojo\Core\Base\Mail(SMTP_HOST, SMTP_PORT, SMTP_USERNAME, SMTP_PASSWORD);
       if(empty($fromAddr))
            {
                $fromAddr = array('email' => 'tech@marketsmojo.com', 'name' => 'Markets MOJO Alerts');
            }
            if(empty($toAddr))
            {
                $toAddr = array(
                   ['email' => 'harshal@marketsmojo.com', 'name' => 'MarketsMojo Tech']
                   );                
            }
        $mailObj->sendMailViaSMTP($fromAddr, $toAddr, '', $subject, $message);
    }
}
