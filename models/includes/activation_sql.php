<?php
require_once 'config/database.php';
require_once 'lib/functions.php';

class Activation extends ActivationAPI{
	private $dbc;
	
    function __construct(){
        $this->dbc = Db::connect(GLOBAL_DATABASE);
		$this->dbc->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    
    public function activateUser(){
        $errorCode = 0;
        $status = false;
        $userID = null;
        $langCode = 'sl-SI';

        if(isset($_GET['user']) && isset($_GET['key'])){
            $userID = $_GET['user'];

            // FIND USER 
            $query = "SELECT u.*, la.code AS lang_code
                FROM farmacevti u 
                LEFT JOIN languages la ON la.id = u.languages_id
                WHERE u.inicialke = :id";
            $stmt = $this->dbc->prepare($query);
            $stmt->bindValue(":id",$userID,PDO::PARAM_STR);
            if($stmt->execute()){
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            } else return printErrAndDie($stmt);

            if(!empty($user)){

                // IF USER ALRDY ACTIVE
                if($user['active'] == '0'){

                    //CHECK KEY FOR CORRECT ONE
                    $token_check = "USER_NEED_AC_".$_GET['key'];

                    $query="SELECT tok.* FROM auth_tokens tok 
                    WHERE tok.token = :token
                    AND tok.expires >= '".date("Y-m-d H:i:s")."'";

                    $stmt = $this->dbc->prepare($query);
                    $stmt->bindValue(":token",$token_check,PDO::PARAM_STR);
                    if($stmt->execute()){
                        $token = $stmt->fetch(PDO::FETCH_ASSOC);
                    } else return printErrAndDie($stmt);

                    if($token && $token['inicialke'] == $user['inicialke']){

                        $langCode = $user['lang_code'];

                        // $chekPass = $token['session_token'];
                        
                        // ACTIVATE USER
                        $query = "UPDATE farmacevti SET active = 1 WHERE inicialke = :id";
                        $stmt = $this->dbc->prepare($query);
                        $stmt->bindValue(":id",$userID,PDO::PARAM_STR);
                        if($stmt->execute()){
                            $status = true;  

                            // DELETE THAT ACTIVATION TOKEN
                            $query = "DELETE FROM auth_tokens WHERE token = :token";
                            $stmt = $this->dbc->prepare($query);
                            $stmt->bindValue(":token",$token_check,PDO::PARAM_STR);
                            $stmt->execute();
                        }

                    } else {
                        // INCORRECT KEY
                        $errorCode = 3;
                    }
                } else {
                    // ALLRDY ACTIVE USER
                    $errorCode = 1;
                }
            } else {
                // NO USER EXIST
                $errorCode = 2;
            }
        } else {
            // NO CORRECT PARAMETERS
            $errorCode = 4;
        }
        $url = REGISTER_REDIRECT_URL.'register/activate';

        /*if(isset($chekPass) && $chekPass == '1'){
            $url .= 'fpassword';
        } else $url .= 'activate';*/
        
        $url .= '/'.$errorCode.'/'.$langCode;
        //echo $url;
        header('Location: '.$url);
    }
   
}
?>