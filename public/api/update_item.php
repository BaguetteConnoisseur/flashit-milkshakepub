<?php

require "../../src/db.php";
require "../../src/broadcast.php";

$data = json_decode(file_get_contents("php://input"), true);

$item_id = $data["item_id"];
$status = $data["status"];

$db = db();

$stmt = $db->prepare("
UPDATE order_items
SET status=?
WHERE id=?
");

$stmt->execute([$status,$item_id]);

broadcast([
    "type"=>"item_updated",
    "item_id"=>$item_id,
    "status"=>$status
]);

echo json_encode(["ok"=>true]);