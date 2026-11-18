<?php
declare(strict_types=1);

require_once __DIR__ . '/modelo/cargar_clases.php';

function e(mixed $dato): string
{
    return htmlspecialchars((string) ($dato ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$error = null;
$avisos = [];
$comprobaciones = [];

try {
    // 1. Creamos un objeto de cada clase hija de Usuario.
    $publicador = new Publicador(1, 'Rafael', 'rafael@ejemplo.com', '099123456');
    $administrador = new Administrador(2, 'Ana', 'ana@ejemplo.com', '098123456');

    // 2. Creamos objetos de las distintas clases hijas de Aviso.
    $laboral = new AvisoLaboral(
        'Técnico de soporte', $publicador, false,
        'Empresa TC2', 'Reparación de computadoras y soporte a usuarios', 38000.0
    );

    $producto = new AvisoProducto(
        'Notebook usada', $publicador, true,
        'Notebook de 14 pulgadas, en buen estado', 12500.0
    );

    $venta = new AvisoVenta(
        'Casa en venta', $publicador, false,
        '18 de Julio 1234', 'casa', 'Inmobiliaria Centro', 120000.0
    );

    $alquiler = new AvisoAlquiler(
        'Apartamento en alquiler', $publicador, false,
        'Rivera 2500', 'apartamento', 'Inmobiliaria Centro', 21000.0, 2800.0
    );

    // 3. Probamos los setters: modificamos el título y el precio.
    $producto->setTitulo('Notebook Dell usada');
    $producto->setPrecio(11900.0);

    // 4. Guardamos diferentes subclases dentro del mismo array.
    //    Gracias al polimorfismo, todas comparten los métodos de Aviso.
    $avisos = [$laboral, $producto, $venta, $alquiler];

    $comprobaciones = [
        ['Publicador hereda de Usuario', $publicador instanceof Usuario],
        ['Administrador hereda de Usuario', $administrador instanceof Usuario],
        ['AvisoLaboral hereda de Aviso', $laboral instanceof Aviso],
        ['AvisoProducto hereda de Aviso', $producto instanceof Aviso],
        ['AvisoVenta hereda de AvisoInmobiliario', $venta instanceof AvisoInmobiliario],
        ['AvisoAlquiler hereda de AvisoInmobiliario', $alquiler instanceof AvisoInmobiliario],
        ['AvisoVenta también hereda de Aviso', $venta instanceof Aviso],
        ['Setters modifican valores', $producto->getPrecio() === 11900.0],
        ['Agregación: el aviso se relaciona con su autor', $laboral->getAutor() === $publicador],
        ['Composición: Usuario contiene un Contacto', $publicador->getContacto() instanceof Contacto],
        ['Contacto conserva los datos del usuario',
            $publicador->getContacto()->getEmail() === $publicador->getEmail()
            && $publicador->getContacto()->getTelefono() === $publicador->getTelefono()],
    ];

    // 6. Ahora probamos el Factory Method del profesor: recibe un tipo y crea
    //    un objeto de la jerarquía sin llamar directamente a su constructor.
    $fabrica = new FabricaDeAvisos();
    $avisoFabricado = $fabrica->fabricarAviso([
        'tipo' => 'producto',
        'titulo' => 'Monitor de prueba',
        'descripcion' => 'Monitor de 24 pulgadas',
        'precio' => '3500',
        'resaltado' => '1',
    ], $publicador);
    $comprobaciones[] = ['Factory crea un AvisoProducto', $avisoFabricado instanceof AvisoProducto];
    $comprobaciones[] = ['Factory asigna el título', $avisoFabricado->getTitulo() === 'Monitor de prueba'];
    $comprobaciones[] = ['Factory asigna el precio', $avisoFabricado instanceof AvisoProducto && $avisoFabricado->getPrecio() === 3500.0];

    $fabricaLaboral = $fabrica->fabricarAviso([
        'tipo' => 'laboral', 'titulo' => 'Auxiliar técnico',
        'empresa' => 'TC2', 'descripcion' => 'Mantenimiento de equipos', 'sueldo' => '30000'
    ], $publicador);
    $fabricaVenta = $fabrica->fabricarAviso([
        'tipo' => 'venta', 'titulo' => 'Apartamento en venta',
        'direccion' => 'Colonia 1000', 'tipo_inmueble' => 'apartamento',
        'inmobiliaria' => 'Centro', 'precio' => '90000'
    ], $publicador);
    $fabricaAlquiler = $fabrica->fabricarAviso([
        'tipo' => 'alquiler', 'titulo' => 'Casa en alquiler',
        'direccion' => 'Salto 123', 'tipo_inmueble' => 'casa',
        'inmobiliaria' => 'Centro', 'precio' => '22000', 'gastos_comunes' => '0'
    ], $publicador);
    $comprobaciones[] = ['Factory crea un AvisoLaboral', $fabricaLaboral instanceof AvisoLaboral];
    $comprobaciones[] = ['Factory crea un AvisoVenta', $fabricaVenta instanceof AvisoVenta];
    $comprobaciones[] = ['Factory crea un AvisoAlquiler', $fabricaAlquiler instanceof AvisoAlquiler];

    // 5. Probamos una excepción sin detener el resto del programa.
    try {
        $producto->setPrecio(-100);
        $comprobaciones[] = ['Se rechaza un precio negativo', false];
    } catch (InvalidArgumentException $e) {
        $comprobaciones[] = ['Se rechaza un precio negativo', true];
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Prueba de herencia · Clasificados TC2</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>
<header>
    <nav class="contenedor navegacion">
        <a class="marca" href="clasificados.php">Clasificados TC2</a>
        <div class="enlaces">
            <a href="clasificados.php">Volver al programa</a>
            <span>Prueba de POO</span>
        </div>
    </nav>
</header>
<main class="contenedor">
    <section class="portada">
        <h1>Prueba de clases y herencia</h1>
        <p>Estos objetos se crean directamente desde PHP: <strong>no requieren MySQL</strong>.</p>
        <p>Ejemplo para practicar constructores, <code>extends</code>, <code>parent::__construct()</code>,
           métodos get/set, herencia de dos niveles, polimorfismo, agregación, composición y Factory Method.</p>
        <p><strong>Agregación:</strong> un <code>Aviso</code> hace referencia a su autor <code>Usuario</code>,
           que puede existir aunque el aviso se borre.
           <strong>Composición:</strong> cada <code>Usuario</code> crea y contiene su objeto <code>Contacto</code>
           con el correo y el teléfono.</p>
    </section>

    <?php if ($error): ?>
        <p class="alerta error">Error al crear los objetos: <?= e($error) ?></p>
    <?php else: ?>
        <h2>Objetos creados mediante sus constructores</h2>
        <div class="tarjetas">
            <?php foreach ($avisos as $aviso): ?>
                <article class="tarjeta <?= $aviso->estaResaltado() ? 'destacada' : '' ?>">
                    <span class="etiqueta"><?= e(ucfirst($aviso->getTipo())) ?></span>
                    <?php if ($aviso->estaResaltado()): ?><span class="etiqueta oro">Destacado</span><?php endif; ?>
                    <h3><?= e($aviso->getTitulo()) ?></h3>
                    <p><strong>Clase:</strong> <?= e(get_class($aviso)) ?></p>
                    <p><strong>Categoría:</strong> <?= e($aviso->getCategoria()) ?></p>
                    <p><strong>Autor:</strong> <?= e($aviso->getAutor()->getNombre()) ?>
                        (<?= e($aviso->getAutor()->getRol()) ?>)</p>
                    <?php if ($aviso instanceof AvisoLaboral): ?>
                        <p><strong>Empresa:</strong> <?= e($aviso->getEmpresa()) ?></p>
                        <p><?= e($aviso->getDescripcionCargo()) ?></p>
                        <p><strong>Sueldo:</strong> $<?= e($aviso->getSueldoMensual()) ?></p>
                    <?php elseif ($aviso instanceof AvisoProducto): ?>
                        <p><?= e($aviso->getDescripcionProducto()) ?></p>
                        <p><strong>Precio:</strong> $<?= e($aviso->getPrecio()) ?></p>
                    <?php elseif ($aviso instanceof AvisoVenta): ?>
                        <p><strong>Dirección:</strong> <?= e($aviso->getDireccion()) ?></p>
                        <p><strong>Venta:</strong> $<?= e($aviso->getPrecioVenta()) ?></p>
                    <?php elseif ($aviso instanceof AvisoAlquiler): ?>
                        <p><strong>Dirección:</strong> <?= e($aviso->getDireccion()) ?></p>
                        <p><strong>Alquiler:</strong> $<?= e($aviso->getAlquilerMensual()) ?></p>
                        <p><strong>Gastos comunes:</strong> $<?= e($aviso->getGastosComunes()) ?></p>
                    <?php endif; ?>
                    <small>Fecha: <?= e($aviso->getFechaCreacion()) ?></small>
                </article>
            <?php endforeach; ?>
        </div>

        <section class="panel separador-superior">
            <h2>Comprobaciones de herencia</h2>
            <table class="tabla-pruebas">
                <thead><tr><th>Comprobación</th><th>Resultado</th></tr></thead>
                <tbody>
                <?php foreach ($comprobaciones as [$descripcion, $correcto]): ?>
                    <tr>
                        <td><?= e($descripcion) ?></td>
                        <td><?= $correcto ? '✅ Correcto' : '❌ Falló' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php endif; ?>
</main>
<footer>TC2 · Ejemplo de clases padre e hijas, separado del sitio funcional</footer>
</body>
</html>
