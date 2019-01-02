<?php
namespace Mojo\App\Controller\Valuation;

/**
 * Description of Valuation of stocks
 *s
 * @author Amit
 */
use Mojo\Core\Base as Base;
use Mojo\App as App;
use Mojo\Core\Db as Db;

class Qvaws extends App\Models\ValuationCheck\Qvaws{
    
    public function __construct() {
        parent::__construct();
        $this->_post = json_decode(file_get_contents('php://input'), true);
        if (empty($this->_post)) {
            $this->_post = [];
        }
    }
    public function dot()
    {
        $subject = "Dot (stocks_qvaws)";
        $message = "<html><body>Start time:" . date ("Y-m-d H:i:s") . " Dot (stocks_qvaws)";
        $this->awsdot();
        $message .= "<br/>End Time:" . date("Y-m-d H:i:s") . "</body></html>";
        mailalert( $subject ,$message );
    }
    
}
?>