<?php
namespace GestionUsuarios\Models;

use GestionUsuarios\Config\Database;
use Exception;

class Estructura {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->connectApps();
    }

    // ==================== SECTORES ====================
    public function listarSectores() {
        $sql = "SELECT s.id, s.codigo, s.nombre, s.descripcion, s.activo,
                       (SELECT COUNT(*) FROM FP_SUBSECTORES sub WHERE sub.sector_id = s.id) as cant_subsectores,
                       (SELECT COUNT(*) FROM FP_SOF_USUARIOS u WHERE u.sector_id = s.id) as cant_usuarios
                FROM FP_SECTORES s ORDER BY s.nombre ASC";
        $stmt = sqlsrv_query($this->db, $sql);
        $res = [];
        if ($stmt) {
            while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $res[] = $r;
            }
        }
        return $res;
    }

    public function crearSector($codigo, $nombre, $descripcion = '') {
        $sql = "INSERT INTO FP_SECTORES (codigo, nombre, descripcion, activo) VALUES (?, ?, ?, 1)";
        return sqlsrv_query($this->db, $sql, [strtoupper(trim($codigo)), trim($nombre), trim($descripcion)]);
    }

    public function actualizarSector($id, $codigo, $nombre, $descripcion = '', $activo = 1) {
        $sql = "UPDATE FP_SECTORES SET codigo = ?, nombre = ?, descripcion = ?, activo = ? WHERE id = ?";
        return sqlsrv_query($this->db, $sql, [strtoupper(trim($codigo)), trim($nombre), trim($descripcion), (int)$activo, (int)$id]);
    }

    public function eliminarSector($id) {
        // Verificar si tiene subsectores o usuarios
        $sqlCheck = "SELECT (SELECT COUNT(*) FROM FP_SUBSECTORES WHERE sector_id = ?) as sub,
                            (SELECT COUNT(*) FROM FP_SOF_USUARIOS WHERE sector_id = ?) as usr";
        $stmt = sqlsrv_query($this->db, $sqlCheck, [(int)$id, (int)$id]);
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if (($row['sub'] ?? 0) > 0 || ($row['usr'] ?? 0) > 0) {
            return false;
        }
        return sqlsrv_query($this->db, "DELETE FROM FP_SECTORES WHERE id = ?", [(int)$id]);
    }

    // ==================== SUBSECTORES ====================
    public function listarSubsectores($sectorId = null) {
        $sql = "SELECT sub.id, sub.sector_id, s.nombre as sector_nombre, s.codigo as sector_codigo,
                       sub.codigo, sub.nombre, sub.descripcion, sub.activo,
                       (SELECT COUNT(*) FROM FP_ROLES_PERMISOS_MAP r WHERE r.subsector_id = sub.id) as cant_roles,
                       (SELECT COUNT(*) FROM FP_SOF_USUARIOS u WHERE u.subsector_id = sub.id) as cant_usuarios
                FROM FP_SUBSECTORES sub
                JOIN FP_SECTORES s ON s.id = sub.sector_id";
        $params = [];
        if ($sectorId) {
            $sql .= " WHERE sub.sector_id = ?";
            $params[] = (int)$sectorId;
        }
        $sql .= " ORDER BY s.nombre ASC, sub.nombre ASC";
        $stmt = sqlsrv_query($this->db, $sql, $params);
        $res = [];
        if ($stmt) {
            while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $res[] = $r;
            }
        }
        return $res;
    }

    public function crearSubsector($sectorId, $codigo, $nombre, $descripcion = '') {
        $sql = "INSERT INTO FP_SUBSECTORES (sector_id, codigo, nombre, descripcion, activo) VALUES (?, ?, ?, ?, 1)";
        return sqlsrv_query($this->db, $sql, [(int)$sectorId, strtoupper(trim($codigo)), trim($nombre), trim($descripcion)]);
    }

    public function actualizarSubsector($id, $sectorId, $codigo, $nombre, $descripcion = '', $activo = 1) {
        $sql = "UPDATE FP_SUBSECTORES SET sector_id = ?, codigo = ?, nombre = ?, descripcion = ?, activo = ? WHERE id = ?";
        return sqlsrv_query($this->db, $sql, [(int)$sectorId, strtoupper(trim($codigo)), trim($nombre), trim($descripcion), (int)$activo, (int)$id]);
    }

    public function eliminarSubsector($id) {
        return sqlsrv_query($this->db, "DELETE FROM FP_SUBSECTORES WHERE id = ?", [(int)$id]);
    }

    // ==================== ROLES & PERMISOS ====================
    public function listarRoles() {
        $sql = "SELECT r.id, r.codigo, r.nombre, r.descripcion, r.es_admin, r.subsector_id,
                       sub.nombre as subsector_nombre, s.nombre as sector_nombre,
                       (SELECT COUNT(*) FROM FP_ROL_PERMISO rp WHERE rp.rol_id = r.id) as cant_permisos,
                       (SELECT COUNT(*) FROM FP_SOF_USUARIOS u WHERE u.rol_id = r.id) as cant_usuarios
                FROM FP_ROLES_PERMISOS_MAP r
                LEFT JOIN FP_SUBSECTORES sub ON sub.id = r.subsector_id
                LEFT JOIN FP_SECTORES s ON s.id = sub.sector_id
                ORDER BY r.nombre ASC";
        $stmt = sqlsrv_query($this->db, $sql);
        $res = [];
        if ($stmt) {
            while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $res[] = $r;
            }
        }
        return $res;
    }

    public function crearRol($subsectorId, $codigo, $nombre, $descripcion = '', $esAdmin = 0) {
        $subId = !empty($subsectorId) ? (int)$subsectorId : null;
        $sql = "INSERT INTO FP_ROLES_PERMISOS_MAP (subsector_id, codigo, nombre, descripcion, es_admin, activo) VALUES (?, ?, ?, ?, ?, 1)";
        return sqlsrv_query($this->db, $sql, [$subId, strtoupper(trim($codigo)), trim($nombre), trim($descripcion), (int)$esAdmin]);
    }

    public function actualizarRol($id, $subsectorId, $codigo, $nombre, $descripcion = '', $esAdmin = 0) {
        $subId = !empty($subsectorId) ? (int)$subsectorId : null;
        $sql = "UPDATE FP_ROLES_PERMISOS_MAP SET subsector_id = ?, codigo = ?, nombre = ?, descripcion = ?, es_admin = ? WHERE id = ?";
        return sqlsrv_query($this->db, $sql, [$subId, strtoupper(trim($codigo)), trim($nombre), trim($descripcion), (int)$esAdmin, (int)$id]);
    }

    public function listarModulosConPermisos() {
        $sqlModulos = "SELECT * FROM FP_MODULOS ORDER BY id ASC";
        $stmt = sqlsrv_query($this->db, $sqlModulos);
        $modulos = [];
        if ($stmt) {
            while ($m = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $mId = $m['id'];
                $sqlPermisos = "SELECT * FROM FP_PERMISOS WHERE modulo_id = ? ORDER BY id ASC";
                $stmtP = sqlsrv_query($this->db, $sqlPermisos, [$mId]);
                $permisos = [];
                $submodulos = [];

                if ($stmtP) {
                    while ($p = sqlsrv_fetch_array($stmtP, SQLSRV_FETCH_ASSOC)) {
                        $desc = $p['descripcion'] ?? '';
                        $subNombre = 'General';
                        if (preg_match('/^\[(.*?)\]\s*(.*)$/', $desc, $matches)) {
                            $subNombre = trim($matches[1]);
                            $p['descripcion'] = trim($matches[2]);
                        }
                        $p['submodulo'] = $subNombre;
                        $permisos[] = $p;
                        $submodulos[$subNombre][] = $p;
                    }
                }
                $m['permisos'] = $permisos;
                $m['submodulos'] = $submodulos;
                $modulos[] = $m;
            }
        }
        return $modulos;
    }

    public function obtenerPermisosRol($rolId) {
        $sql = "SELECT permiso_id FROM FP_ROL_PERMISO WHERE rol_id = ?";
        $stmt = sqlsrv_query($this->db, $sql, [(int)$rolId]);
        $ids = [];
        if ($stmt) {
            while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $ids[] = $r['permiso_id'];
            }
        }
        return $ids;
    }

    public function guardarPermisosRol($rolId, array $permisoIds) {
        sqlsrv_begin_transaction($this->db);
        sqlsrv_query($this->db, "DELETE FROM FP_ROL_PERMISO WHERE rol_id = ?", [(int)$rolId]);
        $sqlIns = "INSERT INTO FP_ROL_PERMISO (rol_id, permiso_id) VALUES (?, ?)";
        foreach ($permisoIds as $pId) {
            sqlsrv_query($this->db, $sqlIns, [(int)$rolId, (int)$pId]);
        }
        sqlsrv_commit($this->db);
        return true;
    }

    // ==================== MATRIZ HUB & APLICACIONES ====================
    public function listarModulosHub() {
        $sql = "SELECT m.id, m.codigo, m.nombre, m.categoria, m.ruta_url, m.icono, m.color, m.descripcion, m.target_blank, m.orden, m.activo,
                       p.id as permiso_id, p.clave as permiso_clave,
                       (SELECT COUNT(*) FROM FP_PERMISOS sp WHERE sp.modulo_id = m.id AND sp.clave NOT LIKE 'hub.app.%') as cant_submodulos
                FROM FP_MODULOS m
                LEFT JOIN FP_PERMISOS p ON p.modulo_id = m.id AND p.clave LIKE 'hub.app.%'
                WHERE m.activo = 1
                ORDER BY m.categoria ASC, m.orden ASC, m.nombre ASC";
        $stmt = sqlsrv_query($this->db, $sql);
        $modulos = [];
        if ($stmt) {
            while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $modulos[] = $r;
            }
        }
        return $modulos;
    }

    public function obtenerMatrizEstructuraCompleta() {
        // Sectores
        $sqlSec = "SELECT s.id, s.codigo, s.nombre, s.descripcion, s.activo,
                          (SELECT COUNT(*) FROM FP_SOF_USUARIOS u WHERE u.sector_id = s.id) as cant_usuarios
                   FROM FP_SECTORES s WHERE s.activo = 1 ORDER BY s.nombre ASC";
        $stmtSec = sqlsrv_query($this->db, $sqlSec);
        $sectores = [];
        if ($stmtSec) {
            while ($s = sqlsrv_fetch_array($stmtSec, SQLSRV_FETCH_ASSOC)) {
                $sId = $s['id'];
                // Subsectores
                $sqlSub = "SELECT sub.id, sub.sector_id, sub.codigo, sub.nombre, sub.descripcion, sub.activo,
                                  (SELECT COUNT(*) FROM FP_SOF_USUARIOS u WHERE u.subsector_id = sub.id) as cant_usuarios
                           FROM FP_SUBSECTORES sub WHERE sub.sector_id = ? AND sub.activo = 1 ORDER BY sub.nombre ASC";
                $stmtSub = sqlsrv_query($this->db, $sqlSub, [$sId]);
                $subsectores = [];
                if ($stmtSub) {
                    while ($sub = sqlsrv_fetch_array($stmtSub, SQLSRV_FETCH_ASSOC)) {
                        $subId = $sub['id'];
                        // Roles
                        $sqlRol = "SELECT r.id, r.subsector_id, r.codigo, r.nombre, r.descripcion, r.es_admin, r.activo,
                                          (SELECT COUNT(*) FROM FP_SOF_USUARIOS u WHERE u.rol_id = r.id) as cant_usuarios
                                   FROM FP_ROLES_PERMISOS_MAP r WHERE r.subsector_id = ? AND r.activo = 1 ORDER BY r.nombre ASC";
                        $stmtRol = sqlsrv_query($this->db, $sqlRol, [$subId]);
                        $roles = [];
                        if ($stmtRol) {
                            while ($rol = sqlsrv_fetch_array($stmtRol, SQLSRV_FETCH_ASSOC)) {
                                $rol['permisos_hub'] = $this->obtenerPermisosHubRol($rol['id']);
                                $roles[] = $rol;
                            }
                        }
                        $sub['roles'] = $roles;
                        $subsectores[] = $sub;
                    }
                }
                $s['subsectores'] = $subsectores;
                $sectores[] = $s;
            }
        }
        return $sectores;
    }

    public function obtenerPermisosHubRol($rolId) {
        $sql = "SELECT p.id, p.clave, m.codigo as modulo_codigo
                FROM FP_ROL_PERMISO rp
                JOIN FP_PERMISOS p ON p.id = rp.permiso_id
                JOIN FP_MODULOS m ON m.id = p.modulo_id
                WHERE rp.rol_id = ? AND p.clave LIKE 'hub.app.%'";
        $stmt = sqlsrv_query($this->db, $sql, [(int)$rolId]);
        $res = [];
        if ($stmt) {
            while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $res[$r['clave']] = $r['id'];
            }
        }
        return $res;
    }

    public function togglePermisoHubRol($rolId, $permisoId, $activo) {
        $rolId = (int)$rolId;
        $permisoId = (int)$permisoId;
        if ($rolId <= 0 || $permisoId <= 0) return false;

        if ($activo) {
            $sqlCheck = "SELECT 1 FROM FP_ROL_PERMISO WHERE rol_id = ? AND permiso_id = ?";
            $stmt = sqlsrv_query($this->db, $sqlCheck, [$rolId, $permisoId]);
            if (!sqlsrv_has_rows($stmt)) {
                return sqlsrv_query($this->db, "INSERT INTO FP_ROL_PERMISO (rol_id, permiso_id) VALUES (?, ?)", [$rolId, $permisoId]);
            }
            return true;
        } else {
            return sqlsrv_query($this->db, "DELETE FROM FP_ROL_PERMISO WHERE rol_id = ? AND permiso_id = ?", [$rolId, $permisoId]);
        }
    }

    public function guardarPermisosHubRol($rolId, array $permisoIds) {
        $rolId = (int)$rolId;
        if ($rolId <= 0) return false;
        sqlsrv_begin_transaction($this->db);
        // Borrar solo los permisos del Hub asignados a este rol
        $sqlDel = "DELETE FROM FP_ROL_PERMISO WHERE rol_id = ? AND permiso_id IN (SELECT id FROM FP_PERMISOS WHERE clave LIKE 'hub.app.%')";
        sqlsrv_query($this->db, $sqlDel, [$rolId]);
        $sqlIns = "INSERT INTO FP_ROL_PERMISO (rol_id, permiso_id) VALUES (?, ?)";
        foreach ($permisoIds as $pId) {
            $pId = (int)$pId;
            if ($pId > 0) {
                sqlsrv_query($this->db, $sqlIns, [$rolId, $pId]);
            }
        }
        sqlsrv_commit($this->db);
        return true;
    }

    public function copiarPermisosHubRol($rolOrigenId, $rolDestinoId) {
        $rolOrigenId = (int)$rolOrigenId;
        $rolDestinoId = (int)$rolDestinoId;
        if ($rolOrigenId <= 0 || $rolDestinoId <= 0) return false;

        $permisosOrigen = $this->obtenerPermisosHubRol($rolOrigenId);
        return $this->guardarPermisosHubRol($rolDestinoId, array_values($permisosOrigen));
    }

    public function aplicarPermisosHubSubsector($subsectorId, array $permisoIds) {
        $subsectorId = (int)$subsectorId;
        if ($subsectorId <= 0) return false;
        
        $sqlRoles = "SELECT id FROM FP_ROLES_PERMISOS_MAP WHERE subsector_id = ? AND activo = 1";
        $stmt = sqlsrv_query($this->db, $sqlRoles, [$subsectorId]);
        if ($stmt) {
            while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $this->guardarPermisosHubRol($r['id'], $permisoIds);
            }
        }
        return true;
    }

    public function obtenerSubmodulosCentralizador($moduloId, $rolId) {
        $moduloId = (int)$moduloId;
        $rolId = (int)$rolId;
        if ($moduloId <= 0) return null;

        // Info del módulo
        $stmtMod = sqlsrv_query($this->db, "SELECT * FROM FP_MODULOS WHERE id = ?", [$moduloId]);
        $modulo = sqlsrv_fetch_array($stmtMod, SQLSRV_FETCH_ASSOC);
        if (!$modulo) return null;

        // Info del rol
        $rolNombre = '';
        if ($rolId > 0) {
            $stmtRol = sqlsrv_query($this->db, "SELECT nombre FROM FP_ROLES_PERMISOS_MAP WHERE id = ?", [$rolId]);
            if ($stmtRol && ($rRol = sqlsrv_fetch_array($stmtRol, SQLSRV_FETCH_ASSOC))) {
                $rolNombre = $rRol['nombre'];
            }
        }

        // Permisos del rol para este módulo
        $permisosRolIds = [];
        if ($rolId > 0) {
            $sqlPermRol = "SELECT rp.permiso_id FROM FP_ROL_PERMISO rp 
                           JOIN FP_PERMISOS p ON p.id = rp.permiso_id 
                           WHERE rp.rol_id = ? AND p.modulo_id = ? AND p.clave NOT LIKE 'hub.app.%'";
            $stmtPR = sqlsrv_query($this->db, $sqlPermRol, [$rolId, $moduloId]);
            if ($stmtPR) {
                while ($rPR = sqlsrv_fetch_array($stmtPR, SQLSRV_FETCH_ASSOC)) {
                    $permisosRolIds[$rPR['permiso_id']] = true;
                }
            }
        }

        // Obtener todos los ítems/permisos de este centralizador
        $sqlItems = "SELECT * FROM FP_PERMISOS WHERE modulo_id = ? AND clave NOT LIKE 'hub.app.%' ORDER BY id ASC";
        $stmtItems = sqlsrv_query($this->db, $sqlItems, [$moduloId]);
        $secciones = [];
        $totalItems = 0;
        $totalActivos = 0;

        // Si el rol no tiene ningún permiso específico configurado para este módulo pero tiene acceso al Hub,
        // por defecto consideramos activos todos los ítems (hasta que el admin personalice)
        $tieneConfiguracionEspecifica = !empty($permisosRolIds);

        if ($stmtItems) {
            while ($p = sqlsrv_fetch_array($stmtItems, SQLSRV_FETCH_ASSOC)) {
                $desc = $p['descripcion'] ?? '';
                $secNombre = 'General';
                $itemDesc = $desc;
                if (preg_match('/^\[(.*?)\]\s*(.*)$/', $desc, $matches)) {
                    $secNombre = trim($matches[1]);
                    $itemDesc = trim($matches[2]);
                }

                $p['seccion'] = $secNombre;
                $p['descripcion_limpia'] = $itemDesc;
                $activo = $tieneConfiguracionEspecifica ? isset($permisosRolIds[$p['id']]) : true;
                $p['activo'] = $activo;

                $secciones[$secNombre][] = $p;
                $totalItems++;
                if ($activo) $totalActivos++;
            }
        }

        return [
            'modulo' => $modulo,
            'rol_id' => $rolId,
            'rol_nombre' => $rolNombre,
            'total_items' => $totalItems,
            'total_activos' => $totalActivos,
            'secciones' => $secciones
        ];
    }

    public function guardarSubmodulosRol($rolId, $moduloId, array $permisoIds) {
        $rolId = (int)$rolId;
        $moduloId = (int)$moduloId;
        if ($rolId <= 0 || $moduloId <= 0) return false;

        sqlsrv_begin_transaction($this->db);
        // Borrar solo los permisos internos de este módulo para este rol (sin tocar hub.app.%)
        $sqlDel = "DELETE FROM FP_ROL_PERMISO 
                   WHERE rol_id = ? AND permiso_id IN (SELECT id FROM FP_PERMISOS WHERE modulo_id = ? AND clave NOT LIKE 'hub.app.%')";
        sqlsrv_query($this->db, $sqlDel, [$rolId, $moduloId]);

        $sqlIns = "INSERT INTO FP_ROL_PERMISO (rol_id, permiso_id) VALUES (?, ?)";
        foreach ($permisoIds as $pId) {
            $pId = (int)$pId;
            if ($pId > 0) {
                sqlsrv_query($this->db, $sqlIns, [$rolId, $pId]);
            }
        }
        sqlsrv_commit($this->db);
        return true;
    }

    public function sembrarSectoresIniciales() {
        // Sectores y subsectores de XL
        $datos = [
            'COMERCIAL' => [
                'nombre' => 'Comercial y Ventas',
                'desc' => 'Gestión de locales propios, franquicias, mayoristas y canales de venta',
                'subsectores' => [
                    ['LOCALES_PROPIOS', 'Locales Propios', 'Gestión de sucursales directas'],
                    ['FRANQUICIAS', 'Franquicias', 'Gestión de locales y grupos franquiciados'],
                    ['MAYORISTAS', 'Mayoristas', 'Cuentas mayoristas y distribuidores'],
                    ['SUPERVISION', 'Supervisión Comercial', 'Supervisión regional y auditoría de tiendas']
                ]
            ],
            'ADMINISTRACION' => [
                'nombre' => 'Administración y Finanzas',
                'desc' => 'Tesorería, cobros, pagos, bancos y contabilidad',
                'subsectores' => [
                    ['TESORERIA', 'Tesorería', 'Egresos, cajas y cheques'],
                    ['COBRANZAS', 'Cobranzas', 'Cuentas corrientes y seguimiento de cobros'],
                    ['PROVEEDORES', 'Cuentas a Pagar', 'Pagos a proveedores y compras'],
                    ['CONTABILIDAD', 'Contabilidad e Impuestos', 'Libros contables e impositivos']
                ]
            ],
            'LOGISTICA' => [
                'nombre' => 'Logística y Abastecimiento',
                'desc' => 'Depósito central, inventarios, abastecimiento y distribución',
                'subsectores' => [
                    ['DEPOSITO_CENTRAL', 'Depósito Central', 'Recepción, picking y despacho'],
                    ['ABASTECIMIENTO', 'Abastecimiento', 'Planificación de compras y reposición'],
                    ['CONTROL_STOCK', 'Control de Stock', 'Conteos físicos, fallas y ajustes']
                ]
            ],
            'ECOMMERCE' => [
                'nombre' => 'E-Commerce y Digital',
                'desc' => 'Canales digitales, plataformas online y atención digital',
                'subsectores' => [
                    ['TIENDA_ONLINE', 'Tienda Online', 'Operaciones web y pedidos online'],
                    ['MARKETPLACES', 'Marketplaces', 'MercadoLibre y otros canales']
                ]
            ],
            'RRHH' => [
                'nombre' => 'Recursos Humanos',
                'desc' => 'Personal, legajos, control de horarios y sueldos',
                'subsectores' => [
                    ['PERSONAL', 'Administración de Personal', 'Legajos y asistencias'],
                    ['SUELDOS', 'Liquidación de Sueldos', 'Nóminas y anticipos']
                ]
            ],
            'SISTEMAS' => [
                'nombre' => 'Sistemas e IT',
                'desc' => 'Infraestructura, desarrollo, soporte y seguridad',
                'subsectores' => [
                    ['DESARROLLO', 'Desarrollo de Software', 'Aplicaciones web y backend'],
                    ['INFRAESTRUCTURA', 'Soporte e Infraestructura', 'Redes, servidores y puestos']
                ]
            ]
        ];

        foreach ($datos as $secCodigo => $info) {
            $sql = "IF NOT EXISTS (SELECT 1 FROM FP_SECTORES WHERE codigo = ?)
                    INSERT INTO FP_SECTORES (codigo, nombre, descripcion, activo) VALUES (?, ?, ?, 1)";
            sqlsrv_query($this->db, $sql, [$secCodigo, $secCodigo, $info['nombre'], $info['desc']]);

            $stmt = sqlsrv_query($this->db, "SELECT id FROM FP_SECTORES WHERE codigo = ?", [$secCodigo]);
            $secRow = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            $sectorId = $secRow['id'] ?? null;

            if ($sectorId) {
                foreach ($info['subsectores'] as $sub) {
                    $sqlSub = "IF NOT EXISTS (SELECT 1 FROM FP_SUBSECTORES WHERE sector_id = ? AND codigo = ?)
                               INSERT INTO FP_SUBSECTORES (sector_id, codigo, nombre, descripcion, activo) VALUES (?, ?, ?, ?, 1)";
                    sqlsrv_query($this->db, $sqlSub, [$sectorId, $sub[0], $sectorId, $sub[0], $sub[1], $sub[2]]);
                }
            }
        }

        $this->sembrarPermisosNodo();
    }

    public function sembrarPermisosNodo() {
        $permisosNodo = [
            ['tiempo.ver', 'Acceso a Planificación de Vacaciones', '[Planificación de Vacaciones] Permite visualizar el calendario general de vacaciones y estado de saldos'],
            ['vacaciones.solicitar', 'Solicitar Vacaciones de Personal', '[Planificación de Vacaciones] Permite cargar y planificar tramos de vacaciones para el personal a cargo'],
            ['vacaciones.modificar', 'Solicitar Modificación / Reprogramación', '[Planificación de Vacaciones] Permite solicitar cancelaciones, cambios de fechas y resolver rechazos'],
            ['tiempo.aprobar', 'Aprobar Solicitudes Comerciales (Etapa 2)', '[Planificación de Vacaciones] Aprobación formal de tramos de vacaciones para Supervisión / Dirección'],
            ['vacaciones.cobertura_ver', 'Matriz de Cobertura y Dotación Mínima', '[Planificación de Vacaciones] Acceso a la solapa de matriz de cobertura, cupos y dotación por local'],
            ['vacaciones.propio_ver', 'Ver Mis Propias Vacaciones', '[Planificación de Vacaciones] Consulta de saldo de vacaciones y períodos propios del empleado'],
            ['vacaciones.configurar', 'Configuración General de Vacaciones', '[Planificación de Vacaciones] Parámetros anuales, reglas de cobertura y carga de días de años anteriores'],

            ['tiempo.ausencias_ver', 'Acceso y Consulta de Ausencias', '[Gestión de Ausencias] Visualización de inasistencias repentinas y programadas del personal'],
            ['tiempo.ausencias_cargar', 'Cargar / Solicitar Ausencias', '[Gestión de Ausencias] Carga de ausencias repentinas del día y solicitud de ausencias programadas con certificado'],
            ['tiempo.ausencias_aprobar', 'Aprobación Comercial de Ausencias', '[Gestión de Ausencias] 1ª Validación comercial de ausencias para gestión de cobertura y reemplazos'],
            ['tiempo.ausencias_rrhh', 'Validación Médica y Dictamen RRHH', '[Gestión de Ausencias] Revisión de certificados médicos, justificación y dictamen de descuento'],

            ['tiempo.permisos_ver', 'Acceso y Consulta de Permisos', '[Gestión de Permisos] Visualización del registro de permisos de locales comerciales y turnos'],
            ['tiempo.permisos_solicitar', 'Solicitar / Cargar Permisos', '[Gestión de Permisos] Solicitud de permisos programados y de urgencia con esquema de compensación'],
            ['tiempo.permisos_validar_local', '1ª Validación de Permisos (Encargadas)', '[Gestión de Permisos] Validación inicial del local y verificación de compensación de horas'],
            ['tiempo.permisos_autorizar_comercial', '2ª Autorización Final Comercial / RRHH', '[Gestión de Permisos] Autorización definitiva por Comercial/Supervisoras y control de plazos'],

            ['tiempo.planificacion_ver', 'Planificación Horaria y Turnos', '[Gestión de Tiempo y Turnos] Visualización y gestión de grillas horarias semanales y mensuales'],
            ['tiempo.planificacion_editar', 'Editar Planificación Horaria', '[Gestión de Tiempo y Turnos] Crear, modificar y guardar asignaciones de turnos'],
            ['tiempo.distribucion_ver', 'Distribución de Horas y Curvas', '[Gestión de Tiempo y Turnos] Análisis de cobertura horaria y curvas de dotación'],
            ['tiempo.fichadas_ver', 'Control de Fichadas y Asistencia', '[Gestión de Tiempo y Turnos] Consulta de marcas de reloj y fichadas biométricas'],
            ['tiempo.fichadas_sincronizar', 'Sincronizar Relojes Biométricos', '[Gestión de Tiempo y Turnos] Sincronización manual de registros ZKTeco'],
            ['homeoffice.fichar', 'Fichada Home Office con Foto', '[Gestión de Tiempo y Turnos] Registro seguro de ingreso y egreso remoto con verificación fotográfica'],

            ['personas.ver', 'Fichas de Personal y Legajos', '[Personas y Estructura] Directorio general de colaboradores, legajo 360° y datos de contacto'],
            ['estructura.ver', 'Organigrama y Jerarquías', '[Personas y Estructura] Visualización de la estructura organizacional, sectores, áreas y locales'],
            ['puestos.ver', 'Catálogo de Puestos', '[Personas y Estructura] Consulta de descriptivos de puestos y perfiles de rol'],

            ['liquidacion.ver', 'Consulta de Liquidación y Novedades', '[Liquidación y Novedades] Acceso al módulo de novedades, comisiones y anticipos salariales'],
            ['liquidacion.pvc_ver', 'Plan de Ventas y Comisiones (PVC)', '[Liquidación y Novedades] Control y cálculo del plan de ventas y comisiones'],
            ['liquidacion.anticipos_ver', 'Anticipos de Sueldo', '[Liquidación y Novedades] Consulta y gestión de anticipos salariales'],
            ['liquidacion.cerrar', 'Cierre de Período y Exportación', '[Liquidación y Novedades] Cierre mensual de novedades y generación de asiento contable'],

            ['analisis.ver', 'Métricas e Indicadores de Gestión', '[Análisis e Indicadores] Reportes y tableros analíticos de capital humano y dotación'],
            ['analisis.costo_ver', 'Costo de Personal', '[Análisis e Indicadores] Reporte analítico de costos laborales y masa salarial'],
            ['analisis.ausentismo_ver', 'Índice de Ausentismo', '[Análisis e Indicadores] Tasa de ausentismo, motivos frecuentes y estadísticas comparativas'],

            ['config.ver', 'Ver Configuración NODO', '[Configuración NODO] Consulta de parámetros y tablas maestras'],
            ['config.editar', 'Gestión de Motivos y Reglas', '[Configuración NODO] Alta y edición de motivos estandarizados y reglas de negocio'],

            ['hub.app.nodo', 'Acceso a NODO en Hub', '[Hub] Plataforma centralizada de gestión de capital humano, control de tiempo, licencias y asistencias.']
        ];

        foreach ($permisosNodo as $p) {
            $sql = "IF EXISTS (SELECT 1 FROM FP_PERMISOS WHERE modulo_id = 1 AND clave = ?)
                    BEGIN
                        UPDATE FP_PERMISOS SET nombre = ?, descripcion = ? WHERE modulo_id = 1 AND clave = ?
                    END
                    ELSE
                    BEGIN
                        INSERT INTO FP_PERMISOS (modulo_id, clave, nombre, descripcion) VALUES (1, ?, ?, ?)
                    END";
            sqlsrv_query($this->db, $sql, [
                $p[0], $p[1], $p[2], $p[0],
                $p[0], $p[1], $p[2]
            ]);
        }
    }

    // ==================== GESTIÓN DE MÓDULOS & APPS (CRUD) ====================

    public function listarModulosTodos() {
        $sql = "SELECT m.id, m.codigo, m.nombre, m.categoria, m.ruta_url, m.icono, m.color, m.descripcion, m.target_blank, m.orden, m.activo,
                       (SELECT COUNT(*) FROM FP_PERMISOS sp WHERE sp.modulo_id = m.id) as cant_permisos_total,
                       (SELECT COUNT(*) FROM FP_PERMISOS sp WHERE sp.modulo_id = m.id AND sp.clave NOT LIKE 'hub.app.%') as cant_submodulos,
                       (SELECT COUNT(DISTINCT rp.rol_id) FROM FP_ROL_PERMISO rp JOIN FP_PERMISOS p ON p.id = rp.permiso_id WHERE p.modulo_id = m.id) as cant_roles_asignados
                FROM FP_MODULOS m
                ORDER BY m.categoria ASC, m.orden ASC, m.nombre ASC";
        $stmt = sqlsrv_query($this->db, $sql);
        $modulos = [];
        if ($stmt) {
            while ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $modulos[] = $r;
            }
        }
        return $modulos;
    }

    public function obtenerModulo($id) {
        $sql = "SELECT * FROM FP_MODULOS WHERE id = ?";
        $stmt = sqlsrv_query($this->db, $sql, [(int)$id]);
        if ($stmt && ($r = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
            return $r;
        }
        return null;
    }

    public function crearModulo($codigo, $nombre, $categoria, $rutaUrl, $icono, $color, $descripcion = '', $orden = 100, $targetBlank = 0, $activo = 1) {
        $codigo = strtoupper(preg_replace('/[^A-Za-z0-9_]/', '', trim($codigo)));
        $nombre = trim($nombre);
        $categoria = trim($categoria) ?: 'General';
        $rutaUrl = trim($rutaUrl);
        $icono = trim($icono) ?: 'fa-cube';
        $color = trim($color) ?: 'linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%)';
        $descripcion = trim($descripcion);
        $orden = (int)$orden;
        $targetBlank = $targetBlank ? 1 : 0;
        $activo = $activo ? 1 : 0;

        if (empty($codigo) || empty($nombre)) {
            throw new Exception("El código y el nombre del módulo son obligatorios.");
        }

        // Verificar código único
        $stmtCheck = sqlsrv_query($this->db, "SELECT 1 FROM FP_MODULOS WHERE codigo = ?", [$codigo]);
        if ($stmtCheck && sqlsrv_has_rows($stmtCheck)) {
            throw new Exception("Ya existe un módulo con el código '$codigo'.");
        }

        sqlsrv_begin_transaction($this->db);
        try {
            $sql = "INSERT INTO FP_MODULOS (codigo, nombre, categoria, ruta_url, icono, color, descripcion, orden, target_blank, activo) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = sqlsrv_query($this->db, $sql, [
                $codigo, $nombre, $categoria, $rutaUrl, $icono, $color, $descripcion, $orden, $targetBlank, $activo
            ]);
            if (!$stmt) {
                throw new Exception("Error al insertar en FP_MODULOS: " . print_r(sqlsrv_errors(), true));
            }

            // Obtener el ID generado
            $stmtId = sqlsrv_query($this->db, "SELECT id FROM FP_MODULOS WHERE codigo = ?", [$codigo]);
            $rowId = sqlsrv_fetch_array($stmtId, SQLSRV_FETCH_ASSOC);
            $moduloId = $rowId['id'] ?? null;

            if ($moduloId) {
                // Crear automáticamente el permiso principal del Hub para este módulo
                $claveHub = 'hub.app.' . strtolower($codigo);
                $nombreHub = 'Acceso a ' . $nombre . ' en Hub';
                $descHub = '[Hub] ' . ($descripcion ?: $nombre);

                $sqlHub = "IF NOT EXISTS (SELECT 1 FROM FP_PERMISOS WHERE modulo_id = ? AND clave = ?)
                           INSERT INTO FP_PERMISOS (modulo_id, clave, nombre, descripcion) VALUES (?, ?, ?, ?)";
                sqlsrv_query($this->db, $sqlHub, [$moduloId, $claveHub, $moduloId, $claveHub, $nombreHub, $descHub]);
            }

            sqlsrv_commit($this->db);
            return $moduloId;
        } catch (Exception $e) {
            sqlsrv_rollback($this->db);
            throw $e;
        }
    }

    public function actualizarModulo($id, $codigo, $nombre, $categoria, $rutaUrl, $icono, $color, $descripcion = '', $orden = 100, $targetBlank = 0, $activo = 1) {
        $id = (int)$id;
        $codigo = strtoupper(preg_replace('/[^A-Za-z0-9_]/', '', trim($codigo)));
        $nombre = trim($nombre);
        $categoria = trim($categoria) ?: 'General';
        $rutaUrl = trim($rutaUrl);
        $icono = trim($icono) ?: 'fa-cube';
        $color = trim($color) ?: 'linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%)';
        $descripcion = trim($descripcion);
        $orden = (int)$orden;
        $targetBlank = $targetBlank ? 1 : 0;
        $activo = $activo ? 1 : 0;

        if ($id <= 0 || empty($codigo) || empty($nombre)) {
            throw new Exception("Datos incompletos para actualizar el módulo.");
        }

        // Verificar código único en otros módulos
        $stmtCheck = sqlsrv_query($this->db, "SELECT 1 FROM FP_MODULOS WHERE codigo = ? AND id != ?", [$codigo, $id]);
        if ($stmtCheck && sqlsrv_has_rows($stmtCheck)) {
            throw new Exception("Ya existe otro módulo con el código '$codigo'.");
        }

        sqlsrv_begin_transaction($this->db);
        try {
            $sql = "UPDATE FP_MODULOS SET 
                        codigo = ?, nombre = ?, categoria = ?, ruta_url = ?, icono = ?, 
                        color = ?, descripcion = ?, orden = ?, target_blank = ?, activo = ? 
                    WHERE id = ?";
            $stmt = sqlsrv_query($this->db, $sql, [
                $codigo, $nombre, $categoria, $rutaUrl, $icono, $color, $descripcion, $orden, $targetBlank, $activo, $id
            ]);
            if (!$stmt) {
                throw new Exception("Error al actualizar FP_MODULOS: " . print_r(sqlsrv_errors(), true));
            }

            // Asegurar permiso del Hub
            $claveHub = 'hub.app.' . strtolower($codigo);
            $nombreHub = 'Acceso a ' . $nombre . ' en Hub';
            $descHub = '[Hub] ' . ($descripcion ?: $nombre);

            $stmtCheckHub = sqlsrv_query($this->db, "SELECT id FROM FP_PERMISOS WHERE modulo_id = ? AND clave LIKE 'hub.app.%'", [$id]);
            if ($stmtCheckHub && ($rHub = sqlsrv_fetch_array($stmtCheckHub, SQLSRV_FETCH_ASSOC))) {
                sqlsrv_query($this->db, "UPDATE FP_PERMISOS SET clave = ?, nombre = ?, descripcion = ? WHERE id = ?", [
                    $claveHub, $nombreHub, $descHub, $rHub['id']
                ]);
            } else {
                sqlsrv_query($this->db, "INSERT INTO FP_PERMISOS (modulo_id, clave, nombre, descripcion) VALUES (?, ?, ?, ?)", [
                    $id, $claveHub, $nombreHub, $descHub
                ]);
            }

            sqlsrv_commit($this->db);
            return true;
        } catch (Exception $e) {
            sqlsrv_rollback($this->db);
            throw $e;
        }
    }

    public function eliminarModulo($id) {
        $id = (int)$id;
        if ($id <= 0) return false;

        sqlsrv_begin_transaction($this->db);
        try {
            // Eliminar asignaciones de permisos a roles
            sqlsrv_query($this->db, "DELETE FROM FP_ROL_PERMISO WHERE permiso_id IN (SELECT id FROM FP_PERMISOS WHERE modulo_id = ?)", [$id]);
            // Eliminar permisos
            sqlsrv_query($this->db, "DELETE FROM FP_PERMISOS WHERE modulo_id = ?", [$id]);
            // Eliminar módulo
            $stmt = sqlsrv_query($this->db, "DELETE FROM FP_MODULOS WHERE id = ?", [$id]);
            sqlsrv_commit($this->db);
            return true;
        } catch (Exception $e) {
            sqlsrv_rollback($this->db);
            throw $e;
        }
    }

    public function listarPermisosDeModulo($moduloId) {
        $moduloId = (int)$moduloId;
        $sql = "SELECT p.id, p.modulo_id, p.clave, p.nombre, p.descripcion,
                       (SELECT COUNT(*) FROM FP_ROL_PERMISO rp WHERE rp.permiso_id = p.id) as cant_roles_con_permiso
                FROM FP_PERMISOS p
                WHERE p.modulo_id = ?
                ORDER BY CASE WHEN p.clave LIKE 'hub.app.%' THEN 0 ELSE 1 END, p.id ASC";
        $stmt = sqlsrv_query($this->db, $sql, [$moduloId]);
        $res = [];
        if ($stmt) {
            while ($p = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $desc = $p['descripcion'] ?? '';
                $secNombre = 'General';
                $itemDesc = $desc;
                if (preg_match('/^\[(.*?)\]\s*(.*)$/', $desc, $matches)) {
                    $secNombre = trim($matches[1]);
                    $itemDesc = trim($matches[2]);
                }
                $p['seccion'] = $secNombre;
                $p['descripcion_limpia'] = $itemDesc;
                $p['es_raiz_hub'] = (strpos($p['clave'], 'hub.app.') === 0);
                $res[] = $p;
            }
        }
        return $res;
    }

    public function crearPermisoModulo($moduloId, $clave, $nombre, $seccion = '', $descripcion = '') {
        $moduloId = (int)$moduloId;
        $clave = strtolower(trim($clave));
        $nombre = trim($nombre);
        $seccion = trim($seccion);
        $descripcion = trim($descripcion);

        if ($moduloId <= 0 || empty($clave) || empty($nombre)) {
            throw new Exception("La clave y el nombre del permiso son obligatorios.");
        }

        // Verificar clave única dentro del módulo
        $stmtCheck = sqlsrv_query($this->db, "SELECT 1 FROM FP_PERMISOS WHERE modulo_id = ? AND clave = ?", [$moduloId, $clave]);
        if ($stmtCheck && sqlsrv_has_rows($stmtCheck)) {
            throw new Exception("Ya existe un permiso con la clave '$clave' en este módulo.");
        }

        $descCompleta = !empty($seccion) ? "[$seccion] $descripcion" : $descripcion;
        $sql = "INSERT INTO FP_PERMISOS (modulo_id, clave, nombre, descripcion) VALUES (?, ?, ?, ?)";
        $stmt = sqlsrv_query($this->db, $sql, [$moduloId, $clave, $nombre, $descCompleta]);
        if (!$stmt) {
            throw new Exception("Error al guardar el permiso: " . print_r(sqlsrv_errors(), true));
        }
        return true;
    }

    public function actualizarPermisoModulo($permisoId, $clave, $nombre, $seccion = '', $descripcion = '') {
        $permisoId = (int)$permisoId;
        $clave = strtolower(trim($clave));
        $nombre = trim($nombre);
        $seccion = trim($seccion);
        $descripcion = trim($descripcion);

        if ($permisoId <= 0 || empty($clave) || empty($nombre)) {
            throw new Exception("Datos incompletos para actualizar el permiso.");
        }

        $descCompleta = !empty($seccion) ? "[$seccion] $descripcion" : $descripcion;
        $sql = "UPDATE FP_PERMISOS SET clave = ?, nombre = ?, descripcion = ? WHERE id = ?";
        $stmt = sqlsrv_query($this->db, $sql, [$clave, $nombre, $descCompleta, $permisoId]);
        if (!$stmt) {
            throw new Exception("Error al actualizar el permiso: " . print_r(sqlsrv_errors(), true));
        }
        return true;
    }

    public function eliminarPermisoModulo($permisoId) {
        $permisoId = (int)$permisoId;
        if ($permisoId <= 0) return false;

        sqlsrv_begin_transaction($this->db);
        try {
            sqlsrv_query($this->db, "DELETE FROM FP_ROL_PERMISO WHERE permiso_id = ?", [$permisoId]);
            sqlsrv_query($this->db, "DELETE FROM FP_PERMISOS WHERE id = ?", [$permisoId]);
            sqlsrv_commit($this->db);
            return true;
        } catch (Exception $e) {
            sqlsrv_rollback($this->db);
            throw $e;
        }
    }
}
