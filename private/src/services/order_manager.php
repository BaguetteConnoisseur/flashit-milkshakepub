<?php

class OrderManager {
    private PDO $db;
    private int $eventId;

    public function __construct(PDO $db, int $eventId) {
        $this->db = $db;
        $this->eventId = $eventId;
    }

    public function getOrdersWithSummaries(): array {
        $stmt = $this->db->prepare("SELECT * FROM orders WHERE event_id = ? ORDER BY created_at DESC");
        $stmt->execute([$this->eventId]);
        $orders = $stmt->fetchAll();

        foreach ($orders as &$order) {
            $order['summary'] = $this->getOrderSummary((int) $order['order_id']);
        }
        unset($order);

        return $orders;
    }

    public function getOrderById(int $orderId): ?array {
        $stmt = $this->db->prepare("SELECT * FROM orders WHERE order_id = ? AND event_id = ?");
        $stmt->execute([$orderId, $this->eventId]);
        $order = $stmt->fetch();

        return $order ?: null;
    }

    public function getOrderItems(int $orderId): array {
        $stmt = $this->db->prepare("SELECT oi.*, mi.name, mi.category FROM order_items oi JOIN menu_items mi ON oi.item_id = mi.item_id WHERE oi.order_id = ?");
        $stmt->execute([$orderId]);

        return $stmt->fetchAll();
    }

    public function getEventOrders(): array {
        return $this->fetchOrdersWithItems(false);
    }

    public function getActiveOrders(): array {
        $orders = $this->fetchOrdersWithItems(true);

        foreach ($orders as &$order) {
            $order['items'] = array_values(array_filter($order['items'], function ($item) {
                return ($item['status'] ?? null) !== 'Delivered';
            }));
        }
        unset($order);

        return array_values(array_filter($orders, function ($order) {
            return ($order['status'] ?? null) !== 'Delivered';
        }));
    }

    public function createOrder(array $request, string $defaultCustomerName = 'Guest'): int {
        $itemsToAdd = $request['items'] ?? [];
        if (empty($itemsToAdd)) {
            throw new InvalidArgumentException('No items in order');
        }

        $slugs = [];
        foreach ($itemsToAdd as $item) {
            if (is_array($item) && isset($item['slug'])) {
                $slugs[] = $item['slug'];
            } elseif (is_string($item)) {
                $slugs[] = $item;
            }
        }

        if (empty($slugs)) {
            throw new InvalidArgumentException('No valid item slugs');
        }

        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare('SELECT COALESCE(MAX(order_number), 0) + 1 AS next_num FROM orders WHERE event_id = ?');
            $stmt->execute([$this->eventId]);
            $nextOrderNumber = (int) $stmt->fetchColumn();

            $customerName = $request['customer_name'] ?? $defaultCustomerName;
            $orderComment = $request['order_comment'] ?? null;

            $stmt = $this->db->prepare('INSERT INTO orders (event_id, order_number, customer_name, order_comment) VALUES (?, ?, ?, ?)');
            $stmt->execute([$this->eventId, $nextOrderNumber, $customerName, $orderComment]);
            $orderId = (int) $this->db->lastInsertId();

            $placeholders = implode(',', array_fill(0, count($slugs), '?'));
            $stmt = $this->db->prepare("SELECT item_id, slug FROM menu_items WHERE slug IN ($placeholders)");
            $stmt->execute($slugs);

            $slugToId = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $slugToId[$row['slug']] = $row['item_id'];
            }

            $orderItemsValues = [];
            foreach ($itemsToAdd as $item) {
                if (is_array($item) && isset($item['slug']) && isset($slugToId[$item['slug']])) {
                    $comment = $item['comment'] ?? '';
                    $orderItemsValues[] = '(' . implode(',', [
                        $this->db->quote($orderId),
                        $this->db->quote($slugToId[$item['slug']]),
                        $this->db->quote('Pending'),
                        $this->db->quote($comment)
                    ]) . ')';
                } elseif (is_string($item) && isset($slugToId[$item])) {
                    $orderItemsValues[] = '(' . implode(',', [
                        $this->db->quote($orderId),
                        $this->db->quote($slugToId[$item]),
                        $this->db->quote('Pending'),
                        $this->db->quote('')
                    ]) . ')';
                }
            }

            if (empty($orderItemsValues)) {
                throw new InvalidArgumentException('No valid items found');
            }

            $sql = 'INSERT INTO order_items (order_id, item_id, status, item_comment) VALUES ' . implode(',', $orderItemsValues);
            $this->db->exec($sql);

