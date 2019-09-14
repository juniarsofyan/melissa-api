<?php

namespace App\Controllers;

use Slim\Http\Request;
use Slim\Http\Response;

class AuthController
{
    protected $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function login(Request $request, Response $response)
    {
        $credential = $request->getParsedBody();

        $sql = "SELECT 
                    no_member,
                    nama,
                    tanggal AS tanggal_register
                FROM tb_member 
                WHERE 
                    md5(email)=:email AND 
                    ver='DONE'";

        $stmt = $this->db->prepare($sql);

        $data = [
            ":email" => $credential["email"]
        ];

        $stmt->execute($data);

        $result = $stmt->rowCount();

        if ($result > 0) {
            $customer = $stmt->fetch();
            $id = $customer['no_member'];
            $nama = $customer['nama'];
            $tanggal_register = $customer['tanggal_register'];

            $savedToken = $this->saveLoginToken($id);

            if ($savedToken) {

                $result = array(
                    "token" => $savedToken,
                    "no_member" => $id,
                    "nama" => $nama,
                    "tanggal_register" => $tanggal_register,
                );

                return $response->withJson(["status" => "success", "data" => $result], 200);
            }

            return $response->withJson(["status" => "failed", "data" => "0"], 200);
        }

        return $response->withJson(["status" => "failed", "data" => "0"], 200);
    }

    public function saveLoginToken($id)
    {
        $sql = "UPDATE tb_member
                SET login_token=:login_token
                WHERE no_member=:id";

        $stmt = $this->db->prepare($sql);

        //generate simple random code
        $set = '123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $login_token = substr(str_shuffle($set), 0, 12);

        $data = [
            ":login_token" => $login_token,
            ":id" => $id
        ];

        if ($stmt->execute($data)) {
            return $login_token;
        }

        return false;
    }

    public function logout(Request $request, Response $response)
    {
        $credential = $request->getParsedBody();

        $sql = "UPDATE tb_member
                SET login_token=''
                WHERE login_token=:login_token";

        $stmt = $this->db->prepare($sql);

        $data = [":login_token" => $credential['token']];

        if ($stmt->execute($data)) {
            return $response->withJson(["status" => "success", "data" => "1"], 200);
        }

        return $response->withJson(["status" => "failed", "data" => "0"], 200);
    }
}
