<?php

namespace App\Controllers;

use Slim\Http\Request;
use Slim\Http\Response;

class AccessKeyController
{
    protected $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function check(Request $request, Response $response)
    {
        $credential = $request->getParsedBody();

        $sql = "SELECT
                    no_member,
                    nama,
                    email,
                    kota, 
                    MD5(email) AS 'key',
                    photo
                FROM tb_member 
                WHERE 
                    MD5(email)=:key";

        $stmt = $this->db->prepare($sql);

        $data = [
            ":key" => $credential["key"]
        ];

        $stmt->execute($data);

        if ($stmt->rowCount() > 0) {
            
            $member = $stmt->fetch();

            return $response->withJson(["status" => "success", "data" => $member], 200);
        }

        return $response->withJson(["status" => "failed", "data" => "0"], 200);
    }

}
