<?php
class ActivationAPI extends RestAPI{

	function __construct($restParts,$modelID){

		$this->modelID = $modelID;
		$this->modelFn = null;
		if(sizeof($restParts) > 0) { 
			$this->modelFn = $restParts[1];
		}
		require_once 'includes/activation_sql.php';
		$this->response = $this->execRequest(new Activation());
		$this->sendResponseJSON($this->response);
	}
}
?>