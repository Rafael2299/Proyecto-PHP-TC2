<?php
declare(strict_types=1);

/*
 * Versión de demostración del integrador TC2.
 * Clasificados usa Singleton y MySQLi; las clases hijas están en modelo/.
 * La fábrica construye objetos reales antes de insertarlos en MySQL.
 */

require_once __DIR__ . '/modelo/cargar_clases.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
// La versión demo usa su propia cookie y no interfiere con otras instalaciones.
session_name('TC2_POO_DEMO');
session_start();

final class Clasificados
{
    private static ?Clasificados $instancia = null;
    private mysqli $conexion;
    // Esta base es EXCLUSIVA de la nueva versión de demostración.
    private string $nombreBD = 'clasificados_poo_demo';
    private ?string $codigoInstalacion = null;

    public const EMAIL_ADMIN_DEMO = 'admin@demo.local';
    public const CLAVE_ADMIN_DEMO = 'AdminDemo2026!';
    public const EMAIL_PUBLICADOR_DEMO = 'publicador@demo.local';
    public const CLAVE_PUBLICADOR_DEMO = 'Publicador2026!';

    // Singleton: solo esta clase puede llamar al constructor.
    private function __construct()
    {
        $servidor = getenv('DB_HOST') ?: 'localhost';
        $puerto = (int) (getenv('DB_PORT') ?: '3306');
        $usuario = getenv('DB_USER') ?: 'root';
        $clave = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';

        // Sin nombre de base de datos, porque tal vez todavía no exista.
        $this->conexion = new mysqli($servidor, $usuario, $clave, '', $puerto);
        $this->conexion->set_charset('utf8mb4');
    }

    public static function getInstancia(): Clasificados
    {
        if (self::$instancia === null) {
            self::$instancia = new Clasificados();
        }
        return self::$instancia;
    }

