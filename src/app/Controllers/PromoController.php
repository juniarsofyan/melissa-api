<?php

namespace App\Controllers;

use Slim\Http\Request;
use Slim\Http\Response;

class PromoController
{
    protected $db, $promomanager;

    public function __construct($db, $promomanager)
    {
        $this->db = $db;
        $this->promomanager = $promomanager;
    }

    public function getProducts($product_type = "", $specific_product_codes = "")
    {
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
                    AND brg.cat = 0";

        if ($product_type != "") {
            $sql .= " AND brg.jenis=:jenis";
            $stmt = $this->db->prepare($sql);
            $data = [":jenis" => $product_type];
            $stmt->execute($data);
        } else {
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
        }

        if ($specific_product_codes != "") {
            $sql .= " AND kode_barang IN (" . $specific_product_codes . ")";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
        }

        $result = $stmt->fetchAll();

        return $result;
    }

    public function getProductDetail($product_code, $is_free_item = false)
    {
        $sql = "SELECT 
                    brg.kode_barang, 
                    brg.nama, 
                    brg.jenis,
                    brg.unit,
                    brg.berat,
                    brg.h_member harga, 
                    brg.h_hpb harga_perhitungan_bonus, 
                    IFNULL(brg.h_member - (brg.h_member * (brg.diskon / 100)), 0) AS harga_diskon,
                    IFNULL(brg.diskon, 0) as diskon,
                    brg.pic,
                    despro.des1 AS deskripsi,
                    despro.pakai AS cara_pakai
                FROM cn_barang brg
                LEFT JOIN cn_des_pro despro
                    ON brg.kode_barang = despro.kode
                WHERE 
                    brg.kode_barang=:kode_barang";

        if ($is_free_item) {
            $sql .= " AND brg.h_member = 0 AND brg.cat = 1";
        } else {
            $sql .= " AND brg.h_member > 0 AND brg.cat = 0";
        }

        $stmt = $this->db->prepare($sql);

        $data = [":kode_barang" => $product_code];

        $stmt->execute($data);

        $result = $stmt->fetch();

        if ($result) {
            $result['deskripsi'] = preg_replace("/[^a-zA-Z0-9\s]/", "", $result['deskripsi']);
            $result['cara_pakai'] = preg_replace("/[^a-zA-Z0-9\s]/", "", $result['cara_pakai']);

            return $result;
        }

        return false;
    }

