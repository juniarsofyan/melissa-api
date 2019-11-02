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

    public function index(Request $request, Response $response, array $args)
    {
        $category = $args["category"];
        $limit = $args["limit"];
        $offset = $args["offset"];

        $where_clause = "jenis";

        if ($category == "SERIES" || $category == "series") {
            $where_clause = "unit";
        }

        $sql = "SELECT 
                    brg.kode_barang, 
                    brg.nama, 
                    brg.berat, 
                    brg.h_nomem AS harga, 
                    IFNULL(brg.h_nomem - (brg.h_nomem * (brg.diskon / 100)), 0) AS harga_diskon,
                    IFNULL(brg.diskon, 0) as diskon,
                    brg.pic,
                    tipe_kulit,
                    unit,
                    cat
                FROM cn_barang brg
                WHERE 
                    kode_barang NOT IN (
                        SELECT kode_barang 
                        FROM cn_barang
                        WHERE kode_barang BETWEEN 'SK005' AND 'SK024'
                    )
                    AND brg.h_nomem > 0
                    AND brg.cat = 0
                    AND {$where_clause} = :category
                    LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':category', $category, \PDO::PARAM_STR);
        $stmt->bindValue(':limit', (int) $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int) $offset, \PDO::PARAM_INT);
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
                    AND brg.h_nomem > 0
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
                    kode_barang,
                    nama,
                    harga,
                    harga_diskon,
                    diskon,
                    pic,
                    tipe_kulit,
                    unit,
                    jenis
                FROM
                    (
                        SELECT
                            brg.kode_barang,
                            brg.nama,
                            brg.h_nomem harga,
                            IFNULL(brg.h_nomem - (brg.h_nomem * (brg.diskon / 100)), 0) AS harga_diskon,
                            IFNULL(brg.diskon, 0) as diskon,
                            brg.pic,
                            brg.tipe_kulit,
                            brg.unit,
                            brg.jenis,
                            brg.cat
                        FROM
                            cn_barang brg
                        WHERE
                            brg.kode_barang NOT IN (
                                SELECT cnb.kode_barang
                                FROM cn_barang cnb
                                WHERE 
                                    (cnb.kode_barang BETWEEN 'SK005' AND 'SK024') OR 
                                    (cnb.kode_barang BETWEEN '8800A' AND '8800F')
                            )
                    ) a
                WHERE
                    a.kode_barang LIKE :keyword
                    OR a.nama LIKE :keyword
                    OR a.jenis LIKE :keyword
                    AND a.harga > 0
                    AND a.cat = 0
                ORDER BY
                    a.nama ASC";

        // echo $sql; exit();

        $keyword = $args['keyword'];

        $stmt = $this->db->prepare($sql);
        $data = [":keyword" => "%${keyword}%"];
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
                    brg.h_nomem harga, 
                    brg.h_hpb harga_perhitungan_bonus, 
                    IFNULL(brg.h_nomem - (brg.h_nomem * (brg.diskon / 100)), 0) AS harga_diskon,
                    IFNULL(brg.diskon, 0) as diskon,
                    brg.pic,
                    brg.unit,
                    des_singkat,
                    despro.des1 AS deskripsi,
                    despro.pakai AS cara_pakai,
                    despro.des_singkat AS des_singkat,
                    despro.manfaat AS manfaat,
                    cat
                FROM cn_barang brg
                LEFT JOIN cn_des_pro despro
                    ON brg.kode_barang = despro.kode
                WHERE 
                    brg.h_nomem > 0 
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
                    brg.h_nomem harga, 
                    IFNULL(brg.h_nomem - (brg.h_nomem * (brg.diskon / 100)), 0) AS harga_diskon,
                    IFNULL(brg.diskon, 0) as diskon,
                    brg.pic
                FROM cn_barang brg
                WHERE brg.h_nomem > 0
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

    public function getProductCodes(Request $request, Response $response)
    {
        $sql = "SELECT 
                    b.kode_barang
                FROM 
                    (
                        SELECT
                            brg.kode_barang,
                            brg.h_nomem harga,
                            brg.cat
                        FROM
                            cn_barang brg
                        WHERE
                            brg.kode_barang NOT IN (
                                SELECT cnb.kode_barang
                                FROM cn_barang cnb
                                WHERE 
                                    (cnb.kode_barang BETWEEN 'SK005' AND 'SK024') OR 
                                    (cnb.kode_barang BETWEEN '8800A' AND '8800F')
                            )
                    ) b
                WHERE 
                    b.harga > 0
                    AND b.cat = 0";

        $stmt = $this->db->query($sql);
        
        if ($stmt->rowCount() > 0) {
            $result = $stmt->fetchAll();
            return $response->withJson(["status" => "success", "data" => $result], 200);
        } else {
            return $response->withJson(["status" => "success", "data" => null], 200);
        }
    }
}
