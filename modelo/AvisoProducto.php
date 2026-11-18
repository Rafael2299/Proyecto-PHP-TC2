<?php
declare(strict_types=1);

class AvisoProducto extends Aviso
{
    private string $descripcionProducto;
    private float $precio;

    public function __construct(
        string $titulo, Usuario $autor, bool $resaltado,
        string $descripcionProducto, float $precio
    ) {
        parent::__construct($titulo, $autor, $resaltado);
        $this->setDescripcionProducto($descripcionProducto);
        $this->setPrecio($precio);
    }

    public function getCategoria(): string { return 'producto'; }
    public function getTipo(): string { return 'producto'; }
    public function getDescripcionProducto(): string { return $this->descripcionProducto; }
    public function getPrecio(): float { return $this->precio; }

    public function setDescripcionProducto(string $descripcionProducto): void
    {
        $descripcionProducto = trim($descripcionProducto);
        if ($descripcionProducto === '' || mb_strlen($descripcionProducto) > 4000) {
            throw new InvalidArgumentException('La descripción del producto es obligatoria.');
        }
        $this->descripcionProducto = $descripcionProducto;
    }

    public function setPrecio(float $precio): void
    {
        if (!is_finite($precio) || $precio <= 0) {
            throw new InvalidArgumentException('El precio debe ser positivo.');
        }
        $this->precio = $precio;
    }
}
