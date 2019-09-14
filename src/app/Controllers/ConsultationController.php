<?php

namespace App\Controllers;

use Slim\Http\Request;
use Slim\Http\Response;

class ConsultationController
{
    protected $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function add(Request $request, Response $response)
    {
        $consultation = $request->getParsedBody();
        $sql = "INSERT INTO cn_consultation (nama, jenis_kelamin, rentang_usia, keluhan_kulit, tipe_kulit, tingkat_kulit_sensitif, deskripsi, telp) VALUE (:nama, :jenis_kelamin, :rentang_usia, :keluhan_kulit, :tipe_kulit, :tingkat_kulit_sensitif, :deskripsi, :telp)";
        $stmt = $this->db->prepare($sql);

        $data = [
            ":nama" => $consultation["name"],
            ":jenis_kelamin" => $consultation["gender"],
            ":rentang_usia" => $consultation["age_range"],
            ":keluhan_kulit" => $consultation["main_problem"],
            ":tipe_kulit" => $consultation["skin_type"],
            ":tingkat_kulit_sensitif" => $consultation["skin_sensitivity"],
            ":deskripsi" => $consultation["description"],
            ":telp" => $consultation["phone"]
        ];

        if ($stmt->execute($data)) {
            return $response->withJson(["status" => "success", "data" => "1"], 200);
        }

        return $response->withJson(["status" => "failed", "data" => "0"], 200);
    }
}
