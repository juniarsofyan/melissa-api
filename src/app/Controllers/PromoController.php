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

    

    public function countProducts()
    {
        $sql = "SELECT count(brg.kode_barang) AS rowcount
                FROM cn_barang brg
                WHERE 
                    kode_barang NOT IN (
                        SELECT kode_barang 
                        FROM cn_barang
                        WHERE kode_barang BETWEEN 'SK005' AND 'SK024'
                    )
                    AND brg.h_nomem > 0
                    AND brg.cat = 0";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch()['rowcount'];
        return $result;
    }

    /* public function getProducts($offset = 0, $limit = 48)
    {
        $sql = "SELECT 
                    brg.kode_barang, 
                    brg.nama, 
                    brg.berat, 
                    brg.h_nomem AS harga, 
                    IFNULL(brg.h_nomem - (brg.h_nomem * (brg.diskon / 100)), 0) AS harga_diskon,
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
                    AND brg.h_nomem > 0
                    AND brg.cat = 0
                    LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', (int)$limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll();

        return $result;
    } */

    public function getProductDetail($product_code, $is_free_item = false)
    {
        $sql = "SELECT 
                    brg.kode_barang, 
                    brg.nama, 
                    brg.jenis,
                    brg.unit,
                    brg.berat,
                    brg.h_nomem harga, 
                    brg.h_hpb harga_perhitungan_bonus, 
                    IFNULL(brg.h_nomem - (brg.h_nomem * (brg.diskon / 100)), 0) AS harga_diskon,
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
            $sql .= " AND brg.h_nomem = 0 AND brg.cat = 1";
        } else {
            $sql .= " AND brg.h_nomem > 0 AND brg.cat = 0";
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
        $limit = isset($args["limit"]) ? $args["limit"] : 48;
        $offset = isset($args["offset"]) ? $args["offset"] : 0;
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

            $current_date = strtotime(date('Y-m-d', strtotime('2019-09-01')));
            $current_products = $this->getProducts($offset, $limit);

            $i = 0;
            foreach ($current_products as $key => $value) {

                foreach ($promos as $promo) {

                    if ($promo->promo_type == "REGULAR") {

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

                                                if (isset($promo->h_nomem)) {
                                                    $current_products[$i]['harga'] = $promo->h_nomem;
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

                                                if (isset($promo->h_nomem)) {
                                                    $current_products[$i]['harga'] = $promo->h_nomem;
                                                }

                                                if (isset($promo->vc)) {
                                                    $current_products[$i]['harga_perhitungan_bonus'] = $promo->vc;
                                                }

                                                if (isset($promo->diskon)) {
                                                    $current_products[$i]['diskon'] = $promo->diskon;
                                                }

                                                if (isset($promo->harga_diskon)) {
                                                    $current_products[$i]['harga_diskon'] = $promo->harga_diskon;
                                                }

                                                if (isset($promo->free_items)) {
                                                    $current_products[$i]['free_items'] = $promo->free_items;
                                                }

                                                if (isset($promo->multiply_applies)) {
                                                    $current_products[$i]['multiply_applies'] = $promo->multiply_applies;
                                                }

                                                if (isset($promo->promo_caption)) {
                                                    $current_products[$i]['promo_caption'] = $promo->promo_caption;
                                                }

                                                if (isset($promo->promo_tag)) {
                                                    $current_products[$i]['promo_tag'] = $promo->promo_tag;
                                                }
                                            }
                                        }
                                    } else {
                                        if (isset($promo->h_nomem)) {
                                            $current_products[$i]['harga'] = $promo->h_nomem;
                                        }

                                        if (isset($promo->vc)) {
                                            $current_products[$i]['harga_perhitungan_bonus'] = $promo->vc;
                                        }

                                        if (isset($promo->diskon)) {
                                            $current_products[$i]['diskon'] = $promo->diskon;
                                        }

                                        if (isset($promo->harga_diskon)) {
                                            $current_products[$i]['harga_diskon'] = $promo->harga_diskon;
                                        }

                                        if (isset($promo->free_items)) {
                                            $current_products[$i]['free_items'] = $promo->free_items;
                                        }

                                        if (isset($promo->multiply_applies)) {
                                            $current_products[$i]['multiply_applies'] = $promo->multiply_applies;
                                        }

                                        if (isset($promo->promo_caption)) {
                                            $current_products[$i]['promo_caption'] = $promo->promo_caption;
                                        }

                                        if (isset($promo->promo_tag)) {
                                            $current_products[$i]['promo_tag'] = $promo->promo_tag;
                                        }
                                    }
                                }
                            } else {
                                if (isset($promo->h_nomem)) {
                                    $current_products[$i]['harga'] = $promo->h_nomem;
                                }

                                if (isset($promo->vc)) {
                                    $current_products[$i]['harga_perhitungan_bonus'] = $promo->vc;
                                }

                                if (isset($promo->diskon)) {
                                    $current_products[$i]['diskon'] = $promo->diskon;

                                    if (isset($promo->free_items)) {
                                        $current_products[$i]['free_items'] = $promo->free_items;
                                    }

                                    if (isset($promo->multiply_applies)) {
                                        $current_products[$i]['multiply_applies'] = $promo->multiply_applies;
                                    }
                                }

                                if (isset($promo->harga_diskon)) {
                                    $current_products[$i]['harga_diskon'] = $promo->harga_diskon;
                                }
                            }
                        }
                    }
                }
                $i++;
            }
            return $response->withJson(["status" => "success", "count" => $this->countProducts(),"data" => $current_products], 200);
        } else {
            return $response->withJson(["status" => "success", "data" => "0"], 200);
        }
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

            $current_date = strtotime(date('Y-m-d', strtotime('2019-09-01')));
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

                                                if (isset($promo->h_nomem)) {
                                                    $current_products['harga'] = $promo->h_nomem;
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

                                                if (isset($promo->h_nomem)) {
                                                    $current_products['harga'] = $promo->h_nomem;
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
                                        if (isset($promo->h_nomem)) {
                                            $current_products['harga'] = $promo->h_nomem;
                                        }

                                        if (isset($promo->vc)) {
                                            $current_products['harga_perhitungan_bonus'] = $promo->vc;
                                        }

                                        if (isset($promo->diskon)) {
                                            $current_products['diskon'] = $promo->diskon;
                                        }

                                        if (isset($promo->harga_diskon)) {
                                            $current_products['harga_diskon'] = $promo->harga_diskon;
                                        }

                                        if (isset($promo->free_items)) {
                                            $current_products['free_items'] = $promo->free_items;
                                        }

                                        if (isset($promo->multiply_applies)) {
                                            $current_products['multiply_applies'] = $promo->multiply_applies;
                                        }

                                        if (isset($promo->promo_caption)) {
                                            $current_products['promo_caption'] = $promo->promo_caption;
                                        }

                                        if (isset($promo->promo_tag)) {
                                            $current_products['promo_tag'] = $promo->promo_tag;
                                        }
                                    }
                                }
                            } else {
                                if (isset($promo->h_nomem)) {
                                    $current_products['harga'] = $promo->h_nomem;
                                }

                                if (isset($promo->vc)) {
                                    $current_products['harga_perhitungan_bonus'] = $promo->vc;
                                }

                                if (isset($promo->diskon)) {
                                    $current_products['diskon'] = $promo->diskon;

                                    if (isset($promo->free_items)) {
                                        $current_products['free_items'] = $promo->free_items;
                                    }

                                    if (isset($promo->multiply_applies)) {
                                        $current_products['multiply_applies'] = $promo->multiply_applies;
                                    }
                                }

                                if (isset($promo->harga_diskon)) {
                                    $current_products['harga_diskon'] = $promo->harga_diskon;
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

    public function firstTransactionPromo(Request $request, Response $response, array $args)
    {
        // CHECK AVAILABLE PROMOS
        $raw_data = json_decode($this->promomanager->getAllPromos());
        $promos = (array) $raw_data->rules;
        $promos_count = count($promos);
        $data = "0";

        // IF PROMO AVAILABLE
        if ($promos_count > 0) {

            $current_date = strtotime(date('Y-m-d', strtotime('2019-09-01')));

            foreach ($promos as $promo) {

                if ($promo->promo_type == "DISCOUNT-ON-PURCHASE") {

                    // IF PROMO BASED ON PERIOD
                    if (isset($promo->period)) {

                        $date_period = explode(" - ", $promo->period);
                        $date_period_start = strtotime($date_period[0]);
                        $date_period_end = strtotime($date_period[1]);

                        // IF PROMO PERIOD HAS NOT EXPIRED
                        if ($current_date >= $date_period_start && $current_date <= $date_period_end) {

                            if (!$this->hasTransactionAlready($args['no_member'])) {
                                // if ($this->hasTransactionAlready($args['no_member'])) {
                                $data = array(
                                    "minimum_purchase" => $promo->minimum_purchase,
                                    "discount_amount" => $promo->discount_amount
                                );
                            }
                        }
                    }
                }
            }
        }

        return $response->withJson(["status" => "success", "data" => $data], 200);
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

    public function buyGetDiscount(Request $request, Response $response)
    {
        $options = $request->getParsedBody();

        // CONTAINS LIST OF PRODUCT CODES AND QTY
        $products = $options['products'];
        $date_join = strtotime($options['date_join']);

        // CHECK AVAILABLE PROMOS
        $raw_data = json_decode($this->promomanager->getAllPromos());
        $promos = (array) $raw_data->rules;
        $promos_count = count($promos);
        $data = "0";

        // IF PROMO AVAILABLE
        if ($promos_count > 0) {

            $current_date = strtotime(date('Y-m-d', strtotime('2019-09-01')));

            foreach ($promos as $promo) {

                // IF THERE IS A BUY PRODUCT GET DISCOUNT PROMO
                if ($promo->promo_type == "BUY-GET-DISCOUNT") {

                    // IF PROMO BASED ON PERIOD
                    if (isset($promo->period)) {

                        $date_period = explode(" - ", $promo->period);
                        $date_period_start = strtotime($date_period[0]);
                        $date_period_end = strtotime($date_period[1]);

                        // IF PROMO PERIOD HAS NOT EXPIRED
                        if ($current_date >= $date_period_start && $current_date <= $date_period_end) {

                            // IF THIS PROMO ONLY FOR SERIES
                            if ($promo->kode_barang == "SERIES") {

                                $multiples = 0;

                                // LOOP AND CHECK IF PRODUCT IS SERIES, 
                                // IF YES, SUM THE QUANTITY AS MULTIPLES
                                foreach ($products as $product) {
                                    if ($product['product_code'] != "90099") {
                                        $find = $this->getProducts("", $product['product_code']);

                                        if ($find[0]['unit'] == "SERIES") {
                                            $multiples += $product['qty'];
                                        }
                                    }
                                }

                                // IF MULTIPLES IS NOT 0, MEANS IF CUSTOMER BUYS SERIES PRODUCT
                                // THEN RETURN THE MULTIPLES BASED ON THE SUM OF SERIES QTY
                                // AND ALSO RETURN THE PROMOTED PRODUCT
                                if ($multiples > 0) {
                                    $data = array(
                                        "multiples" => $multiples,
                                        "promoted_item" => $promo->promoted_item
                                    );
                                }
                            }
                            // IF THIS PROMO ONLY FOR SERIES
                            elseif (is_array($promo->kode_barang)) {

                                $multiples = 0;

                                // LOOP AND CHECK IF PRODUCT IS NOT 90099 (ITEM DISCOUNT 29K), 
                                // IF YES, SUM THE QUANTITY AS MULTIPLES
                                foreach ($products as $product) {
                                    if ($product['product_code'] != "90099") {
                                        foreach ($promo->kode_barang as $item) {
                                            if ($product['product_code'] === $item) {
                                                $multiples += $product['qty'] * 1;
                                            }
                                        }
                                    }
                                }

                                // IF MULTIPLES IS NOT 0, MEANS IF CUSTOMER BUYS SERIES PRODUCT
                                // THEN RETURN THE MULTIPLES BASED ON THE SUM OF SERIES QTY
                                // AND ALSO RETURN THE PROMOTED PRODUCT
                                if ($multiples > 0) {

                                    $data = array("multiples" => $multiples);

                                    foreach ($promo->promoted_item as $item) {
                                        if (isset($item->join_date_clause)) {
                                            if ($item->join_date_clause == "before") {
                                                if ($date_join < strtotime($item->join_date_range)) {
                                                    $data["promoted_item"] = $item->items;
                                                    break;
                                                }
                                            } elseif ($item->join_date_clause == "between") {

                                                $join_date_range = explode(" - ", $item->join_date_range);
                                                $join_date_range_start = strtotime($join_date_range[0]);
                                                $join_date_range_end = strtotime($join_date_range[1]);

                                                if ($date_join >= $join_date_range_start && $date_join <= $join_date_range_end) {
                                                    $data["promoted_item"] = $item->items;
                                                    break;
                                                }
                                            }
                                        } else {
                                            $data["promoted_item"][] = $item;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return $response->withJson(["status" => "success", "data" => $data], 200);
    }

    public function hasTransactionAlready($no_member)
    {
        $sql = "SELECT no_member from vw_transaksi
                WHERE no_member = :no_member
                AND tanggal BETWEEN '2019-08-01' AND '2019-08-15'
                AND kode_barang = '90099'
                LIMIT 1";

        $stmt = $this->db->prepare($sql);

        $data = [":no_member" => $no_member];

        $stmt->execute($data);

        if ($stmt->rowCount() > 0) {
            return true;
        }

        return false;
    }

    public function buyGetDiscountSAF(Request $request, Response $response)
    {
        $options = $request->getParsedBody();

        // CONTAINS LIST OF PRODUCT CODES AND QTY
        $products = $options['products'];

        // CHECK AVAILABLE PROMOS
        $raw_data = json_decode($this->promomanager->getAllPromos());
        $promos = (array) $raw_data->rules;
        $promos_count = count($promos);
        $data = "0";

        // IF PROMO AVAILABLE
        if ($promos_count > 0) {

            $current_date = strtotime(date('Y-m-d', strtotime('2019-09-01')));

            foreach ($promos as $promo) {

                // IF THERE IS A BUY PRODUCT GET DISCOUNT PROMO
                if ($promo->promo_type == "SAF-BUY-GET-DISCOUNT") {

                    // IF PROMO BASED ON PERIOD
                    if (isset($promo->period)) {

                        $date_period = explode(" - ", $promo->period);
                        $date_period_start = strtotime($date_period[0]);
                        $date_period_end = strtotime($date_period[1]);

                        // IF PROMO PERIOD HAS NOT EXPIRED
                        if ($current_date >= $date_period_start && $current_date <= $date_period_end) {

                            // IF THIS PROMO ONLY FOR SERIES
                            if (is_array($promo->kode_barang)) {

                                $multiples = 0;
                                $total_qty = 0;

                                // LOOP AND CHECK IF PRODUCT IS SERIES, 
                                // IF YES, SUM THE QUANTITY AS MULTIPLES
                                foreach ($products as $product) {
                                    if ($product['product_code'] != "90099") {
                                        if (in_array($product['product_code'], $promo->kode_barang)) {
                                            $total_qty += $product['qty'];
                                        }
                                    }
                                }

                                // OLD RULES
                                // if ($total_qty >= 2 && $total_qty < 4) {
                                //     $multiples = 5;
                                // } elseif ($total_qty >= 4 && $total_qty <= 5) {
                                //     $multiples = 10;
                                // } elseif ($total_qty >= 6) {
                                //     $multiples = 15;
                                // } else {
                                //     $multiples = 0;
                                // }

                                $multiples = $total_qty * 5;

                                // IF MULTIPLES IS NOT 0, MEANS IF CUSTOMER BUYS SERIES PRODUCT
                                // THEN RETURN THE MULTIPLES BASED ON THE SUM OF SERIES QTY
                                // AND ALSO RETURN THE PROMOTED PRODUCT
                                if ($multiples > 0) {
                                    $data = array(
                                        "multiples" => $multiples,
                                        "promoted_item" => $promo->promoted_item
                                    );
                                }
                            }
                        }
                    }
                }
            }
        }

        return $response->withJson(["status" => "success", "data" => $data], 200);
    }

    public function newRecruitPromo(Request $request, Response $response, array $args)
    {
        // CHECK AVAILABLE PROMOS
        $raw_data = json_decode($this->promomanager->getAllPromos());
        $promos = (array) $raw_data->rules;
        $promos_count = count($promos);
        $data = "0";

        // IF PROMO AVAILABLE
        if ($promos_count > 0) {

            $current_date = strtotime(date('Y-m-d', strtotime('2019-09-01')));

            foreach ($promos as $promo) {

                if ($promo->promo_type == "NEW-RECRUIT-PROMO") {

                    // IF PROMO BASED ON PERIOD
                    if (isset($promo->period)) {

                        $date_period = explode(" - ", $promo->period);
                        $date_period_start = strtotime($date_period[0]);
                        $date_period_end = strtotime($date_period[1]);

                        // IF PROMO PERIOD HAS NOT EXPIRED
                        if ($current_date >= $date_period_start && $current_date <= $date_period_end) {

                            if (!$this->isFirstTransaction($args['no_member'])) {
                                // if ($this->hasTransactionAlready($args['no_member'])) {
                                $data = array(
                                    "minimum_purchase" => $promo->minimum_purchase,
                                    "discount_amount" => $promo->discount_amount
                                );
                            }
                        }
                    }
                }
            }
        }

        return $response->withJson(["status" => "success", "data" => $data], 200);
    }

    public function isFirstTransaction($no_member)
    {
        $sql = "SELECT kode_barang
                FROM cn_transaksi_detail a
                INNER JOIN cn_transaksi b ON a.transaksi_id = b.id
                WHERE b.customer_id IN (
                    SELECT no_member
                    FROM tb_member
                    WHERE no_member= :no_member
                    AND tanggal BETWEEN '2013-08-16'AND '2019-08-31'
                )
                AND a.kode_barang='90099'";

        $stmt = $this->db->prepare($sql);

        $data = [":no_member" => $no_member];

        $stmt->execute($data);

        if ($stmt->rowCount() > 0) {
            return true;
        }

        return false;
    }

    public function buySeriesGetDiscountRupiah(Request $request, Response $response, array $args)
    {
        $options = $request->getParsedBody();
        $products = $options['products'];
        $no_member = $args['no_member'];


        echo "<pre>";
        print_r($products);
        echo "</pre>";
        exit();

        try {
            // CHECK AVAILABLE PROMOS
            $raw_data = json_decode($this->promomanager->getAllPromos());
            $promos = (array) $raw_data->rules;
            $promos_count = count($promos);
            $promoted_product_codes = [];

            $specific_series_item = false;

            // IF PROMO AVAILABLE
            if ($promos_count > 0) {

                $current_date = strtotime(date('Y-m-d', strtotime('2019-09-16')));

                foreach ($promos as $promo) {

                    if ($promo->promo_type == "BUY-SERIES-GET-DISCOUNT") {

                        // IF PROMO BASED ON PERIOD
                        if (isset($promo->period)) {

                            $date_period = explode(" - ", $promo->period);
                            $date_period_start = strtotime($date_period[0]);
                            $date_period_end = strtotime($date_period[1]);

                            // IF PROMO PERIOD HAS NOT EXPIRED
                            if ($current_date >= $date_period_start && $current_date <= $date_period_end) {

                                echo "current: " . date('Y-m-d', $current_date) . "<br/>";
                                echo "start: " . date('Y-m-d', $date_period_start) . "<br/>";
                                echo "end: " . date('Y-m-d', $date_period_end) . "<br/>";
                                // exit();

                                foreach ($products as $product) {
                                    foreach ($promo->kode_barang as $item) {


                                        // if ($product['kode_barang'] == $item["kode_barang"]) {
                                        // if (1 == 1) {
                                        //     $specific_series_item = true;
                                        //     break;
                                        // }
                                    }
                                }
                                // break;
                            }
                        }
                    }
                }
            }


            echo "<pre>";
            var_dump($specific_series_item);
            echo "</pre>";
            exit();

            if (count($promoted_product_codes) > 0) {

                $promoted_product_codes = implode(", ", $promoted_product_codes);

                $sql = "SELECT b.kode_barang
                    FROM cn_transaksi a
                    INNER JOIN cn_transaksi_detail b
                        ON b.transaksi_id = a.id
                    WHERE b.kode_barang IN (:promoted_product_codes)
                    AND a.note = 'BUY SERIES GET DISCOUNT PRICE'
                    AND a.customer_id = :no_member";

                $stmt = $this->db->prepare($sql);

                $data = [
                    ":promoted_product_codes" => $promoted_product_codes,
                    ":no_member" => $no_member
                ];

                $stmt->execute($data);

                if ($stmt->rowCount() > 0) {
                    return $response->withJson(["status" => "success", "data" => "true"], 200);
                }

                return $response->withJson(["status" => "failed", "data" => "0"], 200);
            }
        } catch (Exception $e) {
            return $response->withJson(["status" => "failed", "data" => "0"], 200);
        }
    }
}
