<?php
require_once 'config/database.php';
require_once 'lib/functions.php';
require_once 'lib/shared_functions_sql.php';

class Login extends LoginAPI{
	private $dbc;
	
    function __construct(){
        $this->dbc = Db::connect(GLOBAL_DATABASE);
	}

    /***************************************************************/
    // POST METHODS 

    public function post_data($data,$type){
        if(empty($data)) return $this->createResponse(null,1,'data_error','Missing data');   
        if(empty($type)) return $this->createResponse(null,2,'data_error','Missing parameters');

        switch($type){
            case 'login': return $this->login($data); break;
            case 'challange': return $this->challange($data); break;
            case 'logout': return $this->logout($data); break;
            case 'isloggedIn': return $this->isloggedIn($data); break;
            case 'sendEmailPass': return $this->sendEmailPass($data); break;
            case 'checkUserKey': return $this->checkUserKey($data); break;
            case 'changePassword': return $this->changePassword($data); break;
            default: return $this->createResponse(null,3,'data_error',"No function found");
        }
    }

   // function createResponse($data, $success = true, $code = 200, $error = false, $message = null, $type = null){

    /**
     * Login Challange Method - Returns User Status(Items) If Found
     * 
     * @param data POST data for challange
     */
    private function challange($data){
        $response = array();
		$query="SELECT inicialke as inicialke,salt FROM uporabniki WHERE inicialke = :username";
        $stmt = $this->dbc->prepare($query);
        $stmt->bindValue(":username",$data->username,PDO::PARAM_STR);

		$stmt->execute();		

        $userdata = $stmt->fetchObject();
       

		if(!$userdata){
            // Error code 4 - User Dont Exist
            return $this->createResponse(null,4,'login',"User dont exist -> ".$data->username);
		}
		$nonce = sha1(time().rand().sha1(rand()).microtime().date("l \t\h\e jS"));
		$response["nonce"] = $nonce;
		$response["salt"] = $userdata->salt;

        $nonce = "USER_LOGIN_NONCE_".$nonce;

        // SAVE NONCE TO DATABASE
        $query="INSERT INTO auth_tokens (Inicialke,token,expires)
            VALUES (:inicialke,:token,:expires)";
        $stmt = $this->dbc->prepare($query);
        $tmp = date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s').' + 1 minute'));
        $stmt->bindValue(":inicialke",$userdata->inicialke,PDO::PARAM_STR);
        $stmt->bindValue(":token",$nonce,PDO::PARAM_STR);
        $stmt->bindValue(":expires",$tmp,PDO::PARAM_STR);
        $stmt->execute();
        
        return $this->createResponse($response);
    }