    public static function escapar(mixed $texto): string
    {
        return htmlspecialchars((string) ($texto ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function token(): string
    {
        if (empty($_SESSION['token'])) {
            $_SESSION['token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['token'];
    }

    public static function verificarToken(): void
    {
        if (!hash_equals(self::token(), (string) ($_POST['token'] ?? ''))) {
            throw new RuntimeException('Formulario no válido. Actualizá la página.');
        }
    }

    public static function esLocal(): bool
    {
        return in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
    }

    public function estaInstalado(): bool
    {
        $stmt = $this->conexion->prepare(
            'SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?'
        );
        $stmt->bind_param('s', $this->nombreBD);
        $stmt->execute();
        $existe = (int) $stmt->get_result()->fetch_row()[0] > 0;
        $stmt->close();
        if (!$existe) {
            return false;
        }

        $this->conexion->select_db($this->nombreBD);
        $stmt = $this->conexion->prepare(
            "SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN ('usuarios', 'avisos', 'configuracion')"
        );
        $stmt->bind_param('s', $this->nombreBD);
        $stmt->execute();
        $cantidad = (int) $stmt->get_result()->fetch_row()[0];
        $stmt->close();
        return $cantidad === 3;
    }

    // Crea o verifica las tablas. No borra información que ya exista.
    public function crearEstructura(): string
    {
        $yaExistia = $this->estaInstalado();

        $this->conexion->query(
            "CREATE DATABASE IF NOT EXISTS clasificados_poo_demo
             CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );
        $this->conexion->select_db($this->nombreBD);

        $this->conexion->query(
            "CREATE TABLE IF NOT EXISTS usuarios (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nombre VARCHAR(100) NOT NULL,
                email VARCHAR(190) NOT NULL UNIQUE,
                telefono VARCHAR(30) NOT NULL,
                clave_hash VARCHAR(255) NOT NULL,
                rol ENUM('administrador', 'publicador') NOT NULL
            ) ENGINE=InnoDB"
        );

        $this->conexion->query(
            "CREATE TABLE IF NOT EXISTS avisos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                usuario_id INT NOT NULL,
                tipo ENUM('laboral', 'producto', 'venta', 'alquiler') NOT NULL,
                titulo VARCHAR(160) NOT NULL,
                resaltado TINYINT(1) NOT NULL DEFAULT 0,
                descripcion TEXT NULL,
                empresa VARCHAR(160) NULL,
                sueldo DECIMAL(12,2) NULL,
                precio DECIMAL(12,2) NULL,
                direccion VARCHAR(255) NULL,
                tipo_inmueble ENUM('casa', 'apartamento') NULL,
                inmobiliaria VARCHAR(160) NULL,
                gastos_comunes DECIMAL(12,2) NULL,
                fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB"
        );

        $this->conexion->query(
            'CREATE TABLE IF NOT EXISTS configuracion (
                clave VARCHAR(40) PRIMARY KEY,
                valor VARCHAR(100) NOT NULL
            ) ENGINE=InnoDB'
        );

        // Un código aleatorio permite invalidar sesiones antiguas tras el reinicio.
        $resultado = $this->conexion->query(
            "SELECT valor FROM configuracion WHERE clave = 'instalacion_id' LIMIT 1"
        );
        if (!$resultado->fetch_assoc()) {
            $clave = 'instalacion_id';
            $valor = bin2hex(random_bytes(16));
            $stmt = $this->conexion->prepare(
                'INSERT IGNORE INTO configuracion (clave, valor) VALUES (?, ?)'
            );
            $stmt->bind_param('ss', $clave, $valor);
            $stmt->execute();
            $stmt->close();
        }

        $this->codigoInstalacion = null;

        return $yaExistia
            ? 'La base de datos y las tablas ya existían. Se verificaron sin borrar datos.'
            : 'La base de datos y las tablas se crearon correctamente.';
    }

    private function codigoInstalacion(): string
    {
        if ($this->codigoInstalacion === null) {
            $resultado = $this->conexion->query(
                "SELECT valor FROM configuracion WHERE clave = 'instalacion_id' LIMIT 1"
            );
            $fila = $resultado->fetch_assoc();
            if (!$fila) {
                throw new RuntimeException('Falta el identificador de instalación.');
            }
            $this->codigoInstalacion = $fila['valor'];
        }
        return $this->codigoInstalacion;
    }

    public function hayAdministrador(): bool
    {
        if (!$this->estaInstalado()) {
            return false;
        }
        $resultado = $this->conexion->query(
            "SELECT COUNT(*) AS total FROM usuarios WHERE rol = 'administrador'"
        );
        return (int) $resultado->fetch_assoc()['total'] > 0;
    }

    private function validarUsuario(string $nombre, string $email, string $telefono, string $clave): void
    {
        if (trim($nombre) === '' || mb_strlen($nombre) > 100) {
            throw new InvalidArgumentException('Ingresá un nombre de hasta 100 caracteres.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
            throw new InvalidArgumentException('Ingresá un correo válido.');
        }
        if (trim($telefono) === '' || mb_strlen($telefono) > 30) {
            throw new InvalidArgumentException('Ingresá un teléfono válido.');
        }
        if (strlen($clave) < 8) {
            throw new InvalidArgumentException('La contraseña debe tener al menos 8 caracteres.');
        }
    }

    private function insertarUsuario(string $nombre, string $email, string $telefono, string $clave, string $rol): void
    {
        $this->validarUsuario($nombre, $email, $telefono, $clave);
        $nombre = trim($nombre);
        $email = mb_strtolower(trim($email));
        $telefono = trim($telefono);
        $hash = password_hash($clave, PASSWORD_DEFAULT);

        $stmt = $this->conexion->prepare(
            'INSERT INTO usuarios (nombre, email, telefono, clave_hash, rol) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('sssss', $nombre, $email, $telefono, $hash, $rol);
        $stmt->execute();
        $stmt->close();
    }

    private function existeCorreo(string $email): bool
    {
        $stmt = $this->conexion->prepare('SELECT 1 FROM usuarios WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $existe = $stmt->get_result()->fetch_row() !== null;
        $stmt->close();
        return $existe;
    }

    // Se ejecuta en la primera instalación o cuando el administrador lo pide.
    public function crearUsuariosDemo(): string
    {
        if (!self::esLocal()) {
            throw new RuntimeException('Las cuentas de demostración solo se crean desde localhost.');
        }
        if (!$this->estaInstalado()) {
            throw new RuntimeException('Primero hay que crear la estructura de la base de datos.');
        }

        $creados = 0;
        if (!$this->existeCorreo(self::EMAIL_ADMIN_DEMO)) {
            $this->insertarUsuario(
                'Administrador Demo', self::EMAIL_ADMIN_DEMO, '099111111',
                self::CLAVE_ADMIN_DEMO, 'administrador'
            );
            $creados++;
        }
        if (!$this->existeCorreo(self::EMAIL_PUBLICADOR_DEMO)) {
            $this->insertarUsuario(
                'Publicador Demo', self::EMAIL_PUBLICADOR_DEMO, '099222222',
                self::CLAVE_PUBLICADOR_DEMO, 'publicador'
            );
            $creados++;
        }

        return $creados > 0
            ? "Se crearon {$creados} cuentas de demostración."
            : 'Las cuentas de demostración ya existían; no se modificaron.';
    }

    // BORRA únicamente la base aislada clasificados_poo_demo.
    // No se llama automáticamente: requiere una acción explícita del administrador.
    public function reiniciarBaseDemostracion(string $confirmacion): string
    {
        $this->exigirRol('administrador');
        if (!self::esLocal()) {
            throw new RuntimeException('El reinicio solamente está habilitado desde localhost.');
        }
        if ($confirmacion !== 'REINICIAR') {
            throw new InvalidArgumentException('Para reiniciar debés escribir REINICIAR exactamente.');
        }
        if ($this->nombreBD !== 'clasificados_poo_demo') {
            throw new RuntimeException('El nombre de la base de pruebas no coincide con el permitido.');
        }

        // Los datos de esta base se perderán; las otras bases NO se tocan.
        $this->conexion->query('DROP DATABASE IF EXISTS `clasificados_poo_demo`');
        $this->codigoInstalacion = null;
        $this->crearEstructura();
        $this->crearUsuariosDemo();
        $this->cerrarSesion();

        return 'Base de demostración reiniciada. Se reconstruyeron las tablas y las dos cuentas; iniciá sesión otra vez.';
    }

    public function crearPublicador(string $nombre, string $email, string $telefono, string $clave): void
    {
        $this->exigirRol('administrador');
        $this->insertarUsuario($nombre, $email, $telefono, $clave, 'publicador');
    }

    public function iniciarSesion(string $email, string $clave): void
    {
        $email = mb_strtolower(trim($email));
        $stmt = $this->conexion->prepare('SELECT * FROM usuarios WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $usuario = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$usuario || !password_verify($clave, $usuario['clave_hash'])) {
            throw new RuntimeException('Correo o contraseña incorrectos.');
        }

        session_regenerate_id(true);
        $_SESSION['usuario'] = [
            'id' => (int) $usuario['id'],
            'nombre' => $usuario['nombre'],
            'rol' => $usuario['rol'],
        ];
        $_SESSION['instalacion_id'] = $this->codigoInstalacion();
    }

    public function cerrarSesion(): void
    {
        unset($_SESSION['usuario']);
        unset($_SESSION['instalacion_id']);
        session_regenerate_id(true);
    }

    public function usuario(): ?array
    {
        if (!isset($_SESSION['usuario'])) {
            return null;
        }

        // Si otra sesión reinició la BD, la cuenta vieja ya no es válida.
        try {
            $codigoGuardado = (string) ($_SESSION['instalacion_id'] ?? '');
            if (!hash_equals($this->codigoInstalacion(), $codigoGuardado)) {
                $this->cerrarSesion();
                return null;
            }
        } catch (Throwable $e) {
            $this->cerrarSesion();
            return null;
        }

        return $_SESSION['usuario'];
    }

    public function exigirRol(string $rol): void
    {
        $usuario = $this->usuario();
        if (!$usuario || $usuario['rol'] !== $rol) {
            throw new RuntimeException('No tenés permiso para realizar esta operación.');
        }

        // También se comprueba en MySQL, por si se borró la cuenta en otra sesión.
        $id = (int) $usuario['id'];
        $stmt = $this->conexion->prepare(
            'SELECT 1 FROM usuarios WHERE id = ? AND rol = ? LIMIT 1'
        );
        $stmt->bind_param('is', $id, $rol);
        $stmt->execute();
        $existe = $stmt->get_result()->fetch_row() !== null;
        $stmt->close();
        if (!$existe) {
            $this->cerrarSesion();
            throw new RuntimeException('La cuenta ya no existe o cambió de rol. Volvé a iniciar sesión.');
        }
    }

    public function listarPublicadores(): array
    {
        $this->exigirRol('administrador');
        return $this->conexion->query(
            "SELECT id, nombre, email, telefono FROM usuarios
             WHERE rol = 'publicador' ORDER BY nombre"
        )->fetch_all(MYSQLI_ASSOC);
    }

    public function borrarPublicador(int $id): void
    {
        $this->exigirRol('administrador');
        // La integridad referencial impide borrar autores con avisos publicados.
        $consulta = $this->conexion->prepare('SELECT COUNT(*) FROM avisos WHERE usuario_id = ?');
        $consulta->bind_param('i', $id);
        $consulta->execute();
        $cantidadAvisos = (int) $consulta->get_result()->fetch_row()[0];
        $consulta->close();
        if ($cantidadAvisos > 0) {
            throw new RuntimeException('No se puede borrar un publicador que todavía tiene avisos.');
        }

        $stmt = $this->conexion->prepare(
            "DELETE FROM usuarios WHERE id = ? AND rol = 'publicador'"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $filasAfectadas = $stmt->affected_rows;
        $stmt->close();
        if ($filasAfectadas === 0) {
            throw new RuntimeException('No se encontró ese publicador.');
        }
    }

    private function publicadorActual(): Publicador
    {
        $this->exigirRol('publicador');
        $id = (int) $this->usuario()['id'];
        $stmt = $this->conexion->prepare(
            "SELECT id, nombre, email, telefono FROM usuarios
             WHERE id = ? AND rol = 'publicador' LIMIT 1"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $datos = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$datos) {
            throw new RuntimeException('El publicador no existe o ya no tiene permisos.');
        }

        return new Publicador((int) $datos['id'], $datos['nombre'], $datos['email'], $datos['telefono']);
    }

    // Transformamos el objeto del modelo en campos para la tabla avisos.
    private function datosAviso(array $datos): array
    {
        $autor = $this->publicadorActual();
        $fabrica = new FabricaDeAvisos();
        $objeto = $fabrica->fabricarAviso($datos, $autor);

        $aviso = [
            'tipo' => $objeto->getTipo(),
            'titulo' => $objeto->getTitulo(),
            'resaltado' => $objeto->estaResaltado() ? 1 : 0,
            'descripcion' => null,
            'empresa' => null,
            'sueldo' => null,
            'precio' => null,
            'direccion' => null,
            'tipo_inmueble' => null,
            'inmobiliaria' => null,
            'gastos_comunes' => null,
        ];

        if ($objeto instanceof AvisoLaboral) {
            $aviso['empresa'] = $objeto->getEmpresa();
            $aviso['descripcion'] = $objeto->getDescripcionCargo();
            $aviso['sueldo'] = $objeto->getSueldoMensual();
        } elseif ($objeto instanceof AvisoProducto) {
            $aviso['descripcion'] = $objeto->getDescripcionProducto();
            $aviso['precio'] = $objeto->getPrecio();
        } elseif ($objeto instanceof AvisoInmobiliario) {
            $aviso['direccion'] = $objeto->getDireccion();
            $aviso['tipo_inmueble'] = $objeto->getTipoInmueble();
            $aviso['inmobiliaria'] = $objeto->getInmobiliaria();
            // En esta práctica, la descripción opcional del inmueble está en el formulario.
            $descripcion = trim((string) ($datos['descripcion'] ?? ''));
            if (mb_strlen($descripcion) > 4000) {
                throw new InvalidArgumentException('La descripción del inmueble es demasiado larga.');
            }
            $aviso['descripcion'] = $descripcion === '' ? null : $descripcion;

            if ($objeto instanceof AvisoVenta) {
                $aviso['precio'] = $objeto->getPrecioVenta();
            } elseif ($objeto instanceof AvisoAlquiler) {
                $aviso['precio'] = $objeto->getAlquilerMensual();
                $aviso['gastos_comunes'] = $objeto->getGastosComunes();
            }
        }

        return $aviso;
    }

    public function guardarAviso(array $datos, ?int $id = null): void
    {
        $this->exigirRol('publicador');
        $usuarioId = (int) $this->usuario()['id'];

        // La consigna permite editar datos, pero prohíbe cambiar la categoría.
        // Validamos también en el servidor, aunque se modifique el campo oculto del formulario.
        if ($id !== null) {
            $consulta = $this->conexion->prepare(
                'SELECT tipo FROM avisos WHERE id = ? AND usuario_id = ? LIMIT 1'
            );
            $consulta->bind_param('ii', $id, $usuarioId);
            $consulta->execute();
            $existente = $consulta->get_result()->fetch_assoc();
            $consulta->close();
            if (!$existente) {
                throw new RuntimeException('El aviso no existe o no te pertenece.');
            }
            if ((string) ($datos['tipo'] ?? '') !== $existente['tipo']) {
                throw new InvalidArgumentException('No se puede cambiar la categoría de un aviso publicado.');
            }
        }

        $aviso = $this->datosAviso($datos);

        $tipo = $aviso['tipo'];
        $titulo = $aviso['titulo'];
        $resaltado = $aviso['resaltado'];
        $descripcion = $aviso['descripcion'];
        $empresa = $aviso['empresa'];
        $sueldo = $aviso['sueldo'];
        $precio = $aviso['precio'];
        $direccion = $aviso['direccion'];
        $tipoInmueble = $aviso['tipo_inmueble'];
        $inmobiliaria = $aviso['inmobiliaria'];
        $gastos = $aviso['gastos_comunes'];

        if ($id === null) {
            $stmt = $this->conexion->prepare(
                'INSERT INTO avisos
                (usuario_id, tipo, titulo, resaltado, descripcion, empresa, sueldo,
                 precio, direccion, tipo_inmueble, inmobiliaria, gastos_comunes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('ississddsssd', $usuarioId, $tipo, $titulo, $resaltado,
                $descripcion, $empresa, $sueldo, $precio, $direccion,
                $tipoInmueble, $inmobiliaria, $gastos);
        } else {
            $stmt = $this->conexion->prepare(
                'UPDATE avisos SET tipo = ?, titulo = ?, resaltado = ?, descripcion = ?,
                 empresa = ?, sueldo = ?, precio = ?, direccion = ?, tipo_inmueble = ?,
                 inmobiliaria = ?, gastos_comunes = ? WHERE id = ? AND usuario_id = ?'
            );
            $stmt->bind_param('ssissddsssdii', $tipo, $titulo, $resaltado,
                $descripcion, $empresa, $sueldo, $precio, $direccion, $tipoInmueble,
                $inmobiliaria, $gastos, $id, $usuarioId);
        }

        $stmt->execute();
        $filasAfectadas = $stmt->affected_rows;
        $stmt->close();
        if ($id !== null && $filasAfectadas === 0) {
            $existente = $this->buscarAviso($id);
            if (!$existente || (int) $existente['usuario_id'] !== $usuarioId) {
                throw new RuntimeException('El aviso no existe o no te pertenece.');
            }
        }
    }

    public function listarAvisos(): array
    {
        return $this->conexion->query(
            "SELECT a.*, u.nombre AS autor, u.telefono AS telefono_autor
             FROM avisos a JOIN usuarios u ON u.id = a.usuario_id
             ORDER BY FIELD(a.tipo, 'laboral', 'producto', 'venta', 'alquiler'),
                      a.fecha_creacion DESC, a.id DESC"
        )->fetch_all(MYSQLI_ASSOC);
    }

    public function listarMisAvisos(): array
    {
        $this->exigirRol('publicador');
        $id = (int) $this->usuario()['id'];
        $stmt = $this->conexion->prepare(
            'SELECT * FROM avisos WHERE usuario_id = ? ORDER BY fecha_creacion DESC, id DESC'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $avisos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $avisos;
    }

    public function buscarAviso(int $id): ?array
    {
        $stmt = $this->conexion->prepare('SELECT * FROM avisos WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $aviso = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $aviso ?: null;
    }

    public function borrarAviso(int $id): void
    {
        $usuario = $this->usuario();
        if (!$usuario) {
            throw new RuntimeException('Iniciá sesión para borrar un aviso.');
        }

        if ($usuario['rol'] === 'administrador') {
            $this->exigirRol('administrador');
            $stmt = $this->conexion->prepare('DELETE FROM avisos WHERE id = ?');
            $stmt->bind_param('i', $id);
        } else {
            $this->exigirRol('publicador');
            $usuarioId = (int) $usuario['id'];
            $stmt = $this->conexion->prepare('DELETE FROM avisos WHERE id = ? AND usuario_id = ?');
            $stmt->bind_param('ii', $id, $usuarioId);
        }
        $stmt->execute();
        if ($stmt->affected_rows === 0) {
            throw new RuntimeException('No se encontró un aviso que puedas eliminar.');
        }
        $stmt->close();
    }
}

// --------------------------------------------------------------------------
// Controlador: recibe acciones POST y utiliza la única clase Clasificados.
// --------------------------------------------------------------------------
$aplicacion = null;
$error = null;
$mensaje = $_SESSION['mensaje'] ?? null;
unset($_SESSION['mensaje']);
$seccion = (string) ($_GET['seccion'] ?? 'inicio');

try {
    $aplicacion = Clasificados::getInstancia();
    $instalado = $aplicacion->estaInstalado();

    // Primera visita local: crear todo automáticamente, sin borrar ninguna base existente.
    if (!$instalado && Clasificados::esLocal()) {
        $aplicacion->crearEstructura();
        $textoInstalacion = $aplicacion->crearUsuariosDemo();
        $mensaje = [
            'tipo' => 'exito',
            'texto' => 'Primera ejecución: base de datos creada. ' . $textoInstalacion,
        ];
        $instalado = true;
    }

    $hayAdmin = $instalado && $aplicacion->hayAdministrador();

    // Recuperación de una instalación local que quedó sin cuentas administradoras.
    if ($instalado && !$hayAdmin && Clasificados::esLocal()) {
        $aplicacion->crearUsuariosDemo();
        $hayAdmin = $aplicacion->hayAdministrador();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        Clasificados::verificarToken();
        $accion = (string) ($_POST['accion'] ?? '');
        $destino = 'inicio';

        switch ($accion) {
            case 'login':
                $aplicacion->iniciarSesion(
                    (string) ($_POST['email'] ?? ''), (string) ($_POST['clave'] ?? '')
                );
                $texto = 'Sesión iniciada correctamente.';
                $destino = $aplicacion->usuario()['rol'] === 'administrador' ? 'admin' : 'publicador';
                break;

            case 'salir':
                $aplicacion->cerrarSesion();
                $texto = 'Sesión cerrada.';
                break;

            case 'crear_publicador':
                $aplicacion->crearPublicador(
                    (string) ($_POST['nombre'] ?? ''), (string) ($_POST['email'] ?? ''),
                    (string) ($_POST['telefono'] ?? ''), (string) ($_POST['clave'] ?? '')
                );
                $texto = 'Publicador creado correctamente.';
                $destino = 'admin';
                break;

            case 'borrar_publicador':
                $aplicacion->borrarPublicador((int) ($_POST['id'] ?? 0));
                $texto = 'Publicador eliminado.';
                $destino = 'admin';
                break;

            case 'guardar_aviso':
                $id = (int) ($_POST['id'] ?? 0);
                $aplicacion->guardarAviso($_POST, $id > 0 ? $id : null);
                $texto = $id > 0 ? 'Aviso actualizado.' : 'Aviso publicado.';
                $destino = 'publicador';
                break;

            case 'borrar_aviso':
                $aplicacion->borrarAviso((int) ($_POST['id'] ?? 0));
                $texto = 'Aviso eliminado.';
                $destino = $aplicacion->usuario()['rol'] === 'administrador' ? 'admin' : 'publicador';
                break;

            case 'verificar_bd':
                $aplicacion->exigirRol('administrador');
                $texto = $aplicacion->crearEstructura();
                $destino = 'admin';
                break;

            case 'crear_usuarios_demo':
                $aplicacion->exigirRol('administrador');
                $texto = $aplicacion->crearUsuariosDemo();
                $destino = 'admin';
                break;

            case 'reiniciar_bd':
                $texto = $aplicacion->reiniciarBaseDemostracion(
                    (string) ($_POST['confirmacion'] ?? '')
                );
                $destino = 'login';
                break;

            default:
                throw new InvalidArgumentException('Acción desconocida.');
        }

        $_SESSION['mensaje'] = ['tipo' => 'exito', 'texto' => $texto];
        // Cada perfil vuelve a su propia página, tal como pide la consigna.
        $paginaDestino = match ($destino) {
            'admin' => 'admin.php',
            'publicador' => 'publicador.php',
            'login' => 'clasificados.php?seccion=login',
            default => 'clasificados.php',
        };
        header('Location: ' . $paginaDestino);
        exit;
    }
} catch (mysqli_sql_exception $e) {
    error_log((string) $e);
    $error = 'No se pudo completar la operación en MySQL. Revisá la conexión, los datos o los permisos.';
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$usuarioActual = null;
$instalado = false;
$hayAdmin = false;
try {
    if ($aplicacion) {
        $instalado = $aplicacion->estaInstalado();
        $hayAdmin = $instalado && $aplicacion->hayAdministrador();
        $usuarioActual = $instalado ? $aplicacion->usuario() : null;
    }
} catch (Throwable $e) {
    error_log((string) $e);
    $error = $error ?? 'No se pudo comprobar el estado de MySQL.';
}
$esLocal = Clasificados::esLocal();

$avisos = [];
$publicadores = [];
$formularioAviso = null;
$tipoFormulario = null;

if ($instalado && $hayAdmin && $aplicacion) {
    try {
        if ($seccion === 'inicio') {
            $avisos = $aplicacion->listarAvisos();
        } elseif ($seccion === 'publicador' && $usuarioActual && $usuarioActual['rol'] === 'publicador') {
            $avisos = $aplicacion->listarMisAvisos();
            $idEditar = (int) ($_GET['editar'] ?? 0);
            if ($idEditar > 0) {
                $formularioAviso = $aplicacion->buscarAviso($idEditar);
                if (!$formularioAviso || (int) $formularioAviso['usuario_id'] !== $usuarioActual['id']) {
                    $formularioAviso = null;
                    $error = 'No podés modificar ese aviso.';
                }
            }
            $tipoFormulario = $formularioAviso['tipo'] ?? ($_GET['nuevo'] ?? null);
        } elseif ($seccion === 'admin' && $usuarioActual && $usuarioActual['rol'] === 'administrador') {
            $publicadores = $aplicacion->listarPublicadores();
            $avisos = $aplicacion->listarAvisos();
        }
    } catch (mysqli_sql_exception $e) {
        error_log((string) $e);
        $error = 'No fue posible consultar los registros.';
    }
}

$tiposValidos = ['laboral', 'producto', 'venta', 'alquiler'];
if (!in_array($tipoFormulario, $tiposValidos, true)) {
    $tipoFormulario = null;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Avisos clasificados · Práctica PHP</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>
<header>
    <nav class="contenedor navegacion">
        <a class="marca" href="clasificados.php">Clasificados TC2</a>
        <div class="enlaces">
            <a href="clasificados.php">Ver avisos</a>
            <a href="prueba_herencia.php">Ejemplo de herencia</a>
            <?php if ($usuarioActual && $usuarioActual['rol'] === 'publicador'): ?>
                <a href="publicador.php">Mis avisos</a>
            <?php elseif ($usuarioActual && $usuarioActual['rol'] === 'administrador'): ?>
                <a href="admin.php">Administración</a>
            <?php endif; ?>
            <?php if ($usuarioActual): ?>
                <span>Hola, <?= Clasificados::escapar($usuarioActual['nombre']) ?></span>
                <form method="post" class="form-linea">
                    <input type="hidden" name="token" value="<?= Clasificados::escapar(Clasificados::token()) ?>">
                    <input type="hidden" name="accion" value="salir">
                    <button class="boton secundario" type="submit">Salir</button>
                </form>
            <?php else: ?>
                <a href="clasificados.php?seccion=login" class="boton">Ingresar</a>
            <?php endif; ?>
        </div>
    </nav>
</header>

<main class="contenedor">
    <?php if ($mensaje): ?>
        <p class="alerta <?= Clasificados::escapar($mensaje['tipo']) ?>"><?= Clasificados::escapar($mensaje['texto']) ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
        <p class="alerta error"><?= Clasificados::escapar($error) ?></p>
    <?php endif; ?>

    <?php if (!$aplicacion): ?>
        <section class="panel">
            <h1>No hay conexión con MySQL</h1>
            <p>Iniciá MySQL en XAMPP y revisá el usuario y contraseña de la conexión.</p>
        </section>

    <?php elseif (!$hayAdmin): ?>
        <section class="panel estrecho">
            <h1>Instalación pendiente</h1>
            <p>Esta versión crea automáticamente la base y las cuentas de prueba cuando se abre desde localhost.</p>
            <?php if (!$esLocal): ?>
                <p class="alerta error">Por seguridad, ingresá desde localhost para completar la instalación.</p>
            <?php else: ?>
                <p>Comprobá que MySQL esté funcionando y recargá la página.</p>
            <?php endif; ?>
        </section>

    <?php elseif ($seccion === 'login' && !$usuarioActual): ?>
        <section class="panel estrecho">
            <h1>Iniciar sesión</h1>
            <form method="post">
                <input type="hidden" name="accion" value="login">
                <input type="hidden" name="token" value="<?= Clasificados::escapar(Clasificados::token()) ?>">
                <label>Correo electrónico<input name="email" type="email" required></label>
                <label>Contraseña<input name="clave" type="password" required></label>
                <button class="boton" type="submit">Ingresar</button>
            </form>
            <?php if ($esLocal): ?>
                <div class="alerta info">
                    <strong>Cuentas iniciales para las pruebas:</strong><br>
                    Administrador: <code><?= Clasificados::escapar(Clasificados::EMAIL_ADMIN_DEMO) ?></code><br>
                    Contraseña: <code><?= Clasificados::escapar(Clasificados::CLAVE_ADMIN_DEMO) ?></code><br><br>
                    Publicador: <code><?= Clasificados::escapar(Clasificados::EMAIL_PUBLICADOR_DEMO) ?></code><br>
                    Contraseña: <code><?= Clasificados::escapar(Clasificados::CLAVE_PUBLICADOR_DEMO) ?></code>
                </div>
            <?php endif; ?>
        </section>

    <?php elseif ($seccion === 'publicador' && $usuarioActual && $usuarioActual['rol'] === 'publicador'): ?>
        <h1>Mis avisos publicados</h1>
        <p>Solo podés modificar o eliminar tus propios avisos.</p>
        <div class="fila-botones">
            <a class="boton" href="publicador.php?nuevo=laboral">Nuevo aviso laboral</a>
            <a class="boton" href="publicador.php?nuevo=producto">Nuevo producto</a>
            <a class="boton" href="publicador.php?nuevo=venta">Nueva venta</a>
            <a class="boton" href="publicador.php?nuevo=alquiler">Nuevo alquiler</a>
        </div>

        <?php if ($tipoFormulario): ?>
            <section class="panel">
                <h2><?= $formularioAviso ? 'Modificar aviso' : 'Crear aviso' ?>: <?= Clasificados::escapar(ucfirst($tipoFormulario)) ?></h2>
                <form method="post">
                    <input type="hidden" name="accion" value="guardar_aviso">
                    <input type="hidden" name="token" value="<?= Clasificados::escapar(Clasificados::token()) ?>">
                    <input type="hidden" name="id" value="<?= (int) ($formularioAviso['id'] ?? 0) ?>">
                    <input type="hidden" name="tipo" value="<?= Clasificados::escapar($tipoFormulario) ?>">
                    <label>Título<input name="titulo" required maxlength="160"
                        value="<?= Clasificados::escapar($formularioAviso['titulo'] ?? '') ?>"></label>

                    <?php if ($tipoFormulario === 'laboral'): ?>
                        <label>Empresa<input name="empresa" required maxlength="160"
                            value="<?= Clasificados::escapar($formularioAviso['empresa'] ?? '') ?>"></label>
                        <label>Descripción del cargo<textarea name="descripcion" required><?= Clasificados::escapar($formularioAviso['descripcion'] ?? '') ?></textarea></label>
                        <label>Sueldo mensual (opcional)<input name="sueldo" type="number" min="0.01" step="0.01"
                            value="<?= Clasificados::escapar($formularioAviso['sueldo'] ?? '') ?>"></label>
                    <?php elseif ($tipoFormulario === 'producto'): ?>
                        <label>Descripción del producto<textarea name="descripcion" required><?= Clasificados::escapar($formularioAviso['descripcion'] ?? '') ?></textarea></label>
                        <label>Precio<input name="precio" type="number" min="0.01" step="0.01" required
                            value="<?= Clasificados::escapar($formularioAviso['precio'] ?? '') ?>"></label>
                    <?php else: ?>
                        <label>Dirección<input name="direccion" required maxlength="160"
                            value="<?= Clasificados::escapar($formularioAviso['direccion'] ?? '') ?>"></label>
                        <label>Tipo de inmueble
                            <select name="tipo_inmueble" required>
                                <option value="casa" <?= ($formularioAviso['tipo_inmueble'] ?? '') === 'casa' ? 'selected' : '' ?>>Casa</option>
                                <option value="apartamento" <?= ($formularioAviso['tipo_inmueble'] ?? '') === 'apartamento' ? 'selected' : '' ?>>Apartamento</option>
                            </select>
                        </label>
                        <label>Inmobiliaria<input name="inmobiliaria" required maxlength="160"
                            value="<?= Clasificados::escapar($formularioAviso['inmobiliaria'] ?? '') ?>"></label>
                        <label>Descripción (opcional)<textarea name="descripcion"><?= Clasificados::escapar($formularioAviso['descripcion'] ?? '') ?></textarea></label>
                        <label><?= $tipoFormulario === 'venta' ? 'Precio de venta' : 'Alquiler mensual' ?>
                            <input name="precio" type="number" min="0.01" step="0.01" required
                                value="<?= Clasificados::escapar($formularioAviso['precio'] ?? '') ?>"></label>
                        <?php if ($tipoFormulario === 'alquiler'): ?>
                            <label>Gastos comunes<input name="gastos_comunes" type="number" min="0" step="0.01" required
                                value="<?= Clasificados::escapar($formularioAviso['gastos_comunes'] ?? '0') ?>"></label>
                        <?php endif; ?>
                    <?php endif; ?>
                    <label class="casilla"><input type="checkbox" name="resaltado" value="1"
                        <?= !empty($formularioAviso['resaltado']) ? 'checked' : '' ?>> Destacar aviso (simula el pago doble)</label>
                    <button class="boton" type="submit"><?= $formularioAviso ? 'Guardar cambios' : 'Publicar aviso' ?></button>
                    <a href="publicador.php" class="boton secundario">Cancelar</a>
                </form>
            </section>
        <?php endif; ?>

        <div class="tarjetas">
            <?php foreach ($avisos as $aviso): ?>
                <article class="tarjeta <?= $aviso['resaltado'] ? 'destacada' : '' ?>">
                    <span class="etiqueta"><?= Clasificados::escapar(ucfirst($aviso['tipo'])) ?></span>
                    <h3><?= Clasificados::escapar($aviso['titulo']) ?></h3>
                    <p><?= Clasificados::escapar($aviso['descripcion'] ?? $aviso['direccion']) ?></p>
                    <div class="acciones">
                        <a class="boton secundario" href="publicador.php?editar=<?= (int) $aviso['id'] ?>">Modificar</a>
                        <form method="post" onsubmit="return confirm('¿Borrar este aviso?');">
                            <input type="hidden" name="accion" value="borrar_aviso">
                            <input type="hidden" name="token" value="<?= Clasificados::escapar(Clasificados::token()) ?>">
                            <input type="hidden" name="id" value="<?= (int) $aviso['id'] ?>">
                            <button class="boton peligro" type="submit">Borrar</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <?php if (!$avisos): ?><p>Aún no publicaste avisos.</p><?php endif; ?>

    <?php elseif ($seccion === 'admin' && $usuarioActual && $usuarioActual['rol'] === 'administrador'): ?>
        <h1>Panel del administrador</h1>
        <div class="columnas">
            <section class="panel">
                <h2>Crear publicador</h2>
                <form method="post">
                    <input type="hidden" name="accion" value="crear_publicador">
                    <input type="hidden" name="token" value="<?= Clasificados::escapar(Clasificados::token()) ?>">
                    <label>Nombre<input name="nombre" required maxlength="100"></label>
                    <label>Correo<input name="email" type="email" required></label>
                    <label>Teléfono<input name="telefono" required maxlength="30"></label>
                    <label>Contraseña inicial<input name="clave" type="password" required minlength="8"></label>
                    <button class="boton" type="submit">Crear usuario</button>
                </form>
            </section>
            <section class="panel">
                <h2>Base de datos de prueba</h2>
                <p>Nombre: <code>clasificados_poo_demo</code></p>
                <p>Podés verificar que la base y sus tablas existan sin eliminar información.</p>
                <form method="post">
                    <input type="hidden" name="accion" value="verificar_bd">
                    <input type="hidden" name="token" value="<?= Clasificados::escapar(Clasificados::token()) ?>">
                    <button class="boton" type="submit">Crear / verificar tablas</button>
                </form>
                <?php if ($esLocal): ?>
                    <p>También podés restaurar las dos cuentas de prueba si alguna falta.</p>
                    <form method="post">
                        <input type="hidden" name="accion" value="crear_usuarios_demo">
                        <input type="hidden" name="token" value="<?= Clasificados::escapar(Clasificados::token()) ?>">
                        <button class="boton secundario" type="submit">Crear / verificar usuarios demo</button>
                    </form>
                <?php endif; ?>
            </section>
        </div>

        <?php if ($esLocal): ?>
            <section class="panel zona-peligro">
                <h2>Reiniciar la base de demostración</h2>
                <p><strong>Atención:</strong> esta operación elimina definitivamente los usuarios y avisos de
                    <code>clasificados_poo_demo</code>. Después vuelve a crear las tablas y las dos cuentas
                    de prueba. No afecta a <code>clasificados_unificado</code> ni a
                    <code>clasificados_herencia_paso1</code>.</p>
                <p>Tu sesión se cerrará y tendrás que ingresar otra vez.</p>
                <form method="post" onsubmit="return confirm('ATENCIÓN: se eliminarán TODOS los avisos y usuarios de clasificados_poo_demo. ¿Confirmás?');">
                    <input type="hidden" name="accion" value="reiniciar_bd">
                    <input type="hidden" name="token" value="<?= Clasificados::escapar(Clasificados::token()) ?>">
                    <label>Para confirmar, escribí REINICIAR
                        <input type="text" name="confirmacion" required pattern="REINICIAR" autocomplete="off" placeholder="REINICIAR">
                    </label>
                    <button class="boton peligro" type="submit">Eliminar y reconstruir base demo</button>
                </form>
            </section>
        <?php endif; ?>

        <section class="panel">
            <h2>Publicadores registrados</h2>
            <?php foreach ($publicadores as $publicador): ?>
                <div class="fila">
                    <span><?= Clasificados::escapar($publicador['nombre']) ?> — <?= Clasificados::escapar($publicador['email']) ?></span>
                    <form method="post" onsubmit="return confirm('Solo se puede borrar si no tiene avisos. ¿Continuar?');">
                        <input type="hidden" name="accion" value="borrar_publicador">
                        <input type="hidden" name="token" value="<?= Clasificados::escapar(Clasificados::token()) ?>">
                        <input type="hidden" name="id" value="<?= (int) $publicador['id'] ?>">
                        <button class="boton peligro" type="submit">Borrar</button>
                    </form>
                </div>
            <?php endforeach; ?>
            <?php if (!$publicadores): ?><p>No hay publicadores registrados.</p><?php endif; ?>
        </section>

        <h2>Todos los avisos</h2>
        <div class="tarjetas">
            <?php foreach ($avisos as $aviso): ?>
                <article class="tarjeta <?= $aviso['resaltado'] ? 'destacada' : '' ?>">
                    <span class="etiqueta"><?= Clasificados::escapar(ucfirst($aviso['tipo'])) ?></span>
                    <h3><?= Clasificados::escapar($aviso['titulo']) ?></h3>
                    <p>Publicado por <?= Clasificados::escapar($aviso['autor']) ?></p>
                    <form method="post" onsubmit="return confirm('¿Borrar este aviso?');">
                        <input type="hidden" name="accion" value="borrar_aviso">
                        <input type="hidden" name="token" value="<?= Clasificados::escapar(Clasificados::token()) ?>">
                        <input type="hidden" name="id" value="<?= (int) $aviso['id'] ?>">
                        <button class="boton peligro" type="submit">Borrar aviso</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>

    <?php elseif ($seccion === 'inicio'): ?>
        <section class="portada">
            <h1>Avisos clasificados</h1>
            <p>Ofertas de trabajo, productos, ventas y alquileres.</p>
            <p>Los avisos están ordenados por categoría y fecha.</p>
        </section>
        <div class="tarjetas">
            <?php foreach ($avisos as $aviso): ?>
                <article class="tarjeta <?= $aviso['resaltado'] ? 'destacada' : '' ?>">
                    <span class="etiqueta"><?= Clasificados::escapar(ucfirst($aviso['tipo'])) ?></span>
                    <?php if ($aviso['resaltado']): ?><span class="etiqueta oro">Destacado</span><?php endif; ?>
                    <h2><?= Clasificados::escapar($aviso['titulo']) ?></h2>
                    <?php if ($aviso['empresa']): ?><p>Empresa: <?= Clasificados::escapar($aviso['empresa']) ?></p><?php endif; ?>
                    <?php if ($aviso['direccion']): ?><p>Dirección: <?= Clasificados::escapar($aviso['direccion']) ?></p><?php endif; ?>
                    <?php if ($aviso['descripcion']): ?><p><?= nl2br(Clasificados::escapar($aviso['descripcion'])) ?></p><?php endif; ?>
                    <?php if ($aviso['sueldo'] !== null): ?><p>Sueldo: $<?= Clasificados::escapar($aviso['sueldo']) ?></p><?php endif; ?>
                    <?php if ($aviso['precio'] !== null): ?><p>Precio: $<?= Clasificados::escapar($aviso['precio']) ?></p><?php endif; ?>
                    <?php if ($aviso['gastos_comunes'] !== null): ?><p>Gastos comunes: $<?= Clasificados::escapar($aviso['gastos_comunes']) ?></p><?php endif; ?>
                    <small>Contacto: <?= Clasificados::escapar($aviso['autor']) ?> · <?= Clasificados::escapar($aviso['telefono_autor']) ?></small><br>
                    <small>Publicado: <?= Clasificados::escapar($aviso['fecha_creacion']) ?></small>
                </article>
            <?php endforeach; ?>
        </div>
        <?php if (!$avisos): ?><p>Todavía no hay avisos publicados.</p><?php endif; ?>
    <?php else: ?>
        <section class="panel estrecho">
            <h1>Acceso restringido</h1>
            <p>Ingresá con una cuenta que tenga permiso para acceder a esta sección.</p>
            <a href="clasificados.php?seccion=login" class="boton">Iniciar sesión</a>
        </section>
    <?php endif; ?>
</main>
<footer>Proyecto integrador TC2 · POO, Factory Method, MySQLi y base de demostración</footer>
</body>
</html>
