<?php
namespace App\Services;

class PromoManager
{
    private $json_data;

    public function __construct()
    {
        $file_path = "../promos/data.json";

        if (file_exists($file_path)) {
            $this->json_data = file_get_contents($file_path);
        }
    }

    public function getAllPromos()
    {
        return $this->json_data;
    }
}
