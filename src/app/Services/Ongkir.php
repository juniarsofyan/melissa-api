<?php
namespace App\Services;

use Steevenz\Rajaongkir;

class Ongkir
{
    protected $raja_ongkir;

    public function __construct($config)
    {
        $this->raja_ongkir = new Rajaongkir($config['api_key'], $config['account_type']);
    }

    public function getAllProvinces()
    {
        return $this->raja_ongkir->getProvinces();
    }

    public function getProvinceDetail($province_id)
    {
        return $this->raja_ongkir->getProvince($province_id);
    }

    public function getProvinceCities($province_id)
    {
        return $this->raja_ongkir->getCities($province_id);
    }

    public function getAllCities()
    {
        return $this->raja_ongkir->getCities();
    }

    public function getCityDetail($city_id)
    {
        return $this->raja_ongkir->getCity($city_id);
    }

    public function getAllCitySubdistricts($city_id)
    {
        return $this->raja_ongkir->getSubdistricts($city_id);
    }

    public function getSubdistrictDetail($subdistrict_id)
    {
        return $this->raja_ongkir->getSubdistrict($subdistrict_id);
    }

    public function getCost($origin_city_id, $origin_subdistrict_id, $destination_city_id, $destination_subdistrict_id, $weight, $courier)
    {
        return $this->raja_ongkir->getCost(
            [
                'city' => $origin_city_id,
                'subdistrict' => $origin_subdistrict_id
            ],
            [
                'city' => $destination_city_id,
                'subdisctrict' => $destination_subdistrict_id
            ],
            $weight,
            $courier
        );
    }

    public function getDeliveryStatus($receipt_number, $courier)
    {
        return $this->raja_ongkir->getWaybill($receipt_number, $courier);
    }
}
