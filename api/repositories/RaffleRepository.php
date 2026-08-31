<?php
/**
 * Raffle Repository
 */

require_once __DIR__ . '/BaseRepository.php';

class RaffleRepository extends BaseRepository
{
    protected $table = 'raffles';

    /**
     * Obtener rifas activas con estadísticas
     */
    public function getActiveRaffles(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT
                    r.*,
                    l.name as lottery_name,
                    COUNT(CASE WHEN t.status = ? THEN 1 END) as sold_tickets,
                    COUNT(CASE WHEN t.status = ? THEN 1 END) as available_tickets,
                    ROUND((COUNT(CASE WHEN t.status = ? THEN 1 END) / r.total_tickets) * 100, 2) as sold_percentage,
                    TIMESTAMPDIFF(SECOND, NOW(), r.draw_date) as seconds_remaining
                FROM raffles r
                LEFT JOIN tickets t ON r.id = t.raffle_id
                LEFT JOIN lotteries l ON r.lottery_id = l.id
                WHERE r.status = ?";

        $params = [
            TICKET_STATUS_PAID,
            TICKET_STATUS_AVAILABLE,
            TICKET_STATUS_PAID,
            RAFFLE_STATUS_ACTIVE
        ];

        // Filtros opcionales
        if (!empty($filters['department'])) {
            $sql .= " AND r.department = ?";
            $params[] = $filters['department'];
        }

        if (!empty($filters['city'])) {
            $sql .= " AND r.city = ?";
            $params[] = $filters['city'];
        }

        if (!empty($filters['lottery_id'])) {
            $sql .= " AND r.lottery_id = ?";
            $params[] = (int)$filters['lottery_id'];
        }

        if (!empty($filters['vendor_id'])) {
            $sql .= " AND COALESCE(r.vendor_id, r.created_by) = ?";
            $params[] = (int)$filters['vendor_id'];
        }

        if (!empty($filters['min_price'])) {
            $sql .= " AND r.ticket_price >= ?";
            $params[] = (float)$filters['min_price'];
        }

        if (!empty($filters['max_price'])) {
            $sql .= " AND r.ticket_price <= ?";
            $params[] = (float)$filters['max_price'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (r.name LIKE ? OR r.description LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $sql .= " GROUP BY r.id";

        // Ordenamiento
        $allowedOrders = ['draw_date', 'ticket_price', 'created_at', 'name', 'id', 'views', 'shares', 'sold_percentage'];
        $orderBy = in_array($filters['order_by'] ?? '', $allowedOrders) ? $filters['order_by'] : 'draw_date';
        $orderDir = strtoupper($filters['order_dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
        $sql .= " ORDER BY r.{$orderBy} {$orderDir}";

        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Obtener rifa por ID con estadísticas
     */
    public function getRaffleWithStats(int $id): ?array
    {
        $sql = "SELECT
                    r.*,
                    l.name as lottery_name,
                    l.day_of_week,
                    l.draw_time,
                    COUNT(CASE WHEN t.status = ? THEN 1 END) as sold_tickets,
                    COUNT(CASE WHEN t.status = ? THEN 1 END) as reserved_tickets,
                    COUNT(CASE WHEN t.status = ? THEN 1 END) as available_tickets,
                    ROUND((COUNT(CASE WHEN t.status = ? THEN 1 END) / r.total_tickets) * 100, 2) as sold_percentage,
                    SUM(CASE WHEN t.status = ? THEN r.ticket_price ELSE 0 END) as total_revenue
                FROM raffles r
                LEFT JOIN tickets t ON r.id = t.raffle_id
                LEFT JOIN lotteries l ON r.lottery_id = l.id
                WHERE r.id = ?
                GROUP BY r.id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            TICKET_STATUS_PAID,
            TICKET_STATUS_RESERVED,
            TICKET_STATUS_AVAILABLE,
            TICKET_STATUS_PAID,
            TICKET_STATUS_PAID,
            $id
        ]);

        $result = $stmt->fetch();
        if ($result) {
            // Cargar imágenes adicionales
            $sqlImg = "SELECT image_url, is_primary FROM raffle_images WHERE raffle_id = ? ORDER BY sort_order ASC";
            $stmtImg = $this->db->prepare($sqlImg);
            $stmtImg->execute([$id]);
            $result['images'] = $stmtImg->fetchAll();
        }
        return $result ?: null;
    }

    /**
     * Crear rifa con cálculo automático del cobro a la plataforma.
     *
     * Dos modos (setting `billing_mode`), ambos sobre la misma tubería de
     * commission_amount/commission_due_date/commission_paid:
     *  - 'commission': porcentaje sobre el valor total de la rifa
     *    (commission_percentage), el modo histórico.
     *  - 'talonario': tarifa PLANA por talonario creado (talonario_fee) — un
     *    talonario = la rifa completa con su rango de números (p. ej. 2
     *    cifras 00-99). Independiente del precio y ventas.
     * `commission_enabled` sigue siendo el interruptor maestro on/off.
     */
    public function createRaffle(array $data): int
    {
        $commissionEnabled = $this->getSettingValue('commission_enabled');

        if ($commissionEnabled) {
            $mode = $this->getSettingValue('billing_mode') ?: 'commission';
            if ($mode === 'talonario') {
                $data['commission_amount'] = (float)($this->getSettingValue('talonario_fee') ?: 0);
            } else {
                $commissionPercentage = $this->getSettingValue('commission_percentage');
                $totalValue = $data['ticket_price'] * $data['total_tickets'];
                $data['commission_amount'] = $totalValue * ($commissionPercentage / 100);
            }
            $data['commission_due_date'] = date('Y-m-d H:i:s',
                strtotime($data['draw_date'] . ' -8 days'));
        }

        // Crear rifa
        return $this->create($data);
    }

    /**
     * Bloquear rifa
     */
    public function blockRaffle(int $id, string $reason = ''): bool
    {
        return $this->update($id, [
            'status' => RAFFLE_STATUS_BLOCKED,
        ]);
    }

    /**
     * Desbloquear rifa
     */
    public function unblockRaffle(int $id): bool
    {
        return $this->update($id, [
            'status' => RAFFLE_STATUS_ACTIVE,
        ]);
    }

    /**
     * Marcar comisión como pagada
     */
    public function markCommissionAsPaid(int $id, string $paymentReference): bool
    {
        return $this->update($id, [
            'commission_paid' => true,
            'commission_payment_date' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Obtener rifas con comisión pendiente
     */
    public function getRafflesWithPendingCommission(): array
    {
        $sql = "SELECT * FROM raffles
                WHERE status = ?
                  AND commission_paid = FALSE
                  AND commission_due_date <= NOW()";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([RAFFLE_STATUS_ACTIVE]);
        return $stmt->fetchAll();
    }

    /**
     * Obtener setting del sistema
     */
    private function getSettingValue(string $key)
    {
        $sql = "SELECT setting_value, data_type FROM system_settings WHERE setting_key = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$key]);
        $result = $stmt->fetch();

        if (!$result) {
            return null;
        }

        // Convertir según tipo
        switch ($result['data_type']) {
            case 'boolean':
                return (bool)$result['setting_value'];
            case 'integer':
                return (int)$result['setting_value'];
            case 'decimal':
                return (float)$result['setting_value'];
            case 'json':
                return json_decode($result['setting_value'], true);
            default:
                return $result['setting_value'];
        }
    }

    /**
     * Incrementar vistas
     */
    public function incrementViews(int $id): bool
    {
        $sql = "UPDATE raffles SET views = views + 1 WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }

    /**
     * Incrementar compartidos
     */
    public function incrementShares(int $id): bool
    {
        $sql = "UPDATE raffles SET shares = shares + 1 WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }

    /**
     * Añadir imágenes a una rifa
     */
    public function addRaffleImages(int $raffleId, array $images): bool
    {
        if (empty($images)) return true;
        
        $sql = "INSERT INTO raffle_images (raffle_id, image_url, is_primary, sort_order) VALUES (?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        
        foreach ($images as $index => $url) {
            $isPrimary = ($index === 0) ? 1 : 0;
            $stmt->execute([$raffleId, $url, $isPrimary, $index]);
        }
        
        return true;
    }
}
