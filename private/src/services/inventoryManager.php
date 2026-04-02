<?php

class InventoryManager {
    private $db;
    private $eventId;

    public function __construct(PDO $db, int $eventId) {
        $this->db = $db;
        $this->eventId = $eventId;
    }

    /**
     * Fetch items for the current event by category and active status.
     */
    public function getItemsByCategory(string $category, bool $activeOnly = true) {
        $sql = "SELECT mi.*, emi.is_active as event_active 
                FROM menu_items mi
                JOIN event_menu_items emi ON mi.item_id = emi.item_id
                WHERE emi.event_id = :event_id 
                AND mi.category = :category 
                AND emi.is_active = :is_active
                AND mi.is_archived = 0
                ORDER BY mi.item_id ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'event_id'  => $this->eventId,
            'category'  => $category,
            'is_active' => $activeOnly ? 1 : 0
        ]);
        return $stmt->fetchAll();
    }

    /**
     * Fetch a single item by its ID for the current event.
     */
    public function getItemById(int $itemId) {
        $sql = "SELECT mi.*, emi.is_active as event_active
                FROM menu_items mi
                JOIN event_menu_items emi ON mi.item_id = emi.item_id
                WHERE emi.event_id = :event_id
                  AND mi.item_id = :item_id
                  AND mi.is_archived = 0
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'event_id' => $this->eventId,
            'item_id' => $itemId
        ]);
        return $stmt->fetch();
    }

    /**
     * Add a new item to the system and link it to the current event.
     */
    public function addItem(array $data) {
        try {
            $this->db->beginTransaction();

            // 1. Generate a unique slug
            require_once(__DIR__ . '/../../../private/functions.php');
            $slug = generate_unique_slug($data['name']);

            // 2. Insert into Master List
            $sql = "INSERT INTO menu_items (slug, category, name, description, ingredients, color)
                    VALUES (:slug, :category, :name, :description, :ingredients, :color)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'slug'        => $slug,
                'category'    => $data['category'],
                'name'        => $data['name'],
                'description' => $data['description'] ?? null,
                'ingredients' => $data['ingredients'] ?? null,
                'color'       => $data['color'] ?? '#FFFFFF'
            ]);

            $itemId = $this->db->lastInsertId();

            // 3. Link to current Event
            $sql = "INSERT INTO event_menu_items (event_id, item_id, is_active)
                    VALUES (:event_id, :item_id, 1)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'event_id' => $this->eventId,
                'item_id'  => $itemId
            ]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Inventory Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Toggle item availability for the CURRENT event only.
     */
    public function toggleActive(int $itemId, bool $status) {
        $sql = "UPDATE event_menu_items 
                SET is_active = :status 
                WHERE event_id = :event_id AND item_id = :item_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'status'   => $status ? 1 : 0,
            'event_id' => $this->eventId,
            'item_id'  => $itemId
        ]);
    }

    /**
     * Update a menu item (name, description, ingredients, color, is_archived)
     */
    public function updateItem(int $itemId, array $data) {
        $fields = [];
        $params = [ 'item_id' => $itemId ];
        // Only allow these columns to be updated
        $allowed = ['name', 'description', 'ingredients', 'color', 'is_archived'];
        foreach ($allowed as $col) {
            if (isset($data[$col])) {
                $fields[] = "$col = :$col";
                $params[$col] = ($col === 'is_archived') ? ($data[$col] ? 1 : 0) : $data[$col];
            }
        }
        if (empty($fields)) {
            return false; // Nothing to update
        }
        $sql = 'UPDATE menu_items SET ' . implode(', ', $fields) . ' WHERE item_id = :item_id';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
}