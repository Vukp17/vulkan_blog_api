<?php
class BlogAPI extends RestAPI{
    public $modelID;
    public $modelFn;
    public $deleteId;
    function __construct($restParts,$modelID){
		// CHECK IF LOGGED IN - GET USER ID, CLIENT ID, USER RANK
        // require_once 'includes/Auth.php';
        // $auth = new Auth();
		// $cuData = $auth->checkToken();

        // if(isset($cuData->error) && isset($cuData->status)){
        //     return $this->sendResponseJSON($this->createResponse(null,$cuData->status,'Authorization',$cuData->error));
		// } 
        /* Temp data */
        $cuData = (object) [
            'clientID' => '123',
            'userID' => '456',
            'userRank' => 'admin',
            'userLangID' => 'en',
            'workType' => 'fulltime'
        ];

		$this->modelID = $modelID;
		if(sizeof($restParts) > 1) { 
			$this->modelFn = $restParts[1];
			if(sizeof($restParts) > 2){ 
				$this->deleteId = $restParts[2];
			}
		}
		require_once 'includes/blog_sql.php';
		$this->response = $this->execRequest(new Blog($cuData));

		$this->sendResponseJSON($this->response);
	}
}
?>