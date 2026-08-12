<?php

declare(strict_types=1);

namespace App\Controladores;

use Flight;
use App\modelos\ProductoModel;

class ProductoController
{
    private ProductoModel $modelo;

    public function __construct()
    {
        $this->modelo = new ProductoModel();
    }

    public function index(): void
    {
        Flight::json($this->modelo->todos());
    }


}
