<?php
class MainAPI extends RestAPI{

    function __construct($restParts,$modelID){
		// CHECK IF LOGGED IN - GET USER ID, CLIENT ID, USER RANK
        require_once 'includes/Auth.php';
        $auth = new Auth();
		$cuData = $auth->checkToken();

        if(isset($cuData->error) && isset($cuData->status)){
            return $this->sendResponseJSON($this->createResponse(null,$cuData->status,'Authorization',$cuData->error));
        } 
		$this->modelID = $modelID;
		if(sizeof($restParts) > 1) { 
			$this->modelFn = $restParts[1];
			if(sizeof($restParts) > 2){
				$this->deleteId = $restParts[2];
			}
		}
		require_once 'includes/main_sql.php';
		$this->response = $this->execRequest(new Main($cuData));
		$this->sendResponseJSON($this->response);
	}

	/*public function exec_request($model){
		switch ($_SERVER['REQUEST_METHOD']){
			case "GET":
				if(!empty($this->modelFn)){ //GET ELEMENT BY FUNCTION
					// IF METHOD EXIST
					if(method_exists($model, $this->modelFn)){
						$this->result = $model->{$this->modelFn}(null);
					} else {
						$this->result = $this->createResponse(null,4,'request',"Request function dont exist! Function: ".$this->modelFn, 405);
					}
				} else if(!empty($this->modelId)){ //GET ELEMENT BY ID
					$this->result = $model->get_record($this->modelId);
				} else { // GET LIST OF ELEMENTS
					$this->result = $model->get_list();
				}
				break;

			 case "POST": // SAVE NEW ELEMENT
			 	$data = json_decode(file_get_contents("php://input"), false);
				if(!empty($this->modelFn)){ //GET ELEMENT BY FUNCTION
					// IF METHOD EXIST
					if(method_exists($model, $this->modelFn)){
						$this->result = $model->{$this->modelFn}($data);
					} else {
						$this->result = $this->createResponse(null,4,'request',"Request function dont exist! Function: ".$this->modelFn, 405);
					}
				} //else $this->result = $model->add_record($data);
 				else $this->result = array("error"=>"Request method dont exist! Method: ".$this->modelFn,"status"=>405);
 				break;
			/*
 			case "PUT": // UPDATE ELEMENT
  				$data = json_decode(file_get_contents("php://input"), false);
  				$this->result = $model->update_record($data);
   				break;

 			/*case "DELETE": // DELETE ELEMENT
 				if (!empty($this->model_id)){
 					$this->result = $model->delete_record($this->model_id);
 				}
				 break;*/
				 
	/*		default: // NONE - ERROR 
				$this->result = array("error"=>"Unaccepted request method!","status"=>405);
        }
    }*/
}
?>