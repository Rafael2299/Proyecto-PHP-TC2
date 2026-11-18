<?php
declare(strict_types=1);

class AvisoAlquiler extends AvisoInmobiliario
{
    private float $alquilerMensual;
    private float $gastosComunes;

    public function __construct(
        string $titulo, Usuario $autor, bool $resaltado,
        string $direccion, string $tipoInmueble, string $inmobiliaria,
        float $alquilerMensual, float $gastosComunes
    ) {
        parent::__construct($titulo, $autor, $resaltado,
            $direccion, $tipoInmueble, $inmobiliaria);
        $this->setAlquilerMensual($alquilerMensual);
        $this->setGastosComunes($gastosComunes);
    }

    public function getTipo(): string { return 'alquiler'; }
    public function getAlquilerMensual(): float { return $this->alquilerMensual; }
    public function getGastosComunes(): float { return $this->gastosComunes; }

    public function setAlquilerMensual(float $alquilerMensual): void
    {
        if (!is_finite($alquilerMensual) || $alquilerMensual <= 0) {
            throw new InvalidArgumentException('El alquiler mensual debe ser positivo.');
        }
        $this->alquilerMensual = $alquilerMensual;
    }

    public function setGastosComunes(float $gastosComunes): void
    {
        if (!is_finite($gastosComunes) || $gastosComunes < 0) {
            throw new InvalidArgumentException('Los gastos comunes no pueden ser negativos.');
        }
        $this->gastosComunes = $gastosComunes;
    }
}
