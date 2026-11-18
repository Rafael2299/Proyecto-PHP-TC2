<?php
declare(strict_types=1);

// Clase padre de todos los avisos.
abstract class Aviso
{
    private ?int $id = null;
    private string $titulo;
    private Usuario $autor;
    private bool $resaltado;
    private string $fechaCreacion;

    public function __construct(string $titulo, Usuario $autor, bool $resaltado = false)
    {
        $this->setTitulo($titulo);
        $this->setAutor($autor);
        $this->setResaltado($resaltado);
        $this->fechaCreacion = date('Y-m-d H:i:s');
    }

    public function getId(): ?int { return $this->id; }
    public function getTitulo(): string { return $this->titulo; }
    public function getAutor(): Usuario { return $this->autor; }
    public function estaResaltado(): bool { return $this->resaltado; }
    public function getFechaCreacion(): string { return $this->fechaCreacion; }

    public function setId(int $id): void
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('El ID del aviso debe ser positivo.');
        }
        $this->id = $id;
    }

    public function setTitulo(string $titulo): void
    {
        $titulo = trim($titulo);
        if ($titulo === '' || mb_strlen($titulo) > 160) {
            throw new InvalidArgumentException('El título es obligatorio (máximo 160 caracteres).');
        }
        $this->titulo = $titulo;
    }

    public function setAutor(Usuario $autor): void
    {
        // Agregación: el autor puede existir aunque el aviso sea eliminado.
        $this->autor = $autor;
    }

    public function setResaltado(bool $resaltado): void
    {
        $this->resaltado = $resaltado;
    }

    // Polimorfismo: cada subtipo devolverá su nombre.
    abstract public function getCategoria(): string;
    abstract public function getTipo(): string;
}
