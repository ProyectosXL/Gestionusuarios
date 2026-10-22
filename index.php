<?php
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/src/Models/Usuario.php';
require_once __DIR__ . '/src/Models/Estructura.php';
require_once __DIR__ . '/src/Middleware/AuthCentral.php';

use GestionUsuarios\Models\Usuario;
use GestionUsuarios\Models\Estructura;
use GestionUsuarios\Middleware\AuthCentral;

AuthCentral::init();
AuthCentral::requireAdmin();

$usuarioModel = new Usuario();
$estructuraModel = new Estructura();

// Sembrar iniciales solo si se solicita explícitamente para mantenimiento
if (isset($_GET['seed']) && $_GET['seed'] === '1') {
    $estructuraModel->sembrarSectoresIniciales();
}

$tab = $_GET['tab'] ?? 'usuarios';
$mensaje = '';
$tipoMensaje = 'success';

// Función auxiliar para determinar URL/Destino del login según el usuario
function calcularDestinoLogin($u) {
    $username = strtoupper(trim($u['username'] ?? ''));
    $tipo = strtoupper(trim($u['tipo'] ?? ''));
    $cat = strtoupper(trim($u['categoria_usuario'] ?? 'PERSONAL'));
    $codVend = trim($u['cod_vendedor'] ?? '');
    $nroSuc = $u['nro_sucursal'] ?? null;
    $rol = strtoupper(trim($u['rol_nombre'] ?? ''));

    if ($username === 'FRANCOPERTUS' || strpos($rol, 'ADMIN') !== false || strpos($rol, 'PROYECTOS') !== false) {
        return [
            'nombre' => 'Centralizador Hub (NODO / Identity)',
            'url' => '/sistemas/hub/index.php',
            'badge' => 'bg-indigo-50 text-indigo-700 border-indigo-200'
        ];
    }
    if ($tipo === 'GRUPO' || $cat === 'GRUPO' || strpos($rol, 'GRUPO') !== false) {
        return [
            'nombre' => 'Portal Franquicias Grupo',
            'url' => '/ppp/franquicias/grupo/index.php',
            'badge' => 'bg-amber-50 text-amber-800 border-amber-200'
        ];
    }
    if (strpos($rol, 'CONTEO') !== false || strpos($username, 'CONTEO') !== false || in_array((string)($u['permisos_legacy'] ?? ''), ['5', '6'])) {
        return [
            'nombre' => 'Sistema de Conteos / Auditoría',
            'url' => '/sistemas/conteos/index.php',
            'badge' => 'bg-rose-50 text-rose-700 border-rose-200'
        ];
    }
    if ($cat === 'MAYORISTA' || $tipo === 'MAYORISTA' || (!empty($codVend) && $codVend !== '0') || strpos($rol, 'MAYORISTA') !== false) {
        return [
            'nombre' => 'Portal Clientes Mayoristas B2B',
            'url' => '/sistemas/mayoristas/index.php',
            'badge' => 'bg-teal-50 text-teal-800 border-teal-200'
        ];
    }
    if ($cat === 'PERSONAL' && ($tipo === 'SUPERVISION' || strpos($rol, 'SUPERVISOR') !== false)) {
        return [
            'nombre' => 'Centralizador Hub (NODO / Comerciales)',
            'url' => '/sistemas/hub/index.php',
            'badge' => 'bg-indigo-50 text-indigo-700 border-indigo-200'
        ];
    }
    if ($tipo === 'SUPERVISION' || strpos($rol, 'SUPERVISOR') !== false || in_array($username, ['SUPERVISORAS', 'COMERCIAL', 'COMERCIAL2'])) {
        return [
            'nombre' => 'Estadísticas Comerciales',
            'url' => '/comercial/estadisticas/index.php',
            'badge' => 'bg-purple-50 text-purple-700 border-purple-200'
        ];
    }
    if ($cat === 'PERSONAL' && ($tipo === 'GERENCIA' || strpos($rol, 'GERENCIA') !== false || strpos($rol, 'DIRECCI') !== false)) {
        return [
            'nombre' => 'Centralizador Hub (NODO / Dirección)',
            'url' => '/sistemas/hub/index.php',
            'badge' => 'bg-indigo-50 text-indigo-700 border-indigo-200'
        ];
    }
    if ($tipo === 'GERENCIA' || strpos($rol, 'GERENCIA') !== false || strpos($rol, 'DIRECCI') !== false) {
        return [
            'nombre' => 'Portal Gerencia / PPP',
            'url' => '/ppp/index.php',
            'badge' => 'bg-sky-50 text-sky-700 border-sky-200'
        ];
    }
    if ($username === 'LOGISTICA_CORP' || strpos($rol, 'LOGISTICA') !== false) {
        return [
            'nombre' => 'Control Logística Operativa',
            'url' => '/sistemas/control/control_logistica.php',
            'badge' => 'bg-cyan-50 text-cyan-800 border-cyan-200'
        ];
    }
    if ($cat === 'LOCAL') {
        return [
            'nombre' => 'Terminal Local / Lakers',
            'url' => '/sistemas/eliminaPedido.php',
            'badge' => 'bg-blue-50 text-blue-700 border-blue-200'
        ];
    }
    return [
        'nombre' => 'Hub Central / NODO',
        'url' => '/sistemas/hub/index.php',
        'badge' => 'bg-slate-50 text-slate-700 border-slate-200'
    ];
}

// ==================== EXPORTACIÓN SEGURA DE CREDENCIALES EN EXCEL / CSV ====================
if (isset($_GET['action']) && $_GET['action'] === 'exportar_excel_usuarios') {
    $busqueda = trim($_GET['q'] ?? '');
    $tipo = trim($_GET['tipo'] ?? '');
    $sectorFiltro = trim($_GET['sec'] ?? '');
    $categoriaFiltro = trim($_GET['cat'] ?? '');
    $rolFiltro = trim($_GET['rol'] ?? '');
    $activoFiltro = isset($_GET['act']) && $_GET['act'] !== '' ? trim($_GET['act']) : '';

    $usuariosExport = $usuarioModel->listar($busqueda, $tipo, $sectorFiltro, $categoriaFiltro, $rolFiltro, 10000, 0, $activoFiltro);

    $filename = "Credenciales_Usuarios_XL_" . date('Y-m-d_His') . ".csv";
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // UTF-8 BOM para apertura perfecta y nativa en Excel en español
    echo "\xEF\xBB\xBF";

    $output = fopen('php://output', 'w');
    fputcsv($output, [
        'ID',
        'Usuario (Login)',
        'Contraseña (SQL)',
        'Nombre Completo / Razón Social',
        'Email',
        'Categoría',
        'Sector',
        'Subsector',
        'Rol Operativo',
        'Tipo / Alcance',
        'Cód. Cliente',
        'Nro Sucursal',
        'Sucursales Asignadas',
        'Legajo',
        'Cód. Vendedor (Vend)',
        'Portal de Acceso / URL de Ingreso',
        'Estado Login'
    ], ';');

    foreach ($usuariosExport as $u) {
        $destino = calcularDestinoLogin($u);
        $passVal = $u['password'] ?: $u['password_plain'] ?: '';
        fputcsv($output, [
            $u['id'],
            $u['username'],
            $passVal,
            $u['nombre_completo'] ?: '',
            $u['email'] ?: '',
            $u['categoria_usuario'] ?: 'PERSONAL',
            $u['sector_nombre'] ?: '',
            $u['subsector_nombre'] ?: '',
            $u['rol_nombre'] ?: '',
            $u['tipo'] ?: '',
            $u['cod_client'] ?: '',
            $u['nro_sucursal'] ?: '',
            $u['nro_sucurs'] ?: '',
            $u['legajo'] ?: '',
            $u['cod_vendedor'] ?: '',
            $destino['nombre'] . ' (' . $destino['url'] . ')',
            ($u['activo'] == 1) ? 'HABILITADO' : 'INACTIVO'
        ], ';');
    }

    fclose($output);
    exit;
}