            $this->db->commit();
            return $orderId;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    public function updateOrderStatus(int $orderId, string $status): void {
        $validStatuses = ['Pending', 'In Progress', 'Done', 'Delivered'];
        if (!in_array($status, $validStatuses, true)) {
            throw new InvalidArgumentException('Invalid status');
        }

        if ($status === 'Delivered') {
            $this->db->beginTransaction();
            try {
                $stmt = $this->db->prepare('UPDATE orders SET status = ? WHERE order_id = ? AND event_id = ?');
                $stmt->execute([$status, $orderId, $this->eventId]);

                $stmt = $this->db->prepare("UPDATE order_items SET status = 'Delivered' WHERE order_id = ? AND status = 'Done'");
                $stmt->execute([$orderId]);

                $this->db->commit();
                return;
            } catch (Throwable $e) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }

                throw $e;
            }
        }

        if ($status === 'Done') {
            $this->db->beginTransaction();
            try {
                $stmt = $this->db->prepare('UPDATE orders SET status = ? WHERE order_id = ? AND event_id = ?');
                $stmt->execute([$status, $orderId, $this->eventId]);

                $stmt = $this->db->prepare("UPDATE order_items SET status = 'Done' WHERE order_id = ? AND status = 'Delivered'");
                $stmt->execute([$orderId]);

                $this->db->commit();
                return;
            } catch (Throwable $e) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }

                throw $e;
            }
        }

        $stmt = $this->db->prepare('UPDATE orders SET status = ? WHERE order_id = ? AND event_id = ?');
        $stmt->execute([$status, $orderId, $this->eventId]);
    }

    public function updateOrderItemStatus(int $itemId, string $status): int {
        $allowedStatuses = ['Pending', 'In Progress', 'Done', 'Delivered'];
        if (!in_array($status, $allowedStatuses, true)) {
            throw new InvalidArgumentException('Invalid status');
        }

        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare(
                'SELECT oi.order_id
                 FROM order_items oi
                 JOIN orders o ON o.order_id = oi.order_id
                 WHERE oi.order_item_id = ? AND o.event_id = ?
                 LIMIT 1'
            );
            $stmt->execute([$itemId, $this->eventId]);
            $orderId = $stmt->fetchColumn();

            if (!$orderId) {
                throw new RuntimeException('Order item not found for active event');
            }

            $stmt = $this->db->prepare('UPDATE order_items SET status = ? WHERE order_item_id = ?');
            $stmt->execute([$status, $itemId]);

            $stmt = $this->db->prepare('SELECT status FROM order_items WHERE order_id = ?');
            $stmt->execute([(int) $orderId]);
            $statuses = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if ($statuses) {
                if (count($statuses) > 0 && count(array_unique($statuses)) === 1 && $statuses[0] === 'Delivered') {
                    $newStatus = 'Delivered';
                } elseif (
                    count($statuses) > 0 &&
                    !in_array('Pending', $statuses, true) &&
                    !in_array('In Progress', $statuses, true) &&
                    array_reduce($statuses, function ($carry, $currentStatus) {
                        return $carry && ($currentStatus === 'Done' || $currentStatus === 'Delivered');
                    }, true)
                ) {
                    $newStatus = 'Done';
                } elseif (in_array('In Progress', $statuses, true) || in_array('Done', $statuses, true)) {
                    $newStatus = 'In Progress';
                } else {
                    $newStatus = 'Pending';
                }

                $stmt = $this->db->prepare('UPDATE orders SET status = ? WHERE order_id = ?');
                $stmt->execute([$newStatus, (int) $orderId]);
            }

            $this->db->commit();
            return (int) $orderId;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    public function updateOrder(int $orderId, string $mainStatus, string $mainComment, array $itemStatuses, array $itemComments): void {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare("UPDATE orders SET status = ?, order_comment = ? WHERE order_id = ? AND event_id = ?");
            $stmt->execute([$mainStatus, $mainComment, $orderId, $this->eventId]);

            foreach ($itemStatuses as $itemId => $status) {
                $comment = $itemComments[$itemId] ?? '';
                $stmt = $this->db->prepare("UPDATE order_items SET status = ?, item_comment = ? WHERE order_item_id = ?");
                $stmt->execute([$status, $comment, $itemId]);
            }

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    public function deleteOrder(int $orderId): void {
        $this->db->beginTransaction();

        try {
            $this->db->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$orderId]);
            $this->db->prepare("DELETE FROM orders WHERE order_id = ? AND event_id = ?")->execute([$orderId, $this->eventId]);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    private function getOrderSummary(int $orderId): string {
        $stmt = $this->db->prepare("SELECT mi.name, COUNT(*) as qty FROM order_items oi JOIN menu_items mi ON oi.item_id = mi.item_id WHERE oi.order_id = ? GROUP BY mi.item_id, mi.name");
        $stmt->execute([$orderId]);

        $summary = [];
        foreach ($stmt->fetchAll() as $row) {
            $summary[] = $row['name'] . ($row['qty'] > 1 ? " (x{$row['qty']})" : '');
        }

        return implode(', ', $summary);
    }

    private function fetchOrdersWithItems(bool $includeOrderStatus = true): array {
        $selectOrderStatus = $includeOrderStatus ? 'o.status,' : '';
        $sql = "
            SELECT
                o.order_id, o.created_at, o.customer_name, o.order_comment, o.order_number, {$selectOrderStatus}
                COALESCE(JSON_ARRAYAGG(
                    CASE WHEN oi.order_item_id IS NOT NULL THEN
                        JSON_OBJECT(
                            'order_item_id', oi.order_item_id,
                            'status', oi.status,
                            'comment', oi.item_comment,
                            'item_id', oi.item_id,
                            'name', mi.name,
                            'category', mi.category
                        )
                    END
                ), JSON_ARRAY()) AS items
            FROM orders o
            LEFT JOIN order_items oi ON o.order_id = oi.order_id
            LEFT JOIN menu_items mi ON oi.item_id = mi.item_id
            WHERE o.event_id = :event_id
            GROUP BY o.order_id
            ORDER BY o.created_at ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['event_id' => $this->eventId]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($orders as &$order) {
            $order['items'] = json_decode($order['items'], true) ?: [];
        }
        unset($order);

        return $orders;
    }
}