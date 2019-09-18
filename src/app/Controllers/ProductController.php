<?php

namespace App\Controllers;

use Slim\Http\Request;
use Slim\Http\Response;

class ProductController
{
    // protected $container;
    protected $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /* public function get(Request $request, Response $response, array $args)
    {
        $sql = "SELECT 
                    brg.kode_barang, 
                    brg.nama, 
                    brg.h_member harga, 
                    IFNULL(brg.h_member - (brg.h_member * (brg.diskon / 100)), 0) AS harga_diskon,
                    IFNULL(brg.diskon, 0) as diskon,
                    brg.pic,
                    tipe_kulit,
                    unit
                FROM cn_barang brg
                WHERE 
                    kode_barang NOT IN (
                        SELECT kode_barang 
                        FROM cn_barang
                        WHERE kode_barang BETWEEN 'SK005' AND 'SK024'
                    )
                    AND brg.h_member > 0
                    AND brg.cat = 0";


        if (isset($args["type"])) {
            $sql .= " AND brg.jenis=:jenis";
            $stmt = $this->db->prepare($sql);
            $data = [":jenis" => $args["type"]];
            $stmt->execute($data);
        } else {
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
        }


        $result = $stmt->fetchAll();

        if ($result) {
            return $response->withJson(["status" => "success", "data" => $result], 200);
        }

        return $response->withJson(["status" => "failed", "data" => "0"], 200);
    } */

