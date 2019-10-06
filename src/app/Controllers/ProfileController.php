<?php

namespace App\Controllers;

use Slim\Http\Request;
use Slim\Http\Response;

class ProfileController
{
    protected $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function get(Request $request, Response $response)
    {
        $user = $request->getParsedBody();

        $sql = "SELECT nik, nama, tgl_lahir, telepon, email 
                FROM cn_customer 
                WHERE email=:email";

        $stmt = $this->db->prepare($sql);

        $data = [":email" => $user["email"]];

        $stmt->execute($data);

        $result = $stmt->fetch();

        if ($result) {
            return $response->withJson(["status" => "success", "data" => $result], 200);
        }

        return $response->withJson(["status" => "failed", "data" => "0"], 200);
    }

    public function update(Request $request, Response $response)
    {
        $customer = $request->getParsedBody();

        $sql = "UPDATE cn_customer 
            SET 
                nik=:nik,
                nama=:name,
                tgl_lahir=:birthdate,
                telepon=:phone,
                email=:email
            WHERE 
                email=:email2";

        $stmt = $this->db->prepare($sql);

        $data = [
            ":nik" => $customer['nik'],
            ":name" => $customer['name'],
            ":birthdate" => $customer['birthdate'],
            ":phone" => $customer['phone'],
            ":email" => $customer['email'],
            ":email2" => $customer['email']
        ];

        if ($stmt->execute($data)) {
            return $response->withJson(["status" => "success", "data" => "1"], 200);
        }

        return $response->withJson(["status" => "failed", "data" => "0"], 200);
    }
}
