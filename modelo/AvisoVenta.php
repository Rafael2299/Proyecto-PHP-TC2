<?php
declare(strict_types=1);

// Herencia en dos niveles: Aviso -> AvisoInmobiliario -> AvisoVenta.
class AvisoVenta extends AvisoInmobiliario
{
    private float $precioVenta;

    public function __construct(
        string $titulo, Usuario $autor, bool $resaltado,
        string $direccion, string $tipoInmueble, string $inmobiliaria,
        float $precioVenta
    ) {
        parent::__construct($titulo, $autor, $resaltado,
            $direccion, $tipoInmueble, $inmobiliaria);
        $this->setPrecioVenta($precioVenta);
    }

    public function getTipo(): string { return 'venta'; }
    public function getPrecioVenta(): float { return $this->precioVenta; }

    public function setPrecioVenta(float $precioVenta): void
    {
        if (!is_finite($precioVenta) || $precioVenta <= 0) {
            throw new InvalidArgumentException('El precio de venta debe ser positivo.');
        }
        $this->precioVenta = $precioVenta;
    }
}
