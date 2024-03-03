<?php
require_once 'lib/functions.php';
require_once 'config/configuration.php';
//error_reporting(0);
$restAPI = new RestAPI();
/**
 * Main REST API 
 */
class RestAPI{
    
    public $result;
    public $error;
    public $modelFn;
    // Varibles 
    public $modelID;
    public $deleteId;
    public $response;

    private $modelName;
    public $restParams;
    public $itemId;

    /**
     * Constructor for MAIN API
     */
    public function __construct(){
        $url_parts = explode(API_LOCATION, $_SERVER['REQUEST_URI']);
        debug($url_parts);
        // $url_parts[1] = substr($url_parts[1], 1);
        // print_r($url_parts);
        debug($_SERVER['REQUEST_URI']);
        $url_parts[0] = substr($url_parts[0], 1);
        debug($url_parts);
        if(empty($url_parts[0])){
            $this->response = $this->createResponse(null,1,'request',"Incomplete request", 400);
            $this->sendResponseJSON($this->response);
        } else {
            $rest_url = $url_parts[0];
            // IF URI NOT EMPTY
            if(!empty($rest_url)){
                if(!empty($_SERVER['QUERY_STRING']))
                    $rest_url = substr($rest_url, 0, strpos($rest_url, '?'));
    
                    $restParams = explode('/', $rest_url);

                    // Remove the first element (index 0) from the array
                    array_shift($restParams);
                    
                    // Re-index the array starting from 0
                    $restParams = array_values($restParams);
                $this->modelName = $restParams[0];
                $this->restParams = $restParams;

                $this->itemId = null;
                if(isset($_GET['id']))
                    $this->itemId = $_GET['id'];
                
                // DISPLAY INFO IF DEBUG ON
                if(DEBUG_ON){
                    echo("URL PARTS:");
                    debug($restParams);
                    echo("MODEL NAME:");
                    debug($this->modelName);
                    echo("MODEL ID:");
                    debug($this->itemId);
                    echo("PARAMETERS:");
                    debug($_GET);
                }
                
                //ROUTES - API
                switch($this->modelName){
                    case "login":
                        require_once 'models/login.php';
                        $modelAPI = new LoginAPI($restParams,$this->itemId);
                        break;
                    case "main":
                        require_once 'models/main.php';
                        $modelAPI = new MainAPI($restParams,$this->itemId);
                        break;
                    case "blog":
                        require_once 'models/blog.php';
                        $modelAPI = new BlogAPI($restParams,$this->itemId);
                        break;
                    case "activation":
                        require_once 'models/activation.php';
                        $modelAPI = new ActivationAPI($restParams,$this->itemId);
                        break;
                    default:
                        $this->response = $this->createResponse(null,2,'request',"Bad Request - Wrong model: ".$this->modelName, 400);
                        $this->sendResponseJSON($this->response);
                        break;
                }
            }
        }
    }

    /**
    * Create Response Object
    * 
    * @param data Payload Data
    * @param responseCode Return responseCode(default = 200)
    * @param errorCode Return error Code(default = true)
    * @param errorCodeType Return error Code Type(default = null)
    * @param errorMessage Return error Message(default = null)
    * 
    * @return response
    */
    function createResponse($data, $errorCode = null, $errorCodeType = null, $errorMessage = null, $responseCode = null){
        $response = (object)[
            'data' => $data, // Payload data
            // 'responseCode' => $responseCode, // Response code
        ];
        if(isset($responseCode)){
            $response->responseCode = $responseCode;
        }
        if(isset($errorCode)){
            $response->error = (object)[
                'code' => $errorCode, // Error code
                'type' => $errorCodeType, // Error code Type
                'message' => $errorMessage, // Error Message
            ];
        }
       
        return $response;
    }

