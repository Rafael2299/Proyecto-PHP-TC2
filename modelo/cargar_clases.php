<?php
declare(strict_types=1);

// Se cargan en orden: primero la clase padre y luego las hijas.
require_once __DIR__ . '/Contacto.php';
require_once __DIR__ . '/Usuario.php';
require_once __DIR__ . '/Publicador.php';
require_once __DIR__ . '/Administrador.php';

require_once __DIR__ . '/Aviso.php';
require_once __DIR__ . '/AvisoLaboral.php';
require_once __DIR__ . '/AvisoProducto.php';
require_once __DIR__ . '/AvisoInmobiliario.php';
require_once __DIR__ . '/AvisoVenta.php';
require_once __DIR__ . '/AvisoAlquiler.php';
require_once __DIR__ . '/FabricaDeAvisos.php';
