<?php
declare(strict_types=1);

class Administrador extends Usuario
{
    public function __construct(int $id, string $nombre, string $email, string $telefono)
    {
        parent::__construct($id, $nombre, $email, $telefono);
    }

    public function getRol(): string
    {
        return 'administrador';
    }
}
