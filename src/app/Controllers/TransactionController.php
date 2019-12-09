<?php

namespace App\Controllers;

use Slim\Http\Request;
use Slim\Http\Response;

class TransactionController
{
    protected $db;
    protected $ongkir;
    protected $renderer;
    protected $mailer;
    protected $environment;

    public function __construct($db, $ongkir, $renderer, $mailer, $environment)
    {
        $this->db = $db;
        $this->ongkir = $ongkir;
        $this->renderer = $renderer;
        $this->mailer = $mailer;
        $this->environment = $environment;
    }

    public function index(Request $request, Response $response)
    {
        $customer = $request->getParsedBody();
        return $response->withJson(["status" => "success", "data" => $customer], 200);
    }

    public function add(Request $request, Response $response)
    {
        $transaction = $request->getParsedBody();
        $transaction = $transaction['transaction'];

        $transaction_master = $this->addTransaction($transaction);

        if ($transaction_master) {

            $transaction_detail = $this->addTransactionDetail($transaction_master, $transaction['cart']);

            if ($transaction_detail) {

                $order_history = $this->addOrderHistory($transaction_master, "PLACE ORDER");

                if ($order_history) {

                    $substract_sales_branch_qty = $this->substractSalesBranchQty($transaction['cart'], $transaction['sales_branch_code']);

                    if ($substract_sales_branch_qty) {

                        $branch = $this->findBranch($transaction['sales_branch_code']);
                        $profile = $this->findProfile($transaction['member_id']);
                        $shipping_address = $this->findShippingAddress($transaction['member_id']);
                        $items = $this->findItems($transaction['transaction_number'], $transaction['cart']);
                        $bank = $this->findBank($transaction['bank']);

                        $data = array(
                            "email" => array(
                                "template" => "order-confirmed.php",
                                "subject" => "Transaction Activity",
                                "recipient" => $profile['email']
                            ),
                            "params" => array (
                                "app_url" => $this->environment['app_url'],
                                "name" => $profile['nama'],
                                "transaction_date" => $this->getLocalDateFormat(date('Y-m-d'), true),
                                "transaction_number" => $transaction['transaction_number'],
                                "bank" => $bank,
                                "items" => $items,
                                "branch" => $branch,
                                "user" => array (
                                    "name" => $profile['nama'],
                                    "phone" => $profile['telepon']
                                ),
                                "receiver" => array(
                                    "name" => $shipping_address['name'],
                                    "phone" => $shipping_address['phone'],
                                    "address" => $shipping_address['address']
                                ),
                                "expedition" => array(
                                    "courier" => $transaction['courier'],
                                    "type" => "",
                                    "fee" => $transaction['shipping_fee']
                                ),
                                "grand_total" => $transaction['grand_total']
                            )
                        );

                        $this->sendEmail($request, $response, $data);

                        return $response->withJson(["status" => "success", "data" => 1], 200);
                    }
                }
            }
        }

        return $response->withJson(["status" => "failed", "data" => "0"], 200);
    }

    public function addTransaction($transaction)
    {
        $sql = "INSERT INTO cn_transaksi (
                            tgl_transaksi,
                            nomor_transaksi,
                            member_id,
                            customer_id,
                            nama,
                            metode_pengiriman,
                            kurir,
                            shipping_address_id,
                            subtotal,
                            shipping_fee,
                            grand_total,
                            total_berat,
                            note,
                            kode_spb,
                            jenis_platform,
                            bank,
                            status_transaksi
                        ) VALUE (
                            :tgl_transaksi,
                            :nomor_transaksi,
                            :member_id,
                            :customer_id,
                            :nama,
                            :metode_pengiriman,
                            :kurir,
                            :shipping_address_id,
                            :subtotal,
                            :shipping_fee,
                            :grand_total,
                            :total_berat,
                            :note,
                            :kode_spb,
                            :jenis_platform,
                            :bank,
                            :status_transaksi
                        )";

        $stmt = $this->db->prepare($sql);

