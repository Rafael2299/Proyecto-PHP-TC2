<?php
declare(strict_types=1);

class Publicador extends Usuario
{
    public function __construct(int $id, string $nombre, string $email, string $telefono)
    {
        parent::__construct($id, $nombre, $email, $telefono);
    }

    public function getRol(): string
    {
        return 'publicador';
    }
}
