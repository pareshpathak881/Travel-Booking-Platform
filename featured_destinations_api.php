<?php
declare(strict_types=1);

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/security.php';

try {
    $db = getPDO();

    $idParam = $_GET['id'] ?? '';
    $id = $idParam === '' ? 0 : (int)$idParam;

    if ($id > 0) {
        $stmt = $db->prepare(
            "SELECT destination_id, name, description, hero_image_url, image_url, map_embed_url, state,
                    youtube_url, gallery_images
             FROM featured_destinations
             WHERE destination_id = :id AND is_active = 1
             LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Featured destination not found.']);
            exit;
        }

        $row['gallery_images'] = $row['gallery_images'] ? json_decode($row['gallery_images'], true) : [];
        echo json_encode(['status' => 'success', 'destination' => $row]);
        exit;
    }

    $stmt = $db->prepare(
        "SELECT destination_id, name, description, hero_image_url, image_url, map_embed_url, state,
                youtube_url, gallery_images, is_active, sort_order
         FROM featured_destinations
         WHERE is_active = 1
         ORDER BY sort_order ASC
         LIMIT 20"
    );
    $stmt->execute();
    $rows = $stmt->fetchAll();

    foreach ($rows as &$r) {
        $r['gallery_images'] = $r['gallery_images'] ? json_decode($r['gallery_images'], true) : [];
    }

    echo json_encode(['status' => 'success', 'destinations' => $rows]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}

