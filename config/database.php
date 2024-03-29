<?php

class Db {
    private static $existing_dbhs = array();
    
    public static function connect($db_name){
		# Database setup


		// $db_host = 'localhost';
		//$db_host = 'DESKTOP-K7NO64F\SQLEXPRESS';
		//$db_user = 'vuk@admin';
		//$db_pass = 'citera123';
		//$db_user = 'cito';
		//$db_pass = 'cito123';
		/*$db_user = 'citera_login';
		$db_pass = '123456';*/
		// $db_pass = '';
		// $db_user = 'root';
        // $db_port = '3306';
        $db_host = 'localhost';
		$db_pass = 'vulkan2576';
		$db_user = 'kfjnkrqp_admin';
        $db_port = '3306';

        try {
            $db_name = strtolower($db_name);
        
            // If no connection exists
            if (!array_key_exists($db_name, self::$existing_dbhs)) {
                // Specify the port in the DSN
                $dsn = 'mysql:host=' . $db_host . ';port=3306;dbname=' . $db_name;
				$options = array(
					PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
				);
                $dbh = new PDO($dsn, $db_user, $db_pass, $options);
                self::$existing_dbhs[$db_name] = $dbh;
                return $dbh;
            } else {
                // Returns the existing object
                return self::$existing_dbhs[$db_name];
            }
        } catch (PDOException $e) {
            $message = "Error!: " . $e->getMessage() . "<br/>";
            $response = (object)[
                'success' => false,
                'data' => null,
                'type' => 'DB Error',
                'error' => true,
                'code' => 400,
                'message' => $message
            ];
            $response = json_encode($response);
            header('Content-Type: application/json');
            header('Content-Length: ' . strlen($response));
            echo $response;
            die();
        }
	}
}

?>
