<?php

namespace App\Controllers;

use Slim\Http\Request;
use Slim\Http\Response;

class ShippingAddressController
{
    protected $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function get(Request $request, Response $response, array $args)
    {
        $sql = "SELECT 
                    sa.id, sa.nama, sa.telp, 
                    sa.provinsi_id, sa.provinsi_nama, 
                    sa.kota_id, sa.kota_nama, 
                    sa.kecamatan_id, sa.kecamatan_nama, 
                    sa.alamat, sa.kode_pos, sa.is_default
                FROM cn_shipping_address_member sa
                INNER JOIN tb_member cs
                ON sa.customer_id = cs.no_member
                WHERE cs.login_token=:token";

        $stmt = $this->db->prepare($sql);

        $data = [":token" => $args["token"]];

        $stmt->execute($data);

        $result = $stmt->fetchAll();

        if ($result) {
            return $response->withJson(["status" => "success", "data" => $result], 200);
        }

        return $response->withJson(["status" => "failed", "data" => "0"], 200);
    }

    public function current(Request $request, Response $response, array $args)
    {
        $sql = "SELECT 
                    sa.id, sa.nama, sa.telp, 
                    sa.provinsi_id, sa.provinsi_nama, 
                    sa.kota_id, sa.kota_nama, 
                    sa.kecamatan_id, sa.kecamatan_nama, 
                    sa.alamat, sa.kode_pos, sa.is_default
                FROM cn_shipping_address_member sa
                INNER JOIN tb_member cs
                ON sa.customer_id = cs.no_member
                WHERE cs.login_token=:token
                AND sa.is_default = 1";

        $stmt = $this->db->prepare($sql);

        $data = [":token" => $args["token"]];

        $stmt->execute($data);

        $result = $stmt->fetch();

        if ($result) {
            return $response->withJson(["status" => "success", "data" => $result], 200);
        }

        return $response->withJson(["status" => "failed", "data" => "0"], 200);
    }

    public function detail(Request $request, Response $response, array $args)
    {
        $sql = "SELECT 
                    sa.id, sa.nama, sa.telp, 
                    sa.provinsi_id, sa.provinsi_nama, 
                    sa.kota_id, sa.kota_nama, 
                    sa.kecamatan_id, sa.kecamatan_nama, 
                    sa.alamat, sa.kode_pos, sa.is_default
                FROM cn_shipping_address_member sa
                INNER JOIN tb_member cs
                ON sa.customer_id = cs.no_member
                WHERE sa.id=:id";

        $stmt = $this->db->prepare($sql);

        $data = [":id" => $args["id"]];

        $stmt->execute($data);

        $result = $stmt->fetch();

        if ($result) {
            return $response->withJson(["status" => "success", "data" => $result], 200);
        }

        return $response->withJson(["status" => "failed", "data" => "0"], 200);
    }

    public function add(Request $request, Response $response)
    {
        $shipping_address = $request->getParsedBody();
        // $sql = "INSERT INTO cn_shipping_address_member (customer_id, nama, telp, provinsi, kota, kecamatan, alamat, kode_pos, is_default) VALUE ((SELECT id FROM tb_member WHERE login_token=:token), :nama, :telp, :provinsi, :kota, :kecamatan, :alamat, :kode_pos, :is_default)";
        $sql = "INSERT INTO cn_shipping_address_member (
                    customer_id, nama, telp, 
                    provinsi_id, provinsi_nama, 
                    kota_id, kota_nama, 
                    kecamatan_id, kecamatan_nama, 
                    alamat, kode_pos
                ) VALUE (
                    (SELECT no_member FROM tb_member WHERE login_token=:token), 
                    :nama, :telp, 
                    :provinsi_id, :provinsi_nama, 
                    :kota_id, :kota_nama, 
                    :kecamatan_id, :kecamatan_nama, 
                    :alamat, :kode_pos
                )";
        $stmt = $this->db->prepare($sql);

        $data = [
            ":token" => $shipping_address["token"],
            ":nama" => $shipping_address["name"],
            ":telp" => $shipping_address["phone"],
            ":provinsi_id" => $shipping_address["province_id"],
            ":provinsi_nama" => $shipping_address["province_name"],
            ":kota_id" => $shipping_address["city_id"],
            ":kota_nama" => $shipping_address["city_name"],
            ":kecamatan_id" => $shipping_address["subdistrict_id"],
            ":kecamatan_nama" => $shipping_address["subdistrict_name"],
            ":alamat" => $shipping_address["address"],
            ":kode_pos" => $shipping_address["postcode"],
            // ":is_default" => $shipping_address["is_default"]
        ];

        if ($stmt->execute($data)) {

            $last_insert_id = $this->db->lastInsertId();

            $sql = "SELECT COUNT(id) AS count_shipping_address 
                    FROM cn_shipping_address_member 
                    WHERE customer_id IN (SELECT no_member FROM tb_member WHERE login_token=:token)";

            $stmt = $this->db->prepare($sql);

            $data = [":token" => $shipping_address["token"]];

            $stmt->execute($data);

            $result = $stmt->fetch();

            if ($result['count_shipping_address'] < 2) {
                $this->setDefault($request, $response, array('id' => $last_insert_id));
            }

            return $response->withJson(["status" => "success", "data" => "1"], 200);
        }

        return $response->withJson(["status" => "failed", "data" => "0"], 200);
    }

    public function update(Request $request, Response $response, array $args)
    {
        $shipping_address = $request->getParsedBody();

        $sql = "UPDATE cn_shipping_address_member 
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
            ":nama" => $shipping_address['name'],
            ":telp" => $shipping_address['phone'],
            ":provinsi_id" => $shipping_address['province_id'],
            ":provinsi_nama" => $shipping_address['province_name'],
            ":kota_id" => $shipping_address['city_id'],
            ":kota_nama" => $shipping_address['city_name'],
            ":kecamatan_id" => $shipping_address['subdistrict_id'],
            ":kecamatan_nama" => $shipping_address['subdistrict_name'],
            ":alamat" => $shipping_address['address'],
            ":kode_pos" => $shipping_address['postcode'],
            ":id" => $args['id']
        ];

        if ($stmt->execute($data)) {
            return $response->withJson(["status" => "success", "data" => "1"], 200);
        }

        return $response->withJson(["status" => "failed", "data" => "0"], 200);
    }

    public function delete(Request $request, Response $response, array $args)
    {
        $sql = "DELETE FROM cn_shipping_address_member WHERE id=:id";
        $stmt = $this->db->prepare($sql);

        $data = [":id" => $args["id"]];

        if ($stmt->execute($data)) {
            return $response->withJson(["status" => "success", "data" => "1"], 200);
        }

        return $response->withJson(["status" => "failed", "data" => "0"], 200);
    }

    public function setDefault(Request $request, Response $response, array $args)
    {
        $sql1 = "UPDATE cn_shipping_address_member 
                SET is_default=0
                WHERE id NOT IN(:id)";

        $stmt1 = $this->db->prepare($sql1);

        $params1 = [":id" => $args['id']];

        if ($stmt1->execute($params1)) {

            $sql2 = "UPDATE cn_shipping_address_member 
                    SET is_default=1
                    WHERE id=:id";

            $stmt2 = $this->db->prepare($sql2);

            $params2 = [":id" => $args['id']];

            if ($stmt2->execute($params2)) {
                return $response->withJson(["status" => "success", "data" => "1"], 200);
            }
        }

        return $response->withJson(["status" => "failed", "data" => "0"], 200);
    }
}
