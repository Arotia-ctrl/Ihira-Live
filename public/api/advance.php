<?php
// Appelé par la page de diffusion à la fin du générique : passe à la scène que la régie avait préparée.
// Public, mais sans danger : ne fait que ce qui a déjà été décidé dans la régie (et seulement si le générique est bien à l'écran).
require __DIR__ . '/../../app/bootstrap.php';
session_write_close();
$in = json_decode(file_get_contents('php://input'), true) ?: [];
json_out(['ok' => promote_pending((int)($in['seq'] ?? 0), 2)]);
