<?php

namespace App\Models;

use App\Core\Model;

class Bitacora extends Model
{
    public function segundosDesde(string $accion, int $idUsuario): ?int
    {
        $fila = $this->fetch(
            'SELECT EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP - fecha_hora))::int AS transcurrido
               FROM bitacora
              WHERE id_usuario = :id AND accion = :accion
              ORDER BY fecha_hora DESC
              LIMIT 1',
            ['id' => $idUsuario, 'accion' => $accion]
        );
        if ($fila === null) {
            return null;
        }

        return max(0, (int) $fila['transcurrido']);
    }

    public function registrar(?int $idUsuario, string $accion, string $modulo, ?string $detalle = null): void
    {
        $this->execute(
            'INSERT INTO bitacora (id_usuario, accion, modulo, detalle, ip)
             VALUES (:id_usuario, :accion, :modulo, :detalle, :ip)',
            [
                'id_usuario' => $idUsuario,
                'accion'     => $accion,
                'modulo'     => $modulo,
                'detalle'    => $detalle,
                'ip'         => $_SERVER['REMOTE_ADDR'] ?? null,
            ]
        );
    }
}
