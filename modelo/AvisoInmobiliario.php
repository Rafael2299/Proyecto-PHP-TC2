<?php
declare(strict_types=1);

// Clase intermedia: venta y alquiler comparten datos de inmuebles.
abstract class AvisoInmobiliario extends Aviso
{
    private string $direccion;
    private string $tipoInmueble;
    private string $inmobiliaria;

    public function __construct(
        string $titulo, Usuario $autor, bool $resaltado,
        string $direccion, string $tipoInmueble, string $inmobiliaria
    ) {
        parent::__construct($titulo, $autor, $resaltado);
        $this->setDireccion($direccion);
        $this->setTipoInmueble($tipoInmueble);
        $this->setInmobiliaria($inmobiliaria);
    }

    public function getCategoria(): string { return 'inmobiliario'; }
    public function getDireccion(): string { return $this->direccion; }
    public function getTipoInmueble(): string { return $this->tipoInmueble; }
    public function getInmobiliaria(): string { return $this->inmobiliaria; }

    public function setDireccion(string $direccion): void
    {
        $direccion = trim($direccion);
        if ($direccion === '' || mb_strlen($direccion) > 160) {
            throw new InvalidArgumentException('La dirección es obligatoria.');
        }
        $this->direccion = $direccion;
    }

    public function setTipoInmueble(string $tipoInmueble): void
    {
        if (!in_array($tipoInmueble, ['casa', 'apartamento'], true)) {
            throw new InvalidArgumentException('El tipo de inmueble debe ser casa o apartamento.');
        }
        $this->tipoInmueble = $tipoInmueble;
    }

    public function setInmobiliaria(string $inmobiliaria): void
    {
        $inmobiliaria = trim($inmobiliaria);
        if ($inmobiliaria === '' || mb_strlen($inmobiliaria) > 160) {
            throw new InvalidArgumentException('La inmobiliaria es obligatoria.');
        }
        $this->inmobiliaria = $inmobiliaria;
    }
}