    public function index(Request $request, Response $response, array $args)
    {
        $category = $args["category"];
        $limit = $args["limit"];
        $offset = $args["offset"];

        $sql = "SELECT 
                    brg.kode_barang, 
                    brg.nama, 
                    brg.berat, 
                    brg.h_member AS harga, 
                    IFNULL(brg.h_member - (brg.h_member * (brg.diskon / 100)), 0) AS harga_diskon,
                    IFNULL(brg.diskon, 0) as diskon,
                    brg.pic,
                    tipe_kulit,
                    unit
                FROM cn_barang brg
                WHERE 
                    kode_barang NOT IN (
                        SELECT kode_barang 
                        FROM cn_barang
                        WHERE kode_barang BETWEEN 'SK005' AND 'SK024'
                    )
                    AND brg.h_member > 0
                    AND brg.cat = 0
                    AND jenis = :category
                    LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':category', $category, \PDO::PARAM_STR);
        $stmt->bindValue(':limit', (int)$limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, \PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $result = $stmt->fetchAll();
            $rowcount = $this->countProducts($category);
            return $response->withJson(["status" => "success", "rowcount" => $rowcount, "data" => $result], 200);
        } else {
            return $response->withJson(["status" => "success", "data" => null], 200);
        }
    }

    // public function countProducts(Request $request, Response $response, array $args)
    public function countProducts($category)
    {
        // $category = $args["category"];

        $sql = "SELECT 
                    COUNT(brg.kode_barang) as rowcount
                FROM cn_barang brg
                WHERE 
                    kode_barang NOT IN (
                        SELECT kode_barang 
                        FROM cn_barang
                        WHERE kode_barang BETWEEN 'SK005' AND 'SK024'
                    )
                    AND brg.h_member > 0
                    AND brg.cat = 0
                    AND jenis = :category";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':category', $category, \PDO::PARAM_STR);
        $stmt->execute();
        
        return $stmt->fetch()['rowcount'];

        /* if ($stmt->rowCount() > 0) {
            $result = $stmt->fetch();
            return $response->withJson(["status" => "success", "data" => $result], 200);
        } else {
            return $response->withJson(["status" => "success", "data" => null], 200);
        } */
    }

    public function search(Request $request, Response $response, array $args)
    {
        $sql = "SELECT 
                    brg.kode_barang, 
                    brg.nama, 
                    brg.h_member harga, 
                    IFNULL(brg.h_member - (brg.h_member * (brg.diskon / 100)), 0) AS harga_diskon,
                    IFNULL(brg.diskon, 0) as diskon,
                    brg.pic,
                    tipe_kulit,
                    unit
                FROM cn_barang brg
                WHERE MATCH(brg.kode_barang, brg.nama, brg.jenis, brg.tipe_kulit) AGAINST (:keyword IN NATURAL LANGUAGE MODE)
                    AND brg.h_member > 0 
                    AND brg.cat = 0
                ORDER BY brg.nama ASC";


        $stmt = $this->db->prepare($sql);
        $data = [":keyword" => $args["keyword"]];
        $stmt->execute($data);

        $result = $stmt->fetchAll();

        if ($result) {
            return $response->withJson(["status" => "success", "data" => $result], 200);
        }

        return $response->withJson(["status" => "failed", "data" => "0"], 200);
    }

    public function series(Request $request, Response $response)
    {
        $sql = "SELECT 
                    kode_barang AS kode_pack,
                    nama AS nama_pack,
                    berat,
                    pic
                FROM cn_barang
                WHERE kode_barang BETWEEN 'SK005' AND 'SK024'";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        $result = $stmt->fetchAll();

        if ($result) {
            return $response->withJson(["status" => "success", "data" => $result], 200);
        }

        return $response->withJson(["status" => "failed", "data" => "0"], 200);
    }

    public function detail(Request $request, Response $response, array $args)
    {
        $sql = "SELECT 
                    brg.kode_barang, 
                    brg.nama, 
                    brg.jenis,
                    brg.berat,
                    brg.h_member harga, 
                    brg.h_hpb harga_perhitungan_bonus, 
                    IFNULL(brg.h_member - (brg.h_member * (brg.diskon / 100)), 0) AS harga_diskon,
                    IFNULL(brg.diskon, 0) as diskon,
                    brg.pic,
                    brg.unit,
                    despro.des1 AS deskripsi,
                    despro.pakai AS cara_pakai
                FROM cn_barang brg
                LEFT JOIN cn_des_pro despro
                    ON brg.kode_barang = despro.kode
                WHERE 
                    brg.h_member > 0 
                    AND brg.cat = 0 
                    AND brg.kode_barang=:kode_barang";

        $stmt = $this->db->prepare($sql);

        $data = [":kode_barang" => $args["product_code"]];

        $stmt->execute($data);

        $result = $stmt->fetch();

        if ($result) {
            $result['deskripsi'] = preg_replace("/[^a-zA-Z0-9\s]/", "", $result['deskripsi']);
            $result['cara_pakai'] = preg_replace("/[^a-zA-Z0-9\s]/", "", $result['cara_pakai']);

            return $response->withJson(["status" => "success", "data" => $result], 200);
        }

        return $response->withJson(["status" => "failed", "data" => "0"], 200);
    }

    public function related(Request $request, Response $response, array $args)
    {
        $sql = "SELECT 
                    brg.kode_barang, 
                    brg.nama, 
                    brg.h_member harga, 
                    IFNULL(brg.h_member - (brg.h_member * (brg.diskon / 100)), 0) AS harga_diskon,
                    IFNULL(brg.diskon, 0) as diskon,
                    brg.pic
                FROM cn_barang brg
                WHERE brg.h_member > 0
                    AND brg.cat = 0 AND brg.jenis=:jenis
                    AND brg.kode_barang NOT IN (:kode_barang)
                ORDER BY brg.nama ASC
                LIMIT 3";


        $stmt = $this->db->prepare($sql);
        $data = [
            ":jenis" => $args["type"],
            ":kode_barang" => $args["product_code"]
        ];
        $stmt->execute($data);
        $result = $stmt->fetchAll();

        if ($result) {
            return $response->withJson(["status" => "success", "data" => $result], 200);
        }

        return $response->withJson(["status" => "failed", "data" => "0"], 200);
    }
}
