<?php
declare(strict_types=1);

// Objeto que forma parte de Usuario: no necesita existir por separado.
class Contacto
{
    private string $email;
    private string $telefono;

    public function __construct(string $email, string $telefono)
    {
        $this->setEmail($email);
        $this->setTelefono($telefono);
    }

    public function getEmail(): string { return $this->email; }
    public function getTelefono(): string { return $this->telefono; }

    public function setEmail(string $email): void
    {
        $email = mb_strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
            throw new InvalidArgumentException('El correo electrónico no es válido.');
        }
        $this->email = $email;
    }

    public function setTelefono(string $telefono): void
    {
        $telefono = trim($telefono);
        if ($telefono === '' || mb_strlen($telefono) > 30) {
            throw new InvalidArgumentException('El teléfono es obligatorio (máximo 30 caracteres).');
        }
        $this->telefono = $telefono;
    }
}
