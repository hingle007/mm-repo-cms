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

class Dot extends App\Models\ValuationCheck\Dot{

    private $_post;
    private $filterMailID = [];
    const FROM_EMAIL_ID = array('email' => 'tech@marketsmojo.com', 'name' => 'Tech'); //'tech@marketsmojo.com';
    const TO_EMAIL = array(
//        array('email' => 'amit@marketsmojo.com', 'name' => 'Amit'),
//                            array('email' => 'tech@marketsmojo.com', 'name' => 'Tech'),
                            array('email' => 'harshal@marketsmojo.com', 'name' => 'Harshal'),
    );
    public function __construct() {
        parent::__construct();
        $this->_post = json_decode(file_get_contents('php://input'), true);
        if (empty($this->_post)) {
            $this->_post = [];
        }
    }
    public function overwrite()
    {    
        $this->overwritemysql();
    }
}

?>
