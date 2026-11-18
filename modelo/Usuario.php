<?php
declare(strict_types=1);

// Clase padre: contiene los datos que comparten administrador y publicador.
abstract class Usuario
{
    private int $id;
    private string $nombre;
    // Composición: cada Usuario construye y contiene sus propios datos de Contacto.
    private Contacto $contacto;

    public function __construct(int $id, string $nombre, string $email, string $telefono)
    {
        $this->setId($id);
        $this->setNombre($nombre);
        $this->contacto = new Contacto($email, $telefono);
    }

    public function getId(): int { return $this->id; }
    public function getNombre(): string { return $this->nombre; }
    public function getEmail(): string { return $this->contacto->getEmail(); }
    public function getTelefono(): string { return $this->contacto->getTelefono(); }
    public function getContacto(): Contacto { return $this->contacto; }

    public function setId(int $id): void
    {
        if ($id < 0) {
            throw new InvalidArgumentException('El ID no puede ser negativo.');
        }
        $this->id = $id;
    }

    public function setNombre(string $nombre): void
    {
        $nombre = trim($nombre);
        if ($nombre === '' || mb_strlen($nombre) > 100) {
            throw new InvalidArgumentException('El nombre es obligatorio (máximo 100 caracteres).');
        }
        $this->nombre = $nombre;
    }

    public function setEmail(string $email): void
    {
        $this->contacto->setEmail($email);
    }

    public function setTelefono(string $telefono): void
    {
        $this->contacto->setTelefono($telefono);
    }

    // Cada clase hija define su propio rol.
    abstract public function getRol(): string;
}