    public function all(Request $request, Response $response, array $args)
    {
        $options = $request->getParsedBody();
        $date_join = strtotime($options['date_join']);
        $product_type = isset($args["type"]) ? $args["type"] : "";
        $specific_product_codes = "";

        if (isset($options["product_codes"])) {
            $specific_product_codes = $options["product_codes"];
        }

        // CHECK AVAILABLE PROMOS
        $raw_data = json_decode($this->promomanager->getAllPromos());
        $promos = (array) $raw_data->rules;
        $promos_count = count($promos);

        // IF PROMO AVAILABLE
        if ($promos_count > 0) {

            $current_date = strtotime(date('Y-m-d'));
            $current_products = $this->getProducts($product_type, $specific_product_codes);

            $i = 0;
            foreach ($current_products as $key => $value) {

                foreach ($promos as $promo) {

                    if (!isset($promo->minimum_purchase)) {
                        if (isset($promo->kode_barang)) {
                            if ($value['kode_barang'] == $promo->kode_barang) {

                                // IF PROMO BASED ON PERIOD
                                if (isset($promo->period)) {

                                    $date_period = explode(" - ", $promo->period);
                                    $date_period_start = strtotime($date_period[0]);
                                    $date_period_end = strtotime($date_period[1]);

                                    // IF PROMO PERIOD HAS NOT EXPIRED
                                    if ($current_date >= $date_period_start && $current_date <= $date_period_end) {

                                        // IF PROMO BASED ON JOIN DATE CLAUSE
                                        if (isset($promo->join_date_clause) && isset($promo->join_date_range)) {

                                            // IF MEMBER JOIN BEFORE CERTAIN DATE
                                            if ($promo->join_date_clause == "before") {

                                                if ($date_join < strtotime($promo->join_date_range)) {

                                                    if (isset($promo->h_member)) {
                                                        $current_products[$i]['harga'] = $promo->h_member;
                                                    }

                                                    if (isset($promo->vc)) {
                                                        $current_products[$i]['harga_perhitungan_bonus'] = $promo->vc;
                                                    }

                                                    if (isset($promo->diskon)) {
                                                        $current_products[$i]['diskon'] = $promo->diskon;
                                                        $current_products[$i]['harga_diskon'] = $promo->harga_diskon;
                                                    }

                                                    if (isset($promo->free_items)) {
                                                        $current_products[$i]['free_items'] = $promo->free_items;
                                                    }

                                                    if (isset($promo->multiply_applies)) {
                                                        $current_products[$i]['multiply_applies'] = $promo->multiply_applies;
                                                    }
                                                }
                                            }

                                            // IF MEMBER JOIN BETWEEN TWO DATES
                                            if ($promo->join_date_clause == "between") {

                                                $join_date_range = explode(" - ", $promo->join_date_range);
                                                $join_date_range_start = strtotime($join_date_range[0]);
                                                $join_date_range_end = strtotime($join_date_range[1]);

                                                if ($date_join >= $join_date_range_start && $date_join <= $join_date_range_end) {

                                                    if (isset($promo->h_member)) {
                                                        $current_products[$i]['harga'] = $promo->h_member;
                                                    }

                                                    if (isset($promo->vc)) {
                                                        $current_products[$i]['harga_perhitungan_bonus'] = $promo->vc;
                                                    }

                                                    if (isset($promo->diskon)) {
                                                        $current_products[$i]['diskon'] = $promo->diskon;
                                                        $current_products[$i]['harga_diskon'] = $promo->harga_diskon;
                                                    }

                                                    if (isset($promo->free_items)) {
                                                        $current_products[$i]['free_items'] = $promo->free_items;
                                                    }

                                                    if (isset($promo->multiply_applies)) {
                                                        $current_products[$i]['multiply_applies'] = $promo->multiply_applies;
                                                    }
                                                }
                                            }
                                        } else {
                                            if (isset($promo->h_member)) {
                                                $current_products[$i]['harga'] = $promo->h_member;
                                            }

                                            if (isset($promo->vc)) {
                                                $current_products[$i]['harga_perhitungan_bonus'] = $promo->vc;
                                            }

                                            if (isset($promo->diskon)) {
                                                $current_products[$i]['diskon'] = $promo->diskon;
                                                $current_products[$i]['harga_diskon'] = $promo->harga_diskon;
                                            }

                                            if (isset($promo->free_items)) {
                                                $current_products[$i]['free_items'] = $promo->free_items;
                                            }

                                            if (isset($promo->multiply_applies)) {
                                                $current_products[$i]['multiply_applies'] = $promo->multiply_applies;
                                            }
                                        }
                                    }
                                } else {
                                    if (isset($promo->h_member)) {
                                        $current_products[$i]['harga'] = $promo->h_member;
                                    }

                                    if (isset($promo->vc)) {
                                        $current_products[$i]['harga_perhitungan_bonus'] = $promo->vc;
                                    }

                                    if (isset($promo->diskon)) {
                                        $current_products[$i]['diskon'] = $promo->diskon;
                                        $current_products[$i]['harga_diskon'] = $promo->harga_diskon;


                                        if (isset($promo->free_items)) {
                                            $current_products[$i]['free_items'] = $promo->free_items;
                                        }

                                        if (isset($promo->multiply_applies)) {
                                            $current_products[$i]['multiply_applies'] = $promo->multiply_applies;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                $i++;
            }
            return $response->withJson(["status" => "success", "data" => $current_products], 200);
        } else {
            return $response->withJson(["status" => "success", "data" => "0"], 200);
        }
    }

    public function generatePromoCode(Request $request, Response $response)
    {
        $sql = "SELECT kode_promo
                FROM cn_event_promo
                WHERE (tanggal_awal between DATE_FORMAT(NOW() ,'%Y-%m-01') AND NOW())
                ORDER BY kode_promo desc";

        $stmt = $this->db->prepare($sql);

        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $result = $stmt->fetch();
            $result = ++$result['kode_promo'];
            return $response->withJson(["status" => "success", "data" => $result], 200);
        } else {
            $initial_code = "EVT";
            $alphabets = array("A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K", "L");
            $current_year = date('y');
            $current_month_alphabet = $alphabets[date('m') - 1];
            $current_day = date('d');
            $counter = "0001";
            $promo_code = $initial_code . $current_year . $current_month_alphabet . $current_day . $counter;
            return $response->withJson(["status" => "success", "data" => $promo_code], 200);
        }

        return $response->withJson(["status" => "failed", "data" => "0"], 200);
    }

    public function get(Request $request, Response $response, array $args)
    {
        $sql = "SELECT 
                    kode_promo, 
                    nama_promo, 
                    tanggal_awal, 
                    tanggal_akhir, 
                    deskripsi, 
                    tipe_promo, 
                    status 
                FROM cn_event_promo";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll();

        if ($result) {
            return $response->withJson(["status" => "success", "data" => $result], 200);
        }

        return $response->withJson(["status" => "failed", "data" => "0"], 200);
    }

    public function add(Request $request, Response $response)
    {
        $promo = $request->getParsedBody();

        $sql = "INSERT INTO cn_event_promo (
                    kode_promo, 
                    nama_promo, 
                    tanggal_awal, 
                    tanggal_akhir, 
                    deskripsi, 
                    tipe_promo, 
                    jenis_platform, 
                    status
                ) VALUE (
                    :kode_promo, 
                    :nama_promo, 
                    :tanggal_awal, 
                    :tanggal_akhir, 
                    :deskripsi, 
                    :tipe_promo, 
                    :jenis_platform, 
                    :status
                )";

        $stmt = $this->db->prepare($sql);

        $data = [
            ":kode_promo" => $promo["promo_code"],
            ":nama_promo" => $promo["promo_name"],
            ":tanggal_awal" => $promo["date_start"],
            ":tanggal_akhir" => $promo["date_end"],
            ":deskripsi" => preg_replace("/[^a-zA-Z0-9\s]/", "", $promo["description"]),
            ":tipe_promo" => $promo["type"],
            ":jenis_platform" => $promo["platform"],
            ":status" => (isset($promo["status"]) ? "active" : "inactive")
        ];

        if ($stmt->execute($data)) {
            return $response->withJson(["status" => "success", "data" => "1"], 200);
        }

        return $response->withJson(["status" => "failed", "data" => "0"], 200);
    }

    public function update(Request $request, Response $response, array $args)
    {
        $promo = $request->getParsedBody();

        $sql = "UPDATE cn_event_promo 
                SET 
                    nama_promo=:nama_promo, 
                    tanggal_awal=:tanggal_awal, 
                    tanggal_akhir=:tanggal_akhir, 
                    kelipatan_nominal=:kelipatan_nominal, 
                    tipe_promo=:tipe_promo, 
                    status=:status
                WHERE 
                    kode_promo=:kode_promo";

        $stmt = $this->db->prepare($sql);

        $data = [
            ":nama_promo" => $promo['promo_name'],
            ":tanggal_awal" => $promo['date_start'],
            ":tanggal_akhir" => $promo['date_end'],
            ":deskripsi" => $promo["description"],
            ":tipe_promo" => $promo['type'],
            ":status" => (isset($promo["status"]) ? $promo["status"] : ""),
            ":kode_promo" => $args['promo_code']
        ];

        if ($stmt->execute($data)) {
            return $response->withJson(["status" => "success", "data" => "1"], 200);
        }

        return $response->withJson(["status" => "failed", "data" => "0"], 200);
    }

    public function delete(Request $request, Response $response, array $args)
    {
        $sql = "DELETE FROM cn_event_promo WHERE kode_promo=:kode_promo";
        $stmt = $this->db->prepare($sql);

        $data = [":kode_promo" => $args["promo_code"]];

        if ($stmt->execute($data)) {
            return $response->withJson(["status" => "success", "data" => "1"], 200);
        }

        return $response->withJson(["status" => "failed", "data" => "0"], 200);
    }

    public function setStatus(Request $request, Response $response, array $args)
    {
        $promo = $request->getParsedBody();

        $sql = "UPDATE cn_event_promo 
                SET 
                    status=:status
                WHERE 
                    kode_promo=:kode_promo";

        $stmt = $this->db->prepare($sql);

        $data = [
            ":status" => $promo['status'],
            ":kode_promo" => $args['promo_code']
        ];

        if ($stmt->execute($data)) {
            return $response->withJson(["status" => "success", "data" => "1"], 200);
        }

        return $response->withJson(["status" => "failed", "data" => "0"], 200);
    }

    public function detail(Request $request, Response $response, array $args)
    {
        $options = $request->getParsedBody();
        $date_join = strtotime($options['date_join']);
        $product_code = $args["product_code"];

        // CHECK AVAILABLE PROMOS
        $raw_data = json_decode($this->promomanager->getAllPromos());
        $promos = (array) $raw_data->rules;
        $promos_count = count($promos);

        // IF PROMO AVAILABLE
        if ($promos_count > 0) {

            $current_date = strtotime(date('Y-m-d'));
            $current_products = $this->getProductDetail($product_code);

            foreach ($promos as $promo) {
                if (!isset($promo->minimum_purchase)) {
                    if (isset($promo->kode_barang)) {
                        if ($current_products['kode_barang'] == $promo->kode_barang) {

                            // IF PROMO BASED ON PERIOD
                            if (isset($promo->period)) {

                                $date_period = explode(" - ", $promo->period);
                                $date_period_start = strtotime($date_period[0]);
                                $date_period_end = strtotime($date_period[1]);

                                // IF PROMO PERIOD HAS NOT EXPIRED
                                if ($current_date >= $date_period_start && $current_date <= $date_period_end) {

                                    // IF PROMO BASED ON JOIN DATE CLAUSE
                                    if (isset($promo->join_date_clause) && isset($promo->join_date_range)) {

                                        // IF MEMBER JOIN BEFORE CERTAIN DATE
                                        if ($promo->join_date_clause == "before") {

                                            if ($date_join < strtotime($promo->join_date_range)) {

                                                if (isset($promo->h_member)) {
                                                    $current_products['harga'] = $promo->h_member;
                                                }

                                                if (isset($promo->vc)) {
                                                    $current_products['harga_perhitungan_bonus'] = $promo->vc;
                                                }

                                                if (isset($promo->diskon)) {
                                                    $current_products['diskon'] = $promo->diskon;
                                                    $current_products['harga_diskon'] = $promo->harga_diskon;
                                                }

                                                if (isset($promo->free_items)) {
                                                    $current_products['free_items'] = $promo->free_items;
                                                }

                                                if (isset($promo->multiply_applies)) {
                                                    $current_products['multiply_applies'] = $promo->multiply_applies;
                                                }
                                            }
                                        }

                                        // IF MEMBER JOIN BETWEEN TWO DATES
                                        if ($promo->join_date_clause == "between") {

                                            $join_date_range = explode(" - ", $promo->join_date_range);
                                            $join_date_range_start = strtotime($join_date_range[0]);
                                            $join_date_range_end = strtotime($join_date_range[1]);

                                            if ($date_join >= $join_date_range_start && $date_join <= $join_date_range_end) {

                                                if (isset($promo->h_member)) {
                                                    $current_products['harga'] = $promo->h_member;
                                                }

                                                if (isset($promo->vc)) {
                                                    $current_products['harga_perhitungan_bonus'] = $promo->vc;
                                                }

                                                if (isset($promo->diskon)) {
                                                    $current_products['diskon'] = $promo->diskon;
                                                    $current_products['harga_diskon'] = $promo->harga_diskon;
                                                }

                                                if (isset($promo->free_items)) {
                                                    $current_products['free_items'] = $promo->free_items;
                                                }

                                                if (isset($promo->multiply_applies)) {
                                                    $current_products['multiply_applies'] = $promo->multiply_applies;
                                                }
                                            }
                                        }
                                    } else {
                                        if (isset($promo->h_member)) {
                                            $current_products['harga'] = $promo->h_member;
                                        }

                                        if (isset($promo->vc)) {
                                            $current_products['harga_perhitungan_bonus'] = $promo->vc;
                                        }

                                        if (isset($promo->diskon)) {
                                            $current_products['diskon'] = $promo->diskon;
                                            $current_products['harga_diskon'] = $promo->harga_diskon;
                                        }

                                        if (isset($promo->free_items)) {
                                            $current_products['free_items'] = $promo->free_items;
                                        }

                                        if (isset($promo->multiply_applies)) {
                                            $current_products['multiply_applies'] = $promo->multiply_applies;
                                        }
                                    }
                                }
                            } else {
                                if (isset($promo->h_member)) {
                                    $current_products['harga'] = $promo->h_member;
                                }

                                if (isset($promo->vc)) {
                                    $current_products['harga_perhitungan_bonus'] = $promo->vc;
                                }

                                if (isset($promo->diskon)) {
                                    $current_products['diskon'] = $promo->diskon;
                                    $current_products['harga_diskon'] = $promo->harga_diskon;


                                    if (isset($promo->free_items)) {
                                        $current_products['free_items'] = $promo->free_items;
                                    }

                                    if (isset($promo->multiply_applies)) {
                                        $current_products['multiply_applies'] = $promo->multiply_applies;
                                    }
                                }
                            }
                        }
                    }
                }
            }
            return $response->withJson(["status" => "success", "data" => $current_products], 200);
        } else {
            return $response->withJson(["status" => "success", "data" => "0"], 200);
        }
    }

    public function updateItems(Request $request, Response $response, array $args)
    {
        $items = $request->getParsedBody();

        $sql = "UPDATE cn_event_promo_detail 
                SET 
                    h_hpb=:h_hpb, 
                    h_member=:h_member, 
                    h_nomem=:h_nomem, 
                    keterangan=:keterangan 
                WHERE kode_promo=:kode_promo AND kode_barang=:kode_barang";

        $stmt = $this->db->prepare($sql);

        $this->db->beginTransaction();

        try {
            foreach ($items as $item) :
                $data = [
                    ":h_hpb" => $item["h_hpb"],
                    ":h_member" => $item["h_member"],
                    ":h_nomem" => $item["h_nomem"],
                    ":keterangan" => (isset($item["description"]) ? $item["description"] : NULL),
                    ":kode_promo" => $args["promo_code"],
                    ":kode_barang" => $item["product_code"]
                ];

                $stmt->execute($data);
            endforeach;

            $this->db->commit();

            return $response->withJson(["status" => "success", "data" => "1"], 200);
        } catch (Exception $e) {
            $this->db->rollBack();
            return $response->withJson(["status" => "failed", "data" => $e], 200);
        }
    }

    public function deleteItem(Request $request, Response $response, array $args)
    {
        $sql = "DELETE FROM cn_event_promo_detail WHERE kode_promo=:kode_promo AND kode_barang=:kode_barang";
        $stmt = $this->db->prepare($sql);

        $data = [
            ":kode_promo" => $args["promo_code"],
            ":kode_barang" => $args["product_code"]
        ];

        $stmt->execute($data);

        if ($stmt->rowCount() > 0) {
            return $response->withJson(["status" => "success", "data" => "1"], 200);
        }

        return $response->withJson(["status" => "failed", "data" => "0"], 200);
    }

    public function minimumPurchasePromo(Request $request, Response $response, array $args)
    {
        $options = $request->getParsedBody();
        $no_member = $options['no_member'];
        $total_purchase = (int) $options['total_purchase'];

        // CHECK AVAILABLE PROMOS
        $raw_data = json_decode($this->promomanager->getAllPromos());
        $promos = (array) $raw_data->rules;
        $promos_count = count($promos);

        // IF PROMO AVAILABLE
        if ($promos_count > 0) {

            foreach ($promos as $promo) {

                if (isset($promo->minimum_purchase)) {

                    if (!$this->hasTransactionWithBundling($no_member, $promo->period)) {

                        if ($total_purchase >= $promo->minimum_purchase) {

                            $multiples = 1;

                            if ($promo->multiply_applies == true) {
                                $multiples = floor($total_purchase / $promo->minimum_purchase);
                            }

                            $free_items = array();

                            foreach ($promo->free_items as $item) {

                                $free_items[] = $this->getProductDetail($item, true);
                            }

                            $promo_data = array(
                                "multiples" => $multiples,
                                "minimum_purchase" => $promo->minimum_purchase,
                                "free_items" => $free_items
                            );

                            return $response->withJson(["status" => "success", "data" => $promo_data], 200);
                        }
                    }
                }
            }
        }

        return $response->withJson(["status" => "success", "data" => "0"], 200);
    }

    public function discountGetDiscountPromo(Request $request, Response $response, array $args)
    {
        $options = $request->getParsedBody();
        $products = $options['products'];

        // CHECK AVAILABLE PROMOS
        $raw_data = json_decode($this->promomanager->getAllPromos());
        $promos = (array) $raw_data->rules;
        $promos_count = count($promos);

        // IF PROMO AVAILABLE
        if ($promos_count > 0) {

            foreach ($promos as $promo) {

                if (isset($promo->discount_get_discount)) {

                    foreach ($products as $product) {
                        foreach ($promo->applies_for_items as $item_code) {
                            if ($product['product_code'] == $item_code) {

                                $item_detail = $this->getProductDetail($promo->free_items);
                                $discounted_items = array(
                                    "kode_barang" => $item_detail["kode_barang"],
                                    "nama" => $item_detail["nama"],
                                    "harga" => $promo->applied_item_harga,
                                    "diskon" => $promo->applied_item_diskon,
                                    "harga_diskon" => $promo->applied_item_harga_diskon,
                                    "berat" => $item_detail["berat"],
                                    "jenis" => $item_detail["jenis"],
                                    "multiples" => $promo->multiples * $product['qty'],
                                    "pic" => $item_detail["pic"],
                                    "unit" => $item_detail["unit"],
                                );

                                return $response->withJson(["status" => "success", "data" => $discounted_items], 200);
                            }
                        }
                    }
                }
            }
        }

        return $response->withJson(["status" => "success", "data" => "0"], 200);
    }

    public function hasTransactionWithBundling($no_member, $date_period)
    {
        $date_period = explode(" - ", $date_period);
        $date_start = $date_period[0];
        $date_end = $date_period[1];

        $sql = "SELECT * from vw_sales
                WHERE no_member = :no_member
                AND jenis = 'FREE BUNDLE'
                AND tanggal BETWEEN :date_start AND :date_end";

        $stmt = $this->db->prepare($sql);

        $data = [
            ":no_member" => $no_member,
            ":date_start" => $date_start,
            ":date_end" => $date_end
        ];

        $stmt->execute($data);

        if ($stmt->rowCount() > 0) {
            return true;
        }

        return false;
    }
}
