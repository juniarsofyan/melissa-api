<?php

namespace App\Controllers;

use Slim\Http\Request;
use Slim\Http\Response;

class AuthController
{
    protected $db, $mailer;

    public function __construct($db, $mailer)
    {
        $this->db = $db;
        $this->mailer = $mailer;
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
                    email=:email AND 
                    pwd=:password AND 
                    ver='DONE'";

        $stmt = $this->db->prepare($sql);

        $data = [
            ":email" => $credential["email"],
            ":password" => $credential["password"]
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

    public function register(Request $request, Response $response)
    {
        $customer = $request->getParsedBody();

        if ($this->isEmailAvailable($customer["email"])) {
            $sql = "INSERT INTO cn_customer (
                            email, 
                            password, 
                            kode_aff,
                            note,
                            activation_code
                        ) VALUE (
                            :email, 
                            :password, 
                            :affiliation_code, 
                            :registration_code, 
                            :activation_code
                        )";

            $stmt = $this->db->prepare($sql);

            //generate simple random code
            $set = '123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $activation_code = substr(str_shuffle($set), 0, 12);

            $data = [
                ":email" => $customer["email"],
                ":password" => $customer["password"],
                ":affiliation_code" => "",
                ":registration_code" => "",
                ":activation_code" => "",
            ];

            if ($stmt->execute($data)) {
                $last_insert_id = $this->db->lastInsertId();
                $this->sendActivationEmail($customer["email"], $last_insert_id, $activation_code);

                $data = array(
                    "status" => "succes",
                    "data" => array(
                        "email" => $customer["email"],
                        "token" => $customer["initoken"]
                    )
                );

                return $response->withJson(["status" => "success", "data" => $data], 200);
            }

            return $response->withJson(["status" => "failed", "data" => "0"], 200);
        } else {
            return $response->withJson(["status" => "failed", "data" => "user_already_exists"], 200);
        }
    }

    public function activate(Request $request, Response $response, array $args)
    {
        $sql = "UPDATE cn_customer 
            SET 
                activation_code='', 
                tanggal_registrasi=:tanggal_registrasi,
                tanggal_expired=:tanggal_expired
            WHERE 
                id=:id AND activation_code=:activation_code";

        $stmt = $this->db->prepare($sql);

        $data = [
            ":activation_code" => "",
            ":tanggal_registrasi" => date('Y-m-d H:i:s'),
            ":tanggal_expired" => date('Y-m-d H:i:s', strtotime('+3 months')),
            ":id" => $args['id'],
            ":activation_code" => $args['activation_code'],
        ];

        if ($stmt->execute($data)) {

            $sql = "SELECT kode_aff
                    FROM cn_customer 
                    WHERE id=:id";

            $stmt = $this->db->prepare($sql);

            $data = [":id" => $args["id"]];

            $stmt->execute($data);

            $kode_aff = $stmt->fetch()['kode_aff'];

            return $response->withJson(["status" => "success", "data" => $kode_aff], 200);
        }

        return $response->withJson(["status" => "failed", "data" => "0"], 200);
    }

    public function sendActivationEmail($email, $user_id, $activation_code)
    {
        $mailer = $this->mailer;
        $mail_content = "Silahkan klik link berikut untuk mengaktifkan akun anda: <br/>";
        // $mail_content .= "<a href='http://localhost:8080/activate/{$user_id}/{$activation_code}'>Aktifkan sekarang!</a>";
        $mail_content .= "<a href='https://bshop.bellezkin.com/activate/{$user_id}/{$activation_code}'>Aktifkan sekarang!</a>";
        $send_email = $mailer->sendEmail($email, "Activate Account", $mail_content);

        if ($send_email) {
            return true;
        }

        return false;
    }

    public function isEmailAvailable($email)
    {
        $sql = "SELECT email 
                FROM cn_customer 
                WHERE email=:email";

        $stmt = $this->db->prepare($sql);

        $data = [":email" => $email];

        $stmt->execute($data);

        if ($stmt->rowCount()) {
            return false;
        }

        return true;
    }
}