    /**
     * Login Method - Returns User Data If Found
     * 
     * @param dataPost POST data for login
     */
    private function login($dataPost){
        // CHECK IF NOCE EXIST
        $query="SELECT TOP 1 u.inicialke, u.active, u.status, u.password, u.ime, u.priimek, u.prvi_naziv, u.naziv, u.klinike_id, a.token, a.expires
            FROM uporabniki u 
            LEFT JOIN auth_tokens a ON u.inicialke = a.inicialke
            AND a.token LIKE 'USER_LOGIN_NONCE_%'
            AND a.expires > '".date('Y-m-d H:i:s')."'
            WHERE u.inicialke = :username
            ORDER BY a.expires DESC
            ";
		$stmt = $this->dbc->prepare($query);
		$stmt->execute(array(':username' => $dataPost->username));
        $userdata = $stmt->fetchObject();

        if(!$userdata->token){
            // Error code 4 - No nonce
            return $this->createResponse(null,3,'login',"Need to call challange first");
        } else {
            $nonce = str_replace('USER_LOGIN_NONCE_', '', $userdata->token);
        }

        // Check if client is active
		/*if(!$userdata->client_active){
            // Error code 6 - Client is inactive
            return $this->createResponse(null,6,'login',"Client is inactive");
		}*/
		
		// Check if user is active
		if(!$userdata->active){
            // Error code 5 - User is inactive
            return $this->createResponse(null,5,'login',"User is inactive");
        }

        // DELETE USER_LOGIN_NONCE IF EXIST - DELETE ALL 
        if($userdata->inicialke){
            $query="DELETE FROM auth_tokens 
            WHERE inicialke = :inicialke
            AND token LIKE 'USER_LOGIN_NONCE_%'";
            $stmt = $this->dbc->prepare($query);
            $stmt->execute(array(':inicialke' => $userdata->inicialke));
            $stmt->execute();
        }

        //CHECK PASSWORD
        if($dataPost->password == $userdata->password.$nonce){
            // CREATE TOKEN
            $userSession = sha1(time().rand().sha1(rand()).microtime().date("l \t\h\e jS"));
            $response["token"] = sha1($userSession."56qOUM6R925K|".$userdata->ime."}56qOUM6RS3{47<n9lGQ7".$userdata->inicialke);
            
            //SAVE LAST LOGIN TIME + INC LOGIN COUNT
            $query="UPDATE uporabniki SET last_login = :last_login, login_count = login_count + 1
			WHERE inicialke = :inicialke";

			$stmt = $this->dbc->prepare($query);
			$stmt->bindValue(":last_login",date("Y-m-d H:i:s"),PDO::PARAM_STR);
			$stmt->bindValue(":inicialke",$userdata->inicialke,PDO::PARAM_STR);
            $stmt->execute();

            // FIND IF USER WANST TO BE LOGGED IN ALWAYS
            $sessionToken = true;
            if($dataPost->stayLoggedIn == 'true'){
                $tokenExpire = date("Y-m-d H:i:s",time() + (86400 * 30)); // 1 YEAR
                $sessionToken = false;
            } else $tokenExpire = date("Y-m-d H:i:s",time() + (86400)); // 1 Month
            
            // CREATE USER TOKEN
            $query="INSERT INTO auth_tokens
                ( inicialke, token, expires, session_token)
                VALUES
                ( :inicialke, :token, :expires, :session_token)";
            $stmt = $this->dbc->prepare($query);
            $stmt->bindValue(":expires",$tokenExpire,PDO::PARAM_STR);
            $stmt->bindValue(":token",$response["token"],PDO::PARAM_STR);
            $stmt->bindValue(":inicialke",$userdata->inicialke,PDO::PARAM_STR);
            $stmt->bindValue(":session_token",intval($sessionToken),PDO::PARAM_INT);
            $stmt->execute();
            
            // RETURN REST USER DATA
            $response["user_id"] = $userdata->inicialke;

            // Create Login event - IS IN isLoogedIN method
            // createLogEvent($this->dbc,$userdata->inicialke,$userdata->inicialke,1,'login_api');
        } else {
            // Error code 2 - Wrong password
            return $this->createResponse(null,2,'login',"Wrong password");
        }
        // Return Response
        return $this->createResponse($response);
    }


    /**
     * Logout Method 
     * 
     * @param dataPost User Data for logout(token,userId)
     */
    private function logout($dataPost){

        // CHECK IF LOGGED IN - GET USER ID, CLIENT ID, USER RANK
        require_once 'Auth.php';
        $auth = new Auth();
        $cuData = $auth->checkToken();
        if(isset($cuData->error)){
            return $this->createResponse(null,$cuData->errorCode,'Authorization',$cuData->error);
        }

        // Delete user token from auth tokens
        $query="DELETE FROM auth_tokens 
            WHERE inicialke = :users_id
            AND token = :token";
        $stmt = $this->dbc->prepare($query);  
        $stmt->bindValue(":token",$dataPost->token,PDO::PARAM_STR);
        $stmt->bindValue(":users_id",$dataPost->user_id,PDO::PARAM_STR);
        $stmt->execute();
        // Create Logout event
        createLogEvent($this->dbc,$dataPost->user_id,$dataPost->user_id,2,'login_api');
        return $this->createResponse(true);
    }

