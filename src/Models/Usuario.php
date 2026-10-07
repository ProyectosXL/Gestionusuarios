<?php
namespace GestionUsuarios\Models;

use GestionUsuarios\Config\Database;
use Exception;

class Usuario {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->connectApps();
    }

    public function listar($busqueda = '', $tipo = '', $sectorId = '', $categoria = '', $rolId = '', $limit = 50, $offset = 0) {
        $sql = "SELECT u.id, u.legacy_id, u.username, u.password, u.password_plain, u.nombre_completo, u.email, 
                       u.categoria_usuario, u.tipo, u.permisos_legacy, u.cod_client, u.nro_sucursal, u.nro_sucurs, u.legajo, u.cod_vendedor, u.activo, 
                       u.sector_id, u.subsector_id, u.rol_id, u.ultimo_login, u.created_at,
                       u.usuario_tango,
                       u.relevamiento_estado, u.relevamiento_obs, u.relevamiento_usuario, u.relevamiento_fecha,
                       s.nombre as sector_nombre, sub.nombre as subsector_nombre, r.nombre as rol_nombre
                FROM FP_SOF_USUARIOS u
                LEFT JOIN FP_SECTORES s ON s.id = u.sector_id
                LEFT JOIN FP_SUBSECTORES sub ON sub.id = u.subsector_id
                LEFT JOIN FP_ROLES_PERMISOS_MAP r ON r.id = u.rol_id
                WHERE 1=1";
        $params = [];

        if (!empty($busqueda)) {
            $sql .= " AND (u.username LIKE ? OR u.nombre_completo LIKE ? OR u.cod_client LIKE ? OR CAST(u.legajo AS VARCHAR) LIKE ?)";
            $b = "%$busqueda%";
            $params[] = $b;
            $params[] = $b;
            $params[] = $b;
            $params[] = $b;
        }

        if (!empty($tipo)) {
            $sql .= " AND u.tipo = ?";
            $params[] = $tipo;
        }

        if (!empty($sectorId)) {
            $sql .= " AND u.sector_id = ?";
            $params[] = (int)$sectorId;
        }

        if (!empty($categoria)) {
            $sql .= " AND u.categoria_usuario = ?";
            $params[] = $categoria;
        }

        if (!empty($rolId)) {
            $sql .= " AND u.rol_id = ?";
            $params[] = (int)$rolId;
        }

        $sql .= " ORDER BY u.id DESC OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
        $params[] = (int)$offset;
        $params[] = (int)$limit;

        $stmt = sqlsrv_query($this->db, $sql, $params);
        if ($stmt === false) {
            throw new Exception("Error al consultar usuarios: " . print_r(sqlsrv_errors(), true));
        }

        $usuarios = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $usuarios[] = $row;
        }
        return $usuarios;
    }

    public function totalRegistros($busqueda = '', $tipo = '', $sectorId = '', $categoria = '', $rolId = '') {
        $sql = "SELECT COUNT(*) as total FROM FP_SOF_USUARIOS u WHERE 1=1";
        $params = [];

        if (!empty($busqueda)) {
            $sql .= " AND (u.username LIKE ? OR u.nombre_completo LIKE ? OR u.cod_client LIKE ? OR CAST(u.legajo AS VARCHAR) LIKE ?)";
            $b = "%$busqueda%";
            $params[] = $b;
            $params[] = $b;
            $params[] = $b;
            $params[] = $b;
        }

        if (!empty($tipo)) {
            $sql .= " AND u.tipo = ?";
            $params[] = $tipo;
        }

        if (!empty($sectorId)) {
            $sql .= " AND u.sector_id = ?";
            $params[] = (int)$sectorId;
        }

        if (!empty($categoria)) {
            $sql .= " AND u.categoria_usuario = ?";
            $params[] = $categoria;
        }

        if (!empty($rolId)) {
            $sql .= " AND u.rol_id = ?";
            $params[] = (int)$rolId;
        }

        $stmt = sqlsrv_query($this->db, $sql, $params);
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        return $row['total'] ?? 0;
    }

    public function totalPorCategoria($categoria) {
        $sql = "SELECT COUNT(*) as total FROM FP_SOF_USUARIOS WHERE categoria_usuario = ?";
        $stmt = sqlsrv_query($this->db, $sql, [$categoria]);
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        return $row['total'] ?? 0;
    }

    public function obtenerTipos() {
        $sql = "SELECT DISTINCT tipo FROM FP_SOF_USUARIOS WHERE tipo IS NOT NULL AND tipo != '' ORDER BY tipo ASC";
        $stmt = sqlsrv_query($this->db, $sql);
        $tipos = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $tipos[] = $row['tipo'];
        }
        return $tipos;
    }

    public function crearUsuario($datos) {
        $sql = "INSERT INTO FP_SOF_USUARIOS (
            username, password, password_plain, nombre_completo, email, categoria_usuario, tipo,
            sector_id, subsector_id, rol_id, cod_client, nro_sucursal, nro_sucurs, legajo, cod_vendedor, usuario_tango, activo, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE())";

        $pass = trim($datos['password'] ?? '123456');
        $categoria = trim($datos['categoria_usuario'] ?? 'PERSONAL');
        
        $codClient = !empty($datos['cod_client']) ? trim($datos['cod_client']) : null;
        $nroSuc = !empty($datos['nro_sucursal']) ? (int)$datos['nro_sucursal'] : null;
        $codVend = !empty($datos['cod_vendedor']) ? trim($datos['cod_vendedor']) : null;
        $tipo = !empty($datos['tipo']) ? trim($datos['tipo']) : $categoria;
        $usuarioTango = !empty($datos['usuario_tango']) ? trim($datos['usuario_tango']) : null;

        $nroSucurs = !empty($datos['nro_sucurs']) ? trim($datos['nro_sucurs']) : null;
        $legajo = !empty($datos['legajo']) ? (int)$datos['legajo'] : null;

        $params = [
            trim($datos['username']),
            $pass,
            $pass,
            trim($datos['nombre_completo'] ?? ''),
            trim($datos['email'] ?? ''),
            $categoria,
            $tipo,
            !empty($datos['sector_id']) ? (int)$datos['sector_id'] : null,
            !empty($datos['subsector_id']) ? (int)$datos['subsector_id'] : null,
            !empty($datos['rol_id']) ? (int)$datos['rol_id'] : null,
            $codClient,
            $nroSuc,
            $nroSucurs,
            $legajo,
            $codVend,
            $usuarioTango,
            isset($datos['activo']) ? (int)$datos['activo'] : 1
        ];

        $stmt = sqlsrv_query($this->db, $sql, $params);
        if ($stmt === false) {
            $errs = sqlsrv_errors();
            $msg = is_array($errs) && isset($errs[0]['message']) ? $errs[0]['message'] : 'Error desconocido de SQL';
            throw new Exception("Error al insertar usuario en SQL: " . $msg);
        }
        return true;
    }

    public function actualizarUsuario($id, $datos) {
        $categoria = trim($datos['categoria_usuario'] ?? 'PERSONAL');
        $codClient = !empty($datos['cod_client']) ? trim($datos['cod_client']) : null;
        $nroSuc = !empty($datos['nro_sucursal']) ? (int)$datos['nro_sucursal'] : null;
        $codVend = !empty($datos['cod_vendedor']) ? trim($datos['cod_vendedor']) : null;
        $tipo = !empty($datos['tipo']) ? trim($datos['tipo']) : $categoria;
        $usuarioTango = !empty($datos['usuario_tango']) ? trim($datos['usuario_tango']) : null;

        $nroSucurs = !empty($datos['nro_sucurs']) ? trim($datos['nro_sucurs']) : null;
        $legajo = !empty($datos['legajo']) ? (int)$datos['legajo'] : null;

        $sql = "UPDATE FP_SOF_USUARIOS SET 
                username = ?,
                nombre_completo = ?,
                email = ?,
                categoria_usuario = ?,
                tipo = ?,
                sector_id = ?,
                subsector_id = ?,
                rol_id = ?,
                cod_client = ?,
                nro_sucursal = ?,
                nro_sucurs = ?,
                legajo = ?,
                cod_vendedor = ?,
                usuario_tango = ?,
                activo = ?,
                updated_at = GETDATE()";
        
        $params = [
            trim($datos['username']),
            trim($datos['nombre_completo'] ?? ''),
            trim($datos['email'] ?? ''),
            $categoria,
            $tipo,
            !empty($datos['sector_id']) ? (int)$datos['sector_id'] : null,
            !empty($datos['subsector_id']) ? (int)$datos['subsector_id'] : null,
            !empty($datos['rol_id']) ? (int)$datos['rol_id'] : null,
            $codClient,
            $nroSuc,
            $nroSucurs,
            $legajo,
            $codVend,
            $usuarioTango,
            isset($datos['activo']) ? (int)$datos['activo'] : 1
        ];

        if (!empty($datos['password'])) {
            $sql .= ", password = ?, password_plain = ?";
            $pass = trim($datos['password']);
            $params[] = $pass;
            $params[] = $pass;
        }

        $sql .= " WHERE id = ?";
        $params[] = (int)$id;

        $stmt = sqlsrv_query($this->db, $sql, $params);
        if ($stmt === false) {
            $errs = sqlsrv_errors();
            $msg = is_array($errs) && isset($errs[0]['message']) ? $errs[0]['message'] : 'Error desconocido de SQL';
            throw new Exception("Error al actualizar usuario en SQL: " . $msg);
        }
        return true;
    }

    public function eliminarUsuario($id) {
        $sql = "DELETE FROM FP_SOF_USUARIOS WHERE id = ?";
        $stmt = sqlsrv_query($this->db, $sql, [(int)$id]);
        if ($stmt === false) {
            $errs = sqlsrv_errors();
            $msg = is_array($errs) && isset($errs[0]['message']) ? $errs[0]['message'] : 'Error desconocido de SQL';
            throw new Exception("Error al eliminar usuario en SQL: " . $msg);
        }
        return true;
    }

    public function actualizarPassword($id, $nuevaPassword) {
        $sql = "UPDATE FP_SOF_USUARIOS SET password = ?, password_plain = ?, updated_at = GETDATE() WHERE id = ?";
        return sqlsrv_query($this->db, $sql, [$nuevaPassword, $nuevaPassword, (int)$id]);
    }

    public function obtenerPorId($id) {
        $sql = "SELECT * FROM FP_SOF_USUARIOS WHERE id = ?";
        $stmt = sqlsrv_query($this->db, $sql, [(int)$id]);
        return sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    }

    public function actualizarRelevamiento($id, $estado, $obs = '', $usuarioQuien = 'FRANCO') {
        $sql = "UPDATE FP_SOF_USUARIOS SET 
                relevamiento_estado = ?, 
                relevamiento_obs = ?, 
                relevamiento_usuario = ?, 
                relevamiento_fecha = GETDATE() 
                WHERE id = ?";
        $params = [$estado, $obs, $usuarioQuien, (int)$id];
        $stmt = sqlsrv_query($this->db, $sql, $params);
        if ($stmt === false) {
            $errs = sqlsrv_errors();
            $msg = is_array($errs) && isset($errs[0]['message']) ? $errs[0]['message'] : 'Error en SQL';
            throw new Exception("Error al actualizar relevamiento: " . $msg);
        }
        return true;
    }

    public function marcarLoteRelevamiento(array $ids, $estado, $obs = '', $usuarioQuien = 'FRANCO') {
        if (empty($ids)) return 0;
        $cleanIds = array_map('intval', $ids);
        $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
        $sql = "UPDATE FP_SOF_USUARIOS SET 
                relevamiento_estado = ?, 
                relevamiento_obs = CASE WHEN ? != '' THEN ? ELSE relevamiento_obs END,
                relevamiento_usuario = ?, 
                relevamiento_fecha = GETDATE() 
                WHERE id IN ($placeholders)";
        $params = array_merge([$estado, $obs, $obs, $usuarioQuien], $cleanIds);
        $stmt = sqlsrv_query($this->db, $sql, $params);
        if ($stmt === false) {
            $errs = sqlsrv_errors();
            $msg = is_array($errs) && isset($errs[0]['message']) ? $errs[0]['message'] : 'Error en SQL';
            throw new Exception("Error al actualizar lote de relevamiento: " . $msg);
        }
        return sqlsrv_rows_affected($stmt);
    }

    public function marcarFiltroRelevamiento($busqueda = '', $estadoActual = '', $categoria = '', $nuevoEstado = 'OK', $obs = '', $usuarioQuien = 'FRANCO') {
        $sql = "UPDATE FP_SOF_USUARIOS SET 
                relevamiento_estado = ?, 
                relevamiento_obs = CASE WHEN ? != '' THEN ? ELSE relevamiento_obs END,
                relevamiento_usuario = ?, 
                relevamiento_fecha = GETDATE() 
                WHERE 1=1";
        $params = [$nuevoEstado, $obs, $obs, $usuarioQuien];

        if (!empty($busqueda)) {
            $sql .= " AND (username LIKE ? OR nombre_completo LIKE ? OR cod_client LIKE ? OR relevamiento_obs LIKE ?)";
            $b = "%$busqueda%";
            $params[] = $b;
            $params[] = $b;
            $params[] = $b;
            $params[] = $b;
        }

        if (!empty($estadoActual)) {
            if ($estadoActual === 'PENDIENTE') {
                $sql .= " AND (relevamiento_estado IS NULL OR relevamiento_estado = 'PENDIENTE')";
            } else {
                $sql .= " AND relevamiento_estado = ?";
                $params[] = $estadoActual;
            }
        }

        if (!empty($categoria)) {
            $sql .= " AND categoria_usuario = ?";
            $params[] = $categoria;
        }

        $stmt = sqlsrv_query($this->db, $sql, $params);
        if ($stmt === false) {
            $errs = sqlsrv_errors();
            $msg = is_array($errs) && isset($errs[0]['message']) ? $errs[0]['message'] : 'Error en SQL';
            throw new Exception("Error al actualizar filtro de relevamiento: " . $msg);
        }
        return sqlsrv_rows_affected($stmt);
    }

    public function obtenerEstadisticasRelevamiento() {
        $stats = [
            'total' => 0,
            'ok' => 0,
            'observado' => 0,
            'pendiente' => 0,
            'descartado' => 0,
            'por_categoria' => [
                'PERSONAL' => ['total' => 0, 'ok' => 0, 'observado' => 0, 'pendiente' => 0],
                'LOCAL' => ['total' => 0, 'ok' => 0, 'observado' => 0, 'pendiente' => 0],
                'MAYORISTA' => ['total' => 0, 'ok' => 0, 'observado' => 0, 'pendiente' => 0],
                'GRUPO' => ['total' => 0, 'ok' => 0, 'observado' => 0, 'pendiente' => 0],
            ]
        ];

        // Total y estados generales
        $sqlGen = "SELECT ISNULL(relevamiento_estado, 'PENDIENTE') as estado, COUNT(*) as cant 
                   FROM FP_SOF_USUARIOS GROUP BY ISNULL(relevamiento_estado, 'PENDIENTE')";
        $stmtGen = sqlsrv_query($this->db, $sqlGen);
        if ($stmtGen) {
            while ($row = sqlsrv_fetch_array($stmtGen, SQLSRV_FETCH_ASSOC)) {
                $st = strtoupper(trim($row['estado']));
                $cant = (int)$row['cant'];
                $stats['total'] += $cant;
                if ($st === 'OK' || $st === 'FUNCIONAL' || $st === 'PROBADO') {
                    $stats['ok'] += $cant;
                } elseif ($st === 'OBSERVADO' || $st === 'ERROR' || $st === 'AJUSTAR') {
                    $stats['observado'] += $cant;
                } elseif ($st === 'DESCARTADO' || $st === 'NO_APLICA') {
                    $stats['descartado'] += $cant;
                } else {
                    $stats['pendiente'] += $cant;
                }
            }
        }

        // Por categoría
        $sqlCat = "SELECT categoria_usuario, ISNULL(relevamiento_estado, 'PENDIENTE') as estado, COUNT(*) as cant 
                   FROM FP_SOF_USUARIOS GROUP BY categoria_usuario, ISNULL(relevamiento_estado, 'PENDIENTE')";
        $stmtCat = sqlsrv_query($this->db, $sqlCat);
        if ($stmtCat) {
            while ($row = sqlsrv_fetch_array($stmtCat, SQLSRV_FETCH_ASSOC)) {
                $cat = strtoupper(trim($row['categoria_usuario'] ?? 'PERSONAL'));
                $st = strtoupper(trim($row['estado']));
                $cant = (int)$row['cant'];

                if (!isset($stats['por_categoria'][$cat])) {
                    $stats['por_categoria'][$cat] = ['total' => 0, 'ok' => 0, 'observado' => 0, 'pendiente' => 0];
                }
                $stats['por_categoria'][$cat]['total'] += $cant;
                if ($st === 'OK' || $st === 'FUNCIONAL' || $st === 'PROBADO') {
                    $stats['por_categoria'][$cat]['ok'] += $cant;
                } elseif ($st === 'OBSERVADO' || $st === 'ERROR' || $st === 'AJUSTAR') {
                    $stats['por_categoria'][$cat]['observado'] += $cant;
                } else {
                    $stats['por_categoria'][$cat]['pendiente'] += $cant;
                }
            }
        }

        return $stats;
    }

    public function listarRelevamiento($busqueda = '', $estado = '', $categoria = '', $limit = 50, $offset = 0) {
        $sql = "SELECT u.id, u.legacy_id, u.username, u.password_plain, u.password, u.nombre_completo, 
                       u.categoria_usuario, u.tipo, u.cod_client, u.nro_sucursal, u.nro_sucurs, u.cod_vendedor, u.activo,
                       u.relevamiento_estado, u.relevamiento_obs, u.relevamiento_usuario, u.relevamiento_fecha,
                       r.nombre as rol_nombre
                FROM FP_SOF_USUARIOS u
                LEFT JOIN FP_ROLES_PERMISOS_MAP r ON r.id = u.rol_id
                WHERE 1=1";
        $params = [];

        if (!empty($busqueda)) {
            $sql .= " AND (u.username LIKE ? OR u.nombre_completo LIKE ? OR u.cod_client LIKE ? OR u.relevamiento_obs LIKE ?)";
            $b = "%$busqueda%";
            $params[] = $b;
            $params[] = $b;
            $params[] = $b;
            $params[] = $b;
        }

        if (!empty($estado)) {
            if ($estado === 'PENDIENTE') {
                $sql .= " AND (u.relevamiento_estado IS NULL OR u.relevamiento_estado = 'PENDIENTE')";
            } else {
                $sql .= " AND u.relevamiento_estado = ?";
                $params[] = $estado;
            }
        }

        if (!empty($categoria)) {
            $sql .= " AND u.categoria_usuario = ?";
            $params[] = $categoria;
        }

        $sql .= " ORDER BY CASE WHEN u.relevamiento_estado = 'OBSERVADO' THEN 1 WHEN u.relevamiento_estado = 'PENDIENTE' OR u.relevamiento_estado IS NULL THEN 2 WHEN u.relevamiento_estado = 'OK' THEN 3 ELSE 4 END, u.id DESC OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
        $params[] = (int)$offset;
        $params[] = (int)$limit;

        $stmt = sqlsrv_query($this->db, $sql, $params);
        if ($stmt === false) {
            throw new Exception("Error al consultar relevamiento: " . print_r(sqlsrv_errors(), true));
        }

        $items = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $items[] = $row;
        }
        return $items;
    }

    public function totalRelevamiento($busqueda = '', $estado = '', $categoria = '') {
        $sql = "SELECT COUNT(*) as total FROM FP_SOF_USUARIOS u WHERE 1=1";
        $params = [];

        if (!empty($busqueda)) {
            $sql .= " AND (u.username LIKE ? OR u.nombre_completo LIKE ? OR u.cod_client LIKE ? OR u.relevamiento_obs LIKE ?)";
            $b = "%$busqueda%";
            $params[] = $b;
            $params[] = $b;
            $params[] = $b;
            $params[] = $b;
        }

        if (!empty($estado)) {
            if ($estado === 'PENDIENTE') {
                $sql .= " AND (u.relevamiento_estado IS NULL OR u.relevamiento_estado = 'PENDIENTE')";
            } else {
                $sql .= " AND u.relevamiento_estado = ?";
                $params[] = $estado;
            }
        }

        if (!empty($categoria)) {
            $sql .= " AND u.categoria_usuario = ?";
            $params[] = $categoria;
        }

        $stmt = sqlsrv_query($this->db, $sql, $params);
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        return $row['total'] ?? 0;
    }
}