// ==================== PROCESAMIENTO DE PETICIONES AJAX ====================
if ((isset($_POST['ajax']) && $_POST['ajax'] == '1') || (isset($_GET['ajax']) && $_GET['ajax'] == '1')) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    // 4. Toggle Permiso Hub Rol (Switch Individual)
    if ($action === 'toggle_permiso_hub_rol') {
        $rolId = (int)($_POST['rol_id'] ?? 0);
        $permisoId = (int)($_POST['permiso_id'] ?? 0);
        $activo = (int)($_POST['activo'] ?? 0);
        try {
            $ok = $estructuraModel->togglePermisoHubRol($rolId, $permisoId, $activo);
            echo json_encode(['ok' => $ok ? true : false, 'mensaje' => $activo ? 'Acceso habilitado' : 'Acceso revocado']);
        } catch (\Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    // 5. Guardar Todos los Permisos Hub de un Rol
    elseif ($action === 'guardar_permisos_hub_rol') {
        $rolId = (int)($_POST['rol_id'] ?? 0);
        $permisoIds = $_POST['permiso_ids'] ?? [];
        if (!is_array($permisoIds)) $permisoIds = [];
        try {
            $ok = $estructuraModel->guardarPermisosHubRol($rolId, $permisoIds);
            echo json_encode(['ok' => $ok ? true : false, 'mensaje' => 'Configuración de accesos guardada exitosamente']);
        } catch (\Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    // 6. Copiar Permisos Hub entre Roles
    elseif ($action === 'copiar_permisos_hub_rol') {
        $rolOrigenId = (int)($_POST['rol_origen_id'] ?? 0);
        $rolDestinoIds = $_POST['rol_destino_ids'] ?? [];
        if (!is_array($rolDestinoIds)) {
            if (!empty($_POST['rol_destino_id'])) {
                $rolDestinoIds = [(int)$_POST['rol_destino_id']];
            } else {
                $rolDestinoIds = [];
            }
        }
        try {
            $afectados = 0;
            foreach ($rolDestinoIds as $rDestId) {
                if ($rDestId > 0 && $rDestId != $rolOrigenId) {
                    $estructuraModel->copiarPermisosHubRol($rolOrigenId, $rDestId);
                    $afectados++;
                }
            }
            echo json_encode(['ok' => true, 'mensaje' => "Accesos copiados a $afectados roles con éxito"]);
        } catch (\Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    // 7. Aplicar Permisos Hub a todo un Subsector
    elseif ($action === 'aplicar_permisos_hub_subsector') {
        $subsectorId = (int)($_POST['subsector_id'] ?? 0);
        $permisoIds = $_POST['permiso_ids'] ?? [];
        if (!is_array($permisoIds)) $permisoIds = [];
        try {
            $ok = $estructuraModel->aplicarPermisosHubSubsector($subsectorId, $permisoIds);
            echo json_encode(['ok' => $ok ? true : false, 'mensaje' => 'Accesos aplicados a todos los roles del subsector']);
        } catch (\Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    // 8. Obtener Submódulos / Menús de un Centralizador para un Rol
    elseif ($action === 'obtener_submodulos_centralizador') {
        $moduloId = (int)($_POST['modulo_id'] ?? 0);
        $rolId = (int)($_POST['rol_id'] ?? 0);
        try {
            $data = $estructuraModel->obtenerSubmodulosCentralizador($moduloId, $rolId);
            if ($data) {
                echo json_encode(['ok' => true, 'data' => $data]);
            } else {
                echo json_encode(['ok' => false, 'error' => 'No se encontró el módulo solicitado']);
            }
        } catch (\Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    // 9. Guardar Submódulos / Menús de un Centralizador para un Rol
    elseif ($action === 'guardar_submodulos_centralizador') {
        $moduloId = (int)($_POST['modulo_id'] ?? 0);
        $rolId = (int)($_POST['rol_id'] ?? 0);
        $permisoIds = $_POST['permiso_ids'] ?? [];
        if (!is_array($permisoIds)) $permisoIds = [];
        try {
            $ok = $estructuraModel->guardarSubmodulosRol($rolId, $moduloId, $permisoIds);
            echo json_encode(['ok' => $ok ? true : false, 'mensaje' => 'Accesos internos guardados correctamente']);
        } catch (\Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    // 10. Obtener Datos de un Módulo
    elseif ($action === 'obtener_modulo') {
        $moduloId = (int)($_POST['modulo_id'] ?? 0);
        try {
            $m = $estructuraModel->obtenerModulo($moduloId);
            if ($m) {
                echo json_encode(['ok' => true, 'modulo' => $m]);
            } else {
                echo json_encode(['ok' => false, 'error' => 'Módulo no encontrado']);
            }
        } catch (\Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    // 11. Listar Permisos / Solapas de un Módulo
    elseif ($action === 'listar_permisos_modulo') {
        $moduloId = (int)($_POST['modulo_id'] ?? 0);
        try {
            $modulo = $estructuraModel->obtenerModulo($moduloId);
            $permisos = $estructuraModel->listarPermisosDeModulo($moduloId);
            echo json_encode(['ok' => true, 'modulo' => $modulo, 'permisos' => $permisos]);
        } catch (\Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    // 12. Crear Permiso / Solapa en Módulo
    elseif ($action === 'crear_permiso_modulo') {
        $moduloId = (int)($_POST['modulo_id'] ?? 0);
        $clave = trim($_POST['clave'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $seccion = trim($_POST['seccion'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        try {
            $estructuraModel->crearPermisoModulo($moduloId, $clave, $nombre, $seccion, $descripcion);
            $permisos = $estructuraModel->listarPermisosDeModulo($moduloId);
            echo json_encode(['ok' => true, 'mensaje' => 'Solapa/Permiso agregado correctamente', 'permisos' => $permisos]);
        } catch (\Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    // 13. Actualizar Permiso / Solapa en Módulo
    elseif ($action === 'actualizar_permiso_modulo') {
        $moduloId = (int)($_POST['modulo_id'] ?? 0);
        $permisoId = (int)($_POST['permiso_id'] ?? 0);
        $clave = trim($_POST['clave'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $seccion = trim($_POST['seccion'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        try {
            $estructuraModel->actualizarPermisoModulo($permisoId, $clave, $nombre, $seccion, $descripcion);
            $permisos = $estructuraModel->listarPermisosDeModulo($moduloId);
            echo json_encode(['ok' => true, 'mensaje' => 'Solapa/Permiso actualizado correctamente', 'permisos' => $permisos]);
        } catch (\Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    // 14. Eliminar Permiso / Solapa en Módulo
    elseif ($action === 'eliminar_permiso_modulo') {
        $moduloId = (int)($_POST['modulo_id'] ?? 0);
        $permisoId = (int)($_POST['permiso_id'] ?? 0);
        try {
            $estructuraModel->eliminarPermisoModulo($permisoId);
            $permisos = $estructuraModel->listarPermisosDeModulo($moduloId);
            echo json_encode(['ok' => true, 'mensaje' => 'Solapa/Permiso eliminado correctamente', 'permisos' => $permisos]);
        } catch (\Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    // 15. Cambiar Password (AJAX)
    elseif ($action === 'cambiar_pass') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $nuevaPass = trim($_POST['nueva_pass'] ?? '');
        if ($userId > 0 && $nuevaPass !== '') {
            try {
                $usuarioModel->actualizarPassword($userId, $nuevaPass);
                echo json_encode([
                    'ok' => true,
                    'mensaje' => "Contraseña actualizada con éxito en SQL para el usuario #$userId.",
                    'user_id' => $userId,
                    'nueva_pass' => $nuevaPass
                ]);
            } catch (\Exception $e) {
                echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
            }
        } else {
            echo json_encode(['ok' => false, 'error' => 'Por favor completá la contraseña.']);
        }
        exit;
    }
    // 16. Guardar Usuario (Crear / Editar AJAX)
    elseif ($action === 'guardar_usuario') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $datos = [
            'username' => trim($_POST['username'] ?? ''),
            'nombre_completo' => trim($_POST['nombre_completo'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'password' => trim($_POST['password'] ?? ''),
            'categoria_usuario' => trim($_POST['categoria_usuario'] ?? 'PERSONAL'),
            'tipo' => trim($_POST['tipo'] ?? ''),
            'sector_id' => $_POST['sector_id'] ?? null,
            'subsector_id' => $_POST['subsector_id'] ?? null,
            'rol_id' => $_POST['rol_id'] ?? null,
            'nro_sucurs' => trim($_POST['nro_sucurs'] ?? ''),
            'legajo' => !empty($_POST['legajo']) ? (int)$_POST['legajo'] : null,
            'cod_client' => trim($_POST['cod_client'] ?? ''),
            'nro_sucursal' => $_POST['nro_sucursal'] ?? null,
            'cod_vendedor' => trim($_POST['cod_vendedor'] ?? ''),
            'usuario_tango' => trim($_POST['usuario_tango'] ?? ''),
            'activo' => isset($_POST['activo']) && ($_POST['activo'] == '1' || $_POST['activo'] === 'on') ? 1 : 0
        ];

        if (!empty($datos['username'])) {
            try {
                if ($userId > 0) {
                    $usuarioModel->actualizarUsuario($userId, $datos);
                    $uActualizado = $usuarioModel->obtenerPorId($userId);
                    echo json_encode([
                        'ok' => true,
                        'is_edit' => true,
                        'mensaje' => "Usuario @{$datos['username']} actualizado correctamente.",
                        'usuario' => $uActualizado
                    ]);
                } else {
                    $nuevoId = $usuarioModel->crearUsuario($datos);
                    echo json_encode([
                        'ok' => true,
                        'is_edit' => false,
                        'mensaje' => "Nuevo usuario @{$datos['username']} creado con éxito.",
                        'user_id' => $nuevoId
                    ]);
                }
            } catch (\Exception $e) {
                echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
            }
        } else {
            echo json_encode(['ok' => false, 'error' => 'El nombre de usuario es obligatorio.']);
        }
        exit;
    }
    // 17. Eliminar Usuario (AJAX)
    elseif ($action === 'eliminar_usuario') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $usernameBorrar = trim($_POST['username'] ?? '');
        if ($userId > 0) {
            try {
                $usuarioModel->eliminarUsuario($userId);
                echo json_encode([
                    'ok' => true,
                    'user_id' => $userId,
                    'mensaje' => "Usuario @{$usernameBorrar} eliminado correctamente de SQL."
                ]);
            } catch (\Exception $e) {
                echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
            }
        } else {
            echo json_encode(['ok' => false, 'error' => 'ID de usuario inválido.']);
        }
        exit;
    }
    // 18. Toggle Activo Usuario (AJAX)
    elseif ($action === 'toggle_activo_usuario') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $activo = isset($_POST['activo']) ? (int)$_POST['activo'] : 0;
        if ($userId > 0) {
            try {
                $usuarioModel->cambiarEstadoActivo($userId, $activo);
                echo json_encode([
                    'ok' => true,
                    'user_id' => $userId,
                    'activo' => $activo,
                    'mensaje' => $activo ? "Usuario habilitado correctamente." : "Usuario deshabilitado."
                ]);
            } catch (\Exception $e) {
                echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
            }
        } else {
            echo json_encode(['ok' => false, 'error' => 'ID de usuario inválido.']);
        }
        exit;
    }
    
    if ($action === 'obtener_equipo_ajax') {
        $eqId = (int)($_GET['equipo_id'] ?? 0);
        $equipo = $estructuraModel->obtenerEquipo($eqId);
        echo json_encode(['ok' => ($equipo !== null), 'equipo' => $equipo]);
        exit;
    }

    if ($action === 'buscar_usuarios_equipo_ajax') {
        $q = trim($_GET['q'] ?? '');
        $secId = !empty($_GET['sector_id']) ? (int)$_GET['sector_id'] : null;
        $usuarios = $estructuraModel->buscarUsuariosParaEquipo($q, $secId);
        echo json_encode(['ok' => true, 'usuarios' => $usuarios]);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Acción AJAX desconocida']);
    exit;
}

// ==================== PROCESAMIENTO DE FORMULARIOS (POST-REDIRECT-GET) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Preservar la URL y filtros actuales 100% intactos
    $currentQuery = $_POST['return_query'] ?? $_SERVER['QUERY_STRING'] ?? '';
    if (!empty($currentQuery)) {
        $redirectUrl = '?' . ltrim($currentQuery, '?');
    } else {
        $redirectUrl = "?tab=" . urlencode($tab);
    }

    // 1. Cambiar Password
    if ($action === 'cambiar_pass') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $nuevaPass = trim($_POST['nueva_pass'] ?? '');
        if ($userId > 0 && $nuevaPass !== '') {
            $usuarioModel->actualizarPassword($userId, $nuevaPass);
            $_SESSION['flash_msg'] = [
                'texto' => "Contraseña actualizada con éxito en SQL para el usuario #$userId.",
                'tipo' => 'success'
            ];
        }
    }
    // 1.1 Eliminar Usuario
    elseif ($action === 'eliminar_usuario') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $usernameBorrar = trim($_POST['username'] ?? '');
        if ($userId > 0) {
            try {
                $usuarioModel->eliminarUsuario($userId);
                $_SESSION['flash_msg'] = [
                    'texto' => "Usuario @{$usernameBorrar} (#{$userId}) eliminado correctamente de SQL.",
                    'tipo' => 'success'
                ];
            } catch (\Exception $e) {
                $_SESSION['flash_msg'] = [
                    'texto' => $e->getMessage(),
                    'tipo' => 'error'
                ];
            }
        }
    }
    // 2. Crear / Editar Usuario
    elseif ($action === 'guardar_usuario') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $datos = [
            'username' => trim($_POST['username'] ?? ''),
            'nombre_completo' => trim($_POST['nombre_completo'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'password' => trim($_POST['password'] ?? ''),
            'categoria_usuario' => trim($_POST['categoria_usuario'] ?? 'PERSONAL'),
            'tipo' => trim($_POST['tipo'] ?? ''),
            'sector_id' => $_POST['sector_id'] ?? null,
            'subsector_id' => $_POST['subsector_id'] ?? null,
            'rol_id' => $_POST['rol_id'] ?? null,
            'nro_sucurs' => trim($_POST['nro_sucurs'] ?? ''),
            'legajo' => !empty($_POST['legajo']) ? (int)$_POST['legajo'] : null,
            'cod_client' => trim($_POST['cod_client'] ?? ''),
            'nro_sucursal' => $_POST['nro_sucursal'] ?? null,
            'cod_vendedor' => trim($_POST['cod_vendedor'] ?? ''),
            'usuario_tango' => trim($_POST['usuario_tango'] ?? ''),
            'activo' => isset($_POST['activo']) ? 1 : 0
        ];

        if (!empty($datos['username'])) {
            try {
                if ($userId > 0) {
                    $usuarioModel->actualizarUsuario($userId, $datos);
                    $_SESSION['flash_msg'] = [
                        'texto' => "Usuario @{$datos['username']} actualizado correctamente.",
                        'tipo' => 'success'
                    ];
                } else {
                    $usuarioModel->crearUsuario($datos);
                    $_SESSION['flash_msg'] = [
                        'texto' => "Nuevo usuario @{$datos['username']} creado con éxito.",
                        'tipo' => 'success'
                    ];
                }
            } catch (\Exception $e) {
                $_SESSION['flash_msg'] = [
                    'texto' => $e->getMessage(),
                    'tipo' => 'error'
                ];
            }
        } else {
            $_SESSION['flash_msg'] = [
                'texto' => "El nombre de usuario es obligatorio.",
                'tipo' => 'error'
            ];
        }
    }
    // 3. Crear / Editar Sector
    elseif ($action === 'guardar_sector') {
        $sectorId = (int)($_POST['sector_id'] ?? 0);
        $codigo = trim($_POST['codigo'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $desc = trim($_POST['descripcion'] ?? '');
        if (!empty($codigo) && !empty($nombre)) {
            if ($sectorId > 0) {
                $estructuraModel->actualizarSector($sectorId, $codigo, $nombre, $desc);
                $_SESSION['flash_msg'] = ['texto' => "Sector '$nombre' actualizado exitosamente.", 'tipo' => 'success'];
            } else {
                $estructuraModel->crearSector($codigo, $nombre, $desc);
                $_SESSION['flash_msg'] = ['texto' => "Sector '$nombre' creado exitosamente.", 'tipo' => 'success'];
            }
        }
        $redirectUrl = "?tab=sectores";
    }
    // 4. Crear / Editar Subsector
    elseif ($action === 'guardar_subsector') {
        $subsectorId = (int)($_POST['subsector_id'] ?? 0);
        $sectorId = (int)($_POST['sector_id'] ?? 0);
        $codigo = trim($_POST['codigo'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $desc = trim($_POST['descripcion'] ?? '');
        if ($sectorId > 0 && !empty($codigo) && !empty($nombre)) {
            if ($subsectorId > 0) {
                $estructuraModel->actualizarSubsector($subsectorId, $sectorId, $codigo, $nombre, $desc);
                $_SESSION['flash_msg'] = ['texto' => "Subsector '$nombre' actualizado exitosamente.", 'tipo' => 'success'];
            } else {
                $estructuraModel->crearSubsector($sectorId, $codigo, $nombre, $desc);
                $_SESSION['flash_msg'] = ['texto' => "Subsector '$nombre' creado exitosamente.", 'tipo' => 'success'];
            }
        }
        $redirectUrl = "?tab=sectores";
    }
    // 4.1 Eliminar Subsector
    elseif ($action === 'eliminar_subsector') {
        $subsectorId = (int)($_POST['subsector_id'] ?? 0);
        $nombreSubsector = trim($_POST['nombre_subsector'] ?? '');
        if ($subsectorId > 0) {
            try {
                $estructuraModel->eliminarSubsector($subsectorId);
                $_SESSION['flash_msg'] = [
                    'texto' => "Subsector '$nombreSubsector' eliminado exitosamente.",
                    'tipo' => 'success'
                ];
            } catch (\Exception $e) {
                $_SESSION['flash_msg'] = [
                    'texto' => $e->getMessage(),
                    'tipo' => 'error'
                ];
            }
        }
        $redirectUrl = "?tab=sectores";
    }
    // 5. Crear / Editar Rol
    elseif ($action === 'guardar_rol') {
        $rolId = (int)($_POST['rol_id'] ?? 0);
        $subsectorId = !empty($_POST['subsector_id']) ? (int)$_POST['subsector_id'] : null;
        $codigo = trim($_POST['codigo'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $desc = trim($_POST['descripcion'] ?? '');
        $esAdmin = isset($_POST['es_admin']) ? 1 : 0;
        if (!empty($codigo) && !empty($nombre)) {
            if ($rolId > 0) {
                $estructuraModel->actualizarRol($rolId, $subsectorId, $codigo, $nombre, $desc, $esAdmin);
                $_SESSION['flash_msg'] = ['texto' => "Rol '$nombre' actualizado exitosamente.", 'tipo' => 'success'];
            } else {
                $estructuraModel->crearRol($subsectorId, $codigo, $nombre, $desc, $esAdmin);
                $_SESSION['flash_msg'] = ['texto' => "Rol '$nombre' creado exitosamente.", 'tipo' => 'success'];
            }
        }
        $redirectUrl = "?tab=roles";
    }
    // 6. Guardar Permisos del Rol (Matriz N a N)
    elseif ($action === 'guardar_permisos_rol') {
        $rolId = (int)($_POST['rol_id'] ?? 0);
        $permisosSeleccionados = $_POST['permisos'] ?? [];
        if ($rolId > 0) {
            $estructuraModel->guardarPermisosRol($rolId, $permisosSeleccionados);
            $_SESSION['flash_msg'] = [
                'texto' => "Permisos del rol actualizados correctamente (Total: " . count($permisosSeleccionados) . " permisos).",
                'tipo' => 'success'
            ];
        }
        $redirectUrl = "?tab=roles";
    }
    // 8. Crear / Editar Módulo
    elseif ($action === 'guardar_modulo') {
        $moduloId = (int)($_POST['modulo_id'] ?? 0);
        $codigo = trim($_POST['codigo'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $categoria = trim($_POST['categoria'] ?? 'General');
        $rutaUrl = trim($_POST['ruta_url'] ?? '');
        $icono = trim($_POST['icono'] ?? 'fa-cube');
        $color = trim($_POST['color'] ?? 'linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%)');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $orden = (int)($_POST['orden'] ?? 100);
        $targetBlank = isset($_POST['target_blank']) ? 1 : 0;
        $activo = isset($_POST['activo']) ? 1 : 0;

        if (!empty($codigo) && !empty($nombre)) {
            try {
                if ($moduloId > 0) {
                    $estructuraModel->actualizarModulo($moduloId, $codigo, $nombre, $categoria, $rutaUrl, $icono, $color, $descripcion, $orden, $targetBlank, $activo);
                    $_SESSION['flash_msg'] = ['texto' => "Módulo '$nombre' ($codigo) actualizado exitosamente.", 'tipo' => 'success'];
                } else {
                    $nuevoId = $estructuraModel->crearModulo($codigo, $nombre, $categoria, $rutaUrl, $icono, $color, $descripcion, $orden, $targetBlank, $activo);
                    $_SESSION['flash_msg'] = ['texto' => "Nuevo módulo '$nombre' ($codigo) creado exitosamente con su acceso de Hub configurado.", 'tipo' => 'success'];
                }
            } catch (\Exception $e) {
                $_SESSION['flash_msg'] = ['texto' => $e->getMessage(), 'tipo' => 'error'];
            }
        } else {
            $_SESSION['flash_msg'] = ['texto' => "El código y el nombre del módulo son obligatorios.", 'tipo' => 'error'];
        }
        $redirectUrl = "?tab=modulos";
    }
    // 9. Eliminar Módulo
    elseif ($action === 'eliminar_modulo') {
        $moduloId = (int)($_POST['modulo_id'] ?? 0);
        $nombreModulo = trim($_POST['nombre_modulo'] ?? '');
        if ($moduloId > 0) {
            try {
                $estructuraModel->eliminarModulo($moduloId);
                $_SESSION['flash_msg'] = ['texto' => "Módulo '$nombreModulo' eliminado correctamente del sistema.", 'tipo' => 'success'];
            } catch (\Exception $e) {
                $_SESSION['flash_msg'] = ['texto' => $e->getMessage(), 'tipo' => 'error'];
            }
        }
        $redirectUrl = "?tab=modulos";
    }
    // 10. Guardar Equipo (Crear / Actualizar)
    elseif ($action === 'guardar_equipo') {
        $equipoId = (int)($_POST['equipo_id'] ?? 0);
        $datos = [
            'nombre' => trim($_POST['nombre'] ?? ''),
            'sector_id' => (int)($_POST['sector_id'] ?? 0),
            'subsector_id' => !empty($_POST['subsector_id']) ? (int)$_POST['subsector_id'] : null,
            'lider_legajo' => !empty($_POST['lider_legajo']) ? (int)$_POST['lider_legajo'] : null,
            'lider_usuario_id' => !empty($_POST['lider_usuario_id']) ? (int)$_POST['lider_usuario_id'] : null,
            'gerente_legajo' => !empty($_POST['gerente_legajo']) ? (int)$_POST['gerente_legajo'] : null,
            'gerente_usuario_id' => !empty($_POST['gerente_usuario_id']) ? (int)$_POST['gerente_usuario_id'] : null,
            'tipo_flujo' => trim($_POST['tipo_flujo'] ?? 'CENTRAL'),
            'descripcion' => trim($_POST['descripcion'] ?? ''),
            'activo' => isset($_POST['activo']) ? 1 : 0
        ];

        // Parsear miembros si vienen del formulario
        if (isset($_POST['miembros_legajos']) && is_array($_POST['miembros_legajos'])) {
            $miembros = [];
            foreach ($_POST['miembros_legajos'] as $idx => $leg) {
                $legInt = (int)$leg;
                if ($legInt > 0) {
                    $uId = !empty($_POST['miembros_user_ids'][$idx]) ? (int)$_POST['miembros_user_ids'][$idx] : null;
                    $rolEq = trim($_POST['miembros_roles'][$idx] ?? 'MIEMBRO');
                    $miembros[] = [
                        'legajo' => $legInt,
                        'usuario_id' => $uId,
                        'rol_en_equipo' => $rolEq,
                    ];
                }
            }
            $datos['miembros'] = $miembros;
        }

        try {
            if ($equipoId > 0) {
                $estructuraModel->actualizarEquipo($equipoId, $datos);
                $_SESSION['flash_msg'] = ['texto' => "Equipo '{$datos['nombre']}' actualizado con éxito.", 'tipo' => 'success'];
            } else {
                $nuevoId = $estructuraModel->crearEquipo($datos);
                $_SESSION['flash_msg'] = ['texto' => "Equipo '{$datos['nombre']}' (#$nuevoId) creado con éxito.", 'tipo' => 'success'];
            }
        } catch (\Exception $e) {
            $_SESSION['flash_msg'] = ['texto' => $e->getMessage(), 'tipo' => 'error'];
        }
        $redirectUrl = "?tab=equipos";
    }
    // 11. Eliminar Equipo
    elseif ($action === 'eliminar_equipo') {
        $equipoId = (int)($_POST['equipo_id'] ?? 0);
        $nombreEq = trim($_POST['nombre_equipo'] ?? '');
        if ($equipoId > 0) {
            try {
                $estructuraModel->eliminarEquipo($equipoId);
                $_SESSION['flash_msg'] = ['texto' => "Equipo '$nombreEq' eliminado correctamente.", 'tipo' => 'success'];
            } catch (\Exception $e) {
                $_SESSION['flash_msg'] = ['texto' => $e->getMessage(), 'tipo' => 'error'];
            }
        }
        $redirectUrl = "?tab=equipos";
    }

    header("Location: " . $redirectUrl);
    exit;
}

// Recuperar flash message de la sesión si existe
if (isset($_SESSION['flash_msg'])) {
    $mensaje = $_SESSION['flash_msg']['texto'] ?? '';
    $tipoMensaje = $_SESSION['flash_msg']['tipo'] ?? 'success';
    unset($_SESSION['flash_msg']);
}

// Filtros y Paginación para Usuarios
$busqueda = trim($_GET['q'] ?? '');
$tipo = trim($_GET['tipo'] ?? '');
$sectorFiltro = trim($_GET['sec'] ?? '');
$categoriaFiltro = trim($_GET['cat'] ?? '');
$rolFiltro = trim($_GET['rol'] ?? '');
$activoFiltro = isset($_GET['act']) && $_GET['act'] !== '' ? trim($_GET['act']) : '';
$pagina = max(1, (int)($_GET['p'] ?? 1));
$porPagina = max(10, min(1000, (int)($_GET['limit'] ?? 30)));
$offset = ($pagina - 1) * $porPagina;

// Totales consolidados por categoría en una única consulta
$totalesCat = $usuarioModel->totalesPorCategoriaAgrupados();
$totalPersonales = $totalesCat['PERSONAL'] ?? 0;
$totalLocales = $totalesCat['LOCAL'] ?? 0;
$totalMayoristas = $totalesCat['MAYORISTA'] ?? 0;
$totalGrupos = $totalesCat['GRUPO'] ?? 0;
$totalUsuariosGeneral = $totalPersonales + $totalLocales + $totalMayoristas + $totalGrupos;

// Carga perezosa según pestaña activa
if ($tab === 'usuarios' || empty($tab)) {
    $totalUsuarios = $usuarioModel->totalRegistros($busqueda, $tipo, $sectorFiltro, $categoriaFiltro, $rolFiltro, $activoFiltro);
    $usuarios = $usuarioModel->listar($busqueda, $tipo, $sectorFiltro, $categoriaFiltro, $rolFiltro, $porPagina, $offset, $activoFiltro);
    $totalPaginas = max(1, ceil($totalUsuarios / $porPagina));
} else {
    $totalUsuarios = $totalUsuariosGeneral;
    $usuarios = [];
    $totalPaginas = 1;
}

$tipos = $usuarioModel->obtenerTipos();
$sectores = $estructuraModel->listarSectores();
$subsectores = $estructuraModel->listarSubsectores();
$roles = $estructuraModel->listarRoles();
$modulos = $estructuraModel->listarModulosConPermisos();
$modulosHub = $estructuraModel->listarModulosHub();
$modulosTodos = $estructuraModel->listarModulosTodos();
$categoriasHubExistentes = array_values(array_unique(array_filter(array_map(function($m){ return trim($m['categoria'] ?? ''); }, $modulosTodos))));
sort($categoriasHubExistentes);
$matrizEstructuraHub = ($tab === 'hub_matriz') ? $estructuraModel->obtenerMatrizEstructuraCompleta() : [];

// Mapa de permisos por rol en 1 sola consulta SQL
$mapaPermisosPorRol = $estructuraModel->obtenerMapaPermisosTodosLosRoles();

// Lista y conteo de Equipos de Trabajo
$cantEquiposTotal = $estructuraModel->contarEquipos();
$equiposTotal = ($tab === 'equipos') ? $estructuraModel->listarEquipos() : [];
$usuariosParaEquipos = ($tab === 'equipos') ? $estructuraModel->buscarUsuariosParaEquipo() : [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Centro de Control de Usuarios & Roles - XL</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .toast-notify {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        /* Custom scrollbar para la tabla */
        .tabla-scroll-container::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        .tabla-scroll-container::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        .tabla-scroll-container::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        .tabla-scroll-container::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        /* Top Progress Bar transition */
        #topProgressBar {
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.3s ease;
        }
        @keyframes pulseGlow {
            0%, 100% { opacity: 0.85; }
            50% { opacity: 0.4; }
        }
        .animate-pulse-glow {
            animation: pulseGlow 1.5s infinite ease-in-out;
        }
        /* Switch Toggle de Estado */
        .switch-toggle-box {
            width: 36px;
            height: 20px;
            background-color: #cbd5e1;
            border-radius: 9999px;
            position: relative;
            cursor: pointer;
            transition: background-color 0.2s ease-in-out;
            display: inline-block;
        }
        .switch-toggle-knob {
            width: 16px;
            height: 16px;
            background-color: #ffffff;
            border-radius: 9999px;
            position: absolute;
            top: 2px;
            left: 2px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.2);
            transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        input[type="checkbox"]:checked + .switch-toggle-box {
            background-color: #10b981;
        }
        input[type="checkbox"]:checked + .switch-toggle-box .switch-toggle-knob {
            transform: translateX(16px);
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col antialiased">

    <!-- Barra de progreso superior interactiva para cargas / envíos -->
    <div id="topProgressBar" class="fixed top-0 left-0 h-1 bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500 z-50 pointer-events-none opacity-0 w-0 shadow-sm shadow-indigo-300"></div>

    <!-- Header Principal Light -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-40 shadow-xs">
        <div class="w-full px-4 sm:px-6 lg:px-8 py-3.5 flex flex-col md:flex-row md:items-center justify-between gap-3">
            <div class="flex items-center space-x-3.5">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-600 flex items-center justify-center text-white shadow-md shadow-indigo-200">
                    <i class="fas fa-id-badge text-lg"></i>
                </div>
                <div>
                    <h1 class="text-base font-bold text-slate-900 tracking-tight flex items-center gap-2">
                        Centro de Control de Usuarios & Roles
                    </h1>
                </div>
            </div>

            <!-- Navegación por Pestañas Light -->
            <div class="flex items-center bg-slate-100 p-1 rounded-xl border border-slate-200 space-x-1 overflow-x-auto">
                <a href="?tab=usuarios" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition <?= $tab === 'usuarios' ? 'bg-white text-indigo-700 shadow-xs' : 'text-slate-600 hover:text-slate-900' ?>">
                    <i class="fas fa-users mr-1.5"></i> Usuarios (<?= number_format($totalUsuarios) ?>)
                </a>
                <a href="?tab=sectores" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition <?= $tab === 'sectores' ? 'bg-white text-indigo-700 shadow-xs' : 'text-slate-600 hover:text-slate-900' ?>">
                    <i class="fas fa-sitemap mr-1.5"></i> Sectores (<?= count($sectores) ?>)
                </a>
                <a href="?tab=roles" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition <?= $tab === 'roles' ? 'bg-white text-indigo-700 shadow-xs' : 'text-slate-600 hover:text-slate-900' ?>">
                    <i class="fas fa-user-shield mr-1.5"></i> Roles (<?= count($roles) ?>)
                </a>
                <a href="?tab=hub_matriz" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition <?= $tab === 'hub_matriz' ? 'bg-white text-indigo-700 shadow-xs' : 'text-slate-600 hover:text-slate-900' ?>">
                    <i class="fas fa-shapes mr-1.5 text-indigo-600"></i> Matriz Hub & Accesos
                    <span class="ml-1 px-1.5 py-0.2 rounded-full text-[10px] bg-indigo-100 text-indigo-800 font-bold">
                        <?= count($modulosHub) ?> apps
                    </span>
                </a>
                <a href="?tab=modulos" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition <?= $tab === 'modulos' ? 'bg-white text-indigo-700 shadow-xs' : 'text-slate-600 hover:text-slate-900' ?>">
                    <i class="fas fa-cubes mr-1.5 text-indigo-600"></i> Módulos & Apps (<?= count($modulosTodos) ?>)
                </a>
                <a href="?tab=equipos" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition <?= $tab === 'equipos' ? 'bg-white text-indigo-700 shadow-xs' : 'text-slate-600 hover:text-slate-900' ?>">
                    <i class="fas fa-people-roof mr-1.5 text-blue-600"></i> Equipos de Trabajo (<?= $cantEquiposTotal ?>)
                </a>
            </div>
        </div>
    </header>

    <!-- Contenido Principal -->
    <main class="w-full px-4 sm:px-6 lg:px-8 py-6 flex-1">

        <?php if(!empty($mensaje)): ?>
            <div id="alertaFlash" class="mb-6 p-4 rounded-xl <?= $tipoMensaje === 'success' ? 'bg-emerald-50 border border-emerald-200 text-emerald-800' : 'bg-rose-50 border border-rose-200 text-rose-800' ?> flex items-center justify-between shadow-xs transition-opacity duration-500">
                <div class="flex items-center space-x-2.5">
                    <i class="fas <?= $tipoMensaje === 'success' ? 'fa-circle-check text-emerald-600' : 'fa-triangle-exclamation text-rose-600' ?> text-lg"></i>
                    <span class="text-sm font-medium"><?= htmlspecialchars($mensaje) ?></span>
                </div>
                <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
            </div>
            <script>
                setTimeout(() => {
                    const alerta = document.getElementById('alertaFlash');
                    if (alerta) {
                        alerta.style.opacity = '0';
                        setTimeout(() => alerta.remove(), 500);
                    }
                }, 4000);
            </script>
        <?php endif; ?>

        <!-- ========================================== PESTAÑA: USUARIOS ========================================== -->
        <?php if($tab === 'usuarios'): ?>
            <!-- Tarjetas Resumen Light / Acceso Rápido de Filtros -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-5">
                <a href="?tab=usuarios" class="bg-white rounded-2xl p-4 border <?= empty($categoriaFiltro) ? 'border-indigo-500 ring-2 ring-indigo-500/10' : 'border-slate-200/80 hover:border-slate-300' ?> shadow-xs flex items-center justify-between transition cursor-pointer group">
                    <div>
                        <p class="text-[11px] font-semibold tracking-wider text-slate-400 uppercase group-hover:text-slate-600 transition">Total Cuentas</p>
                        <h4 class="text-xl font-bold text-slate-900 mt-0.5"><?= number_format($totalPersonales + $totalLocales + $totalMayoristas + $totalGrupos) ?></h4>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center group-hover:scale-105 transition">
                        <i class="fas fa-users text-lg"></i>
                    </div>
                </a>

                <a href="?tab=usuarios&cat=PERSONAL" class="bg-white rounded-2xl p-4 border <?= $categoriaFiltro === 'PERSONAL' ? 'border-emerald-500 ring-2 ring-emerald-500/10' : 'border-slate-200/80 hover:border-slate-300' ?> shadow-xs flex items-center justify-between transition cursor-pointer group">
                    <div>
                        <p class="text-[11px] font-semibold tracking-wider text-slate-400 uppercase group-hover:text-emerald-700 transition">Personal Staff</p>
                        <h4 class="text-xl font-bold text-emerald-600 mt-0.5"><?= number_format($totalPersonales) ?></h4>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center group-hover:scale-105 transition">
                        <i class="fas fa-user-tie text-lg"></i>
                    </div>
                </a>

                <a href="?tab=usuarios&cat=LOCAL" class="bg-white rounded-2xl p-4 border <?= $categoriaFiltro === 'LOCAL' ? 'border-blue-500 ring-2 ring-blue-500/10' : 'border-slate-200/80 hover:border-slate-300' ?> shadow-xs flex items-center justify-between transition cursor-pointer group">
                    <div>
                        <p class="text-[11px] font-semibold tracking-wider text-slate-400 uppercase group-hover:text-blue-700 transition">Locales / Sucursales</p>
                        <h4 class="text-xl font-bold text-blue-600 mt-0.5"><?= number_format($totalLocales) ?></h4>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center group-hover:scale-105 transition">
                        <i class="fas fa-store text-lg"></i>
                    </div>
                </a>

                <a href="?tab=usuarios&cat=MAYORISTA" class="bg-white rounded-2xl p-4 border <?= $categoriaFiltro === 'MAYORISTA' ? 'border-teal-500 ring-2 ring-teal-500/10' : 'border-slate-200/80 hover:border-slate-300' ?> shadow-xs flex items-center justify-between transition cursor-pointer group">
                    <div>
                        <p class="text-[11px] font-semibold tracking-wider text-slate-400 uppercase group-hover:text-teal-700 transition">Mayoristas B2B</p>
                        <h4 class="text-xl font-bold text-teal-600 mt-0.5"><?= number_format($totalMayoristas) ?></h4>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center group-hover:scale-105 transition">
                        <i class="fas fa-boxes-packing text-lg"></i>
                    </div>
                </a>

                <a href="?tab=usuarios&cat=GRUPO" class="bg-white rounded-2xl p-4 border <?= $categoriaFiltro === 'GRUPO' ? 'border-amber-500 ring-2 ring-amber-500/10' : 'border-slate-200/80 hover:border-slate-300' ?> shadow-xs flex items-center justify-between transition cursor-pointer group">
                    <div>
                        <p class="text-[11px] font-semibold tracking-wider text-slate-400 uppercase group-hover:text-amber-700 transition">Grupos Franquicia</p>
                        <h4 class="text-xl font-bold text-amber-600 mt-0.5"><?= number_format($totalGrupos) ?></h4>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center group-hover:scale-105 transition">
                        <i class="fas fa-sitemap text-lg"></i>
                    </div>
                </a>
            </div>

            <!-- Barra de Filtros & Botón Crear Usuario Completa -->
            <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-xs mb-4 flex flex-wrap items-center justify-between gap-3">
                <form method="GET" id="formFiltroUsuarios" class="flex flex-wrap items-center gap-2.5 flex-1">
                    <input type="hidden" name="tab" value="usuarios">
                    
                    <!-- Input de búsqueda -->
                    <div class="relative flex-1 min-w-[200px]">
                        <i id="searchIconUsr" class="fas fa-search absolute left-3.5 top-2.5 text-slate-400 text-xs transition-all"></i>
                        <input type="text" name="q" id="inputBusquedaUsuarios" value="<?= htmlspecialchars($busqueda) ?>" placeholder="Buscar por usuario, nombre, cliente o legajo..." 
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-3 py-1.5 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition">
                    </div>

                    <!-- Filtro Categoría -->
                    <div class="w-36">
                        <select name="cat" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-2.5 py-1.5 text-xs text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition">
                            <option value="">Categorías (Todas)</option>
                            <option value="PERSONAL" <?= $categoriaFiltro === 'PERSONAL' ? 'selected' : '' ?>>👤 Personal Staff</option>
                            <option value="LOCAL" <?= $categoriaFiltro === 'LOCAL' ? 'selected' : '' ?>>🏪 Locales / Sucursales</option>
                            <option value="MAYORISTA" <?= $categoriaFiltro === 'MAYORISTA' ? 'selected' : '' ?>>📦 Clientes Mayoristas</option>
                            <option value="GRUPO" <?= $categoriaFiltro === 'GRUPO' ? 'selected' : '' ?>>🏢 Grupos Franquicia</option>
                        </select>
                    </div>

                    <!-- Filtro Sector -->
                    <div class="w-36">
                        <select name="sec" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-2.5 py-1.5 text-xs text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition">
                            <option value="">Sectores (Todos)</option>
                            <?php foreach($sectores as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= $sectorFiltro == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Filtro Rol Asignado -->
                    <div class="w-40">
                        <select name="rol" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-2.5 py-1.5 text-xs text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition">
                            <option value="">Roles (Todos)</option>
                            <?php foreach($roles as $r): ?>
                                <option value="<?= $r['id'] ?>" <?= $rolFiltro == $r['id'] ? 'selected' : '' ?>><?= htmlspecialchars($r['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Filtro Tipo / Alcance -->
                    <div class="w-40">
                        <select name="tipo" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-2.5 py-1.5 text-xs text-slate-700 font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition">
                            <option value="">Tipo / Alcance (Todos)</option>
                            <?php foreach($tipos as $t): ?>
                                <option value="<?= htmlspecialchars($t) ?>" <?= $tipo === $t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Filtro Estado (Habilitado / Deshabilitado) -->
                    <div class="w-36">
                        <select name="act" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-2.5 py-1.5 text-xs text-slate-700 font-medium focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition">
                            <option value="">Estado (Todos)</option>
                            <option value="1" <?= $activoFiltro === '1' ? 'selected' : '' ?>>🟢 Habilitados</option>
                            <option value="0" <?= $activoFiltro === '0' ? 'selected' : '' ?>>🔴 Inactivos</option>
                        </select>
                    </div>

                    <!-- Selector Registros por Página -->
                    <div class="w-28">
                        <select name="limit" onchange="document.getElementById('formFiltroUsuarios').submit()" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-2.5 py-1.5 text-xs text-slate-700 font-medium focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition">
                            <option value="30" <?= $porPagina == 30 ? 'selected' : '' ?>>30 por pág</option>
                            <option value="50" <?= $porPagina == 50 ? 'selected' : '' ?>>50 por pág</option>
                            <option value="100" <?= $porPagina == 100 ? 'selected' : '' ?>>100 por pág</option>
                            <option value="250" <?= $porPagina == 250 ? 'selected' : '' ?>>250 por pág</option>
                            <option value="500" <?= $porPagina == 500 ? 'selected' : '' ?>>500 por pág</option>
                            <option value="1000" <?= $porPagina >= 1000 ? 'selected' : '' ?>>Ver Todos (<?= $totalUsuarios ?>)</option>
                        </select>
                    </div>

                    <button type="submit" class="px-3.5 py-1.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-semibold transition shadow-2xs cursor-pointer flex items-center gap-1.5">
                        <i class="fas fa-filter text-[10px]"></i> Filtrar
                    </button>
                    <?php if(!empty($busqueda) || !empty($tipo) || !empty($sectorFiltro) || !empty($categoriaFiltro) || !empty($rolFiltro) || $activoFiltro !== '' || $porPagina != 30): ?>
                        <a href="?tab=usuarios" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-medium transition">
                            Limpiar
                        </a>
                    <?php endif; ?>
                </form>

                <div class="flex items-center space-x-2 flex-wrap">
                    <a href="?action=exportar_excel_usuarios&q=<?= urlencode($busqueda) ?>&tipo=<?= urlencode($tipo) ?>&sec=<?= urlencode($sectorFiltro) ?>&cat=<?= urlencode($categoriaFiltro) ?>&rol=<?= urlencode($rolFiltro) ?>&act=<?= urlencode($activoFiltro) ?>" 
                       title="Descargar listado completo de credenciales en Excel / CSV con el filtro actual"
                       class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold transition shadow-xs flex items-center gap-1.5 cursor-pointer">
                        <i class="fas fa-file-excel text-xs"></i> Exportar Excel (<?= $totalUsuarios ?>)
                    </a>
                    <button type="button" onclick="abrirModalEnvioRapidoFiltro()" 
                            title="Generar texto listo para enviar por WhatsApp o Email" 
                            class="px-3.5 py-1.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-semibold transition shadow-2xs flex items-center gap-1.5 cursor-pointer">
                        <i class="fas fa-paper-plane text-indigo-600 text-xs"></i> Envío Rápido
                    </button>
                    <button onclick="abrirModalUsuario()" class="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl transition shadow-xs flex items-center gap-1.5 cursor-pointer">
                        <i class="fas fa-plus text-xs"></i> Nuevo Usuario
                    </button>
                </div>
            </div>

            <!-- Tabla de Usuarios con Scroll Propio -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden relative">
                <!-- Overlay de carga interactivo -->
                <div id="overlayLoadingUsr" class="hidden absolute inset-0 bg-white/80 backdrop-blur-[1px] flex flex-col items-center justify-center z-30 transition-all duration-200">
                    <div class="flex items-center space-x-2.5 bg-slate-900 text-white px-4 py-2.5 rounded-2xl shadow-xl text-xs font-semibold">
                        <i class="fas fa-circle-notch fa-spin text-indigo-400 text-sm"></i>
                        <span>Cargando datos...</span>
                    </div>
                </div>

                <div class="tabla-scroll-container max-h-[62vh] overflow-y-auto overflow-x-auto relative">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead class="sticky top-0 z-20 bg-slate-100/95 backdrop-blur-xs border-b border-slate-200 text-slate-600 font-bold uppercase text-[10px] tracking-wider shadow-2xs">
                            <tr>
                                <th class="py-3 px-4 bg-slate-100">Usuario</th>
                                <th class="py-3 px-4 bg-slate-100">Categoría</th>
                                <th class="py-3 px-4 text-center bg-slate-100">Estado</th>
                                <th class="py-3 px-4 bg-slate-100">Contraseña (SQL)</th>
                                <th class="py-3 px-4 bg-slate-100">Nombre Completo</th>
                                <th class="py-3 px-4 bg-slate-100">Sector / Subsector</th>
                                <th class="py-3 px-4 bg-slate-100">Rol Asignado</th>
                                <th class="py-3 px-4 bg-slate-100">Tipo / Alcance</th>
                                <th class="py-3 px-4 text-center bg-slate-100">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if(empty($usuarios)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-8 text-slate-400 italic">No se encontraron usuarios</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($usuarios as $u): ?>
                                    <tr id="row-user-<?= $u['id'] ?>" class="hover:bg-slate-50/80 transition <?= ($u['activo'] == 0) ? 'bg-slate-100/50 opacity-75' : '' ?>">
                                        <td class="py-3 px-4 font-mono font-bold text-slate-900">
                                            <div class="flex items-center space-x-2">
                                                <div class="w-7 h-7 rounded-lg <?= $u['categoria_usuario'] === 'GRUPO' ? 'bg-amber-50 text-amber-600' : ($u['categoria_usuario'] === 'MAYORISTA' ? 'bg-teal-50 text-teal-600' : ($u['categoria_usuario'] === 'LOCAL' ? 'bg-blue-50 text-blue-600' : 'bg-emerald-50 text-emerald-600')) ?> flex items-center justify-center text-xs">
                                                    <i class="fas <?= $u['categoria_usuario'] === 'GRUPO' ? 'fa-sitemap' : ($u['categoria_usuario'] === 'MAYORISTA' ? 'fa-boxes-packing' : ($u['categoria_usuario'] === 'LOCAL' ? 'fa-store' : 'fa-user')) ?>"></i>
                                                </div>
                                                <div>
                                                    <span class="block"><?= htmlspecialchars($u['username']) ?></span>
                                                    <span class="text-[10px] text-slate-400 font-normal">#<?= $u['id'] ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-3 px-4">
                                            <?php if($u['categoria_usuario'] === 'GRUPO'): ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                                    GRUPO
                                                </span>
                                            <?php elseif($u['categoria_usuario'] === 'MAYORISTA'): ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-teal-50 text-teal-700 border border-teal-200">
                                                    MAYORISTA
                                                </span>
                                            <?php elseif($u['categoria_usuario'] === 'LOCAL'): ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200">
                                                    LOCAL
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                    PERSONAL
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <!-- Columna Estado / Habilitado con Switch interactivo y Badge -->
                                        <td class="py-3 px-4 text-center whitespace-nowrap">
                                            <div class="inline-flex items-center justify-center gap-2">
                                                <label class="inline-flex items-center cursor-pointer select-none" title="<?= $u['activo'] == 1 ? 'Habilitado - Clic para deshabilitar' : 'Inactivo - Clic para habilitar' ?>">
                                                    <input type="checkbox" onchange="toggleActivoUsuario(<?= $u['id'] ?>, this)" <?= $u['activo'] == 1 ? 'checked' : '' ?> class="sr-only">
                                                    <div class="switch-toggle-box">
                                                        <div class="switch-toggle-knob"></div>
                                                    </div>
                                                </label>
                                                <span id="badge-activo-<?= $u['id'] ?>" class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold <?= $u['activo'] == 1 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200' ?>">
                                                    <span class="w-1.5 h-1.5 rounded-full mr-1 <?= $u['activo'] == 1 ? 'bg-emerald-500' : 'bg-rose-500' ?>"></span>
                                                    <?= $u['activo'] == 1 ? 'Habilitado' : 'Inactivo' ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="py-3 px-4 font-mono">
                                            <span id="pass-user-<?= $u['id'] ?>" class="px-2 py-0.5 rounded-md bg-amber-50 border border-amber-200 text-amber-900 font-semibold tracking-wide text-[11px] transition-all duration-300">
                                                <?= htmlspecialchars($u['password'] ?: $u['password_plain'] ?: '-') ?>
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-slate-700 font-medium">
                                            <?= htmlspecialchars($u['nombre_completo'] ?: '-') ?>
                                            <?php if(!empty($u['email'])): ?>
                                                <span class="block text-[10px] text-slate-400"><?= htmlspecialchars($u['email']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3 px-4">
                                            <?php if(!empty($u['sector_nombre'])): ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold bg-purple-50 text-purple-700 border border-purple-100">
                                                    <?= htmlspecialchars($u['sector_nombre']) ?>
                                                </span>
                                                <?php if(!empty($u['subsector_nombre'])): ?>
                                                    <span class="block text-[10px] text-slate-400 mt-0.5"><?= htmlspecialchars($u['subsector_nombre']) ?></span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-slate-400 italic text-[11px]">Sin asignar</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3 px-4">
                                            <?php if(!empty($u['rol_nombre'])): ?>
                                                <?php if(!empty($u['rol_id'])): ?>
                                                    <a href="?tab=hub_matriz&rol_id=<?= (int)$u['rol_id'] ?>" 
                                                       title="Ver y configurar permisos en Matriz Hub & Accesos"
                                                       class="inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-600 hover:text-white hover:border-indigo-600 transition group shadow-2xs">
                                                        <i class="fas fa-user-shield mr-1 text-[9px] group-hover:scale-110 transition-transform"></i> <?= htmlspecialchars($u['rol_nombre']) ?>
                                                        <i class="fas fa-arrow-up-right-from-square ml-1 text-[8px] opacity-70 group-hover:opacity-100"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                                        <i class="fas fa-user-shield mr-1 text-[9px]"></i> <?= htmlspecialchars($u['rol_nombre']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-slate-400 italic text-[11px]">Sin rol</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3 px-4 font-mono text-slate-600 text-[11px]">
                                            <?php if($u['categoria_usuario'] === 'GRUPO'): ?>
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] bg-amber-50 border border-amber-200 font-bold text-amber-800 mr-1">GRUPO</span>
                                                <span class="font-semibold text-slate-800"><?= htmlspecialchars($u['cod_client'] ?: '-') ?></span>
                                            <?php elseif($u['categoria_usuario'] === 'MAYORISTA'): ?>
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] bg-teal-50 border border-teal-200 font-bold text-teal-800 mr-1">B2B</span>
                                                <span class="font-semibold text-slate-800"><?= htmlspecialchars($u['cod_client'] ?: '-') ?></span>
                                                <?= !empty($u['cod_vendedor']) ? " • Vend <span class='text-teal-700 font-bold'>{$u['cod_vendedor']}</span>" : '' ?>
                                            <?php elseif($u['categoria_usuario'] === 'LOCAL'): ?>
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] bg-blue-50 border border-blue-200 font-bold text-blue-800 mr-1"><?= htmlspecialchars($u['tipo'] ?: 'LOCAL') ?></span>
                                                <?php if(!empty($u['cod_client']) || !empty($u['nro_sucursal'])): ?>
                                                    <span class="font-semibold text-slate-800"><?= htmlspecialchars($u['cod_client'] ?: '-') ?></span>
                                                    <?= $u['nro_sucursal'] ? " • Suc <span class='text-indigo-600 font-bold'>{$u['nro_sucursal']}</span>" : '' ?>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <?php if(!empty($u['legajo'])): ?>
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] bg-emerald-50 font-bold text-emerald-700 mr-1 border border-emerald-200">
                                                        Leg. <?= $u['legajo'] ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if(!empty($u['nro_sucurs'])): ?>
                                                    <span class="text-slate-700 font-medium">Sucs: <span class="text-indigo-600 font-bold"><?= htmlspecialchars($u['nro_sucurs']) ?></span></span>
                                                <?php elseif(empty($u['legajo'])): ?>
                                                    <span class="text-slate-400 text-[11px]">Personal Interno</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            <div class="inline-flex items-center space-x-1.5">
                                                <button onclick='abrirModalEnvioRapidoUsuario(<?= json_encode($u) ?>)' 
                                                        title="Envío Rápido de Credenciales (WhatsApp / Email)" 
                                                        class="p-1.5 bg-slate-100 hover:bg-emerald-100 text-slate-600 hover:text-emerald-700 rounded-lg transition border border-slate-200">
                                                    <i class="fas fa-paper-plane text-xs"></i>
                                                </button>
                                                <button onclick="editarPass(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username']) ?>', '<?= htmlspecialchars($u['password'] ?: $u['password_plain'] ?: '') ?>')" 
                                                        title="Editar Contraseña" class="p-1.5 bg-slate-100 hover:bg-amber-100 text-slate-600 hover:text-amber-700 rounded-lg transition border border-slate-200">
                                                    <i class="fas fa-key text-xs"></i>
                                                </button>
                                                <button onclick='editarUsuario(<?= json_encode($u) ?>)' 
                                                        title="Editar Usuario / Sector" class="p-1.5 bg-slate-100 hover:bg-indigo-100 text-slate-600 hover:text-indigo-700 rounded-lg transition border border-slate-200">
                                                    <i class="fas fa-pencil text-xs"></i>
                                                </button>
                                                <button onclick="confirmarEliminarUsuario(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username']) ?>')" 
                                                        title="Eliminar Usuario" class="p-1.5 bg-slate-100 hover:bg-rose-100 text-slate-500 hover:text-rose-600 rounded-lg transition border border-slate-200">
                                                    <i class="fas fa-trash-can text-xs"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Footer & Paginador Usuarios -->
                <div class="px-5 py-3 bg-slate-50/80 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500">
                    <div>
                        Mostrando <span class="font-bold text-slate-800"><?= count($usuarios) ?></span> de <span class="font-bold text-slate-800"><?= number_format($totalUsuarios) ?></span> registros • Página <span class="font-bold text-slate-800"><?= $pagina ?></span> de <span class="font-bold text-slate-800"><?= max(1, $totalPaginas) ?></span>
                    </div>

                    <?php if($totalPaginas > 1): ?>
                        <div class="flex space-x-1.5">
                            <?php if($pagina > 1): ?>
                                <a href="?tab=usuarios&p=<?= $pagina - 1 ?>&q=<?= urlencode($busqueda) ?>&tipo=<?= urlencode($tipo) ?>&sec=<?= urlencode($sectorFiltro) ?>&cat=<?= urlencode($categoriaFiltro) ?>&rol=<?= urlencode($rolFiltro) ?>&act=<?= urlencode($activoFiltro) ?>&limit=<?= $porPagina ?>" class="px-3 py-1 bg-white border border-slate-200 hover:bg-slate-100 text-slate-700 font-medium rounded-lg transition">Anterior</a>
                            <?php endif; ?>

                            <span class="px-3 py-1 bg-indigo-50 border border-indigo-200 text-indigo-700 font-bold rounded-lg"><?= $pagina ?></span>

                            <?php if($pagina < $totalPaginas): ?>
                                <a href="?tab=usuarios&p=<?= $pagina + 1 ?>&q=<?= urlencode($busqueda) ?>&tipo=<?= urlencode($tipo) ?>&sec=<?= urlencode($sectorFiltro) ?>&cat=<?= urlencode($categoriaFiltro) ?>&rol=<?= urlencode($rolFiltro) ?>&act=<?= urlencode($activoFiltro) ?>&limit=<?= $porPagina ?>" class="px-3 py-1 bg-white border border-slate-200 hover:bg-slate-100 text-slate-700 font-medium rounded-lg transition">Siguiente</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <!-- ========================================== PESTAÑA: SECTORES & SUBSECTORES ========================================== -->
        <?php elseif($tab === 'sectores'): ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Columna Izquierda: Formularios Crear Sector & Subsector -->
                <div class="space-y-6">
                    <!-- Form Crear / Editar Sector -->
                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2" id="tituloFormSector">
                                <i class="fas fa-folder-plus text-indigo-600"></i> Nuevo Sector Principal
                            </h3>
                            <button type="button" onclick="resetFormSector()" id="btnResetSector" class="text-[11px] text-indigo-600 hover:underline hidden">
                                Cancelar Edición
                            </button>
                        </div>
                        <form method="POST" class="space-y-3" id="formSector">
                            <input type="hidden" name="action" value="guardar_sector">
                            <input type="hidden" name="sector_id" id="inputSectorId" value="0">
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Código (Único)</label>
                                <input type="text" name="codigo" id="inputSectorCodigo" placeholder="EJ: MARKETING" required 
                                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-800 font-mono uppercase focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Nombre del Sector</label>
                                <input type="text" name="nombre" id="inputSectorNombre" placeholder="Marketing y Publicidad" required 
                                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Descripción</label>
                                <textarea name="descripcion" id="inputSectorDesc" rows="2" placeholder="Alcance o propósito del área..." 
                                          class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"></textarea>
                            </div>
                            <button type="submit" id="btnSubmitSector" class="w-full py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl transition shadow-sm">
                                Crear Sector
                            </button>
                        </form>
                    </div>

                    <!-- Form Crear / Editar Subsector -->
                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2" id="tituloFormSubsector">
                                <i class="fas fa-diagram-project text-purple-600"></i> Nuevo Subsector / Equipo
                            </h3>
                            <button type="button" onclick="resetFormSubsector()" id="btnResetSubsector" class="text-[11px] text-purple-600 hover:underline hidden">
                                Cancelar Edición
                            </button>
                        </div>
                        <form method="POST" class="space-y-3" id="formSubsector">
                            <input type="hidden" name="action" value="guardar_subsector">
                            <input type="hidden" name="subsector_id" id="inputSubsectorId" value="0">
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Sector Padre</label>
                                <select name="sector_id" id="inputSubsectorPadre" required 
                                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500">
                                    <option value="">Seleccione Sector Principal</option>
                                    <?php foreach($sectores as $s): ?>
                                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['nombre']) ?> (<?= htmlspecialchars($s['codigo']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Código del Subsector</label>
                                <input type="text" name="codigo" id="inputSubsectorCodigo" placeholder="EJ: DISENO" required 
                                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-800 font-mono uppercase focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Nombre del Subsector</label>
                                <input type="text" name="nombre" id="inputSubsectorNombre" placeholder="Diseño Gráfico & Audiovisual" required 
                                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Descripción</label>
                                <textarea name="descripcion" id="inputSubsectorDesc" rows="2" placeholder="Detalle funcional del equipo..." 
                                          class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500"></textarea>
                            </div>
                            <button type="submit" id="btnSubmitSubsector" class="w-full py-2 bg-purple-600 hover:bg-purple-700 text-white text-xs font-semibold rounded-xl transition shadow-sm">
                                Crear Subsector
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Columna Derecha: Árbol Estructural Interactivo -->
                <div class="md:col-span-2 space-y-4">
                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                                    <i class="fas fa-sitemap text-indigo-600"></i> Estructura Organizacional
                                </h3>
                                <p class="text-xs text-slate-500 mt-0.5">Sectores corporativos y sus correspondientes subsectores de trabajo.</p>
                            </div>
                            <span class="text-xs bg-indigo-50 text-indigo-700 font-semibold px-3 py-1 rounded-full border border-indigo-100">
                                <?= count($sectores) ?> Sectores / <?= count($subsectores) ?> Subsectores
                            </span>
                        </div>

                        <?php if(empty($sectores)): ?>
                            <p class="text-xs text-slate-400 italic text-center py-8">No hay sectores creados. Utilice el formulario de la izquierda.</p>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach($sectores as $s): ?>
                                    <div class="border border-slate-200 rounded-xl overflow-hidden bg-slate-50/50">
                                        <!-- Header del Sector -->
                                        <div class="bg-white p-4 border-b border-slate-200 flex items-center justify-between">
                                            <div class="flex items-center space-x-3">
                                                <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-xs">
                                                    <i class="fas fa-folder text-sm"></i>
                                                </div>
                                                <div>
                                                    <div class="flex items-center space-x-2">
                                                        <h4 class="text-xs font-bold text-slate-900"><?= htmlspecialchars($s['nombre']) ?></h4>
                                                        <span class="text-[10px] font-mono text-slate-500 bg-slate-100 px-1.5 py-0.5 rounded"><?= htmlspecialchars($s['codigo']) ?></span>
                                                    </div>
                                                    <p class="text-[11px] text-slate-500 mt-0.5"><?= htmlspecialchars($s['descripcion'] ?: 'Sin descripción') ?></p>
                                                </div>
                                            </div>
                                            <div class="flex items-center space-x-2">
                                                <span class="text-xs text-slate-400 mr-2"><i class="fas fa-users mr-1"></i> <?= $s['cant_usuarios'] ?> usuarios</span>
                                                <button onclick='cargarEditarSector(<?= json_encode($s) ?>)' 
                                                        title="Editar Sector" class="p-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg transition text-xs">
                                                    <i class="fas fa-pencil"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Lista de Subsectores Hijos -->
                                        <div class="p-3 space-y-2">
                                            <?php 
                                                $subsDelSector = array_filter($subsectores, function($sub) use ($s) {
                                                    return $sub['sector_id'] == $s['id'];
                                                });
                                            ?>
                                            <?php if(empty($subsDelSector)): ?>
                                                <p class="text-[11px] text-slate-400 italic px-2 py-1">Sin subsectores asociados.</p>
                                            <?php else: ?>
                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                                    <?php foreach($subsDelSector as $sub): ?>
                                                        <div class="bg-white p-2.5 rounded-lg border border-slate-200/80 flex items-center justify-between shadow-2xs hover:border-purple-200 transition">
                                                            <div>
                                                                <div class="flex items-center space-x-1.5">
                                                                    <i class="fas fa-turn-up rotate-90 text-slate-300 text-[10px]"></i>
                                                                    <span class="text-xs font-semibold text-slate-800"><?= htmlspecialchars($sub['nombre']) ?></span>
                                                                    <span class="text-[9px] font-mono text-purple-600 bg-purple-50 px-1 rounded"><?= htmlspecialchars($sub['codigo']) ?></span>
                                                                </div>
                                                                <div class="text-[10px] text-slate-400 ml-4 mt-0.5 flex items-center space-x-2">
                                                                    <span><i class="fas fa-users text-[9px] mr-1"></i><?= $sub['cant_usuarios'] ?></span>
                                                                    <span>•</span>
                                                                    <span><i class="fas fa-user-shield text-[9px] mr-1"></i><?= $sub['cant_roles'] ?> roles</span>
                                                                </div>
                                                            </div>
                                                            <div class="flex items-center space-x-1">
                                                                <button onclick='cargarEditarSubsector(<?= json_encode($sub) ?>)' 
                                                                        title="Editar Subsector" class="p-1 text-slate-400 hover:text-purple-600 transition text-xs rounded hover:bg-purple-50">
                                                                    <i class="fas fa-pencil"></i>
                                                                </button>
                                                                <button type="button" 
                                                                        onclick="confirmarEliminarSubsector(<?= $sub['id'] ?>, '<?= htmlspecialchars(addslashes($sub['nombre'])) ?>', <?= (int)$sub['cant_usuarios'] ?>, <?= (int)$sub['cant_roles'] ?>)" 
                                                                        title="Eliminar Subsector" class="p-1 text-slate-400 hover:text-rose-600 transition text-xs rounded hover:bg-rose-50">
                                                                    <i class="fas fa-trash-alt"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        <!-- ========================================== PESTAÑA: ROLES & PERMISOS ========================================== -->
        <?php elseif($tab === 'roles'): ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Columna Izquierda: Form Crear / Editar Rol -->
                <div>
                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs sticky top-20">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2" id="tituloFormRol">
                                <i class="fas fa-shield-cat text-indigo-600"></i> Nuevo Rol
                            </h3>
                            <button type="button" onclick="resetFormRol()" id="btnResetRol" class="text-[11px] text-indigo-600 hover:underline hidden">
                                Cancelar Edición
                            </button>
                        </div>
                        <form method="POST" class="space-y-3" id="formRol">
                            <input type="hidden" name="action" value="guardar_rol">
                            <input type="hidden" name="rol_id" id="inputRolId" value="0">
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Subsector / Área de Pertenencia</label>
                                <select name="subsector_id" id="inputRolSubsector" 
                                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                                    <option value="">Rol Global (Aplica a toda la empresa)</option>
                                    <?php foreach($subsectores as $sub): ?>
                                        <option value="<?= $sub['id'] ?>"><?= htmlspecialchars($sub['sector_nombre']) ?> › <?= htmlspecialchars($sub['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Código del Rol</label>
                                <input type="text" name="codigo" id="inputRolCodigo" placeholder="EJ: ANALISTA_SR" required 
                                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-800 font-mono uppercase focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Nombre del Rol</label>
                                <input type="text" name="nombre" id="inputRolNombre" placeholder="Analista Senior de Tesorería" required 
                                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Descripción</label>
                                <textarea name="descripcion" id="inputRolDesc" rows="2" placeholder="Responsabilidades o nivel de acceso..." 
                                          class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"></textarea>
                            </div>
                            <div class="flex items-center pt-1">
                                <input type="checkbox" name="es_admin" id="inputRolAdmin" value="1" class="rounded text-indigo-600 focus:ring-indigo-500">
                                <label for="inputRolAdmin" class="ml-2 text-xs text-slate-700 font-semibold">¿Es Rol de Administrador Total?</label>
                            </div>
                            <button type="submit" id="btnSubmitRol" class="w-full py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl transition shadow-sm">
                                Guardar Rol
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Columna Derecha: Lista de Roles Agrupados por Sector -> Subsector -->
                <div class="md:col-span-2 space-y-4">
                    <?php
                        // 1. Mapear roles por subsector_id y capturar roles globales
                        $mapaRolesPorSubsector = [];
                        $rolesGlobales = [];

                        foreach($roles as $r) {
                            if (empty($r['subsector_id'])) {
                                $rolesGlobales[] = $r;
                            } else {
                                $mapaRolesPorSubsector[(int)$r['subsector_id']][] = $r;
                            }
                        }

                        // 2. Construir árbol completo con TODOS los sectores y TODOS los subsectores
                        $arbolSectoresRoles = [];
                        foreach($sectores as $s) {
                            $sId = (int)$s['id'];
                            
                            $subsectoresDelSector = array_filter($subsectores, function($sub) use ($sId) {
                                return (int)$sub['sector_id'] === $sId;
                            });

                            $subsConRoles = [];
                            $totalRolesSector = 0;
                            $totalUsuariosSector = 0;

                            foreach($subsectoresDelSector as $sub) {
                                $subId = (int)$sub['id'];
                                $rolesDelSub = $mapaRolesPorSubsector[$subId] ?? [];
                                $totalRolesSector += count($rolesDelSub);
                                
                                foreach($rolesDelSub as $r) {
                                    $totalUsuariosSector += (int)($r['cant_usuarios'] ?? 0);
                                }

                                $subsConRoles[] = [
                                    'id' => $subId,
                                    'nombre' => $sub['nombre'],
                                    'codigo' => $sub['codigo'],
                                    'roles' => $rolesDelSub
                                ];
                            }

                            $arbolSectoresRoles[] = [
                                'id' => $sId,
                                'nombre' => $s['nombre'],
                                'codigo' => $s['codigo'],
                                'subsectores' => $subsConRoles,
                                'total_roles' => $totalRolesSector,
                                'total_usuarios' => $totalUsuariosSector
                            ];
                        }
                    ?>

                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs mb-4">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                                    <i class="fas fa-user-shield text-indigo-600"></i> Roles por Sector & Subsector
                                </h3>
                                <span class="text-xs text-slate-400" id="contadorRolesVisibles"><?= count($roles) ?> roles configurados en <?= count($sectores) ?> sectores</span>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <!-- Buscador Rápido de Roles -->
                                <div class="relative w-full sm:w-60">
                                    <i class="fas fa-search absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                                    <input type="text" id="buscadorRoles" oninput="filtrarRolesEnVivo(this.value)" placeholder="Buscar rol, código, sector..." 
                                           class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-8 pr-3 py-1.5 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition">
                                </div>

                                <!-- Botón Colapsar / Expandir Todos -->
                                <button type="button" onclick="toggleTodosSectoresRoles()" id="btnToggleTodosRoles" 
                                        class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl border border-slate-200 transition flex items-center gap-1.5 cursor-pointer">
                                    <i class="fas fa-layer-group text-[11px]"></i> <span id="lblToggleTodosRoles">Colapsar todos</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4" id="contenedorListaRoles">
                        
                        <!-- 1. Roles Globales (si existen) -->
                        <?php if(!empty($rolesGlobales)): ?>
                            <div class="sector-roles-bloque bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden transition" data-sector="global">
                                <div class="p-3.5 bg-slate-100/80 border-b border-slate-200 flex items-center justify-between cursor-pointer select-none" onclick="toggleSectorRoles('global')">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-7 h-7 rounded-lg bg-slate-700 text-white flex items-center justify-center text-xs shadow-2xs">
                                            <i class="fas fa-globe"></i>
                                        </div>
                                        <div>
                                            <h4 class="text-xs font-bold text-slate-900">Roles Globales (Toda la Empresa)</h4>
                                            <span class="text-[10px] text-slate-500"><?= count($rolesGlobales) ?> roles generales</span>
                                        </div>
                                    </div>
                                    <i class="fas fa-chevron-down text-slate-400 transition-transform duration-200 chevron-sector-roles" id="chevronSectorRoles_global"></i>
                                </div>
                                <div class="p-4 space-y-3 sector-roles-contenido" id="contenidoSectorRoles_global">
                                    <?php foreach($rolesGlobales as $r): ?>
                                        <?php 
                                            $textoBusquedaRol = strtolower($r['nombre'] . ' ' . $r['codigo'] . ' ' . ($r['descripcion'] ?? '') . ' global general');
                                        ?>
                                        <div class="item-rol-card border border-slate-200 rounded-xl p-3.5 bg-white hover:border-indigo-300 hover:shadow-xs transition duration-150" data-search="<?= htmlspecialchars($textoBusquedaRol) ?>">
                                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-2 border-b border-slate-100">
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <h4 class="text-xs font-bold text-slate-900"><?= htmlspecialchars($r['nombre']) ?></h4>
                                                    <span class="text-[10px] font-mono text-slate-600 bg-slate-50 px-2 py-0.5 rounded border border-slate-200 font-semibold"><?= htmlspecialchars($r['codigo']) ?></span>
                                                    <?php if($r['es_admin']): ?>
                                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-100 text-purple-800 border border-purple-200">Admin Total</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="flex items-center gap-2 shrink-0">
                                                    <button onclick="abrirModalPermisos(<?= $r['id'] ?>, '<?= htmlspecialchars($r['nombre']) ?>', <?= (int)$r['es_admin'] ?>)" 
                                                            class="px-2.5 py-1 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-semibold border border-indigo-200 transition flex items-center gap-1 shadow-2xs cursor-pointer">
                                                        <i class="fas fa-sliders text-[11px]"></i> <span><?= $r['cant_permisos'] ?> permisos</span>
                                                    </button>
                                                    <button onclick='cargarEditarRol(<?= json_encode($r) ?>)' 
                                                            title="Editar Rol" class="p-1 bg-white hover:bg-slate-100 text-slate-500 hover:text-indigo-600 rounded-lg transition border border-slate-200 cursor-pointer">
                                                        <i class="fas fa-pencil text-xs"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="pt-2 flex items-center justify-between text-xs text-slate-500">
                                                <p class="text-xs text-slate-600 line-clamp-1 pr-4"><?= htmlspecialchars($r['descripcion'] ?: 'Sin descripción') ?></p>
                                                <span class="shrink-0 text-slate-500 text-[11px] font-medium"><i class="fas fa-users mr-1 text-slate-400"></i> <?= $r['cant_usuarios'] ?> usuarios</span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- 2. Bloques por Sector (TODOS los sectores) -->
                        <?php foreach($arbolSectoresRoles as $sec): ?>
                            <?php $secSlug = 'sec_' . $sec['id']; ?>
                            <div class="sector-roles-bloque bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden transition" data-sector="<?= htmlspecialchars(strtolower($sec['nombre'] . ' ' . $sec['codigo'])) ?>">
                                <!-- Cabecera de Sector -->
                                <div class="p-3.5 bg-slate-50/90 border-b border-slate-200 flex items-center justify-between cursor-pointer select-none" onclick="toggleSectorRoles('<?= $secSlug ?>')">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg bg-indigo-600 text-white flex items-center justify-center text-sm shadow-2xs">
                                            <i class="fas fa-folder-tree"></i>
                                        </div>
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <h3 class="text-sm font-bold text-slate-900"><?= htmlspecialchars($sec['nombre']) ?></h3>
                                                <span class="text-[10px] font-mono text-slate-500 bg-white px-1.5 py-0.2 rounded border border-slate-200 font-semibold"><?= htmlspecialchars($sec['codigo']) ?></span>
                                                <span class="text-[10px] bg-indigo-50 text-indigo-700 border border-indigo-100 px-2 py-0.2 rounded-full font-semibold">
                                                    <?= $sec['total_roles'] ?> <?= $sec['total_roles'] === 1 ? 'rol' : 'roles' ?>
                                                </span>
                                            </div>
                                            <p class="text-[11px] text-slate-500"><?= count($sec['subsectores']) ?> <?= count($sec['subsectores']) === 1 ? 'subsector' : 'subsectores' ?> · <?= $sec['total_usuarios'] ?> usuarios asignados</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <i class="fas fa-chevron-down text-slate-400 transition-transform duration-200 chevron-sector-roles" id="chevronSectorRoles_<?= $secSlug ?>"></i>
                                    </div>
                                </div>

                                <!-- Contenido: TODOS sus Subsectores -->
                                <div class="p-4 space-y-4 sector-roles-contenido" id="contenidoSectorRoles_<?= $secSlug ?>">
                                    <?php if(empty($sec['subsectores'])): ?>
                                        <p class="text-xs text-slate-400 italic py-2">No hay subsectores configurados en este sector.</p>
                                    <?php else: ?>
                                        <?php foreach($sec['subsectores'] as $sub): ?>
                                            <div class="subsector-roles-bloque border border-slate-200/90 rounded-xl p-3.5 bg-slate-50/40" data-subsector="<?= htmlspecialchars(strtolower($sub['nombre'] . ' ' . $sub['codigo'] . ' ' . $sec['nombre'])) ?>">
                                                <div class="flex items-center justify-between pb-2.5 mb-3 border-b border-slate-200/70">
                                                    <div class="flex items-center gap-2">
                                                        <span class="w-2 h-2 rounded-full <?= empty($sub['roles']) ? 'bg-amber-400' : 'bg-indigo-500' ?>"></span>
                                                        <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wide">
                                                            <?= htmlspecialchars($sub['nombre']) ?>
                                                        </h4>
                                                        <span class="text-[10px] bg-white text-slate-600 border border-slate-200 px-2 py-0.2 rounded-full font-semibold">
                                                            <?= count($sub['roles']) ?> <?= count($sub['roles']) === 1 ? 'rol' : 'roles' ?>
                                                        </span>
                                                    </div>

                                                    <button type="button" onclick="crearRolParaSubsector(<?= $sub['id'] ?>)" 
                                                            title="Crear un nuevo rol para este subsector"
                                                            class="px-2 py-0.5 bg-white hover:bg-indigo-50 text-indigo-700 hover:text-indigo-800 text-[11px] font-semibold rounded-lg border border-slate-200 hover:border-indigo-200 transition flex items-center gap-1 cursor-pointer">
                                                        <i class="fas fa-plus text-[9px]"></i> Nuevo Rol
                                                    </button>
                                                </div>

                                                <!-- Tarjetas de Roles o Aviso si está vacío -->
                                                <?php if(empty($sub['roles'])): ?>
                                                    <div class="item-rol-card p-3 bg-white/70 rounded-xl border border-dashed border-slate-200 text-center text-xs text-slate-400 flex items-center justify-between" data-search="<?= htmlspecialchars(strtolower($sec['nombre'] . ' ' . $sub['nombre'] . ' ' . $sub['codigo'])) ?>">
                                                        <span class="italic text-[11px]"><i class="fas fa-info-circle text-slate-300 mr-1"></i> Sin roles creados en este subsector todavía.</span>
                                                        <button type="button" onclick="crearRolParaSubsector(<?= $sub['id'] ?>)" class="text-indigo-600 font-semibold text-xs hover:underline cursor-pointer">
                                                            + Crear primer rol
                                                        </button>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="space-y-2.5">
                                                        <?php foreach($sub['roles'] as $r): ?>
                                                            <?php 
                                                                $textoBusquedaRol = strtolower($r['nombre'] . ' ' . $r['codigo'] . ' ' . $sec['nombre'] . ' ' . $sub['nombre'] . ' ' . ($r['descripcion'] ?? ''));
                                                            ?>
                                                            <div class="item-rol-card border border-slate-200 rounded-xl p-3 bg-white hover:border-indigo-300 hover:shadow-xs transition duration-150" data-search="<?= htmlspecialchars($textoBusquedaRol) ?>">
                                                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-2 border-b border-slate-100">
                                                                    <div class="flex items-center gap-2 flex-wrap">
                                                                        <h4 class="text-xs font-bold text-slate-900"><?= htmlspecialchars($r['nombre']) ?></h4>
                                                                        <span class="text-[10px] font-mono text-slate-600 bg-slate-50 px-2 py-0.5 rounded border border-slate-200 font-semibold"><?= htmlspecialchars($r['codigo']) ?></span>
                                                                        <?php if($r['es_admin']): ?>
                                                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-100 text-purple-800 border border-purple-200">Admin Total</span>
                                                                        <?php endif; ?>
                                                                    </div>

                                                                    <div class="flex items-center gap-2 shrink-0">
                                                                        <button onclick="abrirModalPermisos(<?= $r['id'] ?>, '<?= htmlspecialchars($r['nombre']) ?>', <?= (int)$r['es_admin'] ?>)" 
                                                                                class="px-2.5 py-1 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-semibold border border-indigo-200 transition flex items-center gap-1 shadow-2xs cursor-pointer">
                                                                            <i class="fas fa-sliders text-[11px]"></i> <span><?= $r['cant_permisos'] ?> permisos</span>
                                                                        </button>
                                                                        <button onclick='cargarEditarRol(<?= json_encode($r) ?>)' 
                                                                                title="Editar Rol" class="p-1 bg-white hover:bg-slate-100 text-slate-500 hover:text-indigo-600 rounded-lg transition border border-slate-200 cursor-pointer">
                                                                            <i class="fas fa-pencil text-xs"></i>
                                                                        </button>
                                                                    </div>
                                                                </div>

                                                                <div class="pt-2 flex items-center justify-between text-xs text-slate-500">
                                                                    <p class="text-xs text-slate-600 line-clamp-1 pr-4"><?= htmlspecialchars($r['descripcion'] ?: 'Sin descripción') ?></p>
                                                                    <span class="shrink-0 text-slate-500 text-[11px] font-medium"><i class="fas fa-users mr-1 text-slate-400"></i> <?= $r['cant_usuarios'] ?> usuarios</span>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div id="sinResultadosRoles" class="hidden text-center py-8 text-slate-400 text-xs bg-white rounded-2xl border border-slate-200">
                        <i class="fas fa-search text-slate-300 text-2xl mb-2 block"></i>
                        No se encontraron roles que coincidan con la búsqueda.
                    </div>
                </div>
            </div>

        <!-- ========================================== PESTAÑA: MATRIZ HUB & ACCESOS ========================================== -->
        <?php elseif($tab === 'hub_matriz'): ?>
            <!-- Header de la Matriz & Controles Globales -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs mb-6">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2.5">
                            <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-base shadow-2xs border border-indigo-100">
                                <i class="fas fa-shapes"></i>
                            </div>
                            <div>
                                <h2 class="text-base font-bold text-slate-900">Matriz de Aplicaciones del Hub</h2>
                                <p class="text-xs text-slate-500">Configuración visual de visibilidad y accesos por <strong>Sector ➔ Subsector ➔ Rol</strong> para el Centralizador Hub.</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2.5">
                        <!-- Buscador en tiempo real -->
                        <div class="relative w-full sm:w-64">
                            <i class="fas fa-search absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                            <input type="text" id="buscadorMatrizHub" oninput="filtrarMatrizHubEnVivo(this.value)" placeholder="Buscar por rol, sector, app..." 
                                   class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-8 pr-3 py-1.5 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition">
                        </div>

                        <!-- Expandir / Colapsar todos -->
                        <button type="button" onclick="toggleTodosSectoresHub()" id="btnToggleTodosSectores" 
                                class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl border border-slate-200 transition flex items-center gap-1.5">
                            <i class="fas fa-layer-group"></i> <span id="lblToggleTodosSectores">Expandir todos</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Listado Jerárquico: Sector -> Subsector -> Roles -->
            <?php if(empty($matrizEstructuraHub)): ?>
                <div class="bg-white p-12 text-center rounded-2xl border border-slate-200 text-slate-400">
                    <i class="fas fa-sitemap text-3xl mb-2 text-slate-300"></i>
                    <p class="text-sm">No se encontraron sectores o roles configurados en la base de datos.</p>
                </div>
            <?php else: ?>
                <div class="space-y-6" id="contenedorMatrizHub">
                    <?php foreach($matrizEstructuraHub as $s): ?>
                        <?php 
                            $totalRolesSector = 0;
                            foreach($s['subsectores'] as $sub) { $totalRolesSector += count($sub['roles']); }
                        ?>
                        <div class="sector-hub-bloque bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden transition" data-sector-id="<?= $s['id'] ?>">
                            <!-- Cabecera del Sector -->
                            <div class="p-4 bg-slate-50/80 border-b border-slate-200 flex items-center justify-between cursor-pointer select-none" onclick="toggleSectorHub(<?= $s['id'] ?>)">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-indigo-600 text-white flex items-center justify-center text-sm shadow-2xs">
                                        <i class="fas fa-folder-tree"></i>
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <h3 class="text-sm font-bold text-slate-900"><?= htmlspecialchars($s['nombre']) ?></h3>
                                            <span class="text-[10px] font-mono text-slate-500 bg-white px-2 py-0.5 rounded border border-slate-200 font-semibold"><?= htmlspecialchars($s['codigo']) ?></span>
                                        </div>
                                        <p class="text-[11px] text-slate-500"><?= count($s['subsectores']) ?> subsectores · <?= $totalRolesSector ?> roles · <?= $s['cant_usuarios'] ?> usuarios</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="text-xs text-slate-400 font-medium hidden sm:inline">Click para colapsar/expandir</span>
                                    <i class="fas fa-chevron-down text-slate-400 transition-transform duration-200" id="chevronSector_<?= $s['id'] ?>"></i>
                                </div>
                            </div>

                            <!-- Contenido del Sector: Subsectores y Roles -->
                            <div class="p-5 space-y-6 sector-hub-contenido hidden" id="contenidoSector_<?= $s['id'] ?>">
                                <?php if(empty($s['subsectores'])): ?>
                                    <p class="text-xs text-slate-400 italic py-2">No hay subsectores en este sector.</p>
                                <?php else: ?>
                                    <?php foreach($s['subsectores'] as $sub): ?>
                                        <div class="subsector-hub-bloque border border-slate-200/90 rounded-xl p-4 bg-slate-50/30">
                                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 mb-4 border-b border-slate-200">
                                                <div class="flex items-center gap-2">
                                                    <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                                                    <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wide">
                                                        <?= htmlspecialchars($sub['nombre']) ?>
                                                    </h4>
                                                    <span class="text-[10px] text-slate-400 font-mono">(<?= htmlspecialchars($sub['codigo']) ?>)</span>
                                                    <span class="text-[10px] bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full font-semibold">
                                                        <?= count($sub['roles']) ?> <?= count($sub['roles']) === 1 ? 'rol' : 'roles' ?>
                                                    </span>
                                                </div>

                                                <?php if(!empty($sub['roles'])): ?>
                                                    <!-- Acciones rápidas para todo el subsector -->
                                                    <div class="flex items-center gap-2">
                                                        <span class="text-[11px] text-slate-400 mr-1">Aplicar a todos:</span>
                                                        <button type="button" onclick="aplicarTodosRolesSubsector(<?= $sub['id'] ?>, true)" class="px-2 py-1 bg-white hover:bg-slate-100 text-indigo-700 text-[11px] font-semibold rounded-lg border border-slate-200 transition">
                                                            Habilitar Todas
                                                        </button>
                                                        <button type="button" onclick="aplicarTodosRolesSubsector(<?= $sub['id'] ?>, false)" class="px-2 py-1 bg-white hover:bg-slate-100 text-slate-600 text-[11px] font-semibold rounded-lg border border-slate-200 transition">
                                                            Solo NODO
                                                        </button>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <?php if(empty($sub['roles'])): ?>
                                                <p class="text-xs text-slate-400 italic py-2">No hay roles asociados a este subsector.</p>
                                            <?php else: ?>
                                                <div class="space-y-4">
                                                    <?php foreach($sub['roles'] as $rol): ?>
                                                        <?php 
                                                            $permisosAsignados = $rol['permisos_hub'] ?? [];
                                                            $cantActivas = count($permisosAsignados);
                                                            $totalApps = count($modulosHub);
                                                            $searchTerms = strtolower($s['nombre'] . ' ' . $sub['nombre'] . ' ' . $rol['nombre'] . ' ' . $rol['codigo']);
                                                        ?>
                                                        <div class="rol-hub-card bg-white border border-slate-200 rounded-xl p-4 shadow-2xs hover:border-indigo-200 transition" 
                                                             data-search="<?= htmlspecialchars($searchTerms) ?>" 
                                                             data-rol-id="<?= $rol['id'] ?>">
                                                            
                                                            <!-- Encabezado de la Tarjeta del Rol -->
                                                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100">
                                                                <div>
                                                                    <div class="flex items-center gap-2 flex-wrap">
                                                                        <h5 class="text-xs font-extrabold text-slate-900 flex items-center gap-1.5">
                                                                            <i class="fas fa-user-shield text-indigo-600"></i> <?= htmlspecialchars($rol['nombre']) ?>
                                                                        </h5>
                                                                        <span class="text-[10px] font-mono text-slate-600 bg-slate-100 px-2 py-0.5 rounded font-bold"><?= htmlspecialchars($rol['codigo']) ?></span>
                                                                        <?php if($rol['es_admin']): ?>
                                                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-100 text-purple-800 border border-purple-200">Admin Total</span>
                                                                        <?php endif; ?>
                                                                        <span class="text-[10px] text-slate-400 font-medium">
                                                                            <i class="fas fa-users mr-1"></i><?= $rol['cant_usuarios'] ?> usuarios
                                                                        </span>
                                                                    </div>
                                                                </div>

                                                                <div class="flex items-center gap-2 shrink-0">
                                                                    <!-- Badge Contador de Apps -->
                                                                    <span id="badgeRolApps_<?= $rol['id'] ?>" class="px-2.5 py-1 rounded-lg text-xs font-bold <?= $cantActivas > 0 ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' : 'bg-slate-100 text-slate-500 border border-slate-200' ?>">
                                                                        <span class="count-val"><?= $cantActivas ?></span> / <?= $totalApps ?> apps activas
                                                                    </span>

                                                                    <!-- Botones de Acción Rápida por Rol -->
                                                                    <button type="button" onclick="marcarTodasAppsRol(<?= $rol['id'] ?>, true)" 
                                                                            title="Habilitar todas las aplicaciones" 
                                                                            class="p-1.5 bg-slate-50 hover:bg-emerald-50 text-slate-600 hover:text-emerald-700 rounded-lg border border-slate-200 text-xs transition">
                                                                        <i class="fas fa-check-double"></i>
                                                                    </button>
                                                                    <button type="button" onclick="marcarTodasAppsRol(<?= $rol['id'] ?>, false)" 
                                                                            title="Desmarcar todas las aplicaciones" 
                                                                            class="p-1.5 bg-slate-50 hover:bg-rose-50 text-slate-600 hover:text-rose-700 rounded-lg border border-slate-200 text-xs transition">
                                                                        <i class="fas fa-ban"></i>
                                                                    </button>
                                                                    <button type="button" onclick="abrirModalCopiarPermisosHub(<?= $rol['id'] ?>)" 
                                                                            title="Copiar accesos de este rol a otros" 
                                                                            class="p-1.5 bg-slate-50 hover:bg-indigo-50 text-slate-600 hover:text-indigo-700 rounded-lg border border-slate-200 text-xs transition">
                                                                        <i class="fas fa-copy"></i>
                                                                    </button>
                                                                </div>
                                                            </div>

                                                            <!-- Grid de Aplicaciones del Hub para este Rol -->
                                                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-2.5 pt-3.5">
                                                                <?php foreach($modulosHub as $app): ?>
                                                                    <?php 
                                                                        $pClave = $app['permiso_clave'];
                                                                        $pId = (int)$app['permiso_id'];
                                                                        $estaActivo = isset($permisosAsignados[$pClave]) || ($rol['es_admin'] && $pClave === 'hub.app.nodo');
                                                                        $tieneSubmodulos = !empty($app['cant_submodulos']) && (int)$app['cant_submodulos'] > 0;
                                                                    ?>
                                                                    <div class="app-hub-item flex items-center justify-between p-2.5 rounded-xl border transition <?= $estaActivo ? 'bg-indigo-50/40 border-indigo-200' : 'bg-slate-50/60 border-slate-200/80 hover:border-slate-300' ?>" id="itemApp_<?= $rol['id'] ?>_<?= $pId ?>">
                                                                        <div class="flex items-center gap-2.5 min-w-0 pr-2">
                                                                            <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white shrink-0 text-xs shadow-2xs" 
                                                                                 style="background: <?= htmlspecialchars($app['color'] ?: 'linear-gradient(135deg, #4f46e5 0%, #6366f1 100%)') ?>;">
                                                                                <i class="fa-solid <?= htmlspecialchars($app['icono'] ?: 'fa-cube') ?>"></i>
                                                                            </div>
                                                                            <div class="min-w-0">
                                                                                <div class="text-xs font-bold text-slate-800 truncate" title="<?= htmlspecialchars($app['nombre']) ?>">
                                                                                    <?= htmlspecialchars($app['nombre']) ?>
                                                                                </div>
                                                                                <?php if($tieneSubmodulos): ?>
                                                                                    <button type="button" 
                                                                                            onclick="abrirModalSubmodulos(<?= $rol['id'] ?>, <?= $app['id'] ?>, '<?= htmlspecialchars(addslashes($rol['nombre'])) ?>', '<?= htmlspecialchars(addslashes($app['nombre'])) ?>')" 
                                                                                            class="inline-flex items-center gap-1 text-[10px] text-indigo-600 hover:text-indigo-800 font-semibold hover:underline mt-0.5" 
                                                                                            title="Configurar submódulos y menús internos">
                                                                                        <i class="fas fa-sliders text-[9px]"></i> Configurar menús (<?= $app['cant_submodulos'] ?>)
                                                                                    </button>
                                                                                <?php else: ?>
                                                                                    <div class="text-[10px] text-slate-400 font-mono truncate" title="<?= htmlspecialchars($app['ruta_url']) ?>">
                                                                                        <?= htmlspecialchars($app['ruta_url']) ?>
                                                                                    </div>
                                                                                <?php endif; ?>
                                                                            </div>
                                                                        </div>

                                                                        <!-- Toggle Switch Switcher -->
                                                                        <label class="relative inline-flex items-center cursor-pointer shrink-0">
                                                                            <input type="checkbox" 
                                                                                   class="sr-only peer switch-hub-permiso" 
                                                                                   data-rol-id="<?= $rol['id'] ?>" 
                                                                                   data-modulo-id="<?= $app['id'] ?>"
                                                                                   data-modulo-nombre="<?= htmlspecialchars($app['nombre']) ?>"
                                                                                   data-rol-nombre="<?= htmlspecialchars($rol['nombre']) ?>"
                                                                                   data-tiene-submodulos="<?= $tieneSubmodulos ? '1' : '0' ?>"
                                                                                   data-permiso-id="<?= $pId ?>" 
                                                                                   data-permiso-clave="<?= htmlspecialchars($pClave) ?>"
                                                                                   <?= $estaActivo ? 'checked' : '' ?> 
                                                                                   onchange="toggleHubPermiso(this, <?= $rol['id'] ?>, <?= $pId ?>)">
                                                                            <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600"></div>
                                                                        </label>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>

                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <!-- ========================================== PESTAÑA: MÓDULOS & APLICACIONES (CRUD) ========================================== -->
        <?php elseif($tab === 'modulos'): ?>
            <?php
                // Agrupar módulos por categoría
                $modulosPorCategoria = [];
                foreach ($modulosTodos as $m) {
                    $cat = trim($m['categoria']) ?: 'General';
                    $modulosPorCategoria[$cat][] = $m;
                }
                ksort($modulosPorCategoria);

                $totalMods = count($modulosTodos);
                $totalModsActivos = count(array_filter($modulosTodos, function($m){ return $m['activo'] == 1; }));
                $totalPermisosTotal = array_sum(array_column($modulosTodos, 'cant_permisos_total'));
                $totalSubmodsTotal = array_sum(array_column($modulosTodos, 'cant_submodulos'));
            ?>

            <!-- Header de Módulos & Controles -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs mb-6">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2.5">
                            <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-lg shadow-2xs border border-indigo-100">
                                <i class="fas fa-cubes"></i>
                            </div>
                            <div>
                                <h2 class="text-base font-bold text-slate-900">Gestor de Módulos & Aplicaciones del Hub</h2>
                                <p class="text-xs text-slate-500">Creación, edición y administración de sistemas del Hub con sus solapas y permisos internos.</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2.5">
                        <!-- Buscador en tiempo real -->
                        <div class="relative w-full sm:w-64">
                            <i class="fas fa-search absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                            <input type="text" id="buscadorModulos" oninput="filtrarModulosEnVivo(this.value)" placeholder="Buscar módulo, código, URL..." 
                                   class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-8 pr-3 py-1.5 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition">
                        </div>

                        <!-- Botón Nuevo Módulo -->
                        <button type="button" onclick="abrirModalCrearModulo()" 
                                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl transition flex items-center gap-1.5 shadow-xs cursor-pointer">
                            <i class="fas fa-plus"></i> Nueva Aplicación / Módulo
                        </button>
                    </div>
                </div>

                <!-- Métricas Resumen -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-5 pt-4 border-t border-slate-100">
                    <div class="bg-slate-50 border border-slate-200/70 rounded-xl p-3">
                        <span class="text-[11px] font-semibold text-slate-500 block">Total Aplicaciones</span>
                        <span class="text-lg font-bold text-slate-900"><?= $totalMods ?></span>
                    </div>
                    <div class="bg-emerald-50/50 border border-emerald-200/70 rounded-xl p-3">
                        <span class="text-[11px] font-semibold text-emerald-700 block">Apps Habilitadas / Activas</span>
                        <span class="text-lg font-bold text-emerald-800"><?= $totalModsActivos ?></span>
                    </div>
                    <div class="bg-indigo-50/50 border border-indigo-200/70 rounded-xl p-3">
                        <span class="text-[11px] font-semibold text-indigo-700 block">Solapas & Permisos Totales</span>
                        <span class="text-lg font-bold text-indigo-800"><?= $totalPermisosTotal ?></span>
                    </div>
                    <div class="bg-purple-50/50 border border-purple-200/70 rounded-xl p-3">
                        <span class="text-[11px] font-semibold text-purple-700 block">Submódulos / Menús Internos</span>
                        <span class="text-lg font-bold text-purple-800"><?= $totalSubmodsTotal ?></span>
                    </div>
                </div>
            </div>

            <!-- Listado de Módulos Agrupados por Categoría -->
            <div class="space-y-6" id="contenedorCategoriasModulos">
                <?php foreach ($modulosPorCategoria as $categoriaNombre => $listaMods): ?>
                    <div class="categoria-modulos-bloque bg-white p-5 rounded-2xl border border-slate-200 shadow-xs" data-categoria="<?= htmlspecialchars(strtolower($categoriaNombre)) ?>">
                        <!-- Cabecera de Categoría -->
                        <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-100">
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-indigo-600"></span>
                                <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wide">
                                    <?= htmlspecialchars($categoriaNombre) ?>
                                </h3>
                                <span class="text-xs bg-slate-100 text-slate-600 border border-slate-200 px-2 py-0.5 rounded-full font-semibold">
                                    <?= count($listaMods) ?> <?= count($listaMods) === 1 ? 'app' : 'apps' ?>
                                </span>
                            </div>
                        </div>

                        <!-- Grilla de Tarjetas de Módulos -->
                        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                            <?php foreach ($listaMods as $m): ?>
                                <div class="modulo-card border border-slate-200 rounded-xl p-4 bg-white hover:border-indigo-300 hover:shadow-xs transition flex flex-col justify-between"
                                     data-search="<?= htmlspecialchars(strtolower($m['nombre'] . ' ' . $m['codigo'] . ' ' . $m['categoria'] . ' ' . $m['ruta_url'] . ' ' . $m['descripcion'])) ?>">
                                    
                                    <div>
                                        <!-- Header de la Tarjeta: Ícono, Nombre y Badges -->
                                        <div class="flex items-start justify-between gap-3 mb-3">
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 rounded-xl text-white flex items-center justify-center text-base shadow-xs shrink-0" 
                                                     style="background: <?= htmlspecialchars($m['color'] ?: 'linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%)') ?>;">
                                                    <i class="fas <?= htmlspecialchars($m['icono'] ?: 'fa-cube') ?>"></i>
                                                </div>
                                                <div>
                                                    <h4 class="text-sm font-bold text-slate-900 leading-tight">
                                                        <?= htmlspecialchars($m['nombre']) ?>
                                                    </h4>
                                                    <div class="flex items-center gap-1.5 mt-1">
                                                        <span class="font-mono text-[10px] bg-slate-100 text-slate-700 font-bold px-1.5 py-0.2 rounded border border-slate-200">
                                                            <?= htmlspecialchars($m['codigo']) ?>
                                                        </span>
                                                        <span class="text-[10px] text-slate-400 font-semibold">#<?= $m['orden'] ?></span>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="flex flex-col items-end gap-1 shrink-0">
                                                <?php if($m['activo']): ?>
                                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center gap-1">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Activo
                                                    </span>
                                                <?php else: ?>
                                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-500 border border-slate-200">
                                                        Inactivo
                                                    </span>
                                                <?php endif; ?>

                                                <?php if($m['target_blank']): ?>
                                                    <span class="text-[10px] text-sky-600 bg-sky-50 px-1.5 py-0.2 rounded border border-sky-100 font-medium" title="Abre en nueva pestaña">
                                                        <i class="fas fa-arrow-up-right-from-square"></i> Nueva pestaña
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- URL Destino -->
                                        <div class="mb-2.5">
                                            <div class="text-[11px] font-mono text-indigo-700 bg-indigo-50/60 border border-indigo-100/80 px-2.5 py-1 rounded-lg flex items-center justify-between">
                                                <span class="truncate font-semibold"><i class="fas fa-link mr-1 text-indigo-400"></i><?= htmlspecialchars($m['ruta_url'] ?: 'Sin URL configurada') ?></span>
                                                <?php if(!empty($m['ruta_url'])): ?>
                                                    <a href="<?= htmlspecialchars($m['ruta_url']) ?>" target="_blank" class="text-indigo-400 hover:text-indigo-700 ml-1.5" title="Abrir enlace">
                                                        <i class="fas fa-external-link-alt text-[10px]"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Descripción -->
                                        <p class="text-xs text-slate-500 line-clamp-2 leading-relaxed mb-3">
                                            <?= htmlspecialchars($m['descripcion'] ?: 'Sin descripción detallada.') ?>
                                        </p>

                                        <!-- Estadísticas / Conteo -->
                                        <div class="flex items-center gap-2 text-[11px] text-slate-600 mb-4">
                                            <span class="bg-slate-50 border border-slate-200 px-2 py-0.5 rounded-md">
                                                <i class="fas fa-list-check text-indigo-500 mr-1"></i> <strong><?= $m['cant_permisos_total'] ?></strong> solapas/permisos
                                            </span>
                                            <span class="bg-slate-50 border border-slate-200 px-2 py-0.5 rounded-md">
                                                <i class="fas fa-users text-indigo-500 mr-1"></i> <strong><?= $m['cant_roles_asignados'] ?></strong> roles con acceso
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Acciones de la Tarjeta -->
                                    <div class="pt-3 border-t border-slate-100 flex items-center justify-between gap-2">
                                        <button type="button" onclick="abrirModalGestionPermisos(<?= $m['id'] ?>)" 
                                                class="px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-semibold rounded-lg border border-indigo-200 transition flex items-center gap-1.5 cursor-pointer">
                                            <i class="fas fa-sliders text-[11px]"></i> Solapas & Permisos (<?= $m['cant_permisos_total'] ?>)
                                        </button>

                                        <div class="flex items-center gap-1">
                                            <button type="button" onclick="abrirModalEditarModulo(<?= $m['id'] ?>)" 
                                                    class="p-1.5 text-slate-500 hover:text-indigo-600 hover:bg-slate-100 rounded-lg transition" title="Editar Módulo">
                                                <i class="fas fa-edit text-xs"></i>
                                            </button>
                                            <button type="button" onclick="confirmarEliminarModulo(<?= $m['id'] ?>, '<?= htmlspecialchars(addslashes($m['nombre'])) ?>')" 
                                                    class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition" title="Eliminar Módulo">
                                                <i class="fas fa-trash-alt text-xs"></i>
                                            </button>
                                        </div>
                                    </div>

                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div id="sinResultadosModulos" class="hidden text-center py-12 text-slate-400 text-xs bg-white rounded-2xl border border-slate-200">
                <i class="fas fa-search text-slate-300 text-3xl mb-2 block"></i>
                No se encontraron módulos que coincidan con la búsqueda.
            </div>

        <!-- ========================================== PESTAÑA: EQUIPOS DE TRABAJO (CENTRAL Y LOCALES) ========================================== -->
        <?php elseif($tab === 'equipos'): ?>
            <!-- Header de Equipos -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs mb-6">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2.5">
                            <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-lg shadow-2xs border border-blue-100">
                                <i class="fas fa-people-roof"></i>
                            </div>
                            <div>
                                <h2 class="text-base font-bold text-slate-900">Gestión de Equipos de Trabajo & Liderazgos</h2>
                                <p class="text-xs text-slate-500">Definición de equipos por sector, asignación de Líder / Jefe, Gerente de Área y colaboradores para flujos jerárquicos (Vacaciones Central, autorizaciones y coberturas).</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2.5">
                        <div class="relative w-full sm:w-64">
                            <i class="fas fa-search absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                            <input type="text" id="buscadorEquipos" oninput="filtrarEquiposEnVivo(this.value)" placeholder="Buscar equipo, líder, sector..." 
                                   class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-8 pr-3 py-1.5 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition">
                        </div>

                        <button type="button" onclick="abrirModalCrearEquipo()" 
                                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl transition flex items-center gap-1.5 shadow-xs cursor-pointer">
                            <i class="fas fa-plus"></i> Nuevo Equipo
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tabla de Equipos -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs" id="tablaEquipos">
                        <thead>
                            <tr class="bg-slate-50/80 border-b border-slate-200 text-[11px] font-bold text-slate-600 uppercase tracking-wider">
                                <th class="py-3 px-4">Equipo de Trabajo</th>
                                <th class="py-3 px-4">Sector General</th>
                                <th class="py-3 px-4">Líder / Jefe de Equipo</th>
                                <th class="py-3 px-4">Gerente Validador</th>
                                <th class="py-3 px-4 text-center">Integrantes</th>
                                <th class="py-3 px-4 text-center">Ámbito</th>
                                <th class="py-3 px-4 text-center">Estado</th>
                                <th class="py-3 px-4 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if(empty($equiposTotal)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-12 text-slate-400">
                                        <i class="fas fa-people-roof text-3xl mb-2 text-slate-300 block"></i>
                                        No hay equipos registrados aún. Cree uno nuevo para comenzar.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($equiposTotal as $eq): ?>
                                    <tr class="hover:bg-slate-50/60 transition item-equipo" data-search="<?= strtolower(htmlspecialchars($eq['nombre'] . ' ' . ($eq['sector_nombre'] ?? '') . ' ' . ($eq['lider_nombre'] ?? '') . ' ' . ($eq['gerente_nombre'] ?? ''))) ?>">
                                        <td class="py-3.5 px-4">
                                            <div class="font-bold text-slate-900"><?= htmlspecialchars($eq['nombre']) ?></div>
                                            <?php if(!empty($eq['descripcion'])): ?>
                                                <div class="text-[11px] text-slate-400 line-clamp-1"><?= htmlspecialchars($eq['descripcion']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3.5 px-4">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-slate-100 text-slate-700">
                                                <?= htmlspecialchars($eq['sector_nombre'] ?? 'Sin Sector') ?>
                                            </span>
                                        </td>
                                        <td class="py-3.5 px-4">
                                            <?php if(!empty($eq['lider_nombre'])): ?>
                                                <div class="font-semibold text-slate-800 flex items-center gap-1.5">
                                                    <i class="fas fa-user-tie text-blue-500 text-xs"></i>
                                                    <?= htmlspecialchars($eq['lider_nombre']) ?>
                                                </div>
                                                <div class="text-[10px] text-slate-400">Legajo #<?= $eq['lider_legajo'] ?: '-' ?></div>
                                            <?php else: ?>
                                                <span class="text-slate-400 italic">Sin Líder Asignado</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3.5 px-4">
                                            <?php if(!empty($eq['gerente_nombre'])): ?>
                                                <div class="font-semibold text-slate-800 flex items-center gap-1.5">
                                                    <i class="fas fa-shield-halved text-purple-500 text-xs"></i>
                                                    <?= htmlspecialchars($eq['gerente_nombre']) ?>
                                                </div>
                                                <div class="text-[10px] text-slate-400">Legajo #<?= $eq['gerente_legajo'] ?: '-' ?></div>
                                            <?php else: ?>
                                                <span class="text-slate-400 italic">Sin Gerente</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3.5 px-4 text-center">
                                            <button type="button" onclick="verMiembrosEquipo(<?= $eq['id'] ?>, '<?= htmlspecialchars(addslashes($eq['nombre'])) ?>')" 
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-indigo-50 text-indigo-700 hover:bg-indigo-100 transition cursor-pointer">
                                                <i class="fas fa-users text-[10px]"></i>
                                                <?= (int)$eq['cant_miembros'] ?> integrantes
                                            </button>
                                        </td>
                                        <td class="py-3.5 px-4 text-center">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold <?= $eq['tipo_flujo'] === 'LOCALES' ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200' ?>">
                                                <?= htmlspecialchars($eq['tipo_flujo'] ?: 'CENTRAL') ?>
                                            </span>
                                        </td>
                                        <td class="py-3.5 px-4 text-center">
                                            <?php if($eq['activo']): ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-100 text-emerald-800">Activo</span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-500">Inactivo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3.5 px-4 text-right">
                                            <div class="flex items-center justify-end gap-1.5">
                                                <button type="button" onclick="editarEquipo(<?= $eq['id'] ?>)" 
                                                        class="p-1.5 text-slate-400 hover:text-indigo-600 hover:bg-slate-100 rounded-lg transition" title="Editar Equipo">
                                                    <i class="fas fa-pen text-xs"></i>
                                                </button>
                                                <form method="POST" onsubmit="return confirm('¿Está seguro de eliminar el equipo <?= htmlspecialchars(addslashes($eq['nombre'])) ?>? Los miembros no se eliminarán pero quedarán desvinculados del equipo.');" class="inline">
                                                    <input type="hidden" name="action" value="eliminar_equipo">
                                                    <input type="hidden" name="equipo_id" value="<?= $eq['id'] ?>">
                                                    <input type="hidden" name="nombre_equipo" value="<?= htmlspecialchars($eq['nombre']) ?>">
                                                    <button type="submit" class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-slate-100 rounded-lg transition" title="Eliminar">
                                                        <i class="fas fa-trash-alt text-xs"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    </main>

    <!-- Modal Configurar Submódulos y Accesos Internos de un Centralizador -->
    <div id="modalSubmodulosCentralizador" class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs hidden items-center justify-center z-50 p-4">
        <div class="bg-white border border-slate-200 rounded-2xl max-w-2xl w-full p-6 shadow-xl max-h-[90vh] flex flex-col">
            <!-- Cabecera del Modal -->
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-3 shrink-0">
                <div class="flex items-center gap-2.5">
                    <div id="submodModalIconBox" class="w-9 h-9 rounded-xl bg-indigo-600 text-white flex items-center justify-center text-sm shadow-2xs">
                        <i class="fas fa-sliders"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 flex items-center gap-1.5" id="submodModalTitulo">
                            Accesos Internos: <span id="submodModalModNombre" class="text-indigo-600">Centralizador</span>
                        </h3>
                        <p class="text-xs text-slate-500">
                            Rol: <strong id="submodModalRolNombre" class="text-slate-800">Nombre Rol</strong> · Seleccioná las aplicaciones permitidas
                        </p>
                    </div>
                </div>
                <button type="button" onclick="cerrarModal('modalSubmodulosCentralizador')" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
            </div>

            <!-- Barra de Controles y Buscador del Modal -->
            <div class="flex flex-wrap items-center justify-between gap-2.5 pb-3 border-b border-slate-100 mb-3 shrink-0">
                <div class="relative w-full sm:w-64">
                    <i class="fas fa-search absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                    <input type="text" id="buscadorSubmodulos" oninput="filtrarSubmodulosEnModal(this.value)" placeholder="Buscar aplicación o menú..." 
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-8 pr-3 py-1.5 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                </div>
                <div class="flex items-center gap-2">
                    <span id="submodModalContador" class="text-xs font-bold text-indigo-700 bg-indigo-50 border border-indigo-200 px-2.5 py-1 rounded-lg">
                        0 / 0 habilitadas
                    </span>
                    <button type="button" onclick="toggleTodosSubmodulos(true)" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-lg transition">
                        Marcar Todo
                    </button>
                    <button type="button" onclick="toggleTodosSubmodulos(false)" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-lg transition">
                        Desmarcar Todo
                    </button>
                </div>
            </div>

            <!-- Contenido Scrolleable con Secciones y Checkboxes -->
            <div class="overflow-y-auto flex-1 pr-1 space-y-4" id="submodModalContenido">
                <div class="py-12 text-center text-slate-400">
                    <i class="fas fa-spinner fa-spin text-2xl text-indigo-500 mb-2 block"></i>
                    Cargando menú y aplicaciones internas...
                </div>
            </div>

            <!-- Footer del Modal -->
            <div class="flex justify-end space-x-2 pt-3 border-t border-slate-100 mt-3 shrink-0">
                <button type="button" onclick="cerrarModal('modalSubmodulosCentralizador')" class="px-4 py-2 text-xs font-medium text-slate-500 hover:text-slate-800 transition">
                    Cancelar
                </button>
                <button type="button" onclick="guardarSubmodulosCentralizador()" id="btnGuardarSubmodulos" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl transition shadow-xs flex items-center gap-1.5">
                    <i class="fas fa-check"></i> Guardar Accesos Internos
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Copiar Permisos Hub entre Roles -->
    <div id="modalCopiarPermisosHub" class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs hidden items-center justify-center z-50 p-4">
        <div class="bg-white border border-slate-200 rounded-2xl max-w-lg w-full p-6 shadow-xl max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                    <i class="fas fa-copy text-indigo-600"></i> Replicar Accesos Hub entre Roles
                </h3>
                <button type="button" onclick="cerrarModal('modalCopiarPermisosHub')" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
            </div>
            
            <form id="formCopiarPermisosHub" onsubmit="ejecutarCopiaPermisosHub(event)" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">1. Rol Modelo (Origen de los accesos) *</label>
                    <select id="copiaRolOrigen" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs text-slate-800 font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                        <option value="">-- Seleccionar Rol Origen --</option>
                        <?php foreach($roles as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nombre']) ?> (<?= htmlspecialchars($r['codigo']) ?>) - <?= htmlspecialchars($r['sector_nombre'] ?: 'General') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-xs font-bold text-slate-700">2. Roles Destino a Actualizar *</label>
                        <button type="button" onclick="toggleTodosRolesDestino()" class="text-[11px] text-indigo-600 font-semibold hover:underline">
                            Seleccionar Todos
                        </button>
                    </div>
                    <div class="max-h-56 overflow-y-auto border border-slate-200 rounded-xl p-3 bg-slate-50/50 space-y-1.5" id="listaRolesDestino">
                        <?php foreach($roles as $r): ?>
                            <label class="flex items-center p-2 rounded-lg bg-white border border-slate-200 hover:border-indigo-300 cursor-pointer text-xs">
                                <input type="checkbox" name="roles_destino[]" value="<?= $r['id'] ?>" class="rounded text-indigo-600 focus:ring-indigo-500 chk-rol-destino">
                                <span class="ml-2 font-semibold text-slate-800"><?= htmlspecialchars($r['nombre']) ?></span>
                                <span class="ml-1 text-[10px] text-slate-400 font-mono">(<?= htmlspecialchars($r['codigo']) ?>)</span>
                                <span class="ml-auto text-[10px] text-slate-500"><?= htmlspecialchars($r['subsector_nombre'] ?: 'General') ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="flex justify-end space-x-2 pt-3 border-t border-slate-100">
                    <button type="button" onclick="cerrarModal('modalCopiarPermisosHub')" class="px-4 py-2 text-xs font-medium text-slate-500 hover:text-slate-800 transition">Cancelar</button>
                    <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl transition shadow-xs">
                        <i class="fas fa-check mr-1.5"></i> Replicar Accesos Ahora
                    </button>
                </div>
            </form>
        </div>
    </div>



    <!-- Modal Editar Permisos por Rol (Matriz N a N) -->
    <div id="modalPermisos" class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs hidden items-center justify-center z-50 p-4">
        <div class="bg-white border border-slate-200 rounded-2xl max-w-3xl w-full p-6 shadow-xl max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between pb-3 border-b border-slate-200 mb-4">
                <div>
                    <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                        <i class="fas fa-sliders text-indigo-600"></i> Matriz de Permisos para el Rol: <span id="nombreRolModal" class="text-indigo-600 font-bold"></span>
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">Marque las aplicaciones y acciones permitidas para este rol.</p>
                </div>
                <button type="button" onclick="cerrarModal('modalPermisos')" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
            </div>

            <form method="POST" id="formPermisosRol">
                <input type="hidden" name="action" value="guardar_permisos_rol">
                <input type="hidden" name="rol_id" id="permisosRolId">

                <div class="space-y-4 mb-6">
                    <?php foreach($modulos as $mod): ?>
                        <div class="app-card border border-slate-200 rounded-xl overflow-hidden bg-slate-50/50 shadow-xs">
                            <div class="bg-slate-100/90 px-4 py-3 flex items-center justify-between border-b border-slate-200 cursor-pointer select-none hover:bg-slate-200/70 transition" onclick="toggleAppCollapse(this, event)">
                                <div class="flex items-center space-x-2.5">
                                    <div class="w-7 h-7 rounded-lg bg-indigo-600/10 text-indigo-600 flex items-center justify-center">
                                        <i class="fas <?= htmlspecialchars($mod['icono'] ?: 'fa-cube') ?> text-xs"></i>
                                    </div>
                                    <div>
                                        <span class="font-bold text-xs text-slate-900 block"><?= htmlspecialchars($mod['nombre']) ?></span>
                                        <span class="text-[10px] text-slate-400 font-mono"><?= htmlspecialchars($mod['codigo']) ?></span>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <button type="button" onclick="event.stopPropagation(); toggleTodosApp(this)" class="text-[11px] text-indigo-600 font-semibold hover:underline bg-white px-2.5 py-1 rounded-lg border border-slate-200 shadow-2xs cursor-pointer">
                                        Marcar / Desmarcar Toda la App
                                    </button>
                                    <div class="w-6 h-6 rounded-full bg-white border border-slate-200 text-slate-500 flex items-center justify-center text-xs transition-transform duration-200 app-chevron">
                                        <i class="fas fa-chevron-down"></i>
                                    </div>
                                </div>
                            </div>

                            <!-- Contenido de la app: cerrado por defecto -->
                            <div class="app-content p-4 bg-white space-y-4 hidden">
                                <?php if(empty($mod['submodulos'])): ?>
                                    <p class="text-[11px] text-slate-400 italic">Sin permisos registrados para esta aplicación.</p>
                                <?php else: ?>
                                    <?php foreach($mod['submodulos'] as $subNombre => $permisosList): ?>
                                        <div class="submodulo-card border border-slate-100 rounded-xl p-3 bg-slate-50/30">
                                            <div class="flex items-center justify-between pb-2 mb-2 border-b border-slate-100">
                                                <div class="flex items-center space-x-1.5">
                                                    <i class="fas fa-layer-group text-slate-400 text-[10px]"></i>
                                                    <span class="text-xs font-bold text-slate-800"><?= htmlspecialchars($subNombre) ?></span>
                                                    <span class="text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded font-medium"><?= count($permisosList) ?> acciones</span>
                                                </div>
                                                <button type="button" onclick="toggleTodosSubmodulo(this)" class="text-[10px] text-slate-500 hover:text-indigo-600 font-medium cursor-pointer">
                                                    Marcar / Desmarcar Módulo
                                                </button>
                                            </div>
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                                <?php foreach($permisosList as $p): ?>
                                                    <label class="flex items-start p-2.5 rounded-lg border border-slate-100 hover:bg-indigo-50/30 hover:border-indigo-200 transition cursor-pointer bg-white">
                                                        <input type="checkbox" name="permisos[]" value="<?= $p['id'] ?>" class="permiso-checkbox mt-0.5 rounded text-indigo-600 focus:ring-indigo-500">
                                                        <div class="ml-2.5">
                                                            <span class="block text-xs font-semibold text-slate-800"><?= htmlspecialchars($p['nombre']) ?></span>
                                                            <span class="block text-[10px] font-mono text-indigo-600"><?= htmlspecialchars($p['clave']) ?></span>
                                                            <?php if(!empty($p['descripcion'])): ?>
                                                                <span class="block text-[10px] text-slate-400 mt-0.5"><?= htmlspecialchars($p['descripcion']) ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="flex justify-end space-x-2 pt-3 border-t border-slate-200">
                    <button type="button" onclick="cerrarModal('modalPermisos')" class="px-4 py-2 text-xs font-medium text-slate-500 hover:text-slate-800 transition">Cancelar</button>
                    <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl transition shadow-xs">Guardar Permisos</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Editar Pass Directo -->
    <div id="modalPass" class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs hidden items-center justify-center z-50 p-4">
        <div class="bg-white border border-slate-200 rounded-2xl max-w-md w-full p-6 shadow-xl">
            <h3 class="text-sm font-bold text-slate-900 mb-1 flex items-center">
                <i class="fas fa-key text-amber-500 mr-2"></i> Modificar Contraseña en SQL
            </h3>
            <p class="text-xs text-slate-500 mb-4">Usuario: <span id="modalUsername" class="text-slate-800 font-mono font-bold"></span></p>
            <form method="POST" id="formPass" onsubmit="guardarPassAjax(event)">
                <input type="hidden" name="action" value="cambiar_pass">
                <input type="hidden" name="user_id" id="modalUserId">
                <input type="hidden" name="return_query" class="input-current-query" value="">
                
                <div class="mb-4">
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Nueva Contraseña (Texto Visible SQL)</label>
                    <input type="text" name="nueva_pass" id="modalNuevaPass" required 
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs text-slate-900 font-mono font-bold focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                </div>

                <div class="flex justify-end space-x-2 pt-2">
                    <button type="button" onclick="cerrarModal('modalPass')" class="px-3.5 py-2 text-xs font-medium text-slate-500 hover:text-slate-800 transition">Cancelar</button>
                    <button type="submit" id="btnGuardarPass" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl transition shadow-xs cursor-pointer">Guardar en SQL</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Confirmar Eliminación de Usuario -->
    <div id="modalEliminarUsuario" class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs hidden items-center justify-center z-50 p-4">
        <div class="bg-white border border-slate-200 rounded-2xl max-w-sm w-full p-6 shadow-xl">
            <div class="w-12 h-12 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center mb-4 mx-auto border border-rose-100">
                <i class="fas fa-triangle-exclamation text-xl"></i>
            </div>
            <h3 class="text-base font-bold text-slate-900 text-center mb-1">
                ¿Eliminar Usuario?
            </h3>
            <p class="text-xs text-slate-500 text-center mb-5">
                Estás por eliminar a <span id="nombreUsuarioBorrar" class="font-bold text-slate-800 font-mono"></span> de la base de datos SQL. Esta acción no se puede deshacer.
            </p>
            <form method="POST" id="formEliminarUsuario" onsubmit="eliminarUsuarioAjax(event)">
                <input type="hidden" name="action" value="eliminar_usuario">
                <input type="hidden" name="user_id" id="borrarUserId">
                <input type="hidden" name="username" id="borrarUsername">
                <input type="hidden" name="return_query" class="input-current-query" value="">
                
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" onclick="cerrarModal('modalEliminarUsuario')" class="w-full py-2 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition cursor-pointer">
                        Cancelar
                    </button>
                    <button type="submit" id="btnConfirmarEliminarUsr" class="w-full py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold rounded-xl transition shadow-xs cursor-pointer">
                        Sí, Eliminar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Crear / Editar Usuario Completo -->
    <div id="modalUsuario" class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs hidden items-center justify-center z-50 p-4">
        <div class="bg-white border border-slate-200 rounded-3xl max-w-4xl w-full p-6 sm:p-8 shadow-2xl max-h-[92vh] overflow-y-auto">
            <h3 class="text-base font-bold text-slate-900 mb-1 flex items-center" id="tituloModalUsuario">
                <i class="fas fa-user-plus text-indigo-600 mr-2"></i> Nuevo Usuario
            </h3>
            <p class="text-xs text-slate-500 mb-4">Configuración de datos de acceso, categoría, sector y rol.</p>
            
            <form method="POST" id="formUsuario" onsubmit="guardarUsuarioAjax(event)" class="space-y-4">
                <input type="hidden" name="action" value="guardar_usuario">
                <input type="hidden" name="user_id" id="formUserId" value="0">
                <input type="hidden" name="return_query" class="input-current-query" value="">

                <!-- Selector de Categoría de Usuario -->
                <div class="p-3.5 bg-slate-100/90 rounded-2xl border border-slate-200">
                    <label class="block text-xs font-bold text-slate-700 mb-2">Categoría del Usuario / Destino *</label>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                        <label class="flex items-center p-2.5 bg-white border border-slate-200 rounded-xl cursor-pointer hover:border-emerald-500 transition has-[:checked]:border-emerald-600 has-[:checked]:bg-emerald-50/60 shadow-2xs">
                            <input type="radio" name="categoria_usuario" id="catPersonal" value="PERSONAL" checked onchange="toggleCategoria()" class="text-emerald-600 focus:ring-emerald-500">
                            <div class="ml-2">
                                <span class="block text-xs font-bold text-slate-900">👤 Personal</span>
                                <span class="block text-[10px] text-slate-500">Staff interno</span>
                            </div>
                        </label>
                        <label class="flex items-center p-2.5 bg-white border border-slate-200 rounded-xl cursor-pointer hover:border-blue-500 transition has-[:checked]:border-blue-600 has-[:checked]:bg-blue-50/60 shadow-2xs">
                            <input type="radio" name="categoria_usuario" id="catLocal" value="LOCAL" onchange="toggleCategoria()" class="text-blue-600 focus:ring-blue-500">
                            <div class="ml-2">
                                <span class="block text-xs font-bold text-slate-900">🏪 Local</span>
                                <span class="block text-[10px] text-slate-500">Propio / Franquicia</span>
                            </div>
                        </label>
                        <label class="flex items-center p-2.5 bg-white border border-slate-200 rounded-xl cursor-pointer hover:border-teal-500 transition has-[:checked]:border-teal-600 has-[:checked]:bg-teal-50/60 shadow-2xs">
                            <input type="radio" name="categoria_usuario" id="catMayorista" value="MAYORISTA" onchange="toggleCategoria()" class="text-teal-600 focus:ring-teal-500">
                            <div class="ml-2">
                                <span class="block text-xs font-bold text-slate-900">📦 Mayorista</span>
                                <span class="block text-[10px] text-slate-500">Cliente B2B</span>
                            </div>
                        </label>
                        <label class="flex items-center p-2.5 bg-white border border-slate-200 rounded-xl cursor-pointer hover:border-amber-500 transition has-[:checked]:border-amber-600 has-[:checked]:bg-amber-50/60 shadow-2xs">
                            <input type="radio" name="categoria_usuario" id="catGrupo" value="GRUPO" onchange="toggleCategoria()" class="text-amber-600 focus:ring-amber-500">
                            <div class="ml-2">
                                <span class="block text-xs font-bold text-slate-900">🏢 Grupo</span>
                                <span class="block text-[10px] text-slate-500">Franquiciado</span>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Username (Login) *</label>
                        <input type="text" name="username" id="formUsername" required 
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Contraseña (Texto Plano SQL) *</label>
                        <input type="text" name="password" id="formPassword" required 
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Nombre Completo / Razón Social / Descripción</label>
                        <input type="text" name="nombre_completo" id="formNombre" 
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Correo Electrónico</label>
                        <input type="email" name="email" id="formEmail" 
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                    </div>
                </div>

                <!-- Bloque Alcance & Parámetros Operativos / Negocio / B2B -->
                <div id="seccionAlcance" class="p-4 bg-slate-50 rounded-2xl border border-slate-200 space-y-3">
                    <h4 class="text-xs font-bold text-slate-800 flex items-center justify-between">
                        <span class="flex items-center gap-1.5"><i class="fas fa-sliders text-indigo-600"></i> Parámetros de Alcance & Negocio</span>
                        <span id="badgeAlcance" class="text-[10px] bg-indigo-50 text-indigo-700 border border-indigo-200 px-2 py-0.5 rounded-md font-semibold">Configuración de Alcance</span>
                    </h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-700 mb-1">Tipo / Perfil</label>
                            <input type="text" name="tipo" id="formTipo" placeholder="LOCAL_PROPIO" list="listaTiposDisponibles"
                                   class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-xs text-slate-800 font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                            <datalist id="listaTiposDisponibles">
                                <option value="LOCAL_PROPIO">
                                <option value="FRANQUICIA">
                                <option value="FRANQUICIA_GA">
                                <option value="MAYORISTA">
                                <option value="GRUPO">
                                <option value="LOCAL_PROPIO_UY">
                                <option value="PERSONAL">
                                <option value="SUPERVISION">
                                <option value="GERENCIA">
                                <option value="ADMINISTRACION">
                                <option value="LOGISTICA">
                                <option value="VENDEDOR">
                            </datalist>
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-700 mb-1" id="lblCodClient">Cód. Cliente / Cuenta</label>
                            <input type="text" name="cod_client" id="formCodClient" placeholder="EJ: MAPEON" 
                                   class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-xs text-slate-800 font-mono uppercase focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-700 mb-1" id="lblNroSucursal">Nro Sucursal / Depósito</label>
                            <input type="number" name="nro_sucursal" id="formNroSucursal" placeholder="EJ: 200" 
                                   class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-xs text-slate-800 font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-700 mb-1" id="lblCodVendedor">
                                <span class="text-teal-700 font-bold">Cód. Vendedor (Vend)</span>
                            </label>
                            <input type="text" name="cod_vendedor" id="formCodVendedor" placeholder="EJ: Z4" 
                                   class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-xs text-slate-800 font-mono uppercase focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                        </div>
                        <div class="sm:col-span-2 lg:col-span-2">
                            <label class="block text-[11px] font-semibold text-slate-700 mb-1" id="lblUsuarioTango">
                                <span class="text-amber-800 font-bold"><i class="fas fa-signature text-amber-500 mr-1"></i> Usuario Tango (Firma & Autorizaciones OC)</span>
                            </label>
                            <div class="relative">
                                <input type="text" name="usuario_tango" id="formUsuarioTango" placeholder="EJ: DANM, SilviaF, LUCAST, JOHANAB" 
                                       class="w-full bg-amber-50/40 border border-amber-200 rounded-lg px-3 py-2 text-xs text-slate-800 font-mono font-semibold focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500"
                                       title="Usuario operativo histórico de Tango para firma y autorizaciones de órdenes de compra">
                                <span class="text-[10px] text-slate-400 mt-1 block">Usuario ERP histórico con el que se le asignan y autoriza las órdenes de compra</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bloque Asignación Estructural (Sector, Subsector, Rol) -->
                <div id="seccionEstructural" class="p-4 bg-slate-50 rounded-2xl border border-slate-200 space-y-3">
                    <h4 class="text-xs font-bold text-slate-800 flex items-center justify-between">
                        <span class="flex items-center gap-1.5"><i class="fas fa-sitemap text-indigo-600"></i> Asignación Estructural & Rol Operativo</span>
                        <span class="text-[10px] bg-slate-200 text-slate-700 px-2 py-0.5 rounded-md font-semibold">Organización</span>
                    </h4>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-600 mb-1">Sector</label>
                            <select name="sector_id" id="formSectorId" onchange="filtrarSubsectoresPorSector()" class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-xs text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                                <option value="">Sin Sector</option>
                                <?php foreach($sectores as $s): ?>
                                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-600 mb-1">Subsector</label>
                            <select name="subsector_id" id="formSubsectorId" onchange="filtrarRolesPorSubsector()" class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-xs text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                                <option value="">Sin Subsector</option>
                                <?php foreach($subsectores as $sub): ?>
                                    <option value="<?= $sub['id'] ?>" data-sector="<?= $sub['sector_id'] ?>"><?= htmlspecialchars($sub['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-600 mb-1">Rol Operativo</label>
                            <select name="rol_id" id="formRolId" class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-xs text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                                <option value="">Sin Rol Asignado</option>
                                <?php foreach($roles as $r): ?>
                                    <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div id="bloqueLegajoSucursales" class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 border-t border-slate-200/60">
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-700 mb-1">
                                <i class="fas fa-id-card text-indigo-500 mr-1"></i> Nro de Legajo (Numérico)
                            </label>
                            <input type="number" name="legajo" id="formLegajo" placeholder="EJ: 12450" 
                                   class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs text-slate-800 font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-700 mb-1">
                                <i class="fas fa-store text-indigo-500 mr-1"></i> Sucursales Asignadas (NRO_SUCURS)
                            </label>
                            <input type="text" name="nro_sucurs" id="formNroSucurs" placeholder="EJ: 105, 106, 200" 
                                   class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs text-slate-800 font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                            <span class="text-[10px] text-slate-400">Podés ingresar múltiples sucursales separadas por coma</span>
                        </div>
                    </div>
                </div>

                <div class="flex items-center pt-2">
                    <input type="checkbox" name="activo" id="formActivo" value="1" checked class="rounded text-indigo-600 focus:ring-indigo-500">
                    <label for="formActivo" class="ml-2 text-xs text-slate-700 font-semibold">Usuario Activo y Habilitado para Login</label>
                </div>

                <div class="flex justify-end space-x-2 pt-3 border-t border-slate-200">
                    <button type="button" onclick="cerrarModal('modalUsuario')" class="px-4 py-2 text-xs font-medium text-slate-500 hover:text-slate-800 transition">Cancelar</button>
                    <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl transition shadow-xs">Guardar Usuario</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Envío Rápido de Credenciales (WhatsApp / Email / Texto Plano) -->
    <div id="modalEnvioRapido" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs hidden items-center justify-center z-50 p-4">
        <div class="bg-white border border-slate-200 rounded-2xl max-w-xl w-full p-6 shadow-2xl animate-fade-in">
            <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                <div class="flex items-center space-x-2.5">
                    <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm shadow-xs">
                        <i class="fas fa-paper-plane"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900" id="tituloEnvioRapido">Envío Rápido de Credenciales</h3>
                        <p class="text-[11px] text-slate-500" id="subtituloEnvioRapido">Texto formateado para copiar y enviar de inmediato.</p>
                    </div>
                </div>
                <button type="button" onclick="cerrarModal('modalEnvioRapido')" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
            </div>

            <!-- Selector de Formato -->
            <div class="mt-4 flex items-center bg-slate-100 p-1 rounded-xl border border-slate-200 space-x-1">
                <button type="button" onclick="setFormatoEnvioRapido('whatsapp')" id="btnFormatoWhatsapp" class="flex-1 py-1.5 rounded-lg text-xs font-bold transition bg-white text-emerald-700 shadow-2xs flex items-center justify-center gap-1.5">
                    <i class="fab fa-whatsapp text-emerald-600"></i> WhatsApp
                </button>
                <button type="button" onclick="setFormatoEnvioRapido('email')" id="btnFormatoEmail" class="flex-1 py-1.5 rounded-lg text-xs font-bold transition text-slate-600 hover:text-slate-900 flex items-center justify-center gap-1.5">
                    <i class="fas fa-envelope text-indigo-600"></i> Formato Correo
                </button>
                <button type="button" onclick="setFormatoEnvioRapido('compacto')" id="btnFormatoCompacto" class="flex-1 py-1.5 rounded-lg text-xs font-bold transition text-slate-600 hover:text-slate-900 flex items-center justify-center gap-1.5">
                    <i class="fas fa-align-left text-slate-500"></i> Simple
                </button>
            </div>

            <!-- Previsualización del Texto Generado -->
            <div class="mt-3.5">
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">
                    Mensaje listo para enviar:
                </label>
                <textarea id="textoEnvioRapido" rows="10" readonly 
                          class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs font-mono text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 select-all leading-relaxed"></textarea>
            </div>

            <!-- Footer & Botones de Acción -->
            <div class="mt-4 pt-3 border-t border-slate-200 flex items-center justify-between">
                <span class="text-[11px] text-slate-400 flex items-center gap-1">
                    <i class="fas fa-lock text-slate-400 text-xs"></i> Credencial segura
                </span>
                <div class="flex items-center space-x-2">
                    <button type="button" onclick="cerrarModal('modalEnvioRapido')" class="px-4 py-2 text-xs font-medium text-slate-600 hover:text-slate-900 transition">
                        Cerrar
                    </button>
                    <button type="button" onclick="copiarTextoEnvioRapido()" id="btnCopiarEnvioRapido" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-xl transition shadow-xs flex items-center gap-1.5 cursor-pointer">
                        <i class="fas fa-copy"></i> Copiar Mensaje
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== MODAL: CREAR / EDITAR MÓDULO ==================== -->
    <div id="modalModulo" class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs hidden items-center justify-center z-50 p-4">
        <div class="bg-white border border-slate-200 rounded-2xl max-w-2xl w-full p-6 shadow-xl max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-sm shadow-2xs font-bold border border-indigo-100">
                        <i class="fas fa-cube" id="modalModIconHeader"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900" id="modalModTitulo">Nueva Aplicación / Módulo</h3>
                        <p class="text-xs text-slate-500">Configurá los datos del sistema para el Centralizador Hub.</p>
                    </div>
                </div>
                <button type="button" onclick="cerrarModal('modalModulo')" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
            </div>

            <form method="POST" id="formModulo" class="space-y-4">
                <input type="hidden" name="action" value="guardar_modulo">
                <input type="hidden" name="modulo_id" id="formModId" value="0">

                <!-- Vista Previa de la Tarjeta en Tiempo Real -->
                <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200/80">
                    <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-2">Vista previa de la tarjeta en el Hub:</span>
                    <div class="flex items-center gap-3 bg-white p-3 rounded-xl border border-slate-200 shadow-2xs">
                        <div id="previewModIconBox" class="w-10 h-10 rounded-xl text-white flex items-center justify-center text-base shadow-xs shrink-0" 
                             style="background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);">
                            <i class="fas fa-cube" id="previewModIcon"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <h4 class="text-xs font-bold text-slate-900 truncate" id="previewModNombre">Nombre de la Aplicación</h4>
                                <span class="font-mono text-[10px] bg-slate-100 text-slate-600 px-1.5 py-0.2 rounded border" id="previewModCodigo">CODIGO_APP</span>
                            </div>
                            <p class="text-[11px] text-slate-400 truncate mt-0.5" id="previewModDesc">Descripción del sistema...</p>
                        </div>
                    </div>
                </div>

                <!-- Nombre y Código -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Nombre de la Aplicación *</label>
                        <input type="text" name="nombre" id="formModNombre" required placeholder="Ej: Informe Económico" 
                               oninput="actualizarPreviewModulo(); generarCodigoDesdeNombre(this.value);"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-xs text-slate-800 font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Código Identificador (Único) *</label>
                        <input type="text" name="codigo" id="formModCodigo" required placeholder="Ej: INFORME_ECONOMICO" 
                               oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9_]/g, ''); actualizarPreviewModulo();"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-xs text-slate-800 font-mono font-bold uppercase focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                    </div>
                </div>

                <!-- Categoría y Ruta URL -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-xs font-bold text-slate-700">Categoría del Hub *</label>
                            <button type="button" onclick="toggleNuevaCategoriaMod(true)" id="btnToggleNuevaCat" class="text-[11px] text-indigo-600 hover:text-indigo-800 hover:underline font-semibold cursor-pointer">
                                <i class="fas fa-plus-circle text-[10px]"></i> Nueva Categoría
                            </button>
                        </div>
                        
                        <!-- Select de categorías existentes -->
                        <div id="contSelectCategoria">
                            <select id="formModCategoriaSelect" onchange="verificarCambioCategoria(this.value)" 
                                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-xs text-slate-800 font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                                <option value="">-- Seleccionar Categoría --</option>
                                <?php foreach($categoriasHubExistentes as $catNombre): ?>
                                    <option value="<?= htmlspecialchars($catNombre) ?>"><?= htmlspecialchars($catNombre) ?></option>
                                <?php endforeach; ?>
                                <option value="__NUEVA__">➕ + Escribir Nueva Categoría...</option>
                            </select>
                        </div>

                        <!-- Input para escribir nueva categoría -->
                        <div id="contInputNuevaCategoria" class="hidden">
                            <div class="flex items-center gap-1.5">
                                <input type="text" id="formModCategoriaInput" placeholder="Nombre de la nueva categoría..." 
                                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-xs text-slate-800 font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                                <button type="button" onclick="toggleNuevaCategoriaMod(false)" class="px-2.5 py-2 text-xs text-slate-500 hover:text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl transition cursor-pointer" title="Volver al desplegable">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <input type="hidden" name="categoria" id="formModCategoriaFinal" value="">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Ruta / URL de Acceso</label>
                        <input type="text" name="ruta_url" id="formModRutaUrl" placeholder="/finanzas/informe_economico/" 
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-xs text-slate-800 font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                    </div>
                </div>

                <!-- Selector de Ícono FontAwesome -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Ícono FontAwesome</label>
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-8 h-8 rounded-lg bg-slate-100 border border-slate-200 text-slate-700 flex items-center justify-center text-sm shrink-0">
                            <i class="fas fa-cube" id="modIconoDisplay"></i>
                        </div>
                        <input type="text" name="icono" id="formModIcono" value="fa-cube" placeholder="fa-cube" 
                               oninput="actualizarDisplayIcono(this.value); actualizarPreviewModulo();"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-xs text-slate-800 font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                    </div>
                    <!-- Chips de íconos rápidos -->
                    <div class="flex flex-wrap gap-1.5">
                        <?php 
                        $iconosSugeridos = [
                            'fa-chart-column', 'fa-table-cells', 'fa-chart-pie', 'fa-chart-line', 'fa-calculator', 
                            'fa-dollar-sign', 'fa-wallet', 'fa-money-bill-wave', 'fa-boxes-packing', 'fa-truck', 
                            'fa-truck-ramp-box', 'fa-store', 'fa-cart-shopping', 'fa-bag-shopping', 'fa-users', 
                            'fa-user-shield', 'fa-id-badge', 'fa-sliders', 'fa-microchip', 'fa-laptop-code', 
                            'fa-file-invoice-dollar', 'fa-handshake', 'fa-landmark', 'fa-shield-halved'
                        ];
                        foreach($iconosSugeridos as $ic): 
                        ?>
                            <button type="button" onclick="seleccionarIconoModulo('<?= $ic ?>')" 
                                    class="w-7 h-7 rounded-lg border border-slate-200 hover:border-indigo-400 hover:bg-indigo-50 text-slate-600 flex items-center justify-center text-xs transition" title="<?= $ic ?>">
                                <i class="fas <?= $ic ?>"></i>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Selector de Color / Degradado -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Color / Degradado Visual</label>
                    <input type="text" name="color" id="formModColor" value="linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%)" 
                           oninput="actualizarDisplayColor(this.value); actualizarPreviewModulo();"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-xs text-slate-800 font-mono mb-2 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                    <!-- Paleta de colores rápidos -->
                    <div class="flex flex-wrap gap-2">
                        <?php 
                        $coloresSugeridos = [
                            ['Indigo & Violeta', 'linear-gradient(135deg, #4338ca 0%, #6366f1 100%)'],
                            ['Azul Océano', 'linear-gradient(135deg, #1d4ed8 0%, #3b82f6 100%)'],
                            ['Esmeralda / Teal', 'linear-gradient(135deg, #0f766e 0%, #14b8a6 100%)'],
                            ['Verde Éxito', 'linear-gradient(135deg, #059669 0%, #10b981 100%)'],
                            ['Cian / Sky', 'linear-gradient(135deg, #0284c7 0%, #0ea5e9 100%)'],
                            ['Naranja Intenso', 'linear-gradient(135deg, #f97316 0%, #ea580c 100%)'],
                            ['Ámbar / Dorado', 'linear-gradient(135deg, #d97706 0%, #f59e0b 100%)'],
                            ['Púrpura Elegante', 'linear-gradient(135deg, #7c3aed 0%, #a855f7 100%)'],
                            ['Rojo / Carmesí', 'linear-gradient(135deg, #dc2626 0%, #ef4444 100%)'],
                            ['Pizarra / Dark', 'linear-gradient(135deg, #334155 0%, #475569 100%)']
                        ];
                        foreach($coloresSugeridos as $col):
                        ?>
                            <button type="button" onclick="seleccionarColorModulo('<?= $col[1] ?>')" 
                                    class="h-6 px-2.5 rounded-lg text-[10px] font-bold text-white shadow-2xs transition hover:scale-105" 
                                    style="background: <?= $col[1] ?>;" title="<?= $col[0] ?>">
                                <?= $col[0] ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Descripción -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Descripción del Sistema</label>
                    <textarea name="descripcion" id="formModDesc" rows="2" placeholder="Resumen claro de la funcionalidad que se mostrará en el Hub..." 
                              oninput="actualizarPreviewModulo()"
                              class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"></textarea>
                </div>

                <!-- Orden, Target Blank y Activo -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-center pt-2 border-t border-slate-100">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">
                            Orden / Posición
                            <span class="text-[10px] font-normal text-slate-400 block">(N° menor aparece primero)</span>
                        </label>
                        <input type="number" name="orden" id="formModOrden" value="100" 
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-1.5 text-xs text-slate-800 font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                    </div>
                    <div class="flex items-center pt-4">
                        <label class="flex items-center gap-2 cursor-pointer text-xs font-semibold text-slate-700">
                            <input type="checkbox" name="target_blank" id="formModTargetBlank" value="1" class="rounded text-indigo-600 focus:ring-indigo-500">
                            <span>Abrir en nueva pestaña</span>
                        </label>
                    </div>
                    <div class="flex items-center pt-4">
                        <label class="flex items-center gap-2 cursor-pointer text-xs font-semibold text-slate-700">
                            <input type="checkbox" name="activo" id="formModActivo" value="1" checked class="rounded text-indigo-600 focus:ring-indigo-500">
                            <span class="text-emerald-700 font-bold">Módulo Habilitado</span>
                        </label>
                    </div>
                </div>

                <div class="flex justify-end space-x-2 pt-4 border-t border-slate-100">
                    <button type="button" onclick="cerrarModal('modalModulo')" class="px-4 py-2 text-xs font-medium text-slate-500 hover:text-slate-800 transition">Cancelar</button>
                    <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl transition shadow-xs flex items-center gap-1.5">
                        <i class="fas fa-check"></i> <span id="btnTextoGuardarModulo">Guardar Módulo</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================== MODAL: GESTIÓN DE SOLAPAS & PERMISOS DE UN MÓDULO ==================== -->
    <div id="modalGestionPermisosModulo" class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs hidden items-center justify-center z-50 p-4">
        <div class="bg-white border border-slate-200 rounded-2xl max-w-3xl w-full p-6 shadow-xl max-h-[90vh] flex flex-col">
            <!-- Header del Modal -->
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-3 shrink-0">
                <div class="flex items-center gap-3">
                    <div id="gestPermModalIconBox" class="w-10 h-10 rounded-xl text-white flex items-center justify-center text-base shadow-xs" style="background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);">
                        <i class="fas fa-cube" id="gestPermModalIcon"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                            Solapas & Permisos Internos: <span id="gestPermModalNombre" class="text-indigo-600">Nombre Módulo</span>
                        </h3>
                        <p class="text-xs text-slate-500">
                            Código: <strong id="gestPermModalCodigo" class="font-mono text-slate-700">CODIGO</strong> · Agregá las solapas y acciones que componen este sistema.
                        </p>
                    </div>
                </div>
                <button type="button" onclick="cerrarModal('modalGestionPermisosModulo')" class="text-slate-400 hover:text-slate-600 text-xl font-bold">&times;</button>
            </div>

            <!-- Formulario para Agregar / Editar Permiso Rápido -->
            <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200 mb-3 shrink-0">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="text-xs font-bold text-slate-800 flex items-center gap-1.5" id="formPermisoTitulo">
                        <i class="fas fa-plus-circle text-indigo-600"></i> Agregar Nueva Solapa / Permiso Interno
                    </h4>
                    <button type="button" onclick="resetFormPermiso()" id="btnCancelarEdicionPermiso" class="hidden text-[11px] text-slate-500 hover:text-slate-800 font-semibold underline">
                        Cancelar edición
                    </button>
                </div>
                <form id="formNuevoPermiso" onsubmit="guardarPermisoModuloAjax(event)" class="space-y-2.5">
                    <input type="hidden" id="permFormModuloId" value="0">
                    <input type="hidden" id="permFormPermisoId" value="0">

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="block text-[11px] font-bold text-slate-700">Sección / Grupo *</label>
                                <button type="button" onclick="toggleNuevaSeccionPerm(true)" id="btnToggleNuevaSec" class="text-[10px] text-indigo-600 hover:text-indigo-800 hover:underline font-semibold cursor-pointer">
                                    <i class="fas fa-plus-circle text-[9px]"></i> Nueva Sección
                                </button>
                            </div>
                            
                            <!-- Select de secciones dinámicas -->
                            <div id="contSelectSeccion">
                                <select id="permFormSeccionSelect" onchange="verificarCambioSeccion(this.value)" 
                                        class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs text-slate-800 font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                                    <option value="">-- Seleccionar Sección --</option>
                                    <option value="__NUEVA__">➕ + Escribir Nueva Sección...</option>
                                </select>
                            </div>

                            <!-- Input para escribir nueva sección -->
                            <div id="contInputNuevaSeccion" class="hidden">
                                <div class="flex items-center gap-1">
                                    <input type="text" id="permFormSeccionInput" placeholder="Nombre de la nueva sección..." 
                                           class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs text-slate-800 font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                                    <button type="button" onclick="toggleNuevaSeccionPerm(false)" class="px-2 py-1.5 text-xs text-slate-400 hover:text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition cursor-pointer" title="Volver al desplegable">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            <input type="hidden" id="permFormSeccionFinal" value="">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 mb-1">Clave Técnica (Identificador) *</label>
                            <input type="text" id="permFormClave" required placeholder="Ej: ie.tab.locales"
                                   oninput="this.value = this.value.toLowerCase().replace(/[^a-z0-9_.]/g, '');"
                                   class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs text-slate-800 font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 mb-1">Nombre Legible *</label>
                            <input type="text" id="permFormNombre" required placeholder="Ej: Solapa IE por Locales"
                                   class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs text-slate-800 font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <div class="flex-1">
                            <input type="text" id="permFormDesc" placeholder="Descripción opcional: ej. Permite ver el informe por local propio..."
                                   class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                        </div>
                        <button type="submit" id="btnGuardarPermisoAjax" class="px-4 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg transition shadow-xs flex items-center gap-1.5 shrink-0 cursor-pointer">
                            <i class="fas fa-save"></i> <span id="lblBtnGuardarPermiso">Guardar</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Listado de Permisos / Solapas Registradas -->
            <div class="overflow-y-auto flex-1 pr-1 space-y-2" id="gestPermListaContenedor">
                <div class="py-8 text-center text-slate-400">
                    <i class="fas fa-spinner fa-spin text-2xl text-indigo-500 mb-2 block"></i>
                    Cargando solapas y permisos...
                </div>
            </div>

            <!-- Footer del Modal -->
            <div class="flex justify-between items-center pt-3 border-t border-slate-100 mt-3 shrink-0">
                <span class="text-xs text-slate-500" id="gestPermTotalContador">0 solapas registradas</span>
                <button type="button" onclick="cerrarModal('modalGestionPermisosModulo')" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl transition">
                    Cerrar
                </button>
            </div>
        </div>
    </div>

    <!-- ==================== MODAL: CONFIRMAR ELIMINACIÓN DE MÓDULO ==================== -->
    <div id="modalEliminarModulo" class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs hidden items-center justify-center z-50 p-4">
        <div class="bg-white border border-slate-200 rounded-2xl max-w-sm w-full p-6 shadow-xl">
            <div class="w-12 h-12 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center mb-4 mx-auto border border-rose-100">
                <i class="fas fa-triangle-exclamation text-xl"></i>
            </div>
            <h3 class="text-base font-bold text-slate-900 text-center mb-1">
                ¿Eliminar Aplicación?
            </h3>
            <p class="text-xs text-slate-500 text-center mb-5">
                Estás por eliminar el módulo <strong id="nombreModuloEliminar" class="text-slate-900 font-bold"></strong> y todas sus solapas asociadas del Hub.
            </p>
            <form method="POST">
                <input type="hidden" name="action" value="eliminar_modulo">
                <input type="hidden" name="modulo_id" id="borrarModuloId">
                <input type="hidden" name="nombre_modulo" id="borrarModuloNombre">
                
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" onclick="cerrarModal('modalEliminarModulo')" class="w-full py-2 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition cursor-pointer">
                        Cancelar
                    </button>
                    <button type="submit" class="w-full py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold rounded-xl transition shadow-xs cursor-pointer">
                        Sí, Eliminar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================== MODAL: CONFIRMAR ELIMINACIÓN DE SUBSECTOR ==================== -->
    <div id="modalEliminarSubsector" class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs hidden items-center justify-center z-50 p-4">
        <div class="bg-white border border-slate-200 rounded-2xl max-w-sm w-full p-6 shadow-xl">
            <div class="w-12 h-12 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center mb-4 mx-auto border border-rose-100">
                <i class="fas fa-trash-alt text-xl"></i>
            </div>
            <h3 class="text-base font-bold text-slate-900 text-center mb-1">
                ¿Eliminar Subsector?
            </h3>
            <p class="text-xs text-slate-500 text-center mb-4">
                Estás por eliminar el subsector <strong id="nombreSubsectorEliminar" class="text-slate-900 font-bold"></strong>.
            </p>
            <div id="avisoSubsectorBloqueado" class="hidden mb-4 p-3 bg-amber-50 border border-amber-200 rounded-xl text-[11px] text-amber-800 flex items-start gap-2">
                <i class="fas fa-exclamation-triangle text-amber-600 mt-0.5 shrink-0"></i>
                <span id="textoSubsectorBloqueado"></span>
            </div>
            <form method="POST" id="formEliminarSubsector">
                <input type="hidden" name="action" value="eliminar_subsector">
                <input type="hidden" name="subsector_id" id="borrarSubsectorId">
                <input type="hidden" name="nombre_subsector" id="borrarSubsectorNombre">
                
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" onclick="cerrarModal('modalEliminarSubsector')" class="w-full py-2 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition cursor-pointer">
                        Cancelar
                    </button>
                    <button type="submit" id="btnConfirmarEliminarSubsector" class="w-full py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold rounded-xl transition shadow-xs cursor-pointer">
                        Sí, Eliminar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================== MODAL: CREAR / EDITAR EQUIPO DE TRABAJO ==================== -->
    <div id="modalEquipo" class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs hidden items-center justify-center z-50 p-4">
        <div class="bg-white border border-slate-200 rounded-2xl max-w-4xl w-full p-6 shadow-xl max-h-[92vh] flex flex-col">
            <!-- Header del Modal -->
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4 shrink-0">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-sm shadow-2xs font-bold border border-blue-100">
                        <i class="fas fa-people-roof"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900" id="modalEquipoTitulo">Nuevo Equipo de Trabajo</h3>
                        <p class="text-xs text-slate-500">Definí el líder, gerente validador, ámbito y los integrantes del equipo.</p>
                    </div>
                </div>
                <button type="button" onclick="cerrarModal('modalEquipo')" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
            </div>

            <!-- Contenido scrollable -->
            <form method="POST" id="formEquipo" class="overflow-y-auto pr-1 space-y-4 flex-1">
                <input type="hidden" name="action" value="guardar_equipo">
                <input type="hidden" name="equipo_id" id="formEquipoId" value="0">
                <input type="hidden" name="lider_usuario_id" id="formEquipoLiderUserId" value="">
                <input type="hidden" name="gerente_usuario_id" id="formEquipoGerenteUserId" value="">

                <!-- Datos Principales -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 mb-1">Nombre del Equipo *</label>
                        <input type="text" name="nombre" id="formEquipoNombre" required placeholder="Ej: Administración General y Tesorería" 
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-xs text-slate-800 font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Ámbito / Flujo *</label>
                        <select name="tipo_flujo" id="formEquipoTipoFlujo" 
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-800 font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                            <option value="CENTRAL">CENTRAL (Sede / Oficinas / Plantas)</option>
                            <option value="LOCALES">LOCALES (Retail / Sucursales)</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Sector Principal *</label>
                        <select name="sector_id" id="formEquipoSectorId" required onchange="filtrarSubsectoresEquipo(this.value)"
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-xs text-slate-800 font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                            <option value="">-- Seleccionar Sector --</option>
                            <?php foreach($sectores as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Subsector / Departamento (Opcional)</label>
                        <select name="subsector_id" id="formEquipoSubsectorId"
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                            <option value="">-- Todos o Sin Subsector específico --</option>
                            <?php foreach($subsectores as $sub): ?>
                                <option value="<?= $sub['id'] ?>" data-sector="<?= $sub['sector_id'] ?>"><?= htmlspecialchars($sub['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Líder y Gerente -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 p-3 bg-slate-50 rounded-xl border border-slate-200/80">
                    <div>
                        <label class="block text-xs font-bold text-slate-800 mb-1 flex items-center gap-1.5">
                            <i class="fas fa-user-tie text-blue-600"></i> Líder / Jefe de Equipo (Carga grilla)
                        </label>
                        <select name="lider_legajo" id="formEquipoLiderLegajo" onchange="actualizarLiderEquipo(this)"
                                class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-800 font-medium focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                            <option value="">-- Sin Líder asignado --</option>
                            <?php foreach($usuariosParaEquipos as $u): ?>
                                <option value="<?= $u['legajo'] ?>" data-userid="<?= $u['id'] ?>">
                                    <?= htmlspecialchars($u['nombre_completo']) ?> (Leg. #<?= $u['legajo'] ?> - <?= htmlspecialchars($u['rol_nombre'] ?? $u['sector_nombre'] ?? '') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="text-[10px] text-slate-400 mt-0.5 block">Tiene permiso de armar la planificación de vacaciones de sus integrantes.</span>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-800 mb-1 flex items-center gap-1.5">
                            <i class="fas fa-shield-halved text-purple-600"></i> Gerente Validador (Nivel Aprobador)
                        </label>
                        <select name="gerente_legajo" id="formEquipoGerenteLegajo" onchange="actualizarGerenteEquipo(this)"
                                class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-800 font-medium focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500">
                            <option value="">-- Sin Gerente asignado --</option>
                            <?php foreach($usuariosParaEquipos as $u): ?>
                                <option value="<?= $u['legajo'] ?>" data-userid="<?= $u['id'] ?>">
                                    <?= htmlspecialchars($u['nombre_completo']) ?> (Leg. #<?= $u['legajo'] ?> - <?= htmlspecialchars($u['rol_nombre'] ?? $u['sector_nombre'] ?? '') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="text-[10px] text-slate-400 mt-0.5 block">Aprueba las vacaciones del equipo y de su jefe/líder.</span>
                    </div>
                </div>

                <!-- Descripción y Estado -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-center">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 mb-1">Descripción / Observaciones</label>
                        <textarea name="descripcion" id="formEquipoDesc" rows="2" placeholder="Detalle funcional o áreas que abarca el equipo..."
                                  class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"></textarea>
                    </div>
                    <div class="flex items-center pt-2">
                        <label class="flex items-center gap-2 cursor-pointer text-xs font-semibold text-slate-700 bg-slate-50 p-3 rounded-xl border border-slate-200 w-full">
                            <input type="checkbox" name="activo" id="formEquipoActivo" value="1" checked class="rounded text-indigo-600 focus:ring-indigo-500">
                            <span class="text-emerald-700 font-bold">Equipo Activo</span>
                        </label>
                    </div>
                </div>

                <!-- SECCIÓN DE INTEGRANTES / COLABORADORES -->
                <div class="pt-3 border-t border-slate-200">
                    <div class="flex items-center justify-between mb-2">
                        <div>
                            <h4 class="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                                <i class="fas fa-users text-indigo-600"></i> Integrantes Asignados al Equipo
                            </h4>
                            <p class="text-[11px] text-slate-500">Agregue a los colaboradores que pertenecerán a la grilla y gestión de este equipo.</p>
                        </div>
                        <span class="text-xs font-bold text-indigo-600 bg-indigo-50 px-2.5 py-1 rounded-full border border-indigo-100" id="contadorMiembrosForm">0 integrantes</span>
                    </div>

                    <!-- Agregar Colaborador Box -->
                    <div class="bg-indigo-50/40 p-3 rounded-xl border border-indigo-100 mb-3 grid grid-cols-1 md:grid-cols-12 gap-2 items-end">
                        <div class="md:col-span-6">
                            <label class="block text-[11px] font-bold text-slate-700 mb-1">Buscar y Seleccionar Colaborador</label>
                            <select id="selectNuevoMiembro" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-1.5 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
                                <option value="">-- Seleccionar colaborador --</option>
                                <?php foreach($usuariosParaEquipos as $u): ?>
                                    <option value="<?= $u['legajo'] ?>" 
                                            data-userid="<?= $u['id'] ?>" 
                                            data-nombre="<?= htmlspecialchars($u['nombre_completo']) ?>"
                                            data-rol="<?= htmlspecialchars($u['rol_nombre'] ?? 'Sin Rol') ?>"
                                            data-sector="<?= htmlspecialchars($u['sector_nombre'] ?? '') ?>">
                                        <?= htmlspecialchars($u['nombre_completo']) ?> (Leg. #<?= $u['legajo'] ?> - <?= htmlspecialchars($u['rol_nombre'] ?? '') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="md:col-span-3">
                            <label class="block text-[11px] font-bold text-slate-700 mb-1">Rol en Equipo</label>
                            <select id="selectRolNuevoMiembro" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-1.5 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
                                <option value="MIEMBRO">Miembro / Colaborador</option>
                                <option value="LIDER">Líder / Responsable</option>
                                <option value="SUPLENTE">Suplente / Co-líder</option>
                            </select>
                        </div>
                        <div class="md:col-span-3 flex items-center gap-2">
                            <button type="button" onclick="agregarMiembroDesdeSelector()" class="w-full py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl transition shadow-xs flex items-center justify-center gap-1.5 cursor-pointer">
                                <i class="fas fa-user-plus text-[11px]"></i> Agregar
                            </button>
                        </div>
                    </div>

                    <!-- Tabla de Miembros Seleccionados -->
                    <div class="border border-slate-200 rounded-xl overflow-hidden max-h-56 overflow-y-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold text-slate-600 uppercase">
                                    <th class="py-2 px-3">Legajo</th>
                                    <th class="py-2 px-3">Colaborador</th>
                                    <th class="py-2 px-3">Puesto / Rol</th>
                                    <th class="py-2 px-3">Rol Equipo</th>
                                    <th class="py-2 px-3 text-right">Quitar</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyMiembrosEquipo" class="divide-y divide-slate-100">
                                <tr id="filaSinMiembros">
                                    <td colspan="6" class="text-center py-6 text-slate-400 text-xs italic">
                                        No hay integrantes en este equipo todavía. Seleccioná arriba para agregarlos.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex justify-end space-x-2 pt-4 border-t border-slate-100">
                    <button type="button" onclick="cerrarModal('modalEquipo')" class="px-4 py-2 text-xs font-medium text-slate-500 hover:text-slate-800 transition">Cancelar</button>
                    <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl transition shadow-xs flex items-center gap-1.5 cursor-pointer">
                        <i class="fas fa-check"></i> <span id="btnTextoGuardarEquipo">Guardar Equipo</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================== MODAL: VER MIEMBROS DE EQUIPO ==================== -->
    <div id="modalMiembrosEquipo" class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs hidden items-center justify-center z-50 p-4">
        <div class="bg-white border border-slate-200 rounded-2xl max-w-2xl w-full p-6 shadow-xl max-h-[88vh] flex flex-col">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-3 shrink-0">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-sm shadow-2xs font-bold border border-indigo-100">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                            Integrantes: <span id="verMiembrosNombreEquipo" class="text-indigo-600">Nombre Equipo</span>
                        </h3>
                        <p class="text-xs text-slate-500" id="verMiembrosDetalleEquipo">Sector · 0 miembros</p>
                    </div>
                </div>
                <button type="button" onclick="cerrarModal('modalMiembrosEquipo')" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
            </div>

            <!-- Lista de Miembros -->
            <div class="overflow-y-auto flex-1 pr-1 space-y-2" id="verMiembrosListaContenedor">
                <div class="py-8 text-center text-slate-400">
                    <i class="fas fa-spinner fa-spin text-2xl text-indigo-500 mb-2 block"></i>
                    Cargando integrantes...
                </div>
            </div>

            <!-- Footer -->
            <div class="flex justify-between items-center pt-3 border-t border-slate-100 mt-3 shrink-0">
                <button type="button" id="btnEditarEquipoDesdeVer" onclick="" class="px-3.5 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-semibold rounded-xl border border-indigo-200 transition flex items-center gap-1.5 cursor-pointer">
                    <i class="fas fa-pen text-[10px]"></i> Editar Equipo
                </button>
                <button type="button" onclick="cerrarModal('modalMiembrosEquipo')" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl transition">
                    Cerrar
                </button>
            </div>
        </div>
    </div>

    <!-- Toast Notification Container -->
    <div id="toastContainer" class="fixed bottom-5 right-5 z-50 flex flex-col space-y-2 pointer-events-none"></div>

    <script>
        const mapaPermisos = <?= json_encode($mapaPermisosPorRol) ?>;

        // ==================== BARRA DE PROGRESO Y ESTADOS DE CARGA ====================
        let progressTimer = null;
        function iniciarProgreso() {
            const bar = document.getElementById('topProgressBar');
            if (!bar) return;
            if (progressTimer) clearInterval(progressTimer);
            bar.classList.remove('opacity-0');
            bar.style.width = '25%';
            let w = 25;
            progressTimer = setInterval(() => {
                if (w < 85) {
                    w += Math.random() * 15;
                    bar.style.width = w + '%';
                }
            }, 200);
        }

        function finalizarProgreso() {
            const bar = document.getElementById('topProgressBar');
            if (!bar) return;
            if (progressTimer) clearInterval(progressTimer);
            bar.style.width = '100%';
            setTimeout(() => {
                bar.classList.add('opacity-0');
                setTimeout(() => {
                    bar.style.width = '0%';
                }, 300);
            }, 300);
        }

        // Interceptor global para llamadas fetch (AJAX)
        const originalFetch = window.fetch;
        window.fetch = async function(...args) {
            iniciarProgreso();
            try {
                const res = await originalFetch.apply(this, args);
                return res;
            } finally {
                finalizarProgreso();
            }
        };

        function mostrarOverlayTabla(tabName = 'usr') {
            iniciarProgreso();
            const overlay = document.getElementById(tabName === 'rel' ? 'overlayLoadingRel' : 'overlayLoadingUsr');
            if (overlay) overlay.classList.remove('hidden');
        }

        // Interceptor global de envíos de formularios para mostrar feedback visual instantáneo
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('form').forEach(f => {
                f.addEventListener('submit', function(e) {
                    if (!this.checkValidity()) return;
                    
                    const btn = this.querySelector('button[type="submit"]');
                    if (btn && !btn.dataset.noLoading) {
                        if (!btn.dataset.originalHtml) {
                            btn.dataset.originalHtml = btn.innerHTML;
                        }
                        btn.disabled = true;
                        btn.classList.add('opacity-75', 'cursor-not-allowed');
                        const isDelete = btn.classList.contains('bg-rose-600') || btn.textContent.toLowerCase().includes('eliminar');
                        const isFilter = f.id === 'formFiltroUsuarios' || f.id === 'formFiltroRelev';
                        
                        let loadingText = 'Guardando...';
                        if (isDelete) loadingText = 'Eliminando...';
                        else if (isFilter) loadingText = 'Filtrando...';
                        
                        btn.innerHTML = `<i class="fas fa-circle-notch fa-spin mr-1.5"></i> ${loadingText}`;
                    }

                    if (f.id === 'formFiltroUsuarios') {
                        mostrarOverlayTabla('usr');
                    } else if (f.id === 'formFiltroRelev') {
                        mostrarOverlayTabla('rel');
                    } else {
                        iniciarProgreso();
                    }
                });
            });

            // Búsqueda rápida automática con debounce optimizado en usuarios (sin necesidad de presionar Enter)
            const inputQ = document.getElementById('inputBusquedaUsuarios');
            const iconQ = document.getElementById('searchIconUsr');
            const formFiltroUsuarios = document.getElementById('formFiltroUsuarios');
            if (inputQ && iconQ && formFiltroUsuarios) {
                let debounceTimeout = null;
                inputQ.addEventListener('input', () => {
                    iconQ.className = 'fas fa-circle-notch fa-spin absolute left-3.5 top-2.5 text-indigo-500 text-xs transition-all';
                    clearTimeout(debounceTimeout);
                    debounceTimeout = setTimeout(() => {
                        const pageInp = formFiltroUsuarios.querySelector('input[name="p"]');
                        if (pageInp) pageInp.value = '1';
                        formFiltroUsuarios.submit();
                    }, 350);
                });
            }

            // Inyectar automáticamente el Query String actual en todos los formularios para no perder filtros
            const queryActual = window.location.search.replace(/^\?/, '');
            document.querySelectorAll('.input-current-query').forEach(inp => {
                inp.value = queryActual;
            });
        });

        // ==================== TOAST NOTIFICATIONS ====================
        function showToast(mensaje, tipo = 'success') {
            const container = document.getElementById('toastContainer');
            if (!container) return;
            const toast = document.createElement('div');
            const bgClass = tipo === 'success' ? 'bg-slate-900 text-white border-emerald-500' : 'bg-rose-900 text-white border-rose-500';
            const icon = tipo === 'success' ? 'fa-circle-check text-emerald-400' : 'fa-circle-exclamation text-rose-400';
            
            toast.className = `toast-notify flex items-center space-x-2 px-4 py-3 rounded-xl border shadow-xl text-xs font-semibold pointer-events-auto transform translate-y-2 opacity-0 ${bgClass}`;
            toast.innerHTML = `<i class="fas ${icon}"></i> <span>${mensaje}</span>`;
            
            container.appendChild(toast);
            
            requestAnimationFrame(() => {
                toast.classList.remove('translate-y-2', 'opacity-0');
            });
            
            setTimeout(() => {
                toast.classList.add('opacity-0', 'translate-y-2');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // ==================== COPIAR CREDENCIALES ====================
        function copiarCredenciales(username, pass) {
            const texto = `Usuario: ${username}\nPassword: ${pass}`;
            navigator.clipboard.writeText(texto).then(() => {
                showToast(`Credenciales de @${username} copiadas al portapapeles!`, 'success');
            }).catch(() => {
                const temp = document.createElement('textarea');
                temp.value = texto;
                document.body.appendChild(temp);
                temp.select();
                document.execCommand('copy');
                document.body.removeChild(temp);
                showToast(`Credenciales de @${username} copiadas!`, 'success');
            });
        }

        // ==================== JS ENVÍO RÁPIDO DE CREDENCIALES ====================
        let usuarioEnvioActual = null;
        let formatoEnvioActual = 'whatsapp';
        const listadoUsuariosFiltroActual = <?= json_encode(array_map(function($u) {
            $destino = calcularDestinoLogin($u);
            return [
                'id' => $u['id'],
                'username' => $u['username'],
                'password' => $u['password'] ?: $u['password_plain'] ?: '',
                'nombre_completo' => $u['nombre_completo'] ?: '',
                'categoria_usuario' => $u['categoria_usuario'] ?: 'PERSONAL',
                'sector_nombre' => $u['sector_nombre'] ?: '',
                'subsector_nombre' => $u['subsector_nombre'] ?: '',
                'rol_nombre' => $u['rol_nombre'] ?: '',
                'tipo' => $u['tipo'] ?: '',
                'cod_client' => $u['cod_client'] ?: '',
                'nro_sucursal' => $u['nro_sucursal'] ?: '',
                'nro_sucurs' => $u['nro_sucurs'] ?: '',
                'legajo' => $u['legajo'] ?: '',
                'cod_vendedor' => $u['cod_vendedor'] ?: '',
                'destino_nombre' => $destino['nombre'],
                'destino_url' => $destino['url']
            ];
        }, $usuarios)) ?>;

        function abrirModalEnvioRapidoUsuario(u) {
            usuarioEnvioActual = u;
            document.getElementById('tituloEnvioRapido').textContent = 'Envío Rápido @' + u.username;
            document.getElementById('subtituloEnvioRapido').textContent = (u.nombre_completo ? u.nombre_completo + ' • ' : '') + 'Credenciales listas para compartir';
            generarTextoEnvioRapido();
            abrirModal('modalEnvioRapido');
        }

        function abrirModalEnvioRapidoFiltro() {
            usuarioEnvioActual = 'FILTRO';
            document.getElementById('tituloEnvioRapido').textContent = 'Envío Rápido • ' + listadoUsuariosFiltroActual.length + ' Usuarios';
            document.getElementById('subtituloEnvioRapido').textContent = 'Listado consolidado de credenciales para el filtro actual';
            generarTextoEnvioRapido();
            abrirModal('modalEnvioRapido');
        }

        function setFormatoEnvioRapido(formato) {
            formatoEnvioActual = formato;
            
            const btnW = document.getElementById('btnFormatoWhatsapp');
            const btnE = document.getElementById('btnFormatoEmail');
            const btnC = document.getElementById('btnFormatoCompacto');
            
            [btnW, btnE, btnC].forEach(b => {
                if (b) {
                    b.className = 'flex-1 py-1.5 rounded-lg text-xs font-bold transition text-slate-600 hover:text-slate-900 flex items-center justify-center gap-1.5';
                }
            });

            if (formato === 'whatsapp' && btnW) {
                btnW.className = 'flex-1 py-1.5 rounded-lg text-xs font-bold transition bg-white text-emerald-700 shadow-2xs flex items-center justify-center gap-1.5';
            } else if (formato === 'email' && btnE) {
                btnE.className = 'flex-1 py-1.5 rounded-lg text-xs font-bold transition bg-white text-indigo-700 shadow-2xs flex items-center justify-center gap-1.5';
            } else if (formato === 'compacto' && btnC) {
                btnC.className = 'flex-1 py-1.5 rounded-lg text-xs font-bold transition bg-white text-slate-900 shadow-2xs flex items-center justify-center gap-1.5';
            }

            generarTextoEnvioRapido();
        }

        function generarTextoEnvioRapido() {
            const txtArea = document.getElementById('textoEnvioRapido');
            if (!txtArea) return;

            if (usuarioEnvioActual === 'FILTRO') {
                let texto = '';
                if (formatoEnvioActual === 'whatsapp') {
                    texto = `🔐 *RESUMEN DE CREDENCIALES - XL EXTRA LARGE*\n` +
                            `📊 *Total de cuentas:* ${listadoUsuariosFiltroActual.length}\n\n`;
                    listadoUsuariosFiltroActual.forEach((u, i) => {
                        texto += `${i+1}. *${u.username}* | Pass: \`${u.password}\`\n`;
                        texto += `   👤 ${u.nombre_completo || '-'}\n`;
                        if (u.rol_nombre) texto += `   🛡️ Rol: ${u.rol_nombre}\n`;
                        if (u.nro_sucurs) texto += `   📍 Sucs: ${u.nro_sucurs}\n`;
                        if (u.cod_client) texto += `   🏢 Cliente/Suc: ${u.cod_client}\n`;
                        texto += `   🌐 ${u.destino_url}\n\n`;
                    });
                } else if (formatoEnvioActual === 'email') {
                    texto = `Estimado equipo,\n\nA continuación compartimos el listado de credenciales de acceso activas:\n\n`;
                    listadoUsuariosFiltroActual.forEach((u, i) => {
                        texto += `[${i+1}] Usuario: ${u.username}\n`;
                        texto += `    Contraseña: ${u.password}\n`;
                        texto += `    Nombre: ${u.nombre_completo || '-'}\n`;
                        texto += `    Rol: ${u.rol_nombre || '-'}\n`;
                        texto += `    Acceso: ${u.destino_nombre} (${u.destino_url})\n\n`;
                    });
                    texto += `Saludos cordiales,\nAdministración de Sistemas - XL Extra Large`;
                } else {
                    texto = `USUARIO\tCONTRASEÑA\tNOMBRE\tROL\tURL\n`;
                    listadoUsuariosFiltroActual.forEach(u => {
                        texto += `${u.username}\t${u.password}\t${u.nombre_completo}\t${u.rol_nombre}\t${u.destino_url}\n`;
                    });
                }
                txtArea.value = texto;
                return;
            }

            const u = usuarioEnvioActual;
            if (!u) return;

            const pass = u.password || u.password_plain || '';
            const rol = u.rol_nombre || 'Personal / Usuario';
            const nombre = u.nombre_completo || u.username;
            
            let portalNombre = 'Terminal Local / Lakers';
            let portalUrl = 'https://app.xl.com.ar/sistemas/eliminaPedido.php';
            const cat = (u.categoria_usuario || 'PERSONAL').toUpperCase();
            const tipo = (u.tipo || '').toUpperCase();
            const userUpper = (u.username || '').toUpperCase();

            if (userUpper === 'FRANCOPERTUS' || rol.toUpperCase().includes('ADMIN') || rol.toUpperCase().includes('PROYECTOS')) {
                portalNombre = 'Centralizador Hub (NODO / Identity)';
                portalUrl = 'https://app.xl.com.ar/sistemas/hub/index.php';
            } else if (tipo === 'GRUPO' || cat === 'GRUPO' || rol.toUpperCase().includes('GRUPO')) {
                portalNombre = 'Portal Franquicias Grupo';
                portalUrl = 'https://app.xl.com.ar/ppp/franquicias/grupo/index.php';
            } else if (cat === 'MAYORISTA' || tipo === 'MAYORISTA' || (u.cod_vendedor && u.cod_vendedor !== '0') || rol.toUpperCase().includes('MAYORISTA')) {
                portalNombre = 'Portal Clientes Mayoristas B2B';
                portalUrl = 'https://app.xl.com.ar/sistemas/mayoristas/index.php';
            } else if (tipo === 'SUPERVISION' || rol.toUpperCase().includes('SUPERVISOR')) {
                portalNombre = 'Estadísticas Comerciales';
                portalUrl = 'https://app.xl.com.ar/comercial/estadisticas/index.php';
            } else if (tipo === 'GERENCIA' || rol.toUpperCase().includes('GERENCIA')) {
                portalNombre = 'Portal Gerencia / PPP';
                portalUrl = 'https://app.xl.com.ar/ppp/index.php';
            } else if (cat === 'LOCAL') {
                portalNombre = 'Terminal Local / Lakers';
                portalUrl = 'https://app.xl.com.ar/sistemas/eliminaPedido.php';
            }

            let texto = '';
            if (formatoEnvioActual === 'whatsapp') {
                texto = `🔐 *DATOS DE ACCESO - XL EXTRA LARGE*\n\n` +
                        `👤 *Usuario:* \`${u.username}\`\n` +
                        `🔑 *Contraseña:* \`${pass}\`\n` +
                        `🏢 *Nombre:* ${nombre}\n` +
                        (rol ? `🛡️ *Rol:* ${rol}\n` : '') +
                        (u.nro_sucurs ? `📍 *Sucursales Asignadas:* ${u.nro_sucurs}\n` : '') +
                        (u.cod_client ? `📦 *Cliente / Sigla:* ${u.cod_client}\n` : '') +
                        (u.cod_vendedor ? `🏷️ *Vendedor:* ${u.cod_vendedor}\n` : '') +
                        `\n🌐 *Portal de Ingreso:* ${portalUrl}\n\n` +
                        `⚠️ _Por favor, conserve estas credenciales en un lugar seguro._`;
            } else if (formatoEnvioActual === 'email') {
                texto = `Estimado/a ${nombre},\n\n` +
                        `Le enviamos sus credenciales de acceso correspondientes al sistema corporativo de XL Extra Large:\n\n` +
                        `• Nombre de Usuario: ${u.username}\n` +
                        `• Contraseña: ${pass}\n` +
                        (rol ? `• Rol Asignado: ${rol}\n` : '') +
                        (u.nro_sucurs ? `• Sucursales Habilitadas: ${u.nro_sucurs}\n` : '') +
                        `• Portal de Ingreso: ${portalNombre} (${portalUrl})\n\n` +
                        `Para ingresar, acceda al enlace provisto e introduzca sus datos.\n\n` +
                        `Saludos cordiales,\n` +
                        `Administración de Sistemas - XL Extra Large`;
            } else {
                texto = `Usuario: ${u.username}\n` +
                        `Password: ${pass}\n` +
                        `Nombre: ${nombre}\n` +
                        `Rol: ${rol}\n` +
                        `Link: ${portalUrl}`;
            }

            txtArea.value = texto;
        }

        function copiarTextoEnvioRapido() {
            const txtArea = document.getElementById('textoEnvioRapido');
            if (!txtArea || !txtArea.value) return;

            navigator.clipboard.writeText(txtArea.value).then(() => {
                showToast('¡Mensaje de credenciales copiado al portapapeles!', 'success');
                const btn = document.getElementById('btnCopiarEnvioRapido');
                if (btn) {
                    const orig = btn.innerHTML;
                    btn.innerHTML = '<i class="fas fa-check"></i> ¡Copiado!';
                    setTimeout(() => { btn.innerHTML = orig; }, 2000);
                }
            }).catch(() => {
                txtArea.select();
                document.execCommand('copy');
                showToast('¡Mensaje copiado!', 'success');
            });
        }

        // ==================== MODAL PERMISOS & ROLES ====================
        function toggleAppCollapse(header, event) {
            const card = header.closest('.app-card');
            if (!card) return;
            const content = card.querySelector('.app-content');
            const chevron = card.querySelector('.app-chevron i');
            if (!content) return;

            const isHidden = content.classList.contains('hidden');
            if (isHidden) {
                content.classList.remove('hidden');
                if (chevron) {
                    chevron.classList.remove('fa-chevron-down');
                    chevron.classList.add('fa-chevron-up');
                }
            } else {
                content.classList.add('hidden');
                if (chevron) {
                    chevron.classList.remove('fa-chevron-up');
                    chevron.classList.add('fa-chevron-down');
                }
            }
        }

        function abrirModalPermisos(rolId, rolNombre, esAdmin) {
            document.getElementById('permisosRolId').value = rolId;
            document.getElementById('nombreRolModal').textContent = rolNombre + (esAdmin ? ' (Administrador Total)' : '');
            
            document.querySelectorAll('#modalPermisos .app-content').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('#modalPermisos .app-chevron i').forEach(el => {
                el.classList.remove('fa-chevron-up');
                el.classList.add('fa-chevron-down');
            });

            const checks = document.querySelectorAll('.permiso-checkbox');
            const asignados = mapaPermisos[rolId] || [];

            checks.forEach(c => {
                c.checked = asignados.includes(parseInt(c.value));
            });

            abrirModal('modalPermisos');
        }

        function toggleTodosApp(btn) {
            const card = btn.closest('.app-card');
            if (!card) return;
            const checkboxes = card.querySelectorAll('.permiso-checkbox');
            const anyUnchecked = Array.from(checkboxes).some(c => !c.checked);
            checkboxes.forEach(c => c.checked = anyUnchecked);
        }

        function toggleTodosSubmodulo(btn) {
            const container = btn.closest('.submodulo-card');
            if (!container) return;
            const checkboxes = container.querySelectorAll('.permiso-checkbox');
            const anyUnchecked = Array.from(checkboxes).some(c => !c.checked);
            checkboxes.forEach(c => c.checked = anyUnchecked);
        }

        function toggleCategoria() {
            const selectedRadio = document.querySelector('input[name="categoria_usuario"]:checked');
            const cat = selectedRadio ? selectedRadio.value : 'PERSONAL';
            
            const badgeAlcance = document.getElementById('badgeAlcance');
            const formTipo = document.getElementById('formTipo');
            const lblCodClient = document.getElementById('lblCodClient');
            const lblNroSucursal = document.getElementById('lblNroSucursal');
            const lblCodVendedor = document.getElementById('lblCodVendedor');
            const bloqueLegajoSucursales = document.getElementById('bloqueLegajoSucursales');

            if (cat === 'PERSONAL') {
                if (badgeAlcance) badgeAlcance.textContent = 'Configuración Personal';
                if (lblCodClient) lblCodClient.textContent = 'Cód. Cliente (Opcional)';
                if (lblNroSucursal) lblNroSucursal.textContent = 'Nro Sucursal Principal';
                if (lblCodVendedor) lblCodVendedor.innerHTML = 'Cód. Vendedor (Opcional)';
                if (bloqueLegajoSucursales) bloqueLegajoSucursales.classList.remove('hidden');
                if (!formTipo.value || ['LOCAL_PROPIO', 'FRANQUICIA', 'MAYORISTA', 'GRUPO'].includes(formTipo.value)) {
                    formTipo.value = 'PERSONAL';
                }
            } else if (cat === 'LOCAL') {
                if (badgeAlcance) badgeAlcance.textContent = 'Configuración Local / Franquicia';
                if (lblCodClient) lblCodClient.textContent = 'Cód. Cliente / Sigla Local';
                if (lblNroSucursal) lblNroSucursal.textContent = 'Nro de Sucursal (NRO_SUCURSAL)';
                if (lblCodVendedor) lblCodVendedor.innerHTML = 'Cód. Vendedor (Vend)';
                if (bloqueLegajoSucursales) bloqueLegajoSucursales.classList.add('hidden');
                if (!['LOCAL_PROPIO', 'FRANQUICIA', 'FRANQUICIA_GA', 'LOCAL_PROPIO_UY'].includes(formTipo.value)) {
                    formTipo.value = 'LOCAL_PROPIO';
                }
            } else if (cat === 'MAYORISTA') {
                if (badgeAlcance) badgeAlcance.textContent = 'Configuración Mayorista B2B';
                if (lblCodClient) lblCodClient.textContent = 'Cód. Cliente B2B (COD_CLIENT)';
                if (lblNroSucursal) lblNroSucursal.textContent = 'Nro Sucursal / Depósito (ej: 200)';
                if (lblCodVendedor) lblCodVendedor.innerHTML = '<span class="text-teal-700 font-bold">Cód. Vendedor Asignado (Vend, ej: Z4)</span>';
                if (bloqueLegajoSucursales) bloqueLegajoSucursales.classList.add('hidden');
                if (!formTipo.value || ['LOCAL_PROPIO', 'FRANQUICIA', 'PERSONAL'].includes(formTipo.value)) {
                    formTipo.value = 'MAYORISTA';
                }
            } else if (cat === 'GRUPO') {
                if (badgeAlcance) badgeAlcance.textContent = 'Configuración Grupo Franquiciado';
                if (lblCodClient) lblCodClient.textContent = 'Cód. Grupo / Cliente (ej: FRMPOU)';
                if (lblNroSucursal) lblNroSucursal.textContent = 'Nro Sucursal (Opcional)';
                if (lblCodVendedor) lblCodVendedor.innerHTML = 'Cód. Vendedor (Opcional)';
                if (bloqueLegajoSucursales) bloqueLegajoSucursales.classList.add('hidden');
                if (!formTipo.value || ['LOCAL_PROPIO', 'FRANQUICIA', 'PERSONAL'].includes(formTipo.value)) {
                    formTipo.value = 'GRUPO';
                }
            }
        }

        // ==================== JS SECTORES ====================
        function cargarEditarSector(s) {
            document.getElementById('tituloFormSector').innerHTML = '<i class="fas fa-pencil text-indigo-600 mr-1.5"></i> Editar Sector';
            document.getElementById('inputSectorId').value = s.id;
            document.getElementById('inputSectorCodigo').value = s.codigo;
            document.getElementById('inputSectorNombre').value = s.nombre;
            document.getElementById('inputSectorDesc').value = s.descripcion || '';
            document.getElementById('btnSubmitSector').textContent = 'Actualizar Sector';
            document.getElementById('btnResetSector').classList.remove('hidden');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function resetFormSector() {
            document.getElementById('tituloFormSector').innerHTML = '<i class="fas fa-folder-plus text-indigo-600 mr-1.5"></i> Nuevo Sector Principal';
            document.getElementById('inputSectorId').value = '0';
            document.getElementById('inputSectorCodigo').value = '';
            document.getElementById('inputSectorNombre').value = '';
            document.getElementById('inputSectorDesc').value = '';
            document.getElementById('btnSubmitSector').textContent = 'Crear Sector';
            document.getElementById('btnResetSector').classList.add('hidden');
        }

        // ==================== JS SUBSECTORES ====================
        function cargarEditarSubsector(sub) {
            document.getElementById('tituloFormSubsector').innerHTML = '<i class="fas fa-pencil text-purple-600 mr-1.5"></i> Editar Subsector';
            document.getElementById('inputSubsectorId').value = sub.id;
            document.getElementById('inputSubsectorPadre').value = sub.sector_id;
            document.getElementById('inputSubsectorCodigo').value = sub.codigo;
            document.getElementById('inputSubsectorNombre').value = sub.nombre;
            document.getElementById('inputSubsectorDesc').value = sub.descripcion || '';
            document.getElementById('btnSubmitSubsector').textContent = 'Actualizar Subsector';
            document.getElementById('btnResetSubsector').classList.remove('hidden');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function resetFormSubsector() {
            document.getElementById('tituloFormSubsector').innerHTML = '<i class="fas fa-diagram-project text-purple-600 mr-1.5"></i> Nuevo Subsector / Equipo';
            document.getElementById('inputSubsectorId').value = '0';
            document.getElementById('inputSubsectorPadre').value = '';
            document.getElementById('inputSubsectorCodigo').value = '';
            document.getElementById('inputSubsectorNombre').value = '';
            document.getElementById('inputSubsectorDesc').value = '';
            document.getElementById('btnSubmitSubsector').textContent = 'Crear Subsector';
            document.getElementById('btnResetSubsector').classList.add('hidden');
        }

        function confirmarEliminarSubsector(subId, nombreSubsector, cantUsuarios, cantRoles) {
            document.getElementById('borrarSubsectorId').value = subId;
            document.getElementById('borrarSubsectorNombre').value = nombreSubsector;
            document.getElementById('nombreSubsectorEliminar').textContent = nombreSubsector;

            const aviso = document.getElementById('avisoSubsectorBloqueado');
            const textoAviso = document.getElementById('textoSubsectorBloqueado');
            const btnSubmit = document.getElementById('btnConfirmarEliminarSubsector');

            if (cantUsuarios > 0 || cantRoles > 0) {
                let razones = [];
                if (cantUsuarios > 0) razones.push(`<b>${cantUsuarios} usuario(s)</b> asignado(s)`);
                if (cantRoles > 0) razones.push(`<b>${cantRoles} rol(es)</b> configurado(s)`);
                
                textoAviso.innerHTML = `No se puede eliminar porque contiene ${razones.join(' y ')}. Reasigná o eliminá esos elementos primero.`;
                aviso.classList.remove('hidden');
                btnSubmit.disabled = true;
                btnSubmit.classList.add('opacity-50', 'cursor-not-allowed');
                btnSubmit.classList.remove('hover:bg-rose-700', 'cursor-pointer');
            } else {
                aviso.classList.add('hidden');
                textoAviso.textContent = '';
                btnSubmit.disabled = false;
                btnSubmit.classList.remove('opacity-50', 'cursor-not-allowed');
                btnSubmit.classList.add('hover:bg-rose-700', 'cursor-pointer');
            }

            abrirModal('modalEliminarSubsector');
        }

        // ==================== JS ROLES ====================
        function cargarEditarRol(r) {
            document.getElementById('tituloFormRol').innerHTML = '<i class="fas fa-pencil text-indigo-600 mr-1.5"></i> Editar Rol';
            document.getElementById('inputRolId').value = r.id;
            document.getElementById('inputRolSubsector').value = r.subsector_id || '';
            document.getElementById('inputRolCodigo').value = r.codigo;
            document.getElementById('inputRolNombre').value = r.nombre;
            document.getElementById('inputRolDesc').value = r.descripcion || '';
            document.getElementById('inputRolAdmin').checked = (r.es_admin == 1);
            document.getElementById('btnSubmitRol').textContent = 'Actualizar Rol';
            document.getElementById('btnResetRol').classList.remove('hidden');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function resetFormRol() {
            document.getElementById('tituloFormRol').innerHTML = '<i class="fas fa-shield-cat text-indigo-600 mr-1.5"></i> Nuevo Rol';
            document.getElementById('inputRolId').value = '0';
            document.getElementById('inputRolSubsector').value = '';
            document.getElementById('inputRolCodigo').value = '';
            document.getElementById('inputRolNombre').value = '';
            document.getElementById('inputRolDesc').value = '';
            document.getElementById('inputRolAdmin').checked = false;
            document.getElementById('btnSubmitRol').textContent = 'Guardar Rol';
            document.getElementById('btnResetRol').classList.add('hidden');
        }

        function crearRolParaSubsector(subId) {
            resetFormRol();
            const selectSub = document.getElementById('inputRolSubsector');
            if (selectSub) {
                selectSub.value = subId;
            }
            const inputNombre = document.getElementById('inputRolNombre');
            if (inputNombre) {
                inputNombre.focus();
            }
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function toggleSectorRoles(secSlug) {
            const contenido = document.getElementById('contenidoSectorRoles_' + secSlug);
            const chevron = document.getElementById('chevronSectorRoles_' + secSlug);
            if (contenido) {
                const isHidden = contenido.classList.contains('hidden');
                contenido.classList.toggle('hidden');
                if (chevron) {
                    chevron.style.transform = isHidden ? 'rotate(0deg)' : 'rotate(-90deg)';
                }
            }
        }

        function toggleTodosSectoresRoles() {
            const contenidos = document.querySelectorAll('.sector-roles-contenido');
            const chevrons = document.querySelectorAll('.chevron-sector-roles');
            const lbl = document.getElementById('lblToggleTodosRoles');
            
            // Si alguno está abierto, los colapsamos todos; sino, los expandimos
            const algunAbierto = Array.from(contenidos).some(c => !c.classList.contains('hidden'));
            
            contenidos.forEach(c => {
                if (algunAbierto) {
                    c.classList.add('hidden');
                } else {
                    c.classList.remove('hidden');
                }
            });
            
            chevrons.forEach(ch => {
                ch.style.transform = algunAbierto ? 'rotate(-90deg)' : 'rotate(0deg)';
            });
            
            if (lbl) {
                lbl.textContent = algunAbierto ? 'Expandir todos' : 'Colapsar todos';
            }
        }

        function filtrarRolesEnVivo(val) {
            const query = val.toLowerCase().trim();
            const items = document.querySelectorAll('.item-rol-card');
            const noResults = document.getElementById('sinResultadosRoles');
            const contador = document.getElementById('contadorRolesVisibles');
            let visibles = 0;

            items.forEach(el => {
                const searchData = el.getAttribute('data-search') || '';
                if (!query || searchData.includes(query)) {
                    el.classList.remove('hidden');
                    visibles++;
                } else {
                    el.classList.add('hidden');
                }
            });

            // Ajustar visibilidad de bloques de subsectores
            document.querySelectorAll('.subsector-roles-bloque').forEach(subBlock => {
                const hasVisible = subBlock.querySelectorAll('.item-rol-card:not(.hidden)').length > 0;
                subBlock.classList.toggle('hidden', !hasVisible);
            });

            // Ajustar visibilidad de bloques de sectores
            document.querySelectorAll('.sector-roles-bloque').forEach(secBlock => {
                const hasVisible = secBlock.querySelectorAll('.item-rol-card:not(.hidden)').length > 0;
                secBlock.classList.toggle('hidden', !hasVisible);
            });

            if (noResults) {
                noResults.classList.toggle('hidden', visibles > 0);
            }
            if (contador) {
                contador.textContent = query 
                    ? `${visibles} rol(es) encontrado(s)` 
                    : `${items.length} roles en el sistema`;
            }
        }

        // ==================== JS USUARIOS ====================
        const todosSubsectores = <?= json_encode(array_map(function($sub) {
            return ['id' => $sub['id'], 'sector_id' => $sub['sector_id'], 'nombre' => $sub['nombre']];
        }, $subsectores)) ?>;

        const todosRoles = <?= json_encode(array_map(function($r) {
            return ['id' => $r['id'], 'subsector_id' => $r['subsector_id'], 'nombre' => $r['nombre']];
        }, $roles)) ?>;

        function filtrarSubsectoresPorSector(selectedSubsectorId = '', selectedRolId = '') {
            const sectorId = document.getElementById('formSectorId').value;
            const selectSub = document.getElementById('formSubsectorId');
            
            selectSub.innerHTML = '<option value="">Sin Subsector</option>';
            
            if (sectorId) {
                const filtrados = todosSubsectores.filter(s => s.sector_id == sectorId);
                filtrados.forEach(s => {
                    const opt = document.createElement('option');
                    opt.value = s.id;
                    opt.textContent = s.nombre;
                    if (selectedSubsectorId && s.id == selectedSubsectorId) {
                        opt.selected = true;
                    }
                    selectSub.appendChild(opt);
                });
            } else {
                todosSubsectores.forEach(s => {
                    const opt = document.createElement('option');
                    opt.value = s.id;
                    opt.textContent = s.nombre;
                    if (selectedSubsectorId && s.id == selectedSubsectorId) {
                        opt.selected = true;
                    }
                    selectSub.appendChild(opt);
                });
            }

            // Actualizar roles dependientes del subsector seleccionado
            filtrarRolesPorSubsector(selectedRolId);
        }

        function filtrarRolesPorSubsector(selectedRolId = '') {
            const subsectorId = document.getElementById('formSubsectorId').value;
            const selectRol = document.getElementById('formRolId');
            
            selectRol.innerHTML = '<option value="">Sin Rol Asignado</option>';
            
            if (subsectorId) {
                const filtrados = todosRoles.filter(r => r.subsector_id == subsectorId);
                filtrados.forEach(r => {
                    const opt = document.createElement('option');
                    opt.value = r.id;
                    opt.textContent = r.nombre;
                    if (selectedRolId && r.id == selectedRolId) {
                        opt.selected = true;
                    }
                    selectRol.appendChild(opt);
                });
            } else {
                // Si no hay subsector seleccionado, solo se muestran roles globales (sin subsector específico) si existen
                const globales = todosRoles.filter(r => !r.subsector_id);
                globales.forEach(r => {
                    const opt = document.createElement('option');
                    opt.value = r.id;
                    opt.textContent = r.nombre;
                    if (selectedRolId && r.id == selectedRolId) {
                        opt.selected = true;
                    }
                    selectRol.appendChild(opt);
                });
            }
        }

        function editarPass(id, username, currentPass) {
            document.getElementById('modalUserId').value = id;
            document.getElementById('modalUsername').textContent = '@' + username;
            document.getElementById('modalNuevaPass').value = currentPass;
            abrirModal('modalPass');
        }

        function abrirModalUsuario() {
            document.getElementById('tituloModalUsuario').innerHTML = '<i class="fas fa-user-plus text-indigo-600 mr-2"></i> Nuevo Usuario';
            document.getElementById('formUserId').value = '0';
            document.getElementById('catPersonal').checked = true;
            document.getElementById('formUsername').value = '';
            document.getElementById('formPassword').value = '';
            document.getElementById('formNombre').value = '';
            document.getElementById('formEmail').value = '';
            document.getElementById('formTipo').value = 'PERSONAL';
            document.getElementById('formSectorId').value = '';
            filtrarSubsectoresPorSector();
            document.getElementById('formLegajo').value = '';
            document.getElementById('formNroSucurs').value = '';
            document.getElementById('formCodClient').value = '';
            document.getElementById('formNroSucursal').value = '';
            document.getElementById('formCodVendedor').value = '';
            document.getElementById('formUsuarioTango').value = '';
            document.getElementById('formActivo').checked = true;
            toggleCategoria();
            abrirModal('modalUsuario');
        }

        function editarUsuario(u) {
            document.getElementById('tituloModalUsuario').innerHTML = '<i class="fas fa-user-pen text-indigo-600 mr-2"></i> Editar Usuario @' + u.username;
            document.getElementById('formUserId').value = u.id;
            
            const cat = (u.categoria_usuario || 'PERSONAL').toUpperCase();
            const radioCat = document.querySelector(`input[name="categoria_usuario"][value="${cat}"]`);
            if (radioCat) {
                radioCat.checked = true;
            } else {
                document.getElementById('catPersonal').checked = true;
            }

            document.getElementById('formUsername').value = u.username || '';
            document.getElementById('formPassword').value = u.password || u.password_plain || '';
            document.getElementById('formNombre').value = u.nombre_completo || '';
            document.getElementById('formEmail').value = u.email || '';
            document.getElementById('formTipo').value = u.tipo || cat;
            document.getElementById('formSectorId').value = u.sector_id || '';
            filtrarSubsectoresPorSector(u.subsector_id || '', u.rol_id || '');
            document.getElementById('formLegajo').value = u.legajo || '';
            document.getElementById('formNroSucurs').value = u.nro_sucurs || '';
            document.getElementById('formCodClient').value = u.cod_client || '';
            document.getElementById('formNroSucursal').value = u.nro_sucursal || '';
            document.getElementById('formCodVendedor').value = u.cod_vendedor || '';
            document.getElementById('formUsuarioTango').value = u.usuario_tango || '';
            document.getElementById('formActivo').checked = (u.activo == 1);
            toggleCategoria();
            abrirModal('modalUsuario');
        }

        function confirmarEliminarUsuario(id, username) {
            document.getElementById('borrarUserId').value = id;
            document.getElementById('borrarUsername').value = username;
            document.getElementById('nombreUsuarioBorrar').textContent = '@' + username;
            abrirModal('modalEliminarUsuario');
        }

        function abrirModal(id) {
            const m = document.getElementById(id);
            if (m) {
                m.classList.remove('hidden');
                m.classList.add('flex');
            }
        }

        function cerrarModal(id) {
            const m = document.getElementById(id);
            if (m) {
                m.classList.add('hidden');
                m.classList.remove('flex');
            }
        }

        // ==================== ACCIONES AJAX DE USUARIOS (PRESERVAN FILTROS) ====================
        function guardarPassAjax(e) {
            e.preventDefault();
            const form = document.getElementById('formPass');
            const btn = document.getElementById('btnGuardarPass');
            const userId = document.getElementById('modalUserId').value;
            const nuevaPass = document.getElementById('modalNuevaPass').value;

            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-circle-notch fa-spin mr-1.5"></i> Guardando...';
            }

            const formData = new FormData(form);
            formData.append('ajax', '1');

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(res => {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = 'Guardar en SQL';
                }
                if (res.ok) {
                    showToast(res.mensaje || 'Contraseña actualizada con éxito');
                    cerrarModal('modalPass');
                    const passEl = document.getElementById('pass-user-' + userId);
                    if (passEl) {
                        passEl.textContent = nuevaPass;
                        passEl.classList.add('bg-emerald-100', 'text-emerald-900', 'border-emerald-300');
                        setTimeout(() => {
                            passEl.classList.remove('bg-emerald-100', 'text-emerald-900', 'border-emerald-300');
                        }, 2000);
                    }
                } else {
                    showToast(res.error || 'Error al actualizar contraseña', 'error');
                }
            })
            .catch(() => {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = 'Guardar en SQL';
                }
                showToast('Error de conexión', 'error');
            });
        }

        function guardarUsuarioAjax(e) {
            e.preventDefault();
            const form = document.getElementById('formUsuario');
            const btn = form.querySelector('button[type="submit"]');

            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-circle-notch fa-spin mr-1.5"></i> Guardando...';
            }

            const formData = new FormData(form);
            formData.append('ajax', '1');

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(res => {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = 'Guardar Usuario';
                }
                if (res.ok) {
                    showToast(res.mensaje || 'Usuario guardado correctamente');
                    cerrarModal('modalUsuario');
                    // Recargar conservando exactamente todos los filtros activos y paginación
                    setTimeout(() => {
                        window.location.reload();
                    }, 400);
                } else {
                    showToast(res.error || 'Error al guardar usuario', 'error');
                }
            })
            .catch(() => {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = 'Guardar Usuario';
                }
                showToast('Error de conexión', 'error');
            });
        }

        function eliminarUsuarioAjax(e) {
            e.preventDefault();
            const form = document.getElementById('formEliminarUsuario');
            const btn = document.getElementById('btnConfirmarEliminarUsr');
            const userId = document.getElementById('borrarUserId').value;

            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-circle-notch fa-spin mr-1.5"></i> Eliminando...';
            }

            const formData = new FormData(form);
            formData.append('ajax', '1');

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(res => {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = 'Sí, Eliminar';
                }
                if (res.ok) {
                    showToast(res.mensaje || 'Usuario eliminado correctamente');
                    cerrarModal('modalEliminarUsuario');
                    const row = document.getElementById('row-user-' + userId);
                    if (row) {
                        row.classList.add('bg-rose-50', 'opacity-50');
                        setTimeout(() => row.remove(), 400);
                    } else {
                        setTimeout(() => window.location.reload(), 400);
                    }
                } else {
                    showToast(res.error || 'Error al eliminar usuario', 'error');
                }
            })
            .catch(() => {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = 'Sí, Eliminar';
                }
                showToast('Error de conexión', 'error');
            });
        }

        function toggleActivoUsuario(userId, checkbox) {
            const nuevoEstado = checkbox.checked ? 1 : 0;
            const badge = document.getElementById('badge-activo-' + userId);
            const row = document.getElementById('row-user-' + userId);

            // Actualización visual inmediata
            if (badge) {
                if (nuevoEstado === 1) {
                    badge.className = 'inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 transition-all';
                    badge.innerHTML = '<span class="w-1.5 h-1.5 rounded-full mr-1 bg-emerald-500"></span>Habilitado';
                } else {
                    badge.className = 'inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200 transition-all';
                    badge.innerHTML = '<span class="w-1.5 h-1.5 rounded-full mr-1 bg-rose-500"></span>Inactivo';
                }
            }
            if (row) {
                if (nuevoEstado === 1) {
                    row.classList.remove('bg-slate-100/50', 'opacity-75');
                } else {
                    row.classList.add('bg-slate-100/50', 'opacity-75');
                }
            }

            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'toggle_activo_usuario');
            formData.append('user_id', userId);
            formData.append('activo', nuevoEstado);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(res => {
                if (res.ok) {
                    showToast(res.mensaje || 'Estado actualizado', 'success');
                } else {
                    // Revertir en caso de error
                    checkbox.checked = !checkbox.checked;
                    toggleActivoUsuario(userId, checkbox);
                    showToast(res.error || 'No se pudo cambiar el estado', 'error');
                }
            })
            .catch(() => {
                checkbox.checked = !checkbox.checked;
                toggleActivoUsuario(userId, checkbox);
                showToast('Error de conexión con el servidor', 'error');
            });
        }

        // ==================== JS MATRIZ HUB & ACCESOS ====================
        function mostrarToast(mensaje, tipo = 'success') {
            let container = document.getElementById('toastGlobalContainer');
            if (!container) {
                container = document.createElement('div');
                container.id = 'toastGlobalContainer';
                container.className = 'fixed bottom-5 right-5 z-50 flex flex-col gap-2 pointer-events-none';
                document.body.appendChild(container);
            }
            const toast = document.createElement('div');
            const esOk = (tipo === 'success');
            toast.className = `pointer-events-auto flex items-center gap-2.5 px-4 py-3 rounded-2xl shadow-lg border text-xs font-semibold transform transition-all duration-300 translate-y-3 opacity-0 ${esOk ? 'bg-slate-900 text-white border-slate-700' : 'bg-rose-600 text-white border-rose-700'}`;
            toast.innerHTML = `<i class="fas ${esOk ? 'fa-circle-check text-emerald-400' : 'fa-triangle-exclamation text-white'} text-sm"></i> <span>${mensaje}</span>`;
            container.appendChild(toast);
            
            requestAnimationFrame(() => {
                toast.classList.remove('translate-y-3', 'opacity-0');
            });
            setTimeout(() => {
                toast.classList.add('opacity-0', 'translate-y-2');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        function toggleHubPermiso(checkbox, rolId, permisoId) {
            const activo = checkbox.checked ? 1 : 0;
            const itemBox = document.getElementById(`itemApp_${rolId}_${permisoId}`);
            
            // Visual feedback inmediato
            if (itemBox) {
                if (activo) {
                    itemBox.classList.remove('bg-slate-50/60', 'border-slate-200/80');
                    itemBox.classList.add('bg-indigo-50/40', 'border-indigo-200');
                } else {
                    itemBox.classList.remove('bg-indigo-50/40', 'border-indigo-200');
                    itemBox.classList.add('bg-slate-50/60', 'border-slate-200/80');
                }
            }
            actualizarContadorAppsRol(rolId);

            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'toggle_permiso_hub_rol');
            formData.append('rol_id', rolId);
            formData.append('permiso_id', permisoId);
            formData.append('activo', activo);

            fetch('', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.ok) {
                        mostrarToast(data.mensaje || (activo ? 'Acceso habilitado' : 'Acceso revocado'));
                    } else {
                        // Revertir si hubo error
                        checkbox.checked = !checkbox.checked;
                        toggleHubPermiso(checkbox, rolId, permisoId);
                        mostrarToast(data.error || 'Error al actualizar permiso', 'error');
                    }
                })
                .catch(err => {
                    checkbox.checked = !checkbox.checked;
                    mostrarToast('Error de conexión con el servidor', 'error');
                });
        }

        function actualizarContadorAppsRol(rolId) {
            const rolCard = document.querySelector(`.rol-hub-card[data-rol-id="${rolId}"]`);
            if (!rolCard) return;
            const switches = rolCard.querySelectorAll('.switch-hub-permiso');
            let activas = 0;
            switches.forEach(s => { if (s.checked) activas++; });
            const badge = document.getElementById(`badgeRolApps_${rolId}`);
            if (badge) {
                const countVal = badge.querySelector('.count-val');
                if (countVal) countVal.textContent = activas;
                if (activas > 0) {
                    badge.className = 'px-2.5 py-1 rounded-lg text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-200';
                } else {
                    badge.className = 'px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 text-slate-500 border border-slate-200';
                }
            }
        }

        function marcarTodasAppsRol(rolId, activar) {
            const rolCard = document.querySelector(`.rol-hub-card[data-rol-id="${rolId}"]`);
            if (!rolCard) return;
            const switches = rolCard.querySelectorAll('.switch-hub-permiso');
            const permisoIds = [];

            switches.forEach(s => {
                s.checked = activar;
                const pId = s.getAttribute('data-permiso-id');
                const itemBox = document.getElementById(`itemApp_${rolId}_${pId}`);
                if (itemBox) {
                    if (activar) {
                        itemBox.classList.remove('bg-slate-50/60', 'border-slate-200/80');
                        itemBox.classList.add('bg-indigo-50/40', 'border-indigo-200');
                        permisoIds.push(pId);
                    } else {
                        itemBox.classList.remove('bg-indigo-50/40', 'border-indigo-200');
                        itemBox.classList.add('bg-slate-50/60', 'border-slate-200/80');
                    }
                }
            });
            actualizarContadorAppsRol(rolId);

            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'guardar_permisos_hub_rol');
            formData.append('rol_id', rolId);
            permisoIds.forEach(id => formData.append('permiso_ids[]', id));

            fetch('', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.ok) {
                        mostrarToast(activar ? 'Todas las apps habilitadas' : 'Accesos desmarcados');
                    } else {
                        mostrarToast(data.error || 'Error al guardar', 'error');
                    }
                })
                .catch(() => mostrarToast('Error de conexión', 'error'));
        }

        function aplicarTodosRolesSubsector(subsectorId, todas) {
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'aplicar_permisos_hub_subsector');
            formData.append('subsector_id', subsectorId);

            // Obtener todos los permisos del catálogo
            if (todas) {
                const primerRol = document.querySelector('.rol-hub-card');
                if (primerRol) {
                    primerRol.querySelectorAll('.switch-hub-permiso').forEach(s => {
                        formData.append('permiso_ids[]', s.getAttribute('data-permiso-id'));
                    });
                }
            } else {
                // Solo NODO
                const switchNodo = document.querySelector('.switch-hub-permiso[data-permiso-clave="hub.app.nodo"]');
                if (switchNodo) {
                    formData.append('permiso_ids[]', switchNodo.getAttribute('data-permiso-id'));
                }
            }

            fetch('', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.ok) {
                        mostrarToast(data.mensaje);
                        setTimeout(() => window.location.reload(), 600);
                    } else {
                        mostrarToast(data.error || 'Error al aplicar a subsector', 'error');
                    }
                })
                .catch(() => mostrarToast('Error de conexión', 'error'));
        }

        function toggleSectorHub(sectorId) {
            const cont = document.getElementById(`contenidoSector_${sectorId}`);
            const icon = document.getElementById(`chevronSector_${sectorId}`);
            if (cont) {
                cont.classList.toggle('hidden');
                if (icon) {
                    icon.classList.toggle('rotate-180', !cont.classList.contains('hidden'));
                }
            }
        }

        let todosSectoresExpandidos = false;
        function toggleTodosSectoresHub() {
            todosSectoresExpandidos = !todosSectoresExpandidos;
            document.querySelectorAll('.sector-hub-contenido').forEach(c => {
                c.classList.toggle('hidden', !todosSectoresExpandidos);
            });
            document.querySelectorAll('[id^="chevronSector_"]').forEach(i => {
                i.classList.toggle('rotate-180', todosSectoresExpandidos);
            });
            const lbl = document.getElementById('lblToggleTodosSectores');
            if (lbl) lbl.textContent = todosSectoresExpandidos ? 'Colapsar todos' : 'Expandir todos';
        }

        // Si se ingresó con ?rol_id=XX, expandir el sector contenedor y hacer scroll suave hasta el rol resaltándolo
        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const rolTargetId = urlParams.get('rol_id');
            if (rolTargetId) {
                const targetCard = document.querySelector(`.rol-hub-card[data-rol-id="${rolTargetId}"]`);
                if (targetCard) {
                    const sectorBloque = targetCard.closest('.sector-hub-bloque');
                    if (sectorBloque) {
                        const secId = sectorBloque.getAttribute('data-sector-id');
                        const contenido = document.getElementById(`contenidoSector_${secId}`);
                        const chevron = document.getElementById(`chevronSector_${secId}`);
                        if (contenido) contenido.classList.remove('hidden');
                        if (chevron) chevron.classList.add('rotate-180');
                    }
                    setTimeout(() => {
                        targetCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        targetCard.classList.add('ring-2', 'ring-indigo-500', 'shadow-lg', 'bg-indigo-50/30');
                        setTimeout(() => {
                            targetCard.classList.remove('ring-2', 'ring-indigo-500', 'shadow-lg', 'bg-indigo-50/30');
                        }, 3000);
                    }, 250);
                }
            }
        });

        function filtrarMatrizHubEnVivo(val) {
            const q = val.toLowerCase().trim();
            const cards = document.querySelectorAll('.rol-hub-card');
            cards.forEach(c => {
                const searchData = c.getAttribute('data-search') || '';
                const match = (!q || searchData.includes(q));
                c.classList.toggle('hidden', !match);
            });

            // Ocultar subsectores vacíos
            document.querySelectorAll('.subsector-hub-bloque').forEach(sub => {
                const hayVisibles = Array.from(sub.querySelectorAll('.rol-hub-card')).some(c => !c.classList.contains('hidden'));
                sub.classList.toggle('hidden', !hayVisibles && q !== '');
            });

            // Ocultar sectores vacíos
            document.querySelectorAll('.sector-hub-bloque').forEach(sec => {
                const hayVisibles = Array.from(sec.querySelectorAll('.rol-hub-card')).some(c => !c.classList.contains('hidden'));
                sec.classList.toggle('hidden', !hayVisibles && q !== '');
            });
        }

        function abrirModalCopiarPermisosHub(rolOrigenId = null) {
            if (rolOrigenId) {
                const sel = document.getElementById('copiaRolOrigen');
                if (sel) sel.value = rolOrigenId;
            }
            abrirModal('modalCopiarPermisosHub');
        }

        function toggleTodosRolesDestino() {
            const chks = document.querySelectorAll('.chk-rol-destino');
            const anyUnchecked = Array.from(chks).some(c => !c.checked);
            chks.forEach(c => { c.checked = anyUnchecked; });
        }

        function ejecutarCopiaPermisosHub(e) {
            e.preventDefault();
            const rolOrigen = document.getElementById('copiaRolOrigen').value;
            const chks = document.querySelectorAll('.chk-rol-destino:checked');
            if (!rolOrigen) {
                mostrarToast('Seleccioná el rol origen', 'error');
                return;
            }
            if (chks.length === 0) {
                mostrarToast('Seleccioná al menos un rol destino', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'copiar_permisos_hub_rol');
            formData.append('rol_origen_id', rolOrigen);
            chks.forEach(c => formData.append('rol_destino_ids[]', c.value));

            fetch('', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.ok) {
                        cerrarModal('modalCopiarPermisosHub');
                        mostrarToast(data.mensaje);
                        setTimeout(() => window.location.reload(), 700);
                    } else {
                        mostrarToast(data.error || 'Error al copiar permisos', 'error');
                    }
                })
                .catch(() => mostrarToast('Error de conexión', 'error'));
        }

        // ==================== JS SUBMÓDULOS DE CENTRALIZADORES ====================
        let currentSubmodRolId = null;
        let currentSubmodModuloId = null;

        function abrirModalSubmodulos(rolId, moduloId, rolNombre, moduloNombre) {
            currentSubmodRolId = rolId;
            currentSubmodModuloId = moduloId;

            document.getElementById('submodModalModNombre').textContent = moduloNombre;
            document.getElementById('submodModalRolNombre').textContent = rolNombre;
            document.getElementById('buscadorSubmodulos').value = '';
            document.getElementById('submodModalContenido').innerHTML = `
                <div class="py-12 text-center text-slate-400">
                    <i class="fas fa-spinner fa-spin text-2xl text-indigo-500 mb-2 block"></i>
                    Cargando menú y aplicaciones internas...
                </div>
            `;
            abrirModal('modalSubmodulosCentralizador');

            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'obtener_submodulos_centralizador');
            formData.append('rol_id', rolId);
            formData.append('modulo_id', moduloId);

            fetch('', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (res.ok && res.data) {
                        renderizarSubmodulosEnModal(res.data);
                    } else {
                        document.getElementById('submodModalContenido').innerHTML = `
                            <div class="p-4 bg-rose-50 text-rose-700 rounded-xl border border-rose-200 text-xs">
                                ${res.error || 'No se pudieron cargar los submódulos'}
                            </div>
                        `;
                    }
                })
                .catch(() => {
                    document.getElementById('submodModalContenido').innerHTML = `
                        <div class="p-4 bg-rose-50 text-rose-700 rounded-xl border border-rose-200 text-xs">
                            Error de conexión con el servidor.
                        </div>
                    `;
                });
        }

        function renderizarSubmodulosEnModal(data) {
            const cont = document.getElementById('submodModalContenido');
            const secciones = data.secciones || {};
            const total = data.total_items || 0;
            const activos = data.total_activos || 0;

            document.getElementById('submodModalContador').textContent = `${activos} / ${total} habilitadas`;

            if (Object.keys(secciones).length === 0) {
                cont.innerHTML = `
                    <div class="py-8 text-center text-slate-400 text-xs">
                        Este centralizador no cuenta con submódulos configurables.
                    </div>
                `;
                return;
            }

            let html = '';
            let secIdx = 0;
            for (const [secNombre, items] of Object.entries(secciones)) {
                secIdx++;
                const cantItemsSec = items.length;
                const cantActivosSec = items.filter(it => it.activo).length;

                html += `
                    <div class="seccion-submod-bloque border border-slate-200 rounded-xl overflow-hidden bg-slate-50/40">
                        <div class="p-3 bg-white border-b border-slate-200/80 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                                <h4 class="text-xs font-bold text-slate-900">${secNombre}</h4>
                                <span class="text-[10px] bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full font-semibold sec-counter-${secIdx}">
                                    ${cantActivosSec} / ${cantItemsSec}
                                </span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <button type="button" onclick="toggleSeccionSubmodulos(${secIdx}, true)" class="text-[11px] text-indigo-600 hover:text-indigo-800 font-semibold px-2 py-0.5 rounded hover:bg-indigo-50 transition">
                                    Marcar Todo
                                </button>
                                <span class="text-slate-300">|</span>
                                <button type="button" onclick="toggleSeccionSubmodulos(${secIdx}, false)" class="text-[11px] text-slate-500 hover:text-slate-700 font-semibold px-2 py-0.5 rounded hover:bg-slate-100 transition">
                                    Desmarcar
                                </button>
                            </div>
                        </div>
                        <div class="p-3 grid grid-cols-1 sm:grid-cols-2 gap-2" id="secItemsCont_${secIdx}">
                `;

                items.forEach(it => {
                    const searchStr = (secNombre + ' ' + it.nombre + ' ' + (it.descripcion_limpia || '')).toLowerCase();
                    html += `
                        <label class="submod-item-card flex items-start p-2.5 rounded-xl border bg-white cursor-pointer transition hover:border-indigo-300 ${it.activo ? 'border-indigo-200 bg-indigo-50/20' : 'border-slate-200 text-slate-600'}" 
                               data-search="${searchStr}">
                            <input type="checkbox" 
                                   value="${it.id}" 
                                   data-sec-idx="${secIdx}"
                                   class="chk-submod-item rounded text-indigo-600 focus:ring-indigo-500 mt-0.5 shrink-0" 
                                   ${it.activo ? 'checked' : ''} 
                                   onchange="actualizarEstadoItemSubmod(this)">
                            <div class="ml-2.5 min-w-0">
                                <div class="text-xs font-bold text-slate-900 leading-tight">${it.nombre}</div>
                                <div class="text-[11px] text-slate-400 line-clamp-2 mt-0.5 leading-snug">${it.descripcion_limpia || 'Acceso directo'}</div>
                            </div>
                        </label>
                    `;
                });

                html += `
                        </div>
                    </div>
                `;
            }

            cont.innerHTML = html;
        }

        function actualizarEstadoItemSubmod(chk) {
            const card = chk.closest('.submod-item-card');
            if (card) {
                if (chk.checked) {
                    card.classList.add('border-indigo-200', 'bg-indigo-50/20');
                    card.classList.remove('border-slate-200');
                } else {
                    card.classList.remove('border-indigo-200', 'bg-indigo-50/20');
                    card.classList.add('border-slate-200');
                }
            }

            // Actualizar contador sección
            const secIdx = chk.getAttribute('data-sec-idx');
            if (secIdx) {
                const itemsSec = document.querySelectorAll(`#secItemsCont_${secIdx} .chk-submod-item`);
                const itemsSecActivos = document.querySelectorAll(`#secItemsCont_${secIdx} .chk-submod-item:checked`);
                const secBadge = document.querySelector(`.sec-counter-${secIdx}`);
                if (secBadge) {
                    secBadge.textContent = `${itemsSecActivos.length} / ${itemsSec.length}`;
                }
            }

            // Actualizar contador global modal
            const total = document.querySelectorAll('.chk-submod-item').length;
            const activos = document.querySelectorAll('.chk-submod-item:checked').length;
            document.getElementById('submodModalContador').textContent = `${activos} / ${total} habilitadas`;
        }

        function toggleSeccionSubmodulos(secIdx, marcar) {
            const chks = document.querySelectorAll(`#secItemsCont_${secIdx} .chk-submod-item`);
            chks.forEach(c => {
                c.checked = marcar;
                actualizarEstadoItemSubmod(c);
            });
        }

        function toggleTodosSubmodulos(marcar) {
            const chks = document.querySelectorAll('.chk-submod-item');
            chks.forEach(c => {
                c.checked = marcar;
                actualizarEstadoItemSubmod(c);
            });
        }

        function filtrarSubmodulosEnModal(val) {
            const q = val.toLowerCase().trim();
            const cards = document.querySelectorAll('.submod-item-card');
            cards.forEach(c => {
                const searchStr = c.getAttribute('data-search') || '';
                c.classList.toggle('hidden', !(!q || searchStr.includes(q)));
            });

            // Ocultar secciones si no tienen ítems visibles
            document.querySelectorAll('.seccion-submod-bloque').forEach(sec => {
                const hayVisibles = Array.from(sec.querySelectorAll('.submod-item-card')).some(c => !c.classList.contains('hidden'));
                sec.classList.toggle('hidden', !hayVisibles && q !== '');
            });
        }

        function guardarSubmodulosCentralizador() {
            if (!currentSubmodRolId || !currentSubmodModuloId) return;

            const chks = document.querySelectorAll('.chk-submod-item:checked');
            const btn = document.getElementById('btnGuardarSubmodulos');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'guardar_submodulos_centralizador');
            formData.append('rol_id', currentSubmodRolId);
            formData.append('modulo_id', currentSubmodModuloId);
            chks.forEach(c => formData.append('permiso_ids[]', c.value));

            fetch('', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-check"></i> Guardar Accesos Internos';
                    if (res.ok) {
                        cerrarModal('modalSubmodulosCentralizador');
                        mostrarToast(res.mensaje || 'Accesos internos guardados con éxito');
                    } else {
                        mostrarToast(res.error || 'Error al guardar accesos', 'error');
                    }
                })
                .catch(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-check"></i> Guardar Accesos Internos';
                    mostrarToast('Error de conexión', 'error');
                });
        }

        // ==================== JS GESTIÓN DE MÓDULOS & APPS (CRUD) ====================
        let currentPermModuloId = null;

        function toggleNuevaCategoriaMod(mostrarInput) {
            const contSel = document.getElementById('contSelectCategoria');
            const contInp = document.getElementById('contInputNuevaCategoria');
            const btnToggle = document.getElementById('btnToggleNuevaCat');
            const sel = document.getElementById('formModCategoriaSelect');
            const inp = document.getElementById('formModCategoriaInput');

            if (mostrarInput) {
                contSel.classList.add('hidden');
                contInp.classList.remove('hidden');
                btnToggle.classList.add('hidden');
                inp.focus();
            } else {
                contInp.classList.add('hidden');
                contSel.classList.remove('hidden');
                btnToggle.classList.remove('hidden');
                if (sel.value === '__NUEVA__') {
                    sel.value = '';
                }
            }
        }

        function verificarCambioCategoria(val) {
            if (val === '__NUEVA__') {
                toggleNuevaCategoriaMod(true);
            }
        }

        function abrirModalCrearModulo() {
            document.getElementById('modalModTitulo').textContent = 'Nueva Aplicación / Módulo';
            document.getElementById('btnTextoGuardarModulo').textContent = 'Crear Módulo';
            document.getElementById('formModId').value = '0';
            document.getElementById('formModNombre').value = '';
            document.getElementById('formModCodigo').value = '';
            document.getElementById('formModCodigo').readOnly = false;
            
            toggleNuevaCategoriaMod(false);
            const selCat = document.getElementById('formModCategoriaSelect');
            if (selCat) {
                selCat.value = selCat.options.length > 1 ? selCat.options[1].value : '';
            }
            document.getElementById('formModCategoriaInput').value = '';
            document.getElementById('formModCategoriaFinal').value = '';

            document.getElementById('formModRutaUrl').value = '';
            document.getElementById('formModIcono').value = 'fa-cube';
            document.getElementById('formModColor').value = 'linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%)';
            document.getElementById('formModDesc').value = '';
            document.getElementById('formModOrden').value = '100';
            document.getElementById('formModTargetBlank').checked = false;
            document.getElementById('formModActivo').checked = true;

            actualizarDisplayIcono('fa-cube');
            actualizarDisplayColor('linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%)');
            actualizarPreviewModulo();
            abrirModal('modalModulo');
        }

        function abrirModalEditarModulo(moduloId) {
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'obtener_modulo');
            formData.append('modulo_id', moduloId);

            fetch('', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (res.ok && res.modulo) {
                        const m = res.modulo;
                        document.getElementById('modalModTitulo').textContent = 'Editar Módulo: ' + m.nombre;
                        document.getElementById('btnTextoGuardarModulo').textContent = 'Guardar Cambios';
                        document.getElementById('formModId').value = m.id;
                        document.getElementById('formModNombre').value = m.nombre;
                        document.getElementById('formModCodigo').value = m.codigo;
                        
                        const cat = m.categoria || 'General';
                        const sel = document.getElementById('formModCategoriaSelect');
                        let existeEnSelect = false;
                        for (let i = 0; i < sel.options.length; i++) {
                            if (sel.options[i].value === cat) {
                                existeEnSelect = true;
                                break;
                            }
                        }

                        if (existeEnSelect) {
                            toggleNuevaCategoriaMod(false);
                            sel.value = cat;
                            document.getElementById('formModCategoriaInput').value = '';
                        } else {
                            toggleNuevaCategoriaMod(true);
                            sel.value = '__NUEVA__';
                            document.getElementById('formModCategoriaInput').value = cat;
                        }
                        document.getElementById('formModCategoriaFinal').value = cat;

                        document.getElementById('formModRutaUrl').value = m.ruta_url || '';
                        document.getElementById('formModIcono').value = m.icono || 'fa-cube';
                        document.getElementById('formModColor').value = m.color || 'linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%)';
                        document.getElementById('formModDesc').value = m.descripcion || '';
                        document.getElementById('formModOrden').value = m.orden || 100;
                        document.getElementById('formModTargetBlank').checked = (m.target_blank == 1);
                        document.getElementById('formModActivo').checked = (m.activo == 1);

                        actualizarDisplayIcono(m.icono || 'fa-cube');
                        actualizarDisplayColor(m.color || 'linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%)');
                        actualizarPreviewModulo();
                        abrirModal('modalModulo');
                    } else {
                        showToast(res.error || 'Error al obtener datos del módulo', 'error');
                    }
                })
                .catch(() => showToast('Error de conexión', 'error'));
        }

        document.addEventListener('DOMContentLoaded', function() {
            const formMod = document.getElementById('formModulo');
            if (formMod) {
                formMod.addEventListener('submit', function(e) {
                    const contInp = document.getElementById('contInputNuevaCategoria');
                    const esNueva = !contInp.classList.contains('hidden');
                    let catFinal = '';
                    if (esNueva) {
                        catFinal = document.getElementById('formModCategoriaInput').value.trim();
                    } else {
                        const sel = document.getElementById('formModCategoriaSelect');
                        catFinal = sel.value;
                    }

                    if (!catFinal || catFinal === '__NUEVA__') {
                        e.preventDefault();
                        showToast('Por favor seleccioná o escribí una categoría para el módulo', 'error');
                        return false;
                    }

                    document.getElementById('formModCategoriaFinal').value = catFinal;
                });
            }
        });

        function confirmarEliminarModulo(moduloId, nombreModulo) {
            document.getElementById('borrarModuloId').value = moduloId;
            document.getElementById('borrarModuloNombre').value = nombreModulo;
            document.getElementById('nombreModuloEliminar').textContent = nombreModulo;
            abrirModal('modalEliminarModulo');
        }

        function seleccionarIconoModulo(iconoClass) {
            document.getElementById('formModIcono').value = iconoClass;
            actualizarDisplayIcono(iconoClass);
            actualizarPreviewModulo();
        }

        function actualizarDisplayIcono(iconoClass) {
            const cleanIcon = iconoClass.trim() || 'fa-cube';
            const disp = document.getElementById('modIconoDisplay');
            if (disp) disp.className = 'fas ' + cleanIcon;
            const hdr = document.getElementById('modalModIconHeader');
            if (hdr) hdr.className = 'fas ' + cleanIcon;
        }

        function seleccionarColorModulo(colorCss) {
            document.getElementById('formModColor').value = colorCss;
            actualizarDisplayColor(colorCss);
            actualizarPreviewModulo();
        }

        function actualizarDisplayColor(colorCss) {
            const box = document.getElementById('previewModIconBox');
            if (box) box.style.background = colorCss;
        }

        function actualizarPreviewModulo() {
            const nombre = document.getElementById('formModNombre').value || 'Nombre de la Aplicación';
            const codigo = document.getElementById('formModCodigo').value || 'CODIGO_APP';
            const desc = document.getElementById('formModDesc').value || 'Descripción del sistema...';
            const icono = document.getElementById('formModIcono').value || 'fa-cube';
            const color = document.getElementById('formModColor').value || 'linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%)';

            document.getElementById('previewModNombre').textContent = nombre;
            document.getElementById('previewModCodigo').textContent = codigo;
            document.getElementById('previewModDesc').textContent = desc;
            document.getElementById('previewModIcon').className = 'fas ' + icono;
            document.getElementById('previewModIconBox').style.background = color;
        }

        function generarCodigoDesdeNombre(val) {
            // Solo autogenerar si estamos creando un nuevo módulo (id === 0)
            const id = document.getElementById('formModId').value;
            if (id === '0' || !id) {
                const slug = val.toUpperCase()
                    .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
                    .replace(/[^A-Z0-9\s]/g, '')
                    .trim()
                    .replace(/\s+/g, '_');
                document.getElementById('formModCodigo').value = slug;
                actualizarPreviewModulo();
            }
        }

        function filtrarModulosEnVivo(val) {
            const q = val.toLowerCase().trim();
            const cards = document.querySelectorAll('.modulo-card');
            cards.forEach(c => {
                const searchData = c.getAttribute('data-search') || '';
                const match = (!q || searchData.includes(q));
                c.classList.toggle('hidden', !match);
            });

            // Ocultar categorías vacías
            let totalVisibles = 0;
            document.querySelectorAll('.categoria-modulos-bloque').forEach(bloque => {
                const hayVisibles = Array.from(bloque.querySelectorAll('.modulo-card')).some(c => !c.classList.contains('hidden'));
                bloque.classList.toggle('hidden', !hayVisibles && q !== '');
                if (hayVisibles) totalVisibles++;
            });

            const sinRes = document.getElementById('sinResultadosModulos');
            if (sinRes) {
                sinRes.classList.toggle('hidden', totalVisibles > 0 || q === '');
            }
        }

        // ==================== JS GESTIÓN DE PERMISOS / SOLAPAS ====================

        function abrirModalGestionPermisos(moduloId) {
            currentPermModuloId = moduloId;
            document.getElementById('permFormModuloId').value = moduloId;
            resetFormPermiso();
            cargarPermisosModuloEnModal(moduloId);
            abrirModal('modalGestionPermisosModulo');
        }

        function cargarPermisosModuloEnModal(moduloId) {
            const cont = document.getElementById('gestPermListaContenedor');
            cont.innerHTML = `
                <div class="py-8 text-center text-slate-400">
                    <i class="fas fa-spinner fa-spin text-2xl text-indigo-500 mb-2 block"></i>
                    Cargando solapas y permisos...
                </div>
            `;

            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'listar_permisos_modulo');
            formData.append('modulo_id', moduloId);

            fetch('', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (res.ok) {
                        const m = res.modulo;
                        document.getElementById('gestPermModalNombre').textContent = m.nombre;
                        document.getElementById('gestPermModalCodigo').textContent = m.codigo;
                        document.getElementById('gestPermModalIcon').className = 'fas ' + (m.icono || 'fa-cube');
                        document.getElementById('gestPermModalIconBox').style.background = m.color || 'linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%)';
                        
                        renderizarPermisosModuloEnModal(res.permisos || []);
                    } else {
                        cont.innerHTML = `<div class="p-4 text-center text-rose-500 text-xs">${res.error || 'Error al cargar'}</div>`;
                    }
                })
                .catch(() => {
                    cont.innerHTML = `<div class="p-4 text-center text-rose-500 text-xs">Error de conexión</div>`;
                });
        }

        function toggleNuevaSeccionPerm(mostrarInput) {
            const contSel = document.getElementById('contSelectSeccion');
            const contInp = document.getElementById('contInputNuevaSeccion');
            const btnToggle = document.getElementById('btnToggleNuevaSec');
            const sel = document.getElementById('permFormSeccionSelect');
            const inp = document.getElementById('permFormSeccionInput');

            if (mostrarInput) {
                contSel.classList.add('hidden');
                contInp.classList.remove('hidden');
                btnToggle.classList.add('hidden');
                inp.focus();
            } else {
                contInp.classList.add('hidden');
                contSel.classList.remove('hidden');
                btnToggle.classList.remove('hidden');
                if (sel.value === '__NUEVA__') {
                    sel.value = '';
                }
            }
        }

        function verificarCambioSeccion(val) {
            if (val === '__NUEVA__') {
                toggleNuevaSeccionPerm(true);
            }
        }

        function renderizarPermisosModuloEnModal(permisos) {
            const cont = document.getElementById('gestPermListaContenedor');
            document.getElementById('gestPermTotalContador').textContent = `${permisos.length} solapas / permisos registrados`;

            // Recopilar las secciones existentes de este módulo y sugerencias
            const seccionesSet = new Set();
            permisos.forEach(p => {
                if (p.seccion && p.seccion !== 'Hub' && p.seccion !== 'General') {
                    seccionesSet.add(p.seccion);
                }
            });
            ['Vistas Principales', 'Informes', 'Ingresos', 'Egresos', 'Proveedores', 'Comercio Exterior', 'Edición', 'Configuración', 'General'].forEach(s => seccionesSet.add(s));

            const selSec = document.getElementById('permFormSeccionSelect');
            if (selSec) {
                let secHtml = '<option value="">-- Seleccionar Sección --</option>';
                seccionesSet.forEach(s => {
                    secHtml += `<option value="${escapeJsStr(s)}">${s}</option>`;
                });
                secHtml += '<option value="__NUEVA__">➕ + Escribir Nueva Sección...</option>';
                selSec.innerHTML = secHtml;
                if (seccionesSet.size > 0) {
                    selSec.value = Array.from(seccionesSet)[0];
                }
            }
            toggleNuevaSeccionPerm(false);

            if (permisos.length === 0) {
                cont.innerHTML = `
                    <div class="p-6 text-center text-slate-400 bg-slate-50 rounded-xl border border-slate-200 text-xs">
                        <i class="fas fa-layer-group text-slate-300 text-2xl mb-2 block"></i>
                        No hay solapas o permisos registrados aún para este módulo. Podés agregarlos usando el formulario superior.
                    </div>
                `;
                return;
            }

            let html = '';
            permisos.forEach(p => {
                const esRaiz = p.es_raiz_hub;
                html += `
                    <div class="flex items-center justify-between p-3 rounded-xl border ${esRaiz ? 'bg-indigo-50/40 border-indigo-200' : 'bg-white border-slate-200'} hover:border-indigo-300 transition gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold ${esRaiz ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-700 border border-slate-200'}">
                                    [${p.seccion || 'General'}]
                                </span>
                                <span class="text-xs font-bold text-slate-900">${p.nombre}</span>
                                <span class="text-[10px] font-mono text-indigo-700 bg-indigo-50 border border-indigo-100 px-1.5 py-0.2 rounded font-semibold">${p.clave}</span>
                                ${esRaiz ? '<span class="text-[10px] bg-indigo-100 text-indigo-800 font-bold px-1.5 py-0.2 rounded">Acceso Principal Hub</span>' : ''}
                            </div>
                            ${p.descripcion_limpia ? `<p class="text-[11px] text-slate-500 mt-0.5 line-clamp-1">${p.descripcion_limpia}</p>` : ''}
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <span class="text-[10px] text-slate-500 bg-slate-50 border border-slate-200 px-2 py-0.5 rounded font-medium">
                                <i class="fas fa-users text-indigo-500 mr-1"></i> ${p.cant_roles_con_permiso || 0} roles
                            </span>
                            <button type="button" onclick="abrirEdicionPermiso(${p.id}, '${escapeJsStr(p.clave)}', '${escapeJsStr(p.nombre)}', '${escapeJsStr(p.seccion)}', '${escapeJsStr(p.descripcion_limpia)}')" 
                                    class="p-1.5 text-slate-500 hover:text-indigo-600 hover:bg-slate-100 rounded-lg transition" title="Editar Solapa">
                                <i class="fas fa-edit text-xs"></i>
                            </button>
                            ${!esRaiz ? `
                                <button type="button" onclick="eliminarPermisoModuloAjax(${p.id})" 
                                        class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition" title="Eliminar Solapa">
                                    <i class="fas fa-trash-alt text-xs"></i>
                                </button>
                            ` : ''}
                        </div>
                    </div>
                `;
            });

            cont.innerHTML = html;
        }

        function escapeJsStr(str) {
            if (!str) return '';
            return str.replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '&quot;');
        }

        function abrirEdicionPermiso(id, clave, nombre, seccion, desc) {
            document.getElementById('permFormPermisoId').value = id;
            document.getElementById('permFormClave').value = clave;
            document.getElementById('permFormNombre').value = nombre;
            document.getElementById('permFormDesc').value = desc || '';

            const sec = seccion || 'General';
            const selSec = document.getElementById('permFormSeccionSelect');
            let existe = false;
            for (let i = 0; i < selSec.options.length; i++) {
                if (selSec.options[i].value === sec) {
                    existe = true;
                    break;
                }
            }
            if (existe) {
                toggleNuevaSeccionPerm(false);
                selSec.value = sec;
                document.getElementById('permFormSeccionInput').value = '';
            } else {
                toggleNuevaSeccionPerm(true);
                selSec.value = '__NUEVA__';
                document.getElementById('permFormSeccionInput').value = sec;
            }

            document.getElementById('formPermisoTitulo').innerHTML = '<i class="fas fa-edit text-indigo-600"></i> Editando Solapa / Permiso';
            document.getElementById('lblBtnGuardarPermiso').textContent = 'Guardar Cambios';
            document.getElementById('btnCancelarEdicionPermiso').classList.remove('hidden');
        }

        function resetFormPermiso() {
            document.getElementById('permFormPermisoId').value = '0';
            document.getElementById('permFormClave').value = '';
            document.getElementById('permFormNombre').value = '';
            document.getElementById('permFormDesc').value = '';

            toggleNuevaSeccionPerm(false);
            const selSec = document.getElementById('permFormSeccionSelect');
            if (selSec && selSec.options.length > 1) {
                selSec.value = selSec.options[1].value;
            }
            document.getElementById('permFormSeccionInput').value = '';

            document.getElementById('formPermisoTitulo').innerHTML = '<i class="fas fa-plus-circle text-indigo-600"></i> Agregar Nueva Solapa / Permiso Interno';
            document.getElementById('lblBtnGuardarPermiso').textContent = 'Guardar';
            document.getElementById('btnCancelarEdicionPermiso').classList.add('hidden');
        }

        function guardarPermisoModuloAjax(e) {
            e.preventDefault();
            const moduloId = document.getElementById('permFormModuloId').value;
            const permisoId = document.getElementById('permFormPermisoId').value;
            const clave = document.getElementById('permFormClave').value.trim();
            const nombre = document.getElementById('permFormNombre').value.trim();
            const desc = document.getElementById('permFormDesc').value.trim();

            const contInp = document.getElementById('contInputNuevaSeccion');
            const esNueva = !contInp.classList.contains('hidden');
            let seccion = '';
            if (esNueva) {
                seccion = document.getElementById('permFormSeccionInput').value.trim();
            } else {
                seccion = document.getElementById('permFormSeccionSelect').value;
            }

            if (!seccion || seccion === '__NUEVA__') {
                showToast('Por favor seleccioná o escribí la sección o grupo', 'error');
                return;
            }

            if (!clave || !nombre) {
                showToast('Completá la clave y el nombre de la solapa', 'error');
                return;
            }

            const btn = document.getElementById('btnGuardarPermisoAjax');
            btn.disabled = true;

            const isEdit = (permisoId && permisoId !== '0');
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', isEdit ? 'actualizar_permiso_modulo' : 'crear_permiso_modulo');
            formData.append('modulo_id', moduloId);
            if (isEdit) formData.append('permiso_id', permisoId);
            formData.append('clave', clave);
            formData.append('nombre', nombre);
            formData.append('seccion', seccion);
            formData.append('descripcion', desc);

            fetch('', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    btn.disabled = false;
                    if (res.ok) {
                        showToast(res.mensaje || 'Solapa guardada correctamente');
                        resetFormPermiso();
                        renderizarPermisosModuloEnModal(res.permisos || []);
                    } else {
                        showToast(res.error || 'Error al guardar solapa', 'error');
                    }
                })
                .catch(() => {
                    btn.disabled = false;
                    showToast('Error de conexión', 'error');
                });
        }

        function eliminarPermisoModuloAjax(permisoId) {
            if (!confirm('¿Estás seguro de que deseás eliminar esta solapa / permiso? Se revocarán los accesos asignados.')) {
                return;
            }

            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'eliminar_permiso_modulo');
            formData.append('modulo_id', currentPermModuloId);
            formData.append('permiso_id', permisoId);

            fetch('', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (res.ok) {
                        showToast(res.mensaje || 'Solapa eliminada correctamente');
                        renderizarPermisosModuloEnModal(res.permisos || []);
                    } else {
                        showToast(res.error || 'Error al eliminar solapa', 'error');
                    }
                })
                .catch(() => {
                    showToast('Error de conexión al eliminar solapa', 'error');
                });
        }

        // ==================== JS GESTIÓN DE EQUIPOS DE TRABAJO ====================
        let currentEquipoVerId = null;

        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function filtrarEquiposEnVivo(val) {
            const q = val.toLowerCase().trim();
            const items = document.querySelectorAll('.item-equipo');
            let visibles = 0;
            items.forEach(el => {
                const search = el.getAttribute('data-search') || '';
                const match = (!q || search.includes(q));
                el.classList.toggle('hidden', !match);
                if (match) visibles++;
            });
        }

        function filtrarSubsectoresEquipo(sectorId, seleccionado = '') {
            const selSub = document.getElementById('formEquipoSubsectorId');
            if (!selSub) return;
            const options = selSub.querySelectorAll('option');
            options.forEach(opt => {
                if (!opt.value) {
                    opt.hidden = false;
                    return;
                }
                const sec = opt.getAttribute('data-sector');
                const match = (!sectorId || sec === String(sectorId));
                opt.hidden = !match;
            });
            if (seleccionado) {
                selSub.value = seleccionado;
            } else {
                selSub.value = '';
            }
        }

        function actualizarLiderEquipo(sel) {
            const opt = sel.options[sel.selectedIndex];
            const userId = opt ? (opt.getAttribute('data-userid') || '') : '';
            document.getElementById('formEquipoLiderUserId').value = userId;
        }

        function actualizarGerenteEquipo(sel) {
            const opt = sel.options[sel.selectedIndex];
            const userId = opt ? (opt.getAttribute('data-userid') || '') : '';
            document.getElementById('formEquipoGerenteUserId').value = userId;
        }

        function abrirModalCrearEquipo() {
            document.getElementById('modalEquipoTitulo').textContent = 'Nuevo Equipo de Trabajo';
            document.getElementById('btnTextoGuardarEquipo').textContent = 'Crear Equipo';
            document.getElementById('formEquipoId').value = '0';
            document.getElementById('formEquipoNombre').value = '';
            document.getElementById('formEquipoSectorId').value = '';
            filtrarSubsectoresEquipo('', '');
            document.getElementById('formEquipoTipoFlujo').value = 'CENTRAL';
            document.getElementById('formEquipoLiderLegajo').value = '';
            document.getElementById('formEquipoLiderUserId').value = '';
            document.getElementById('formEquipoGerenteLegajo').value = '';
            document.getElementById('formEquipoGerenteUserId').value = '';
            document.getElementById('formEquipoDesc').value = '';
            document.getElementById('formEquipoActivo').checked = true;

            const tbody = document.getElementById('tbodyMiembrosEquipo');
            tbody.innerHTML = `
                <tr id="filaSinMiembros">
                    <td colspan="6" class="text-center py-6 text-slate-400 text-xs italic">
                        No hay integrantes en este equipo todavía. Seleccioná arriba para agregarlos.
                    </td>
                </tr>
            `;
            actualizarContadorMiembrosForm();
            abrirModal('modalEquipo');
        }

        function editarEquipo(equipoId) {
            iniciarProgreso();
            fetch('?ajax=1&action=obtener_equipo_ajax&equipo_id=' + equipoId)
                .then(r => r.json())
                .then(res => {
                    finalizarProgreso();
                    if (res.ok && res.equipo) {
                        const eq = res.equipo;
                        document.getElementById('modalEquipoTitulo').textContent = 'Editar Equipo: ' + eq.nombre;
                        document.getElementById('btnTextoGuardarEquipo').textContent = 'Guardar Cambios';
                        document.getElementById('formEquipoId').value = eq.id;
                        document.getElementById('formEquipoNombre').value = eq.nombre;
                        document.getElementById('formEquipoSectorId').value = eq.sector_id;
                        filtrarSubsectoresEquipo(eq.sector_id, eq.subsector_id || '');
                        document.getElementById('formEquipoTipoFlujo').value = eq.tipo_flujo || 'CENTRAL';
                        
                        document.getElementById('formEquipoLiderLegajo').value = eq.lider_legajo || '';
                        document.getElementById('formEquipoLiderUserId').value = eq.lider_usuario_id || '';
                        
                        document.getElementById('formEquipoGerenteLegajo').value = eq.gerente_legajo || '';
                        document.getElementById('formEquipoGerenteUserId').value = eq.gerente_usuario_id || '';
                        
                        document.getElementById('formEquipoDesc').value = eq.descripcion || '';
                        document.getElementById('formEquipoActivo').checked = (eq.activo == 1);

                        // Miembros
                        const tbody = document.getElementById('tbodyMiembrosEquipo');
                        tbody.innerHTML = '';
                        if (eq.miembros && eq.miembros.length > 0) {
                            eq.miembros.forEach(m => {
                                agregarFilaMiembroTabla(m.legajo, m.usuario_id, m.nombre_completo || m.username, m.rol_nombre || m.subsector_nombre || 'Colaborador', m.rol_en_equipo || 'MIEMBRO');
                            });
                        } else {
                            tbody.innerHTML = `
                                <tr id="filaSinMiembros">
                                    <td colspan="5" class="text-center py-6 text-slate-400 text-xs italic">
                                        No hay integrantes en este equipo todavía. Seleccioná arriba para agregarlos.
                                    </td>
                                </tr>
                            `;
                        }
                        actualizarContadorMiembrosForm();
                        abrirModal('modalEquipo');
                    } else {
                        showToast(res.error || 'Error al obtener equipo', 'error');
                    }
                })
                .catch(() => {
                    finalizarProgreso();
                    showToast('Error de conexión al cargar equipo', 'error');
                });
        }

        function agregarMiembroDesdeSelector() {
            const sel = document.getElementById('selectNuevoMiembro');
            const legajo = sel.value;
            if (!legajo) {
                showToast('Seleccioná un colaborador para agregar al equipo', 'error');
                return;
            }

            // Validar que no esté ya agregado
            const existentes = document.querySelectorAll(`input[name="miembros_legajos[]"][value="${legajo}"]`);
            if (existentes.length > 0) {
                showToast('Este colaborador ya forma parte del equipo', 'error');
                return;
            }

            const opt = sel.options[sel.selectedIndex];
            const userId = opt.getAttribute('data-userid') || '';
            const nombre = opt.getAttribute('data-nombre') || opt.text;
            const rol = opt.getAttribute('data-rol') || opt.getAttribute('data-sector') || 'Colaborador';
            const rolEnEquipo = document.getElementById('selectRolNuevoMiembro').value;

            // Remover fila sin miembros si está presente
            const filaVacia = document.getElementById('filaSinMiembros');
            if (filaVacia) filaVacia.remove();

            agregarFilaMiembroTabla(legajo, userId, nombre, rol, rolEnEquipo, false);
            actualizarContadorMiembrosForm();
            sel.value = '';
            showToast('Colaborador agregado a la lista del equipo');
        }

        function agregarFilaMiembroTabla(legajo, userId, nombre, puestoRol, rolEnEquipo) {
            const tbody = document.getElementById('tbodyMiembrosEquipo');
            const filaVacia = document.getElementById('filaSinMiembros');
            if (filaVacia) filaVacia.remove();

            const tr = document.createElement('tr');
            tr.className = 'hover:bg-slate-50 transition border-b border-slate-100 fila-miembro-form';
            tr.innerHTML = `
                <td class="py-2.5 px-3 font-mono font-bold text-slate-700">
                    #${legajo}
                    <input type="hidden" name="miembros_legajos[]" value="${legajo}">
                    <input type="hidden" name="miembros_user_ids[]" value="${userId}">
                </td>
                <td class="py-2.5 px-3">
                    <div class="font-semibold text-slate-900">${escapeHtml(nombre)}</div>
                </td>
                <td class="py-2.5 px-3 text-slate-500 text-[11px]">
                    ${escapeHtml(puestoRol)}
                </td>
                <td class="py-2.5 px-3">
                    <select name="miembros_roles[]" class="bg-white border border-slate-200 rounded-lg px-2 py-1 text-xs text-slate-800 font-medium">
                        <option value="MIEMBRO" ${rolEnEquipo === 'MIEMBRO' ? 'selected' : ''}>Miembro</option>
                        <option value="LIDER" ${rolEnEquipo === 'LIDER' ? 'selected' : ''}>Líder</option>
                        <option value="SUPLENTE" ${rolEnEquipo === 'SUPLENTE' ? 'selected' : ''}>Suplente</option>
                    </select>
                </td>
                <td class="py-2.5 px-3 text-right">
                    <button type="button" onclick="eliminarFilaMiembro(this)" class="p-1 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition" title="Quitar del equipo">
                        <i class="fas fa-times text-xs"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        }

        function toggleCriticoMiembro(chk) {
            const hidden = chk.parentElement.querySelector('input[type="hidden"]');
            if (hidden) {
                hidden.value = chk.checked ? 1 : 0;
            }
        }

        function eliminarFilaMiembro(btn) {
            const tr = btn.closest('tr');
            if (tr) {
                tr.remove();
                const tbody = document.getElementById('tbodyMiembrosEquipo');
                if (tbody.querySelectorAll('.fila-miembro-form').length === 0) {
                    tbody.innerHTML = `
                        <tr id="filaSinMiembros">
                            <td colspan="6" class="text-center py-6 text-slate-400 text-xs italic">
                                No hay integrantes en este equipo todavía. Seleccioná arriba para agregarlos.
                            </td>
                        </tr>
                    `;
                }
                actualizarContadorMiembrosForm();
            }
        }

        function actualizarContadorMiembrosForm() {
            const cant = document.querySelectorAll('.fila-miembro-form').length;
            const badge = document.getElementById('contadorMiembrosForm');
            if (badge) {
                badge.textContent = `${cant} integrante${cant === 1 ? '' : 's'}`;
            }
        }

        function verMiembrosEquipo(id, nombre) {
            currentEquipoVerId = id;
            document.getElementById('verMiembrosNombreEquipo').textContent = nombre;
            document.getElementById('btnEditarEquipoDesdeVer').onclick = () => {
                cerrarModal('modalMiembrosEquipo');
                editarEquipo(id);
            };

            const cont = document.getElementById('verMiembrosListaContenedor');
            cont.innerHTML = `
                <div class="py-12 text-center text-slate-400">
                    <i class="fas fa-spinner fa-spin text-2xl text-indigo-500 mb-2 block"></i>
                    Cargando integrantes...
                </div>
            `;
            abrirModal('modalMiembrosEquipo');

            fetch('?ajax=1&action=obtener_equipo_ajax&equipo_id=' + id)
                .then(r => r.json())
                .then(res => {
                    if (res.ok && res.equipo) {
                        const eq = res.equipo;
                        document.getElementById('verMiembrosDetalleEquipo').textContent = `${eq.sector_nombre || 'Sector'} · ${eq.miembros ? eq.miembros.length : 0} integrantes · Flujo ${eq.tipo_flujo || 'CENTRAL'}`;
                        
                        let html = '';
                        // Card Líder y Gerente
                        html += `
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                                <div class="p-3 bg-blue-50/60 rounded-xl border border-blue-200/70 flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-lg bg-blue-600 text-white flex items-center justify-center font-bold text-sm shadow-xs">
                                        <i class="fas fa-user-tie"></i>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="text-[10px] font-bold text-blue-700 uppercase tracking-wider">Líder / Jefe de Equipo</div>
                                        <div class="font-bold text-slate-900 text-xs truncate">${escapeHtml(eq.lider_nombre || 'Sin Líder Asignado')}</div>
                                        <div class="text-[10px] text-slate-500">${eq.lider_legajo ? 'Legajo #' + eq.lider_legajo : ''}</div>
                                    </div>
                                </div>
                                <div class="p-3 bg-purple-50/60 rounded-xl border border-purple-200/70 flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-lg bg-purple-600 text-white flex items-center justify-center font-bold text-sm shadow-xs">
                                        <i class="fas fa-shield-halved"></i>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="text-[10px] font-bold text-purple-700 uppercase tracking-wider">Gerente Validador</div>
                                        <div class="font-bold text-slate-900 text-xs truncate">${escapeHtml(eq.gerente_nombre || 'Sin Gerente Asignado')}</div>
                                        <div class="text-[10px] text-slate-500">${eq.gerente_legajo ? 'Legajo #' + eq.gerente_legajo : ''}</div>
                                    </div>
                                </div>
                            </div>
                        `;

                        // Lista de miembros
                        if (eq.miembros && eq.miembros.length > 0) {
                            html += '<div class="space-y-2">';
                            eq.miembros.forEach(m => {
                                const esLider = (m.rol_en_equipo === 'LIDER');
                                html += `
                                    <div class="flex items-center justify-between p-3 rounded-xl border ${esLider ? 'bg-indigo-50/40 border-indigo-200' : 'bg-white border-slate-200'} hover:border-slate-300 transition">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full ${esLider ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600'} flex items-center justify-center text-xs font-bold shrink-0">
                                                ${esLider ? '<i class="fas fa-star"></i>' : (m.nombre_completo ? m.nombre_completo.charAt(0) : '#')}
                                            </div>
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <span class="font-bold text-slate-900 text-xs">${escapeHtml(m.nombre_completo || m.username)}</span>
                                                    <span class="font-mono text-[10px] bg-slate-100 text-slate-700 font-semibold px-1.5 py-0.2 rounded border">#${m.legajo}</span>
                                                    ${esLider ? '<span class="text-[10px] bg-indigo-100 text-indigo-700 font-bold px-2 py-0.2 rounded-full border border-indigo-200">LÍDER</span>' : ''}
                                                </div>
                                                <div class="text-[11px] text-slate-500 mt-0.5">
                                                    ${escapeHtml(m.rol_nombre || m.subsector_nombre || 'Colaborador')} ${m.email ? '· ' + escapeHtml(m.email) : ''}
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-[11px] font-semibold text-slate-600">
                                            Rol: <span class="text-indigo-600 font-bold">${escapeHtml(m.rol_en_equipo || 'MIEMBRO')}</span>
                                        </div>
                                    </div>
                                `;
                            });
                            html += '</div>';
                        } else {
                            html += `
                                <div class="text-center py-8 text-slate-400 text-xs">
                                    <i class="fas fa-users-slash text-2xl text-slate-300 mb-2 block"></i>
                                    Este equipo no tiene integrantes asignados aún.
                                </div>
                            `;
                        }
                        cont.innerHTML = html;
                    } else {
                        cont.innerHTML = `<div class="p-4 text-center text-rose-500 text-xs">${res.error || 'Error al cargar miembros'}</div>`;
                    }
                })
                .catch(() => {
                    cont.innerHTML = `<div class="p-4 text-center text-rose-500 text-xs">Error de conexión al cargar miembros</div>`;
                });
        }
    </script>
</body>
</html>