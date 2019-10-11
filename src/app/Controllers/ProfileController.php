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

            $profile = array(
                "email" => $customer['email'],
                "nama" => $customer['name'],
                "telp" => $customer['phone']
            );

            $shipping_address = array(
                "provinsi_id" => $customer['shipping_address']['provinsi_id'],
                "provinsi_nama" => $customer['shipping_address']['provinsi_nama'],
                "kota_id" => $customer['shipping_address']['kota_id'],
                "kota_nama" => $customer['shipping_address']['kota_nama'],
                "kecamatan_id" => $customer['shipping_address']['kecamatan_id'],
                "kecamatan_nama" => $customer['shipping_address']['kecamatan_nama'],
                "alamat" => $customer['shipping_address']['alamat'],
                "kode_pos" => $customer['shipping_address']['kode_pos'] 
            );

            $this->updateShippingAddress($profile, $shipping_address);

            return $response->withJson(["status" => "success", "data" => "1"], 200);
        }

        return $response->withJson(["status" => "failed", "data" => "0"], 200);
    }

    // SAMPE SINI
    public function addShippingAddress($profile, $shipping_address)
    {
        $sql = "INSERT INTO cn_shipping_address (
                    customer_id, nama, telepon, 
                    provinsi_id, provinsi_nama, 
                    kota_id, kota_nama, 
                    kecamatan_id, kecamatan_nama, 
                    alamat, kode_pos
                ) VALUE (
                    (SELECT id FROM cn_customer WHERE email=:email), 
                    :nama, :telp, 
                    :provinsi_id, :provinsi_nama, 
                    :kota_id, :kota_nama, 
                    :kecamatan_id, :kecamatan_nama, 
                    :alamat, :kode_pos
                )";
                
        $stmt = $this->db->prepare($sql);

        $data = [
            ":email" => $profile["email"],
            ":nama" => $profile["name"],
            ":telp" => $profile["phone"],
            ":provinsi_id" => $shipping_address["province_id"],
            ":provinsi_nama" => $shipping_address["province_name"],
            ":kota_id" => $shipping_address["city_id"],
            ":kota_nama" => $shipping_address["city_name"],
            ":kecamatan_id" => $shipping_address["subdistrict_id"],
            ":kecamatan_nama" => $shipping_address["subdistrict_name"],
            ":alamat" => $shipping_address["address"],
            ":kode_pos" => $shipping_address["postcode"]
        ];

        if ($stmt->execute($data)) {

            $last_insert_id = $this->db->lastInsertId();

            $sql = "SELECT COUNT(id) AS count_shipping_address 
                    FROM cn_shipping_address 
                    WHERE customer_id IN (SELECT id FROM cn_customer WHERE email=:email)";

            $stmt = $this->db->prepare($sql);

            $data = [":email" => $shipping_address["email"]];

            $stmt->execute($data);

            $result = $stmt->fetch();

            if ($result['count_shipping_address'] < 2) {
                $this->setDefault($last_insert_id);
            }

            return true;
        }

        return false;
    }

    public function setDefault($shipping_address_id)
    {
        $sql1 = "UPDATE cn_shipping_address 
                SET is_default=0
                WHERE id NOT IN(:id)";

        $stmt1 = $this->db->prepare($sql1);

        $params1 = [":id" => $shipping_address_id];

        if ($stmt1->execute($params1)) {

            $sql2 = "UPDATE cn_shipping_address 
                    SET is_default=1
                    WHERE id=:id";

            $stmt2 = $this->db->prepare($sql2);

            $params2 = [":id" => $shipping_address_id];

            if ($stmt2->execute($params2)) {
                return true;
            }
        }

        return false;
    }

    public function updateShippingAddress($profile, $shipping_address)
    {
        $sql = "UPDATE cn_shipping_address 
                SET 
                    nama=:nama, 
                    telp=:telp, 
                    provinsi_id=:provinsi_id, 
                    provinsi_nama=:provinsi_nama, 
                    kota_id=:kota_id, 
                    kota_nama=:kota_nama, 
                    kecamatan_id=:kecamatan_id, 
                    kecamatan_nama=:kecamatan_nama, 
                    alamat=:alamat, 
                    kode_pos=:kode_pos 
                WHERE 
                    id=:id";

        $stmt = $this->db->prepare($sql);

        $data = [
            ":nama" => $profile['name'],
            ":telp" => $profile['phone'],
            ":provinsi_id" => $shipping_address['province_id'],
            ":provinsi_nama" => $shipping_address['province_name'],
            ":kota_id" => $shipping_address['city_id'],
            ":kota_nama" => $shipping_address['city_name'],
            ":kecamatan_id" => $shipping_address['subdistrict_id'],
            ":kecamatan_nama" => $shipping_address['subdistrict_name'],
            ":alamat" => $shipping_address['address'],
            ":kode_pos" => $shipping_address['postcode'],
            ":id" => $shipping_address['id']
        ];

        if ($stmt->execute($data)) {
            return true;
        }

        return false;
    }
}
