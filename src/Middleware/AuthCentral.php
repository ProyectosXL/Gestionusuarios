<?php
namespace GestionUsuarios\Middleware;

use GestionUsuarios\Config\Database;

class AuthCentral {
    public static function init() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function user() {
        self::init();
        if (isset($_SESSION['fp_auth_user'])) {
            return $_SESSION['fp_auth_user'];
        }

        $username = $_SESSION['username'] ?? $_SESSION['usuario'] ?? $_SESSION['nodo_usuario_activo'] ?? null;
        if (!$username) return null;

        $db = Database::getInstance()->connectApps();
        $sql = "SELECT u.id, u.username, u.password_plain, u.nombre_completo, u.email, u.tipo, 
                       u.permisos_legacy, u.cod_client, u.nro_sucursal, u.nro_sucurs, u.cod_vendedor, u.activo, u.rol_id, u.sector_id,
                       u.usuario_tango,
                       s.codigo as sector_codigo, s.nombre as sector_nombre,
                       r.nombre as rol_nombre, r.codigo as rol_codigo, r.es_admin
                FROM FP_SOF_USUARIOS u 
                LEFT JOIN FP_SECTORES s ON s.id = u.sector_id
                LEFT JOIN FP_ROLES_PERMISOS_MAP r ON r.id = u.rol_id
                WHERE u.username = ? AND u.activo = 1";
        
        $stmt = sqlsrv_query($db, $sql, [$username]);
        if ($stmt && ($user = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
            $esSectorProyectos = ((int)($user['sector_id'] ?? 0) === 7)
                || (strtoupper(trim($user['sector_codigo'] ?? '')) === 'PROYECTOS')
                || (stripos($user['sector_nombre'] ?? '', 'Proyectos') !== false);

            $esAdmin = $esSectorProyectos && (
                !empty($user['es_admin']) 
                || (int)($user['es_admin'] ?? 0) === 1
                || (int)($user['rol_id'] ?? 0) === 1
                || strtoupper(trim($user['rol_codigo'] ?? '')) === 'CONTROLTOTAL'
                || strtoupper(trim($user['username'] ?? '')) === 'FRANCOPERTUS'
            );

            $_SESSION['fp_auth_user'] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'nombre' => $user['nombre_completo'],
                'tipo' => $user['tipo'],
                'permisos_legacy' => $user['permisos_legacy'],
                'cod_client' => $user['cod_client'],
                'nro_sucursal' => $user['nro_sucursal'] ?? $user['nro_sucurs'],
                'cod_vendedor' => $user['cod_vendedor'],
                'rol_id' => $user['rol_id'],
                'sector_id' => $user['sector_id'],
                'sector_nombre' => $user['sector_nombre'],
                'rol_nombre' => $user['rol_nombre'],
                'usuario_tango' => $user['usuario_tango'] ?? null,
                'es_admin' => $esAdmin
            ];
            return $_SESSION['fp_auth_user'];
        }

        return null;
    }

    public static function check() {
        return self::user() !== null;
    }

    public static function requireAdmin() {
        $user = self::user();
        if (!$user) {
            header('Location: /sistemas/login.php');
            exit;
        }

        if (empty($user['es_admin'])) {
            http_response_code(403);
            echo '<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Restringido · XL Centralizador</title>
    <link rel="shortcut icon" href="/sistemas/assets/css/icono.jpg" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: "Plus Jakarta Sans", sans-serif;
            background-color: #f8fafc;
            background-image: 
                radial-gradient(at 100% 0%, rgba(99, 102, 241, 0.08) 0px, transparent 50%),
                radial-gradient(at 0% 100%, rgba(239, 68, 68, 0.05) 0px, transparent 50%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            color: #1e293b;
            margin: 0;
        }
        .error-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            padding: 2.75rem 2.25rem;
            max-width: 480px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 25px -5px rgba(15, 23, 42, 0.05), 0 8px 10px -6px rgba(15, 23, 42, 0.02);
            position: relative;
        }
        .error-icon-wrapper {
            width: 72px;
            height: 72px;
            border-radius: 20px;
            background: #fef2f2;
            color: #ef4444;
            border: 1px solid #fee2e2;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 1.5rem auto;
            box-shadow: 0 10px 15px -3px rgba(239, 68, 68, 0.15);
        }
        .error-title {
            font-size: 1.45rem;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.5px;
            margin-bottom: 0.6rem;
        }
        .error-desc {
            color: #64748b;
            font-size: 0.95rem;
            line-height: 1.55;
            margin-bottom: 2rem;
        }
        .btn-home {
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
            color: #ffffff;
            font-size: 0.92rem;
            font-weight: 700;
            padding: 0.8rem 1.6rem;
            border-radius: 14px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
            transition: all 0.2s ease;
        }
        .btn-home:hover {
            background: linear-gradient(135deg, #4338ca 0%, #4f46e5 100%);
            color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(79, 70, 229, 0.35);
        }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="error-icon-wrapper">
            <i class="fa-solid fa-lock"></i>
        </div>
        <h1 class="error-title">Acceso Restringido</h1>
        <p class="error-desc">El módulo de <strong>Gestión de Usuarios & Roles</strong> está reservado exclusivamente para el sector de <strong>Proyectos e Innovación</strong>.</p>
        <div>
            <a href="/sistemas/hub/index.php" class="btn-home">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Volver al Menú Central</span>
            </a>
        </div>
    </div>
</body>
</html>';
            exit;
        }
    }

    public static function logout() {
        self::init();
        unset($_SESSION['fp_auth_user']);
    }
}