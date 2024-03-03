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
        $this->clientID = $cuData->clientID;
        $this->userID = $cuData->userID;
        $this->userRank = $cuData->userRank;
        $this->userLangID = $cuData->userLangID;
        $this->workType = $cuData->workType;
        $this->cuData = $cuData;
        $this->dbc = Db::connect(GLOBAL_DATABASE);
        $this->dbc->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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
        $return = (object)['success' => true,];
        $query = "INSERT INTO post (title, metaTitle, slug, summary, published, createdAt, content) VALUES (:title, :metaTitle, :slug, :summary, :published, :createdAt, :content)";
        $stmt = $this->dbc->prepare($query);
        // $stmt->bindValue(":authorId", $this->userID, PDO::PARAM_STR);
        $stmt->bindValue(":title", $data->title, PDO::PARAM_STR);
        $stmt->bindValue(":metaTitle", $data->metaTitle, PDO::PARAM_STR);
        $stmt->bindValue(":slug", $data->slug, PDO::PARAM_STR);
        $stmt->bindValue(":summary", $data->summary, PDO::PARAM_STR);
        $stmt->bindValue(":published", $data->published, PDO::PARAM_STR);
        $stmt->bindValue(":createdAt", date('Y-m-d H:i:s'), PDO::PARAM_STR);
        $stmt->bindValue(":content", $data->content, PDO::PARAM_STR);
        if (!$stmt->execute()) {
            $return->success = false;
            $return->message = "Error saving order";
            return $return;
        }
        $return->message = "Post saved";
        return $return;
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
