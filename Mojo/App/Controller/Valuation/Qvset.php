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
    
    public function __construct() {
        parent::__construct();
        $this->_post = json_decode(file_get_contents('php://input'), true);
        if (empty($this->_post)) {
            $this->_post = [];
        }
    }
    public function valuation( $date = "" , $stockid = "" )
    {   
        $date = (!empty($date))?$date:date("Y-m-d");
        $this->getvaluation( $date,$stockid );
    }
    
}
?>