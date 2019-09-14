<?php

namespace App\Controllers;

use Slim\Http\Request;
use Slim\Http\Response;

class CartController
{
    // protected $container;
    protected $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function get(Request $request, Response $response)
    {
        $customer = $request->getQueryParams();

        $sql = "SELECT customer_id, kode_barang, qty 
                FROM cn_shopping_cart 
                WHERE customer_id=:customer_id";

        $stmt = $this->db->prepare($sql);

        $data = [
            ":customer_id" => $customer["customer_id"]
        ];

        $stmt->execute($data);

        $result = $stmt->fetchAll();

        if ($result) {
            return $response->withJson(["status" => "success", "data" => $result], 200);
        }

        return $response->withJson(["status" => "failed", "data" => "0"], 200);
    }

    public function add(Request $request, Response $response)
    {
        try {
            $customer = $request->getParsedBody();

            $this->db->beginTransaction();

            $cart_insert_id = "";

            $sql = "SELECT COUNT(customer_id) as customer_count
                    FROM cn_shopping_cart 
                    WHERE customer_id IN (
                        SELECT id 
                        FROM tb_member 
                        WHERE login_token=:login_token
                    )";

            $stmt = $this->db->prepare($sql);

            $data = [":login_token" => $customer["token"]];

            $stmt->execute($data);

            $customer_count = $stmt->fetch()['customer_count'];

            if ($customer_count < 1) {
                $sql = "INSERT INTO cn_shopping_cart (customer_id, tanggal_dibuat)
                        VALUES (
                            (SELECT id FROM tb_member WHERE login_token=:login_token),
                            NOW()
                        )";

                $stmt = $this->db->prepare($sql);

                $data = [":login_token" => $customer["token"]];

                $stmt->execute($data);

                $cart_insert_id = $this->db->lastInsertId();
            }

            $sql = "INSERT INTO cn_shopping_cart_detail (shopping_cart_id, kode_barang, qty, tanggal_ditambahkan)
                        VALUES (
                            :shopping_cart_id,
                            :kode_barang,
                            :qty,
                            NOW()
                        )";

            $stmt = $this->db->prepare($sql);

            $data = [
                ":shopping_cart_id" => $cart_insert_id,
                ":kode_barang" => $customer["product_code"],
                ":qty" => $customer["qty"],
            ];

            $result = $stmt->execute($data);

            $this->db->commit();


            if ($result) {
                return $response->withJson(["status" => "success", "data" => $result], 200);
            }

            return $response->withJson(["status" => "success", "data" => $customer_count], 200);
        } catch (\Exception $exception) {
            $this->db->rollBack();
            return $response->withJson(["status" => "failed", "data" => "0"], 200);
        }
    }

    public function remove(Request $request, Response $response)
    {
        $customer = $request->getParsedBody();
        $sql = "DELETE FROM cn_shopping_cart WHERE customer_id=:customer_id AND kode_barang=:kode_barang";
        $stmt = $this->db->prepare($sql);

        $data = [
            ":customer_id" => $customer["customer_id"],
            ":kode_barang" => $customer["kode_barang"]
        ];

        if ($stmt->execute($data)) {
            return $response->withJson(["status" => "success", "data" => $data], 200);
        }

        return $response->withJson(["status" => "failed", "data" => "0"], 200);
    }

    public function increase(Request $request, Response $response)
    {
        $customer = $request->getParsedBody();
        $sql = "UPDATE cn_shopping_cart SET qty = qty + 1 WHERE customer_id=:customer_id AND kode_barang=:kode_barang";
        $stmt = $this->db->prepare($sql);

        $data = [
            ":customer_id" => $customer["customer_id"],
            ":kode_barang" => $customer["kode_barang"]
        ];

        if ($stmt->execute($data)) {
            return $response->withJson(["status" => "success", "data" => $data], 200);
        }

        return $response->withJson(["status" => "failed", "data" => "0"], 200);
    }

    public function decrease(Request $request, Response $response)
    {
        $customer = $request->getParsedBody();
        $sql = "UPDATE cn_shopping_cart SET qty = qty - 1 WHERE customer_id=:customer_id AND kode_barang=:kode_barang";
        $stmt = $this->db->prepare($sql);

        $data = [
            ":customer_id" => $customer["customer_id"],
            ":kode_barang" => $customer["kode_barang"]
        ];

        if ($stmt->execute($data)) {
            return $response->withJson(["status" => "success", "data" => $data], 200);
        }

        return $response->withJson(["status" => "failed", "data" => "0"], 200);
    }
}