    /**
     * Logout Method 
     * 
     * @param data User Data for logout(token,userId)
     */
    public function isloggedIn($data){
        // CHECK IF LOGGED IN - GET USER ID, CLIENT ID, USER RANK
        require_once 'Auth.php';
        $auth = new Auth();
        $cuData = $auth->checkToken();
        if(isset($cuData->error)){
            return $this->createResponse(null,$cuData->errorCode,'Authorization',$cuData->error);
        } else {
            $userID = $cuData->userID;
            $userRank = $cuData->userRank;

            if($data && $data->refresh != true){
                // SAVE LAST LOGIN TIME + INC LOGIN COUNT
                $query = "UPDATE uporabniki SET last_login = :last_login,
                        login_count = login_count + 1
                        WHERE inicialke = :user_id";
                $stmt = $this->dbc->prepare($query);
                $stmt->bindValue(":last_login",date("Y-m-d H:i:s"),PDO::PARAM_STR);
                $stmt->bindValue(":user_id",$userID,PDO::PARAM_STR);
                $stmt->execute();
            }
            if($data && $data->wl == true || $data->wl == false){         //true je bilo prvo $data->wl == true
                // SET NEW TOKEN 
                return 'ok!';
                $userSession = sha1(time().rand().sha1(rand()).microtime().date("l \t\h\e jS"));
                $newToken = sha1($userSession."RS3{47<f2332".$cuData->ime."}58f72246g829n9lGQ7".$cuData->userID); 

                $sessionToken = false;
                if($cuData->session_token == '1'){
                    $tokenExpire = date("Y-m-d H:i:s",time() + (86400)); //1 DAY
                    $sessionToken = true;
                } else $tokenExpire = date("Y-m-d H:i:s",time() + (86400 * 30)); //1 Month

                // CREATE USER TOKEN
                $query="INSERT INTO auth_tokens
                    ( inicialke, token, expires, session_token)
                    VALUES
                    ( :inicialke, :token, :expires, :session_token)";
                $stmt = $this->dbc->prepare($query);
                $stmt->bindValue(":expires",$tokenExpire,PDO::PARAM_STR);
                $stmt->bindValue(":token",$newToken,PDO::PARAM_STR);
                $stmt->bindValue(":inicialke",$cuData->userID,PDO::PARAM_STR);
                $stmt->bindValue(":session_token",intval($sessionToken),PDO::PARAM_INT);
                $stmt->execute();

                // Delete OLD token from auth tokens
                $query="DELETE FROM auth_tokens 
                    WHERE inicialke = :inicialke
                    AND token = :token";
                $stmt = $this->dbc->prepare($query);  
                $stmt->bindValue(":token",$cuData->token,PDO::PARAM_STR);
                $stmt->bindValue(":inicialke",$cuData->userID,PDO::PARAM_STR);
                $stmt->execute();

                // Create Login event
                 createLogEvent($this->dbc,$cuData->userID,$cuData->userID,1,'login_api');
            }
            $return['success']=true;
            $return['nt']=$newToken;
            $return['st']=$sessionToken;
            $return['ui']=$userID;
            return $this->createResponse($return);
        }
    }

    /************************************************************************************************************/
    // FORGOTTEN PASSWORD 

    public function checkUserKey($data){
        if(empty($data->key) || empty($data->u)){
            return null;
        }

        $nonce = "USER_PASS_RESET_".$data->key;

        $query="SELECT u.inicialke, u.ime, u.priimek
            FROM auth_tokens a 
            JOIN uporabniki u ON u.inicialke = a.inicialke
            
            WHERE a.token = :token
            AND a.expires > '".date('Y-m-d H:i:s')."'
            AND u.inicialke = :inicialke
            AND u.active = 1";
        $stmt = $this->dbc->prepare($query);
        $stmt->bindValue(":token",$nonce,PDO::PARAM_STR);
        $stmt->bindValue(":inicialke",$data->u,PDO::PARAM_STR);
        if($stmt->execute()){
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } else return printErrAndDie($stmt);
    } 

