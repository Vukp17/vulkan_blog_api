<?php
require_once 'config/database.php';
require_once 'lib/functions.php';
require_once 'lib/shared_functions_sql.php';

class Main extends MainAPI{
	private $dbc;
    private $userID;
    private $clientID;
    private $userRank;
    private $userLang;
    private $cuData;
    private $userLangID;
    function __construct($cuData){
        $this->clientID = $cuData->clientID;
        $this->userID = $cuData->userID;
        $this->userRank = $cuData->userRank;
        $this->userLangID = $cuData->userLangID;
        $this->cuData = $cuData;
        $this->dbc = Db::connect(GLOBAL_DATABASE);
		$this->dbc->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**********************************************************/
    // GET CLIENT-USER DATA
    public function cuData(){
        // GET USER DATA
        $query="SELECT u.inicialke as id, u.prvi_naziv, u.ime, u.priimek, u.naziv,
            u.email, u.rank, u.last_login, u.funkcija, u.status, u.klinike_id,
            u.jeziki_id AS lang_id, l.name AS lang_name, l.code AS lang_code,u.tip_delovnega_mesta as workType
            FROM uporabniki u
            JOIN jeziki l ON l.id = u.jeziki_id 
            WHERE u.inicialke = :userID AND u.active = 1;";
		$stmt = $this->dbc->prepare($query);
		$stmt->execute(array(':userID' => $this->userID));
        $return['userData'] = $stmt->fetchObject();
        if($return['userData']) $return['userData']->session_token = $this->cuData->session_token;

        // GET CLIENT DATA
        $query="SELECT c.*, ISNULL(c.id,'')+' / '+ISNULL(c.naziv,'') AS fullname FROM klinike c WHERE c.id = :IDKlinike";
		$stmt = $this->dbc->prepare($query);
		$stmt->execute(array(':IDKlinike' => $this->clientID));
        $return['clientData'] = $stmt->fetchObject();

        // if($return['userData']->rank == SUPER_ADMIN_RANK) { $return['adminCl'] = $this->admincl(); }
        // $return['settingsCl'] = $this->getClientSettings();
        return $this->createResponse($return);
    }

    /***************************************************************/
    // POST METHODS 
    public function post_data($data,$type){
        if(empty($data)) return $this->createResponse(null,1,'data_error','Missing data');   
        if(empty($type)) return $this->createResponse(null,2,'data_error','Missing parameters');
        switch($type){
            case 'createLogEvent': $return = $this->createLogEvent($data); break;
            default: return $this->createResponse(null,3,'data_error',"No function found");
        }
        if($return && $return->success) return $this->createResponse($return); 
        else return $this->createResponse(null,2,'data_error',$return->message); 
    }

    /**********************************************************/
    // Create Event
    private function createLogEvent($data){
        $result = (object)['success' => true,];
        if(empty($data->type) || empty($data->evId) || empty($data->itemid)) {
            $result->success = false;
            $result->message = 'Missing parameters';
        } else {
            print_r($this->userID);
            createLogEvent($this->dbc,$this->userID,$data->itemid,$data->evId,$data->type);
        }
        return $result;
    }

  /*  // Get Client Settings
    private function getClientSettings(){
        $query= "SELECT a.* FROM ".$this->dbName.".settings a WHERE a.source = 'web_app_setting'";
        $stmt = $this->dbc->prepare($query);
        if($stmt->execute()){
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else return printErrAndDie($stmt);
        return;
    }

    /****************************/
    // GET CLIENTs FOR ADMIN SELECT
   /* private function admincl(){
        if($this->userRank != SUPER_ADMIN_RANK) return null;
        $query= "SELECT id,name FROM clients WHERE active = 1";
        $stmt = $this->dbc->prepare($query);
        if($stmt->execute()){
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else return printErrAndDie($stmt);
    }
    // CHANGE CLIENT ADMIN
    public function admincls(){
        if($this->userRank != SUPER_ADMIN_RANK) return null;
        $client_id = $_GET['c'];
        if(!isset($client_id)) return null;

        $headerAuth = $_SERVER['HTTP_AUTHORIZATION_X'];

        $query= "UPDATE auth_tokens SET clients_id = :cl_id WHERE token = '".$headerAuth."'";
        $stmt = $this->dbc->prepare($query);
        $stmt->bindValue(":cl_id",intval($client_id),PDO::PARAM_INT);

        if($stmt->execute()){
            return true;
        } else return printErrAndDie($stmt);
    }


    /**********************************************************/
    // GET ITEMS BY TYPE -- NEEDED ITEMS VIEW
  /*  public function needItems(){
        $type = $_GET['type'];
        if(!isset($type)) return null;
        $finish = false;
        switch($type){
            case 'instopersparam': // Get intervals - OF Client AND operations
                $query = "SELECT a.id, a.name AS defName, IF(l.name IS NULL, a.name, l.name) AS name, a.interval_range, a.code 
                    FROM intervals a
                    JOIN clients_inter ci ON ci.intervals_id = a.id
                    LEFT JOIN lang_trans l ON l.item_id = a.id AND l.`table` = 'intervals' AND l.languages_id = ".$this->userLang."
                    WHERE ci.clients_id = ".$this->clientID."
                    ORDER BY a.interval_range ASC";
                $stmt = $this->dbc->prepare($query);
                if($stmt->execute()){
                    $item['intervals'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } else return printErrAndDie($stmt);

                $query = "SELECT a.id, a.name AS defName, IF(l.name IS NULL, a.name, l.name) AS name, a.shortname, a.code FROM operations a
                LEFT JOIN lang_trans l ON l.item_id = a.id AND l.`table` = 'operations' AND l.languages_id = ".$this->userLang;
                $stmt = $this->dbc->prepare($query);
                if($stmt->execute()){
                    $item['operations'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } else return printErrAndDie($stmt);

                $codes = $_GET['codes'];
                if(isset($codes)) {
                    $codes = explode(",",$codes);
                    $finalCodes = "'";
                    foreach($codes as $code){
                        $finalCodes .= $code."','"; 
                    }
                    $finalCodes = substr($finalCodes, 0, -2);
                    $query = "SELECT a.id, a.code FROM ".GLOBAL_DATABASE.".parameters a WHERE a.code IN (".$finalCodes.")";
                    $stmt = $this->dbc->prepare($query);
                    if($stmt->execute()){
                        $item['codesParam'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    } else return printErrAndDie($stmt);
                }
                return $item;
                break;
            case 'parameters':
                require_once 'admin_sql.php';
                $adminSQL = new Admin($this->userID,$this->clientID,$this->userRank,$this->dbName,$this->userLang);
                return $adminSQL->needItems('parameters');
                break;
        }
        if($finish){
            $stmt = $this->dbc->prepare($query);
            if($stmt->execute()){
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else return printErrAndDie($stmt);
        }
    }*/

}
?>