    function transformArray($array) {
        $result = array();
        foreach($array as $item) {
            $newItem = array();
            foreach($item as $key => $value) {
                if(is_array($value)) {
                    $newItem[$key] = $this->transformArray($value);
                } elseif(is_numeric($value)) {
                    $newItem[$key] = (float) $value;
                } else {
                    $newItem[$key] = $value;
                }
            }
            $result[] = $newItem;
        }
        return json_encode(array('data' => $result));
    }
    
    
    // EXEC REQUEST
    /**
     * Execute Request Method
     * 
     * @param model Method(function) name
     */
    public function execRequest($model){
        $response = $this->createResponse(null,3,'request',"Unaccepted request function!", 405);

        switch ($_SERVER['REQUEST_METHOD']){
            case "GET":   
                if(!empty($this->modelFn) && $this->modelFn == 'byId'){ // GET BY ID
                    $response = $model->getById($this->modelID,$_GET['type']);
                } else if(!empty($this->modelFn) && $this->modelFn == 'checkMod'){ //CHECK MOD VALUE
                    $response = $model->checkMod($this->modelID,$_GET['type']);
                } else if(!empty($this->modelFn) && $this->modelFn == 'gFile'){ // GET FILE
                    $response = $model->gFile($this->modelID,$_GET['type']);
                } else if(!empty($this->modelFn) && $this->modelFn == 'deleteWarning'){ // GET deleteWarning
                    $response = $model->deleteWarning($this->modelID,$_GET['type']);
                } else if(!empty($this->modelID)){ //GET ELEMENT BY ID
                    if(!empty($this->modelFn)){
                        //$response = $model->{$this->modelFn}(null);
                        $response = $model->get_record($this->modelFn,$this->modelID);
                    } else {
                        $response = $this->createResponse(null,4,'request',"Request function dont exist! Function: ".$this->modelFn, 405);
                    }
                } else {
                    if(!empty($this->modelFn)){ //GET ELEMENT BY FUNCTION
                        // IF METHOD EXIST
                        if(method_exists($model, $this->modelFn)){
                            $response = $model->{$this->modelFn}(null);
                        } else {
                            $response = $this->createResponse(null,4,'request',"Request function dont exist! Function: ".$this->modelFn, 405);
                        }
                    } else {
                        $response = $this->createResponse(null,5,'request',"No request function! ->".$this->modelFn, 405);
                    }
                }
                break;
    
            case "POST": // SAVE NEW ELEMENT && ExportData
                $data = json_decode(file_get_contents("php://input"), false);
                $response = $model->post_data($data,$this->modelFn);
                break;
    
            case "PUT": // UPDATE ELEMENT
                $data = json_decode(file_get_contents("php://input"), false);
                $response = $model->put_data($data,$this->modelFn);
                break;
    
            case "DELETE": // DELETE ELEMENT
                if (!empty($this->modelFn) && !empty($this->deleteId)){
                    $response = $model->delete_record($this->modelFn,$this->deleteId);
                } else {
                    $response = $this->createResponse(null,6,'request',"Unaccepted request function!", 405);
                }
                break;
                
            default: // NONE - ERROR 
                $response = $this->createResponse(null,6,'request',"Unaccepted request function!", 405);
        }
        return $response;
    }

    /**
     * Echo Response in JSON format
     */
    public function sendResponseJSON($response){
        debug($response);
        // Check if any errors
        if(isset($response->error )){
            // CREATE JSON
            $res = json_encode($response,JSON_NUMERIC_CHECK); 

            // CHECK IF SOME ERRORS
            if(isset($response->responseCode)){
                http_response_code(intval($response->responseCode));        
            } else {
                header('Content-Type: application/json');
                header('Content-Length: '.strlen($res)); 
            }
            // PRINT JSON
            echo $res;
        } else {
            $res = json_encode($response,JSON_NUMERIC_CHECK);

            echo $res;
            // debug($res);
        }
    }

    /**
     * Response As File
     */
    public function sendFileResponse($response){
        if($response){
            header('Content-Description: File Transfer');

            //header('Content-Type: application/'.$response['file_type']);
            header('Content-Type: '.$response['file_type']);
            header("Content-Transfer-Encoding: Binary");

            header('Expires: 0');
            $file = $response['file_path'];
            //var_dump($file);
            header('Content-Disposition: attachment; filename="'.$response['name'].'"');
            header('Content-Length: ' . filesize($file));

            readfile($file);
        }
    }

}
?>
