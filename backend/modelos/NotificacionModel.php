<?php

declare(strict_types=1);

namespace App\modelos;

use PDO;
use Flight;

class NotificacionModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Flight::db();
    }

    /**
     * Notificaciones de un usuario con datos del pedido relacionado.
     */
    public function Consultar(int $idUsuario): array
    {
        $sql = "SELECT n.id_notificacion,
                       n.id_pedido,
                       n.mensaje,
                       n.leida,
                       n.fecha_creacion,
                       p.numero_pedido,
                       ep.nombre AS estado_pedido
                FROM notificaciones n
                LEFT JOIN pedidos p ON n.id_pedido = p.id_pedido
                LEFT JOIN estados_pedido ep ON p.id_estado_pedido = ep.id_estado_pedido
                WHERE n.id_usuario = :id_usuario
                ORDER BY n.fecha_creacion DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_usuario' => $idUsuario]);

        return $stmt->fetchAll();
    }

    /**
     * Cantidad de notificaciones no leídas del usuario.
     */
    public function ContarNoLeidas(int $idUsuario): int
    {
        $sql = "SELECT COUNT(*) FROM notificaciones
                WHERE id_usuario = :id_usuario AND leida = FALSE";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_usuario' => $idUsuario]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Marca todas las notificaciones del usuario como leídas.
     */
    public function MarcarTodasLeidas(int $idUsuario): void
    {
        $sql = "UPDATE notificaciones SET leida = TRUE
                WHERE id_usuario = :id_usuario AND leida = FALSE";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_usuario' => $idUsuario]);
    }

    /**
     * Marca como leídas las notificaciones de un pedido del usuario.
     */
    public function MarcarLeidasDePedido(int $idUsuario, int $idPedido): void
    {
        $sql = "UPDATE notificaciones SET leida = TRUE
                WHERE id_usuario = :id_usuario
                  AND id_pedido = :id_pedido
                  AND leida = FALSE";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_usuario' => $idUsuario, 'id_pedido' => $idPedido]);
    }

    /**
     * Crea una notificación para un usuario.
     */
    public function Crear(int $idUsuario, ?int $idPedido, string $mensaje): void
    {
        $sql = "INSERT INTO notificaciones (id_usuario, id_pedido, mensaje)
                VALUES (:id_usuario, :id_pedido, :mensaje)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id_usuario' => $idUsuario,
            'id_pedido'  => $idPedido,
            'mensaje'    => $mensaje,
        ]);
    }
}
