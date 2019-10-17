<?php

namespace App\Controllers;

use Slim\Http\Request;
use Slim\Http\Response;
use Steevenz\Rajaongkir;

class OngkirController
{
    protected $db;
    protected $raja_ongkir;

    public function __construct($db, $config)
    {
        $this->db = $db;
        $this->raja_ongkir = new Rajaongkir($config['api_key'], $config['account_type']);
    }

    public function getAllProvinces(Request $request, Response $response)
    {
        $all_provinces = $this->raja_ongkir->getProvinces();
        return $response->withJson(["status" => "success", "data" => $all_provinces], 200);
    }

    public function getProvinceDetail(Request $request, Response $response, array $args)
    {
        $province_detail = $this->raja_ongkir->getProvince($args['province_id']);
        return $response->withJson(["status" => "success", "data" => $province_detail], 200);
    }

    public function getProvinceCities(Request $request, Response $response, array $args)
    {
        $province_cities = $this->raja_ongkir->getCities($args['province_id']);
        return $response->withJson(["status" => "success", "data" => $province_cities], 200);
    }

    public function getAllCities(Request $request, Response $response)
    {
        $all_cities = $this->raja_ongkir->getCities();
        return $response->withJson(["status" => "success", "data" => $all_cities], 200);
    }

    public function getCityDetail(Request $request, Response $response, array $args)
    {
        $city_detail = $this->raja_ongkir->getCity($args['city_id']);
        return $response->withJson(["status" => "success", "data" => $city_detail], 200);
    }

    public function getAllCitySubdistricts(Request $request, Response $response, array $args)
    {
        $all_city_subdistricts = $this->raja_ongkir->getSubdistricts($args['city_id']);
        return $response->withJson(["status" => "success", "data" => $all_city_subdistricts], 200);
    }

    public function getSubdistrictDetail(Request $request, Response $response, array $args)
    {
        $subdistrict_detail = $this->raja_ongkir->getSubdistrict($args['subdistrict_id']);
        return $response->withJson(["status" => "success", "data" => $subdistrict_detail], 200);
    }

    public function getCost(Request $request, Response $response)
    {
        $item = $request->getParsedBody();
        $ongkir = $this->raja_ongkir->getCost(
            [
                'city' => $item['origin_city_id'],
                'subdistrict' => $item['origin_subdistrict_id']
            ],
            [
                'city' => $item['destination_city_id'],
                'subdisctrict' => $item['destination_subdistrict_id']
            ],
            $item['weight'],
            $item['courier']
        );

        return $response->withJson(["status" => "success", "data" => $ongkir], 200);
    }

    public function getDeliveryStatus(Request $request, Response $response)
    {
        $item = $request->getParsedBody();
        $delivery_status = $this->raja_ongkir->getWaybill($item['receipt_number'], $this->getCourier($item['receipt_number']));
        return $response->withJson(["status" => "success", "data" => $delivery_status], 200);
    }

    public function getCourier($receipt_number) {
        $sql_items = "SELECT tr.kurir
                        FROM cn_transaksi tr
                        WHERE tr.resi=:receipt_number";

        $stmt = $this->db->prepare($sql_items);
        $data = [":receipt_number" => $receipt_number];
        $stmt->execute($data);
        return $stmt->fetch()['kurir'];
    }
}
