<?php
namespace models;

class LinkModel
{
    private $dbConnection;

    public function __construct()
    {
        //Initialize SQLite DB connection
        $this->dbConnection = new \SQLite3(DB_PATH);
    }

    public function checkPrimaryUrlAlreadyParsed($primaryLink)
    {
        $statement = $this->dbConnection->prepare('SELECT `id` FROM `primary_links` WHERE name=:name');
        $statement->bindValue(':name', $primaryLink, SQLITE3_TEXT);
        $result = $statement->execute();
        $resultArray = $result->fetchArray(SQLITE3_NUM);

        if (empty($resultArray)) {
            return false;
        }

        if (isset($resultArray[0])) {
            return $resultArray[0];
        }
    }

    public function checkUrlAlreadyParsed($url, $primaryLinkId)
    {
        $statement = $this->dbConnection->prepare('SELECT `id` FROM `dependent_links` WHERE name=:name AND primary_link_id=:primary_link_id');
        $statement->bindValue(':name', $url, SQLITE3_TEXT);
        $statement->bindValue(':primary_link_id', $primaryLinkId, SQLITE3_INTEGER);
        $result = $statement->execute();
        $resultArray = $result->fetchArray(SQLITE3_NUM);

        if (empty($resultArray)) {
            return false;
        }

        if (isset($resultArray[0])) {
            return $resultArray[0];
        }
    }

    public function addUrlToDB($url, $primaryLinkId)
    {
        $query = "INSERT INTO `dependent_links`('name', 'primary_link_id') VALUES(:name, :primary_link_id) ";
        $statement = $this->dbConnection->prepare($query);
        $statement->bindValue(':name', $url, SQLITE3_TEXT);
        $statement->bindValue(':primary_link_id', $primaryLinkId, SQLITE3_INTEGER);
        $statement->execute();

        return $this->dbConnection->lastInsertRowID();
    }

    public function addPrimaryUrlToDB($url)
    {
        $statement = $this->dbConnection->prepare("INSERT INTO `primary_links`('name') VALUES(:name) ");
        $statement->bindValue(':name', $url);
        $statement->execute();

        return $this->dbConnection->lastInsertRowID();
    }
}