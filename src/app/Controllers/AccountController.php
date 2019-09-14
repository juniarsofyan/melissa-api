<?php

namespace App\Controllers;

use Slim\Http\Request;
use Slim\Http\Response;

class AccountController
{
    protected $db, $mailer;

    public function __construct($db, $mailer)
    {
        $this->db = $db;
        $this->mailer = $mailer;
    }

    public function get(Request $request, Response $response, array $args)
    {
        $sql = "SELECT no_member, no_ktp as nik, nama, tgl_lahir, telp, email 
                FROM tb_member 
                WHERE login_token=:token";

        $stmt = $this->db->prepare($sql);

        $data = [":token" => $args["token"]];

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

        $sql = "UPDATE tb_member 
            SET 
                no_ktp=:no_ktp,
                nama=:name,
                tgl_lahir=:birthdate,
                telp=:phone,
                email=:email
            WHERE 
                login_token=:token";

        $stmt = $this->db->prepare($sql);

        $data = [
            ":no_ktp" => $customer['nik'],
            ":name" => $customer['name'],
            ":birthdate" => $customer['birthdate'],
            ":phone" => $customer['phone'],
            ":email" => $customer['email'],
            ":token" => $customer['token']
        ];

        if ($stmt->execute($data)) {
            return $response->withJson(["status" => "success", "data" => "1"], 200);
        }

        return $response->withJson(["status" => "failed", "data" => "0"], 200);
    }
}