        $data = [
            ":tgl_transaksi"       => date('Y-m-d'),
            ":nomor_transaksi"     => $transaction["transaction_number"],
            ":member_id"           => $transaction["member_id"],
            ":customer_id"         => $transaction["customer_id"],
            ":nama"                => $transaction["customer_name"],
            ":metode_pengiriman"   => $transaction["shipping_method"],
            ":kurir"               => $transaction["courier"],
            ":shipping_address_id" => (int) $transaction["shipping_address_id"],
            ":subtotal"            => (int) $transaction["subtotal"],
            ":shipping_fee"        => (int) $transaction["shipping_fee"],
            ":grand_total"         => (int) $transaction["grand_total"],
            ":total_berat"         => $transaction["total_weight"],
            ":note"                => $transaction["note"] ? $transaction["note"] : "",
            ":kode_spb"            => $transaction["sales_branch_code"] ? $transaction["sales_branch_code"] : "",
            ":jenis_platform"      => $transaction["shopping_platform"],
            ":bank"                => $transaction["bank"],
            ":status_transaksi"    => "PLACE ORDER"
        ];

        if ($stmt->execute($data)) {
            return $this->db->lastInsertId();
        }

        return false;
    }

    public function addOrderHistory($transaction_id, $description)
    {
        $sql = "INSERT INTO cn_order_history (
                    transaksi_id,
                    tanggal,
                    keterangan
                ) VALUE (
                    :transaksi_id,
                    :tanggal,
                    :keterangan
                )";

        $stmt = $this->db->prepare($sql);

        $data = [
            ":transaksi_id" => (int) $transaction_id,
            ":tanggal" => date('Y-m-d H:i:s'),
            ":keterangan" => $description,
        ];

        $stmt->execute($data);

        return true;
    }

    public function addTransactionDetail($transaction_id, $cart)
    {
        $sql = "INSERT INTO cn_transaksi_detail (
                    transaksi_id,
                    kode_barang,
                    harga,
                    qty,
                    subtotal,
                    total_vc,
                    note,
                    promo
                ) VALUE (
                    :transaksi_id,
                    :kode_barang,
                    :harga,
                    :qty,
                    :subtotal,
                    :total_vc,
                    :note,
                    :promo
                )";

        $stmt = $this->db->prepare($sql);

        foreach ($cart as $item) {
            $data = [
                ":transaksi_id" => (int) $transaction_id,
                ":kode_barang"  => $item["product_code"],
                // ":harga"        => (int) $item["price"],
                ":harga"        => ((int) $item["price_discount"] < (int) $item["price"]) ? (int) $item["price_discount"] : (int) $item["price"],
                ":qty"          => (int) $item["qty"],
                ":subtotal"     => (int) $item["grand_total"], // GRAND TOTAL AFTER DISCOUNTED
                ":total_vc"     => (isset($item["price_bonus_calculation"]) ? (int) $item["price_bonus_calculation"] * (int) $item["qty"] : 0),
                ":note"         => (isset($item["note"]) ? $item["note"] : ""),
                ":promo"         => (isset($item["promo"]) ? $item["promo"] : 0),
            ];

            $stmt->execute($data);
        }

        return true;
    }

    public function ongkir(Request $request, Response $response)
    {
        $ongkir = $this->ongkir;
        $a = $ongkir->getAllProvinces();

        return $response->withJson(["status" => "success", "data" => $a], 200);
    }

    public function history(Request $request, Response $response, array $args)
    {
        $customer = $request->getParsedBody();

        try {
            $this->transactions = [];
            $this->order_progress = [];
            $this->order_items = [];

            $sql_transactions = "SELECT
                                    trs.id as transaksi_id,
                                    trs.nomor_transaksi,
                                    sha.nama,
                                    sha.provinsi_nama,
                                    sha.kota_nama,
                                    sha.kota_nama,
                                    sha.kecamatan_nama,
                                    sha.alamat,
                                    sha.kode_pos,
                                    sha.telepon,
                                    trs.kode_spb,
                                    trs.status_transaksi,
                                    trs.tgl_transaksi,
                                    trs.grand_total,
                                    SUBSTRING(trs.grand_total, -3) as kode_unik_transfer,
                                    trs.resi
                                FROM 
                                    cn_transaksi trs
                                INNER JOIN cn_shipping_address_member sha
                                    ON trs.shipping_address_id = sha.id
                                INNER JOIN tb_member tbm
                                    ON tbm.no_member = trs.member_id
                                INNER JOIN cn_order_history odh
                                    ON odh.transaksi_id = trs.id
                                WHERE tbm.email=:email AND trs.jenis_platform = 'MSHOP'
                                GROUP by (transaksi_id)
                                ORDER BY trs.tgl_transaksi DESC, trs.id DESC";

            $stmt = $this->db->prepare($sql_transactions);
            $data = [":email" => $customer["email"]];
            $stmt->execute($data);
            $this->transactions = $stmt->fetchAll();

            if ($stmt->rowCount()) {

                $sql_order_progress = "SELECT
                                        odh.transaksi_id,
                                        odh.id,
                                        DATE_FORMAT (odh.tanggal, '%d-%m-%Y') as tanggal,
                                        odh.keterangan
                                    FROM 
                                        cn_order_history odh
                                    INNER JOIN cn_transaksi trs
                                        ON trs.id = odh.transaksi_id
                                    INNER JOIN tb_member tbm
                                        ON tbm.no_member = trs.member_id
                                    WHERE 
                                        tbm.email=:email
                                        AND
                                        odh.transaksi_id = trs.id 
                                        AND 
                                        trs.jenis_platform = 'MSHOP'
                                    ORDER BY 
                                        trs.tgl_transaksi DESC, 
                                        odh.transaksi_id DESC, 
                                        odh.id ASC";

                $stmt = $this->db->prepare($sql_order_progress);
                $data = [":email" => $customer["email"]];
                $stmt->execute($data);
                $this->order_progress = $stmt->fetchAll();

                if ($stmt->rowCount()) {

                    $sql_items = "SELECT
                                trd.transaksi_id,
                                brg.kode_barang,
                                brg.nama,
                                brg.pic,
                                trd.qty
                            FROM cn_transaksi_detail trd
                            INNER JOIN cn_transaksi trs
                                ON trs.id = trd.transaksi_id
                            INNER JOIN cn_barang brg
                                ON brg.kode_barang = trd.kode_barang
                            INNER JOIN tb_member tbm
                                ON tbm.no_member = trs.member_id
                            WHERE 
                                tbm.email=:email
                                AND trd.transaksi_id in (SELECT transaksi_id FROM cn_order_history) 
                                AND trs.jenis_platform = 'MSHOP'  
                                order by trs.id";

                    $stmt = $this->db->prepare($sql_items);
                    $data = [":email" => $customer["email"]];
                    $stmt->execute($data);
                    $this->order_items = $stmt->fetchAll();
                }
            }

            $data = array();

            foreach ($this->transactions as $transaction) {
                $row = array();
                $row['transaction'] = $transaction;
                $row['progresses'] = array();
                $row['items'] = array();
                
                foreach ($this->order_progress as $progress) {
                    if ($progress['transaksi_id'] == $transaction['transaksi_id']) {
                        $row['progresses'][] = $progress;
                    }
                }

                foreach ($this->order_items as $order_item) {
                    if ($order_item['transaksi_id'] == $transaction['transaksi_id']) {
                        $row['items'][] = $order_item;
                    }
                }
                $data[] = $row;
            }

            return $response->withJson(["status" => "success", "data" => $data], 200);
        } catch (Exception $e) {
            return $response->withJson(["status" => "failed", "data" => "0"], 200);
        }
    }

    public function setPaymentConfirmed(Request $request, Response $response, array $args)
    {
        $sql = "UPDATE cn_transaksi 
                SET status_transaksi = 'TRANSFERRED'
                WHERE id = :transaction_id";

        $stmt = $this->db->prepare($sql);

        $params = [
            // ":status" => isset($args['status']) ? $args['status'] : "PAYMENT CONFIRMED",
            ":transaction_id" => $args['transaction_id']
        ];

        if ($stmt->execute($params)) {

            if ($this->addOrderHistory($args['transaction_id'], "TRANSFERRED")) {

                $transaction_number = $this->findTransactionNumber($args['transaction_id']);
                $customer = $this->findCustomer($args['transaction_id']);
                $date = $this->getLocalDateFormat(date('Y-m-d'), true);
                $grand_total = $this->findGrandTotal($args['transaction_id']);

                $data = array(
                    "email" => array(
                        "template" => "payment-confirmed.php",
                        "subject" => "Transaction Activity",
                        "recipient" => $customer['email']
                    ),
                    "params" => array (
                        "app_url" => $this->environment['app_url'],
                        "name" => ucwords(strtolower($customer['nama'])),
                        "confirm_payment_date" => $date,
                        "transaction_number" => $transaction_number,
                        "grand_total" => $grand_total
                    )
                );

                $this->sendEmail($request, $response, $data);

                return $response->withJson(["status" => "success", "data" => "1"], 200);
            }
        }

        return $response->withJson(["status" => "failed", "data" => "0"], 200);
    }

    public function setItemReceived(Request $request, Response $response, array $args)
    {
        $sql = "UPDATE cn_transaksi 
                SET status_transaksi = 'RECEIVED'
                WHERE id = :transaction_id";

        $stmt = $this->db->prepare($sql);

        $params = [
            // ":status" => isset($args['status']) ? $args['status'] : "PAYMENT CONFIRMED",
            ":transaction_id" => $args['transaction_id']
        ];

        if ($stmt->execute($params)) {

            if ($this->addOrderHistory($args['transaction_id'], "RECEIVED")) {
                return $response->withJson(["status" => "success", "data" => "1"], 200);
            }
        }

        return $response->withJson(["status" => "failed", "data" => "0"], 200);
    }

    public function checkSalesBranchesStock(Request $request, Response $response)
    {
        $products = $request->getParsedBody();
        $products = $products['products'];

        $sales_branch_codes = array(
            array("code" => "00000", "disabled" => false),
            array("code" => "00217", "disabled" => false),
            array("code" => "00553", "disabled" => false),
            array("code" => "00539", "disabled" => false),
            array("code" => "00042", "disabled" => false),
            array("code" => "00005", "disabled" => false),
            array("code" => "01340", "disabled" => false),
            array("code" => "01838", "disabled" => false),
            array("code" => "15666", "disabled" => false),
            array("code" => "15658", "disabled" => false),
            array("code" => "15641", "disabled" => false),
            array("code" => "13722", "disabled" => false),
            array("code" => "02006", "disabled" => false)
        );

        $sql_ho = "SELECT '00000' as no_member
                    FROM tb_barang
                    WHERE 
                        kode_barang =:product_code 
                        AND 
                        stok >= :qty";

        $sql_spb = "SELECT no_member
                    FROM tb_produk
                    WHERE 
                        no_member =:branch_code
                        AND
                        kode_barang =:product_code 
                        AND 
                        stok >= :qty";

        foreach ($sales_branch_codes as $key => $branch) {

            if ($branch['code'] == '00000') {

                $stmt = $this->db->prepare($sql_ho);

                foreach ($products as $product) {
                    if ($product['unit'] == "SERIES") {
                        $series_available = $this->checkSeriesProductAvailability($branch['code'], $product['product_code'], $product['qty']);
                        if (!$series_available) {
                            $sales_branch_codes[$key]['disabled'] = true;
                            break;
                        }
                    } else {
                        $data = [
                            ":product_code" => $product['product_code'],
                            ":qty" => $product['qty']
                        ];

                        $stmt->execute($data);

                        if (!$stmt->rowCount()) {
                            $sales_branch_codes[$key]['disabled'] = true;
                            break;
                        }
                    }
                }
            } else {
                $stmt = $this->db->prepare($sql_spb);

                foreach ($products as $product) {
                    if ($product['unit'] == "SERIES") {
                        $series_available = $this->checkSeriesProductAvailability($branch['code'], $product['product_code'], $product['qty']);
                        if (!$series_available) {
                            $sales_branch_codes[$key]['disabled'] = true;
                            break;
                        }
                    } else {
                        $data = [
                            ":branch_code" => $branch['code'],
                            ":product_code" => $product['product_code'],
                            ":qty" => $product['qty']
                        ];

                        $stmt->execute($data);

                        if (!$stmt->rowCount()) {
                            $sales_branch_codes[$key]['disabled'] = true;
                            break;
                        }
                    }
                }
            }
        }

        return $response->withJson(["status" => "success", "data" => $sales_branch_codes], 200);
    }

    function substractSalesBranchQty($cart_items, $sales_branch_code)
    {
        $sql_ho = "UPDATE tb_barang 
                    SET stok = stok - :qty
                    WHERE kode_barang = :product_code";

        $sql_spb = "UPDATE tb_produk 
                SET stok = stok - :qty
                WHERE 
                no_member =:branch_code
                AND
                kode_barang = :product_code";

        foreach ($cart_items as $item) {

            if ($sales_branch_code == '00000') {

                $stmt = $this->db->prepare($sql_ho);

                if ($item['unit'] == "SERIES") {
                    $products = $this->getSeriesProducts($item['product_code']);

                    foreach ($products as $product) {

                        $data = [
                            ":qty"          => $item['qty'] * $product['jumlah'],
                            ":product_code" => $product['kode_barang']
                        ];

                        $stmt->execute($data);
                    }
                } else {
                    $data = [
                        ":qty"          => $item['qty'],
                        ":product_code" => $item['product_code']
                    ];

                    $stmt->execute($data);
                }
            } else {
                $stmt = $this->db->prepare($sql_spb);

                if ($item['unit'] == "SERIES") {
                    $products = $this->getSeriesProducts($item['product_code']);

                    foreach ($products as $product) {

                        $data = [
                            ":qty"          => $item['qty'] * $product['jumlah'],
                            ":branch_code" => $sales_branch_code,
                            ":product_code" => $product['kode_barang']
                        ];

                        $stmt->execute($data);
                    }
                } else {

                    $data = [
                        ":qty"          => $item['qty'],
                        ":branch_code" => $sales_branch_code,
                        ":product_code" => $item['product_code']
                    ];

                    $stmt->execute($data);
                }
            }
        }

        return true;
    }

    function getSeriesProducts($series_code)
    {
        $sql = "SELECT 
                    kode_barang,
                    jumlah
                FROM 
                    tb_det_pack
                WHERE 
                    kode_pack =:kode_pack";

        $stmt = $this->db->prepare($sql);

        $data = [":kode_pack" => $series_code];

        $stmt->execute($data);

        if ($stmt->rowCount()) {
            return $stmt->fetchAll();
        }

        return false;
    }

    // CHECK DETAIL SERIES ITEM QTY
    function checkSeriesProductAvailability($branch_code, $series_code, $qty)
    {
        $products = $this->getSeriesProducts($series_code);

        $sql_ho = "SELECT kode_barang
                    FROM tb_barang
                    WHERE kode_barang = :product_code AND stok >= :qty";

        $sql_spb = "SELECT kode_barang 
                    FROM tb_produk 
                    WHERE 
                        no_member = :branch_code 
                        AND kode_barang= :product_code 
                        AND stok  >= :qty";

        foreach ($products as $product) {

            if ($branch_code == '00000') {

                $stmt = $this->db->prepare($sql_ho);

                $data = [
                    ":product_code" => $product['kode_barang'],
                    ":qty" => $qty * $product['jumlah']
                ];

                $stmt->execute($data);

                if (!$stmt->rowCount()) {
                    return false;
                }
            } else {
                $stmt = $this->db->prepare($sql_spb);

                $data = [
                    ":branch_code" => $branch_code,
                    ":product_code" => $product['kode_barang'],
                    ":qty" => $qty * $product['jumlah']
                ];

                $stmt->execute($data);

                if (!$stmt->rowCount()) {
                    return false;
                }
            }
        }

        return true;
    }

    public function deleteTransaction(Request $request, Response $response, array $args)
    {
        $transaction_id = $args['transaction_id'];

        try {
            $this->db->beginTransaction();

            $sales_branch = $this->getSalesBranhCode($transaction_id);

            if (!is_array($sales_branch)) {
                throw new Exception();
            }

            $transaction_items = $this->getTransactionItems($transaction_id);

            if (!is_array($transaction_items)) {
                throw new Exception();
            }

            $restore_stock = $this->restoreSalesBranchStock($transaction_items, $sales_branch['kode_spb']);

            if (!$restore_stock) {
                throw new Exception();
            }

            // DELETE TRANSACTION ITEMS
            $sql = "DELETE FROM cn_transaksi_detail WHERE transaksi_id = :transaksi_id";
            $stmt = $this->db->prepare($sql);
            $data = [":transaksi_id" => $transaction_id];
            $stmt->execute($data);

            // DELETE TRANSACTION
            $sql = "DELETE FROM cn_transaksi WHERE id = :transaksi_id";
            $stmt = $this->db->prepare($sql);
            $data = [":transaksi_id" => $transaction_id];
            $stmt->execute($data);


            // DELETE ORDER HISTORY
            $sql = "DELETE FROM cn_order_history WHERE transaksi_id = :transaksi_id";
            $stmt = $this->db->prepare($sql);
            $data = [":transaksi_id" => $transaction_id];
            $stmt->execute($data);

            $this->db->commit();

            return $response->withJson(["status" => "success", "data" => "1"], 200);
        } catch (Exception $e) {

            $this->db->rollBack();

            return $response->withJson(["status" => "failed", "data" => "0"], 200);
        }
    }

    public function getSalesBranhCode($transaction_id)
    {
        $sql = "SELECT kode_spb FROM cn_transaksi WHERE id = :transaction_id;";

        $stmt = $this->db->prepare($sql);

        $params = [":transaction_id" => $transaction_id];

        $stmt->execute($params);

        if ($stmt->rowCount()) {
            return $stmt->fetch();
        }

        return false;
    }

    public function getTransactionItems($transaction_id)
    {
        $sql = "SELECT t.kode_barang, t.qty, b.unit 
                FROM cn_transaksi_detail t 
                INNER JOIN cn_barang b
                ON t.kode_barang = b.kode_barang
                WHERE 
                t.transaksi_id = :transaction_id 
                AND
                t.kode_barang != '90099'";

        $stmt = $this->db->prepare($sql);

        $params = [":transaction_id" => $transaction_id];

        $stmt->execute($params);

        if ($stmt->rowCount()) {
            return $stmt->fetchAll();
        }

        return false;
    }

    public function restoreSalesBranchStock($items, $sales_branch_code)
    {
        $sql = "UPDATE tb_produk SET stok = stok + :qty WHERE kode_barang = :product_code AND no_member = :sales_branch_code";

        if ($sales_branch_code == "00000") {
            $sql = "UPDATE tb_barang SET stok = stok + :qty WHERE kode_barang = :product_code";
        }

        $stmt = $this->db->prepare($sql);

        try {
            foreach ($items as $item) {

                if ($item['unit'] == "SERIES") {
                    $products = $this->getSeriesProducts($item['kode_barang']);

                    foreach ($products as $product) {

                        $params = [
                            ":qty" => (int) $item['qty'] * (int) $product['jumlah'],
                            ":product_code" => $product['kode_barang']
                        ];

                        // IF DATA IS FROM tb_produk / FROM SPB
                        if ($sales_branch_code != "00000") {
                            $params[":sales_branch_code"] = $sales_branch_code;
                        }

                        $stmt->execute($params);
                    }
                } else {
                    $params = [
                        ":qty" => (int) $item['qty'],
                        ":product_code" => $item['kode_barang']
                    ];

                    // IF DATA IS FROM tb_produk / FROM SPB
                    if ($sales_branch_code != "00000") {
                        $params[":sales_branch_code"] = $sales_branch_code;
                    }

                    $stmt->execute($params);
                }
            }

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function findBranch($code) 
    {
        $branch = array(
            "00000" => "BANDUNG", // "BANDUNG - Lengkong",
            "00217" => "BEKASI (Mustika Jaya)", //"BEKASI - Mustika Jaya",
            "00553" => "BOGOR", // "BOGOR - Tanah Sereal",
            "00539" => "MEDAN", // "MEDAN - Medan Marelan",
            "00042" => "PELABUHAN RATU", // "PELABUHAN RATU - Pelabuhan/Palabuhan Ratu",
            "00005" => "SUKABUMI (Cisaat)", // "SUKABUMI - Cisaat",
            "01340" => "SUKABUMI (Pabuaran)", // "SUKABUMI - Pabuaran",
            "01838" => "PURWOKERTO", // "PURWOKERTO - Purwokerto Timur",
            "15666" => "TANGERANG SELATAN", // "TANGERANG SELATAN - Pamulang",
            "15658" => "DEPOK", // "DEPOK - Sawangan",
            "15641" => "JAKARTA TIMUR", // "JAKARTA TIMUR - Duren Sawit",
            "13722" => "BEKASI (Cikarang Selatan)", // "BEKASI - Cikarang Selatan",
            "02006" => "GARUT" // "GARUT - Tarogong Kidul"
        );

        return $branch[$code];
    }

    public function findProfile($no_member) 
    {
        $sql = "SELECT nama, telp as telepon, email
                FROM tb_member 
                WHERE no_member=:no_member";

        $stmt = $this->db->prepare($sql);

        $data = [":no_member" => $no_member];

        $stmt->execute($data);

        if ($stmt->rowCount()) {
            return $stmt->fetch();
        }
    }

    public function findShippingAddress($no_member) 
    {
        $sql = "SELECT nama, telepon, provinsi_nama, kota_nama, kecamatan_nama, alamat, kode_pos 
                FROM cn_shipping_address_member 
                WHERE customer_id = :no_member";

        $stmt = $this->db->prepare($sql);

        $data = [":no_member" => $no_member];

        $stmt->execute($data);

        if ($stmt->rowCount()) {

            $result = $stmt->fetch();

            $shipping_address = array();
            $shipping_address['name'] = $result['nama'];
            $shipping_address['phone'] = $result['telepon'];
            $shipping_address['address'] = "{$result['alamat']} <br />
                                Kec. {$result['kecamatan_nama']} <br /> 
                                {$result['kota_nama']}<br />
                                {$result['provinsi_nama']}";

            return $shipping_address;
        }
    }

    public function findItems($transaction_number, $items) 
    {
        $product_codes = "";

        foreach($items as $item) {
            $code = $item['product_code'];
            $product_codes .= "'${code}'" . ",";
        }

        $product_codes = rtrim($product_codes, ",");

        $sql = "SELECT cn_transaksi_detail.qty, cn_barang.unit, cn_barang.kode_barang, cn_barang.nama
                FROM cn_barang 
                INNER JOIN cn_transaksi_detail
                ON cn_transaksi_detail.kode_barang = cn_barang.kode_barang
                WHERE transaksi_id = (
                        SELECT id 
                        FROM cn_transaksi 
                        WHERE nomor_transaksi = '${transaction_number}'
                    )
                    AND
                    cn_transaksi_detail.kode_barang IN (${product_codes})";

        $stmt = $this->db->query($sql);

        if ($stmt->rowCount()) {
            return $stmt->fetchAll();
        }
    }

    public function sendEmail(Request $request, Response $response, $data)
    {
        $mailer = $this->mailer;
        // $content = $this->renderer->render($response, "order-confirmed.php", array("name" => "IRUL"));
        // $send_email = $mailer->sendEmail("choerulsofyanmf@gmail.com", "Activate Account", $content);
        $mail_content = $this->renderer->render($response, $data["email"]["template"], $data["params"]);
        $send_email = $mailer->sendEmail($data["email"]["recipient"], $data["email"]["subject"], $mail_content);

        if ($send_email) {
            return true;
        }

        return false;
    }

    public function findBank($bank_name) {
        switch ($bank_name) {
            case 'BCA':
                return array(
                    "name" => "BCA",
                    "account_owner" => "Rian Setiawan",
                    "account_number" => "346.277.2308"
                );
                break;
            case 'BNI':
                return array(
                    "name" => "BNI",
                    "account_owner" => "Rian Setiawan",
                    "account_number" => "30.000.11.238",
                );
                break;
            case 'BRI':
                return array(
                    "name" => "BRI",
                    "account_owner" => "Rian Setiawan",
                    "account_number" => "0389.01.025423.50.0",
                );
                break;
            case 'MANDIRI':
                return array(
                    "name" => "MANDIRI",
                    "account_owner" => "Rian Setiawan",
                    "account_number" => "130.05.52.888.888",
                );
                break;
        }
    }

    function getLocalDateFormat($date, $print_day = false)
    {
        $days = array ( 1 =>    'Senin',
                    'Selasa',
                    'Rabu',
                    'Kamis',
                    'Jumat',
                    'Sabtu',
                    'Minggu'
                );
                
        $months = array (1 =>   'Januari',
                    'Februari',
                    'Maret',
                    'April',
                    'Mei',
                    'Juni',
                    'Juli',
                    'Agustus',
                    'September',
                    'Oktober',
                    'November',
                    'Desember'
                );
        $split 	  = explode('-', $date);
        $local_date = $split[2] . ' ' . $months[ (int)$split[1] ] . ' ' . $split[0];
        
        if ($print_day) {
            $num = date('N', strtotime($date));
            return $days[$num] . ', ' . $local_date;
        }
        return $local_date;
    }

    public function findGrandTotal($transaction_id)
    {
        $sql = "SELECT grand_total
                FROM cn_transaksi 
                WHERE id = :id";

        $stmt = $this->db->prepare($sql);

        $data = array(":id" => $transaction_id);
        
        $stmt->execute($data);

        if ($stmt->rowCount()) {
            return $stmt->fetch()['grand_total'];
        }
    }

    public function findTransactionNumber($transaction_id)
    {
        $sql = "SELECT nomor_transaksi
                FROM cn_transaksi 
                WHERE id = :id";

        $stmt = $this->db->prepare($sql);

        $data = array(":id" => $transaction_id);
        
        $stmt->execute($data);

        if ($stmt->rowCount()) {
            return $stmt->fetch()['nomor_transaksi'];
        }
    }

    public function findCustomer($transaction_id)
    {
        $sql = "SELECT nama, email
                FROM tb_member 
                WHERE no_member = (SELECT member_id FROM cn_transaksi WHERE id = :id)";

        $stmt = $this->db->prepare($sql);

        $data = array(":id" => $transaction_id);
        
        $stmt->execute($data);

        if ($stmt->rowCount()) {
            return $stmt->fetch();
        }
    }

    public function deletekExpiredOrders(Request $request, Response $response)
    {
        $sql_order_history = "DELETE FROM cn_order_history
                            WHERE transaksi_id IN (
                                SELECT *
                                FROM (
                                    SELECT id
                                    FROM cn_transaksi
                                    WHERE
                                        DATEDIFF(NOW(), tgl_transaksi) > 1
                                        AND id NOT IN (
                                            SELECT DISTINCT transaksi_id
                                            FROM cn_order_history
                                            WHERE keterangan IN (
                                                'TRANSFERRED',
                                                'SHIPPED',
                                                'PAYMENT CONFIRMED',
                                                'PACKED',
                                                'RECEIVED'
                                            )
                                        )
                                ) as t
                            );";

        if ($this->db->query($sql_order_history)) {
            $sql_transaksi_detail = "DELETE FROM cn_transaksi_detail
                                    WHERE transaksi_id IN (
                                        SELECT *
                                        FROM (
                                            SELECT id
                                            FROM cn_transaksi
                                            WHERE
                                                DATEDIFF(NOW(), tgl_transaksi) > 1
                                                AND id NOT IN (
                                                    SELECT DISTINCT transaksi_id
                                                    FROM cn_order_history
                                                    WHERE keterangan IN (
                                                        'TRANSFERRED',
                                                        'SHIPPED',
                                                        'PAYMENT CONFIRMED',
                                                        'PACKED',
                                                        'RECEIVED'
                                                    )
                                                )
                                        ) as t
                                    );";
            
            if ($this->db->query($sql_transaksi_detail)) {
                $sql_transaksi_detail = "DELETE FROM cn_transaksi_detail
                                    WHERE transaksi_id IN (
                                        SELECT *
                                        FROM (
                                            SELECT id
                                            FROM cn_transaksi
                                            WHERE
                                                DATEDIFF(NOW(), tgl_transaksi) > 1
                                                AND id NOT IN (
                                                    SELECT DISTINCT transaksi_id
                                                    FROM cn_order_history
                                                    WHERE keterangan IN (
                                                        'TRANSFERRED',
                                                        'SHIPPED',
                                                        'PAYMENT CONFIRMED',
                                                        'PACKED',
                                                        'RECEIVED'
                                                    )
                                                )
                                        ) as t
                                    );";
                
                $sql_get_id_transaksi = "SELECT tr.member_id, tm.nama, tr.nomor_transaksi 
                                        FROM cn_transaksi tr
                                        INNER JOIN tb_member tm
                                            ON tr.member_id = tm.no_member
                                        WHERE tr.id IN (
                                            SELECT *
                                            FROM (
                                                SELECT id
                                                FROM cn_transaksi
                                                WHERE
                                                    DATEDIFF(NOW(), tgl_transaksi) > 1
                                                    AND id NOT IN (
                                                        SELECT DISTINCT transaksi_id
                                                        FROM cn_order_history
                                                        WHERE keterangan IN (
                                                            'TRANSFERRED',
                                                            'SHIPPED',
                                                            'PAYMENT CONFIRMED',
                                                            'PACKED',
                                                            'RECEIVED'
                                                        )
                                                    )
                                            ) as t
                                            
                                        );";
                    
                    if ($this->db->query($sql_get_id_transaksi)) {
                        $data = array(
                            "email" => array(
                                "template" => "order-cancelled.php",
                                "subject" => "Transaction Activity",
                                "recipient" => $customer['email']
                            ),
                            "params" => array (
                                "name" => ucwords(strtolower($customer['nama'])),
                                "order_cancellation_date" => $date,
                                "transaction_number" => $transaction_number
                            )
                        );
        
                        $this->sendEmail($request, $response, $data);
                    }


                if ($this->db->query($sql_transaksi_detail)) {
                    $sql_transaksi = "DELETE FROM cn_transaksi
                                    WHERE id IN (
                                        SELECT *
                                        FROM (
                                            SELECT id
                                            FROM cn_transaksi
                                            WHERE
                                                DATEDIFF(NOW(), tgl_transaksi) > 1
                                                AND id NOT IN (
                                                    SELECT DISTINCT transaksi_id
                                                    FROM cn_order_history
                                                    WHERE keterangan IN (
                                                        'TRANSFERRED',
                                                        'SHIPPED',
                                                        'PAYMENT CONFIRMED',
                                                        'PACKED',
                                                        'RECEIVED'
                                                    )
                                                )
                                        ) as t
                                    );";
                    
                    if ($this->db->query($sql_transaksi)) {
                        return $response->withJson(["status" => "success", "data" => "1"], 200);
                    }
                }
            }
        }
    }
}
