<?php
class LoginAPI extends RestAPI{
		
	function __construct($restParams,$itemId){
		
		$this->itemId = $itemId;
		$this->modelFn = null;
		if(sizeof($restParams) > 0) { 
			$this->modelFn = $restParams[1];
		}

		require_once 'includes/login_sql.php';
		$this->response = $this->execRequest(new Login);
		
		$this->sendResponseJSON($this->response);
	}
}
?>