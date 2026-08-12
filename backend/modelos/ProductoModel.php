<?php

declare(strict_types=1);

namespace App\modelos;

use PDO;
use Flight;

class ProductoModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Flight::db();
    }
}
