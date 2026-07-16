<?php

header('Content-Type: application/json');

echo json_encode([
    'status' => 'success',
    'message' => 'SWCS API Ready'
]);