    public function sendEmailPass($data){
        $response = array();
        // Check if Email is set
        if(empty($data->email)) {
            $response["error"] = "10"; // No email to Recover
            $response["message"] = "Missing email"; 
            return $response;
        }

        // Find User Email
		$query="SELECT a.inicialke, a.email, a.ime, a.priimek, a.active, a.rank, a.languages_id,
            l.code AS lang_code
            FROM uporabniki a 
            LEFT JOIN languages l ON a.languages_id = l.id
            WHERE LOWER(a.email) = LOWER(:email)
            AND a.active = 1";
		$stmt = $this->dbc->prepare($query);
		$stmt->execute(array(':email' => $data->email));		

        $userdata = $stmt->fetchObject();

		if(!$userdata){
            $response["error"] = "11"; // Email dont exist
            $response["message"] = "Email dont exist"; 
            return $response;
		}
        // All ok - Send Email

        // Generate token
		$nonce = sha1(time().rand().sha1(rand()).microtime().date("l \t\h\e jS")); //Generiramo naključno besedilo

        $nonce_todb = "USER_PASS_RESET_".$nonce;

        // SAVE NONCE TO DATABASE
        $query="INSERT INTO auth_tokens (inicialke,token,expires)
            VALUES (:inicialke,:token,:expires)";
        $stmt = $this->dbc->prepare($query);
        $stmt->bindValue(":inicialke",$userdata->inicialke,PDO::PARAM_STR);
        $stmt->bindValue(":token",$nonce_todb,PDO::PARAM_STR);
        $stmt->bindValue(":expires",date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s').' + 1 day')),PDO::PARAM_STR); // 1 day - expires
        $stmt->execute();
        
        // GET USER TEMPLATE TO SEND

        // GET MESSAGES -- SLO ONLY

        $message = "Pozdravljeni,<div><br><div>
        Podana je bila zahteva za poenostavitev gesla.&#160;<br></div></div><div>&#268;e ste vi zahtevali poenostavitev 
        kliknite na spodnjo povezavo.</div><div><a href='[link]'>Kliknite tukaj za poenostavitev gesla.</a><br>
        </div><div><br></div><div>&#268;e te zahteva niste vlo&#382;ili, lahko to sporo&#269;ilo prezrete in geslo bo ostalo enako.<br></div>";
        
        $subject = "eCitera - Pozabljeno geslo";

        // CREATE $key FOR ACTIVATION
                        
        // $link = "http://$_SERVER[HTTP_HOST]/register/fpassword/".$userdata->inicialke."/".$userdata->lang_code."/".$nonce;
        $link = REGISTER_REDIRECT_URL."register/fpassword/".$userdata->inicialke."/".$userdata->lang_code."/".$nonce;

        $message = json_decode(str_replace('[link]',$link,json_encode($message)));
        $sendAddress = $userdata->email;
        $from = 'eCitera';

        // SEND EMAIL
        // require_once 'lib/send_email.php';

        // $sendEmail = new SendEmail($this->cuData);
        // $emailInfo = $sendEmail->sendHTMLemail($from,$sendAddress,$subject,$message,null);

        // if($emailInfo != ''){
        //     $response["error"] = "12"; // Email not Send
        //     $response["message"] = $emailInfo; 
        // }
        $response["error"] = 0;
        return $response;
    }

    public function changePassword($data){
        $response = array();
        $response['success'] = 'false';
        if(empty($data)){
            return $response;
        }

        $nonce = "USER_PASS_RESET_".$data->key;

        /*$query="SELECT u.inicialke, u.ime
        FROM auth_tokens a 
        JOIN farmacevti u ON u.inicialke = a.inicialke
        
        WHERE a.token = :token
        AND a.expires > '".date('Y-m-d H:i:s')."'
        AND u.id = :user_id
        AND u.active = 1";*/

        $query="SELECT u.inicialke, u.ime, u.priimek
        FROM auth_tokens a 
        JOIN uporabniki u ON u.inicialke = a.inicialke

        WHERE a.token = :token
        AND a.expires > '".date('Y-m-d H:i:s')."'
        AND u.inicialke = :inicialke
        AND u.active = 1";
        $stmt = $this->dbc->prepare($query);
        $stmt->bindValue(":token",$nonce,PDO::PARAM_STR);
        $stmt->bindValue(":inicialke",$data->id,PDO::PARAM_STR);
        if($stmt->execute()){
            $userCheck = $stmt->fetch(PDO::FETCH_ASSOC);
        } else return printErrAndDie($stmt);

        if(!empty($userCheck)){
            // PASSWORD CHANGE ON
            if(isset($data->password) && $data->password == $data->password_confirm){
                $salt = rand_char(40);
                $query = "UPDATE uporabniki
                        SET password = :password,salt = :salt
                        WHERE inicialke =:id";
                $stmt = $this->dbc->prepare($query);
                $stmt->bindValue(":password",sha1($salt.$data->password),PDO::PARAM_STR);
                $stmt->bindValue(":salt",$salt,PDO::PARAM_STR);
                $stmt->bindValue(":id",$data->id,PDO::PARAM_STR);
                $stmt->execute();

                // CLEAR ALL AUTH TOKENS FOR USERs
                $query = "DELETE FROM auth_tokens WHERE inicialke = :id";
                $stmt = $this->dbc->prepare($query);
                $stmt->bindValue(":id",$data->id,PDO::PARAM_STR); 
                $stmt->execute();
                $response['success'] = 'true';
            }
        } 
        return $response;
    }
}

?>