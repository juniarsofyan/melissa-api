<?php

namespace App\Controllers;

use Slim\Http\Request;
use Slim\Http\Response;

class WishlistController
{
    protected $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function get(Request $request, Response $response, array $args)
    {
        $sql = "SELECT wsl.id, brg.nama as nama_barang
                FROM cn_wishlist wsl
                INNER JOIN cn_barang brg 
                    ON brg.kode_barang = wsl.kode_barang
                WHERE wsl.customer_id=:customer_id";

        $stmt = $this->db->prepare($sql);

        $data = [":customer_id" => $args["customer_id"]];

        $stmt->execute($data);

        $result = $stmt->fetchAll();

        if ($result) {
            return $response->withJson(["status" => "success", "data" => $result], 200);
        }

        return $response->withJson(["status" => "failed", "data" => "0"], 200);
    }

    public function add(Request $request, Response $response)
    {
        $shipping_address = $request->getParsedBody();
        $sql = "INSERT INTO cn_wishlist (customer_id, kode_barang) VALUE (:customer_id, :kode_barang)";
        $stmt = $this->db->prepare($sql);

        $data = [
            ":customer_id" => $shipping_address["customer_id"],
            ":kode_barang" => $shipping_address["kode_barang"]
        ];

        if ($stmt->execute($data)) {
            return $response->withJson(["status" => "success", "data" => "1"], 200);
        }

        return $response->withJson(["status" => "failed", "data" => "0"], 200);
    }

    public function remove(Request $request, Response $response, array $args)
    {
        $sql = "DELETE FROM cn_wishlist WHERE id=:id";
        $stmt = $this->db->prepare($sql);

        $data = [":id" => $args["id"]];

        if ($stmt->execute($data)) {
            return $response->withJson(["status" => "success", "data" => "1"], 200);
        }

        return $response->withJson(["status" => "failed", "data" => "0"], 200);
    }
}
