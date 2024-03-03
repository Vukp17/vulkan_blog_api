<?php 

// DEBUG FUNCTION - PHP
function debug($x){
    if(DEBUG_ON == true){
        echo '<pre style="background-color: #FFCC33;padding: 10px;border-radius: 5px;">';
        $bt = debug_backtrace();
        $caller = array_shift($bt);
        echo $caller['file'].' -> Line: '.$caller['line'].'<br />';
        var_export($x);
        echo '</pre>';
    }
}


// DATABASE PRINT ERRORS
function printErrAndDie($stmt){
    $errArr = $stmt->errorInfo();
    die("Database error! ".$errArr[2]);
    return $errArr[2];
}

// GENERATE RANDOM CHAR TO LENGTH
function rand_char($length) {
    $random = '';
    for ($i = 0; $i < $length; $i++) {
        $random .= chr(mt_rand(33, 126));
    }
    return $random;
}

/**
 * Checks if String is like "a,b,c". If OK returns string OR array 
 * 
 * @param string String to check
 * @param limiter String explode limiter. Default ','
 * @param new_limiter String new limiter. Default ','
 * @param return_table Retruns as table. Default false
 */
function checkStringArray($string = null, $retrun_table = false, $limiter = ',', $new_limiter = ','){
    if(!isset($string) || empty($string)) return null;
    
    $tmp = explode($limiter,$string,null);
    if($retrun_table) return $tmp;
    else {
        $str = '';
        foreach($tmp as $val){
            if($str == '') $str .= $val;
            else $str .= $new_limiter.$val;
        }
        return $str;
    }
    return null;
}

/**
 * Checks if String is like "a,b,c". If OK returns string OR object 
 * 
 * @param string String to check
 * @param limiter String explode limiter. Default ','
 * @param new_limiter String new limiter. Default ','
 * @param return_object Retruns as object. Default false
 */
function checkStringObject($string = null, $return_object = false, $limiter = ',', $new_limiter = ','){
    if(!isset($string) || empty($string)) return null;
    if($return_object) return (object) explode($limiter,$string,null);
    else {
        $tmp = explode($limiter,$string,null);
        $str = '';
        foreach($tmp as $val){
            if($str == '') $str .= $val;
            else $str .= $new_limiter.$val;
        }
        return $str;
    }
    return null;
}

?>