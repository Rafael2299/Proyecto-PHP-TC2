<?php
declare(strict_types=1);

// Factory Method: según el tipo, construye la clase hija correspondiente.
// Es el mismo concepto del ejemplo factory.php del profesor.
class FabricaDeAvisos
{
    public function fabricarAviso(array $datos, Usuario $autor): Aviso
    {
        $titulo = (string) ($datos['titulo'] ?? '');
        $resaltado = isset($datos['resaltado']) && $datos['resaltado'] === '1';
        $tipo = (string) ($datos['tipo'] ?? '');

        switch ($tipo) {
            case 'laboral':
                $sueldo = trim((string) ($datos['sueldo'] ?? ''));
                return new AvisoLaboral(
                    $titulo, $autor, $resaltado,
                    (string) ($datos['empresa'] ?? ''),
                    (string) ($datos['descripcion'] ?? ''),
                    $sueldo === '' ? null : $this->numero($sueldo, 'sueldo')
                );

            case 'producto':
                return new AvisoProducto(
                    $titulo, $autor, $resaltado,
                    (string) ($datos['descripcion'] ?? ''),
                    $this->numero((string) ($datos['precio'] ?? ''), 'precio')
                );

            case 'venta':
                return new AvisoVenta(
                    $titulo, $autor, $resaltado,
                    (string) ($datos['direccion'] ?? ''),
                    (string) ($datos['tipo_inmueble'] ?? ''),
                    (string) ($datos['inmobiliaria'] ?? ''),
                    $this->numero((string) ($datos['precio'] ?? ''), 'precio de venta')
                );

            case 'alquiler':
                return new AvisoAlquiler(
                    $titulo, $autor, $resaltado,
                    (string) ($datos['direccion'] ?? ''),
                    (string) ($datos['tipo_inmueble'] ?? ''),
                    (string) ($datos['inmobiliaria'] ?? ''),
                    $this->numero((string) ($datos['precio'] ?? ''), 'alquiler mensual'),
                    $this->numero((string) ($datos['gastos_comunes'] ?? '0'), 'gastos comunes', true)
                );

            default:
                throw new InvalidArgumentException('Tipo de aviso no reconocido.');
        }
    }

    private function numero(string $valor, string $nombreCampo, bool $permiteCero = false): float
    {
        $valor = str_replace(',', '.', trim($valor));
        if (!is_numeric($valor)) {
            throw new InvalidArgumentException("El campo {$nombreCampo} debe ser numérico.");
        }

        $numero = (float) $valor;
        if (!is_finite($numero) || $numero > 9999999999.99 || ($permiteCero ? $numero < 0 : $numero <= 0)) {
            throw new InvalidArgumentException("El campo {$nombreCampo} no tiene un valor válido.");
        }

        return $numero;
    }
}
