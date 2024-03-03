<?php
require_once 'config/database.php';
require_once 'lib/functions.php';
class Auth{
    private $dbc;
    
    function __construct(){
        $this->dbc = Db::connect(GLOBAL_DATABASE);
    }
    
    public function checkToken($headerAuth = null){
        $return = array();
        if(!$headerAuth) $headerAuth = $_SERVER['HTTP_AUTHORIZATION_X'];    
        if($headerAuth){
            // CHECK FOR TOKEN VALI - (length)
            if(strlen($headerAuth) >= 40){
                // CHECK IF TOKEN EXIST IN DB
                // GET USER / CLIENT INFO FOR AUTH TOKEN
                $query="SELECT u.ime, u.priimek, u.inicialke AS userID, u.klinike_id AS clientID, u.status,
                    u.active, u.rank AS userRank, u.jeziki_id AS userLangID, a.expires, a.session_token,u.tip_delovnega_mesta as workType,u.oddelek_id as departmentID
                    FROM uporabniki u 
                    JOIN auth_tokens a ON u.inicialke = a.inicialke
                    AND a.token = '".$headerAuth."'
                    AND u.active = 1
                    AND a.expires > '".date('Y-m-d H:i:s')."'";
                $stmt = $this->dbc->prepare($query);
               // $stmt->bindValue(":token",$headerAuth,PDO::PARAM_STR);
                $stmt->execute();
                $userdata = $stmt->fetchObject();
                if(!$userdata){
                    return $this->returnError("Invalid Authorization",1);
                } 
                else {
                    // if(!$userdata->clientActive) return $this->returnError("Inactive Client",2);
                    if(!$userdata->active) return $this->returnError("Inactive User",3);
                    if($userdata->expires < date('Y-m-d H:i:s')) return $this->returnError("Expired Authorization",4);

                   /* $return = (object)[
                        'clientID' => $userdata->$IDKlinike,
                        'userID' => $status,
                        'userRank' => $userdata->userRank,
                        'userLangID' => $userdata->userLangID,
                        'username' => $userdata->ime." ".$userdata->priimek,
                        'userRank' => $userdata->userRank,
                    ];*/

                    $userdata->token = $headerAuth;

                    // Update Last Connection Info For Token User
                    $query = "UPDATE auth_tokens SET last_connection = :last_con
                        WHERE token = :token";
                    $stmt = $this->dbc->prepare($query); 
                    $stmt->bindValue(":token",$headerAuth,PDO::PARAM_STR);
                    $stmt->bindValue(":last_con",date("Y-m-d H:i:s"),PDO::PARAM_STR);
                    $stmt->execute();
                    
                    return $userdata;
                }
            } else return $this->returnError("Invalid Authorization",5);
        } else return $this->returnError("No Authorization",6);
    }

    /**
     * Returns error object
     * 
     * @param errorMessage Error message
     * @param errorCode Error code
     */
    private function returnError($errorMessage, $errorCode){
        return (object)[
            'error' => $errorMessage,
            'errorCode' => $errorCode
        ];
    }
   
}
?>