<?php

namespace App\Controllers;

use Slim\Http\Request;
use Slim\Http\Response;

class TransactionController
{
    protected $db;
    protected $ongkir;

    public function __construct($db, $ongkir)
    {
        $this->db = $db;
        $this->ongkir = $ongkir;
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
            ":status_transaksi"    => "PLACE ORDER",
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
                                    sha.telp,
                                    trs.kode_spb,
                                    trs.status_transaksi,
                                    trs.tgl_transaksi,
                                    trs.grand_total,
                                    SUBSTRING(trs.grand_total, -3) as kode_unik_transfer
                                FROM 
                                    cn_transaksi trs
                                INNER JOIN cn_shipping_address_member sha
                                    ON trs.shipping_address_id = sha.id
                                INNER JOIN cn_customer cst
                                    ON cst.id = trs.customer_id
                                INNER JOIN cn_order_history odh
                                    ON odh.transaksi_id = trs.id
                                WHERE cst.email=:email
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
                                    INNER JOIN cn_customer cst
                                        ON cst.id = trs.customer_id
                                    WHERE 
                                        cst.email=:email
                                        AND
                                        odh.transaksi_id = trs.id
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
                            INNER JOIN cn_customer cst
                                ON cst.id = trs.customer_id
                            WHERE 
                                cst.email=:email
                                and trd.transaksi_id in (SELECT transaksi_id FROM cn_order_history)
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
}
