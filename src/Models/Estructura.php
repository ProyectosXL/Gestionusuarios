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
    }
}
