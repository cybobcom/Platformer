<?php

//
$controller = CBinitController('Address');

//
$controller->validateRequired(['id']);

//
if (!$controller->load($_REQUEST['id'])) {
    $controller->errorResponse('Item not found', 404);
}

if (!$controller->checkPermission("admin")) {
    $controller->errorResponse('Permission denied', 403);
}



//
$res = $controller->delete($_REQUEST['id']);

//
if ($res) {
    $controller->successResponse(['id' => $_REQUEST['id']]);
} else {
    $controller->errorResponse('Error deleting', 500);
}

?>