<?php
declare(strict_types=1);

// AvisoLaboral hereda los datos comunes de Aviso.
class AvisoLaboral extends Aviso
{
    private string $empresa;
    private string $descripcionCargo;
    private ?float $sueldoMensual;

    public function __construct(
        string $titulo, Usuario $autor, bool $resaltado,
        string $empresa, string $descripcionCargo, ?float $sueldoMensual
    ) {
        parent::__construct($titulo, $autor, $resaltado);
        $this->setEmpresa($empresa);
        $this->setDescripcionCargo($descripcionCargo);
        $this->setSueldoMensual($sueldoMensual);
    }

    public function getCategoria(): string { return 'laboral'; }
    public function getTipo(): string { return 'laboral'; }
    public function getEmpresa(): string { return $this->empresa; }
    public function getDescripcionCargo(): string { return $this->descripcionCargo; }
    public function getSueldoMensual(): ?float { return $this->sueldoMensual; }

    public function setEmpresa(string $empresa): void
    {
        $empresa = trim($empresa);
        if ($empresa === '' || mb_strlen($empresa) > 160) {
            throw new InvalidArgumentException('Ingresá una empresa válida.');
        }
        $this->empresa = $empresa;
    }

    public function setDescripcionCargo(string $descripcionCargo): void
    {
        $descripcionCargo = trim($descripcionCargo);
        if ($descripcionCargo === '' || mb_strlen($descripcionCargo) > 4000) {
            throw new InvalidArgumentException('Ingresá la descripción del cargo.');
        }
        $this->descripcionCargo = $descripcionCargo;
    }

    public function setSueldoMensual(?float $sueldoMensual): void
    {
        if ($sueldoMensual !== null && (!is_finite($sueldoMensual) || $sueldoMensual <= 0)) {
            throw new InvalidArgumentException('El sueldo debe ser positivo.');
        }
        $this->sueldoMensual = $sueldoMensual;
    }
}
