<?php
require_once 'config/database.php';
require_once 'lib/functions.php';


class Blog extends BlogAPI
{
    private $dbc;
    private $userID;
    private $clientID;
    private $userRank;
    private $userLang;
    private $cuData;
    private $userLangID;
    private $settingsModule;
    private $workType;
    private $adminSettingsModule;
    function __construct($cuData)
    {
        header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');    // cache for 1 day
        $this->clientID = $cuData->clientID;
        $this->userID = $cuData->userID;
        $this->userRank = $cuData->userRank;
        $this->userLangID = $cuData->userLangID;
        $this->workType = $cuData->workType;
        $this->cuData = $cuData;
        $this->dbc = Db::connect(GLOBAL_DATABASE);
        $this->dbc->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    public function posts()
    {
        $sortType = $_GET['sortType'];
        $sortOrder = $_GET['sortOrder'];
        $pageStart = $_GET['pageStart'];
        $pageLimit = $_GET['pageLimit'];
        $searchValue = $_GET['searchValue'];

        $query = "SELECT SQL_CALC_FOUND_ROWS a.* FROM post a ";

        // GROUP BY
        $query .= " GROUP BY a.id";

        if ($searchValue) {
            // GORUP BY - HAVING 
            $searchValue = "%" . $searchValue . "%";
            // BY WICH FIELDS TO SEARCH
            $searchFields = " HAVING a.title LIKE :search
                    OR a.summary LIKE :search  ";
            //  OR a.username LIKE :search
            // OR a.email LIKE :search
            // OR a.gsm LIKE :search
            // OR a.rank LIKE :search
            // OR a.login_count LIKE :search
            // OR a.last_login LIKE :search
            // OR languageName LIKE :search
            // OR languageCode LIKE :search
            $query .= $searchFields;
        }
        if ($sortType) {
            $query .= " ORDER BY `" . $sortType . "`";
            if ($sortOrder == 'true') {
                $query .= " DESC";
            } else $query .= " ASC";
        }
        if (isset($pageStart) && isset($pageLimit)) {
            $query .= " LIMIT :page, :rows";
        }
        $stmt = $this->dbc->prepare($query);
        if ($searchValue) $stmt->bindValue(":search", $searchValue, PDO::PARAM_STR);
        $stmt->bindValue(":page", intval($pageStart), PDO::PARAM_INT);
        $stmt->bindValue(":rows", intval($pageLimit), PDO::PARAM_INT);
        //echo $query;
        $return = array();
        if ($stmt->execute()) {
            $return['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else return printErrAndDie($stmt);

        //GET DATA ROW COUNT
        $query = "SELECT FOUND_ROWS() AS count";
        $stmt = $this->dbc->prepare($query);
        if ($stmt->execute()) {
            $return['rows'] = $stmt->fetch(PDO::FETCH_ASSOC);
        } else return printErrAndDie($stmt);
        return $return;
    }
    /* DATABASE TABLE
    CREATE TABLE `post` (
    `id` bigint NOT NULL,
    `authorId` bigint NOT NULL,
    `parentId` bigint DEFAULT NULL,
    `title` varchar(75) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `metaTitle` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `slug` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    `summary` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    `published` tinyint(1) NOT NULL DEFAULT '0',
    `createdAt` datetime NOT NULL,
    `updatedAt` datetime DEFAULT NULL,
    `publishedAt` datetime DEFAULT NULL,
    `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    */

    /*    GET     */
    public function getById($id, $type)
    {
        if (empty($id)) return $this->createResponse(null, 1, 'data_error', 'Missing id');
        if (empty($type)) return $this->createResponse(null, 2, 'data_error', 'Missing parameters');
        // $id = rawurldecode(base64_decode($id));
        $return = (object)['success' => true,];
        switch ($type) {
            case 'post': // Get order details
                $query = "Select * from post where id = :id";
                $stmt = $this->dbc->prepare($query);
                $stmt->bindValue(":id", $id, PDO::PARAM_STR);
                if ($stmt->execute()) {
                    $item = $stmt->fetch(PDO::FETCH_ASSOC);
                    $stmt->closeCursor();


                    $return = $this->createResponse($item);
                    return   $return;
                } else return $this->createResponse(null, 2, 'data_error', $return->message);
                break;
            default:
                return $this->createResponse(null, 2, 'data_error', 'Invalid type');
        }
    }
    /*    POST     */
    public function post_data($data, $type)
    {
        if (empty($data)) return $this->createResponse(null, 1, 'data_error', 'Missing data');
        if (empty($type)) return $this->createResponse(null, 2, 'data_error', 'Missing parameters');
        switch ($type) {
            case 'post':
                $return = $this->save_post($data);
                break;
            default:
                return $this->createResponse(null, 3, 'data_error', "No function found");
        }
        if ($return && $return->success) return $this->createResponse($return);
        else return $this->createResponse(null, 2, 'data_error', $return->message);
    }
    /*    PUT     */
    public function put_data($data, $type)
    {
        if (empty($data)) return $this->createResponse(null, 1, 'data_error', 'Missing data');
        if (empty($type)) return $this->createResponse(null, 2, 'data_error', 'Missing parameters');

        switch ($type) {
            case 'post':
                $return = $this->update_post($data);
                break;
            default:
                return $this->createResponse(null, 3, 'data_error', "No function found");
        }
        if ($return && $return->success) return $this->createResponse($return);
        else return $this->createResponse(null, 2, 'data_error', $return->message);
    }
    /*    DELETE     */
    public function delete_record($type, $id)
    {
        if (empty($id)) return $this->createResponse(null, 1, 'data_error', 'Missing id');
        if (empty($type)) return $this->createResponse(null, 2, 'data_error', 'Missing parameters');
        switch ($type) {
            case 'post':
                $return = $this->delete_post($id);
                break;
            default:
                return $this->createResponse(null, 3, 'data_error', "No function found");
        }
        if ($return && $return->success) return $this->createResponse($return);
        else return $this->createResponse(null, 2, 'data_error', $return->message);
    }
    /*    FUNCTIONS     */
    public function save_post($data)
    {
        $return = (object)['success' => true];

        try {
            $query = "INSERT INTO post (title, metaTitle, slug, summary, published, createdAt, content) VALUES (:title, :metaTitle, :slug, :summary, :published, :createdAt, :content)";
            $stmt = $this->dbc->prepare($query);

            // Bind parameters
            $stmt->bindValue(":title", $data->title, PDO::PARAM_STR);
            $stmt->bindValue(":metaTitle", $data->metaTitle, PDO::PARAM_STR);
            $stmt->bindValue(":slug", $data->slug, PDO::PARAM_STR);
            $stmt->bindValue(":summary", $data->summary, PDO::PARAM_STR);
            $stmt->bindValue(":published", $data->published, PDO::PARAM_STR);
            $stmt->bindValue(":createdAt", date('Y-m-d H:i:s'), PDO::PARAM_STR);
            $stmt->bindValue(":content", $data->content, PDO::PARAM_STR);

            if (!$stmt->execute()) {
                $return->success = false;
                $return->message = "Error saving post: " . $stmt->errorInfo()[2];
                return $return;
            }

            $return->message = "Post saved";
            return $return;
        } catch (PDOException $e) {
            // Log the error
            error_log("Database Error: " . $e->getMessage());

            // Handle the error gracefully
            http_response_code(500);
            $return->success = false;
            $return->message = "Database Error: " . $e->getMessage();
            return $return;
        }
    }

    public function update_post($data)
    {
        $return = (object)['success' => true,];
        $query = "UPDATE post SET title = :title, metaTitle = :metaTitle, slug = :slug, summary = :summary, published = :published, updatedAt = :updatedAt, content = :content WHERE id = :id";
        $stmt = $this->dbc->prepare($query);
        $stmt->bindValue(":id", $data->id, PDO::PARAM_STR);
        $stmt->bindValue(":title", $data->title, PDO::PARAM_STR);
        $stmt->bindValue(":metaTitle", $data->metaTitle, PDO::PARAM_STR);
        $stmt->bindValue(":slug", $data->slug, PDO::PARAM_STR);
        $stmt->bindValue(":summary", $data->summary, PDO::PARAM_STR);
        $stmt->bindValue(":published", $data->published, PDO::PARAM_STR);
        $stmt->bindValue(":updatedAt", date('Y-m-d H:i:s'), PDO::PARAM_STR);
        $stmt->bindValue(":content", $data->content, PDO::PARAM_STR);
        if (!$stmt->execute()) {
            $return->success = false;
            $return->message = "Error updating post";
            return $return;
        }
        $return->message = "Post updated";
        return $return;
    }
    public function delete_post($id)
    {
        $return = (object)['success' => true,];
        $query = "DELETE FROM post WHERE id = :id";
        $stmt = $this->dbc->prepare($query);
        $stmt->bindValue(":id", $id, PDO::PARAM_STR);
        if (!$stmt->execute()) {
            $return->success = false;
            $return->message = "Error deleting post";
            return $return;
        }
        $return->message = "Post deleted";
        return $return;
    }
}
// Remove the closing PHP tag
