<?php
require_once('../../../helper/header_dashboard.php');
require_once('../../../helper/log_file.php');
require_once('../../../config/read_database.php');

header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json");

if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
}
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

try {

    /* =======================
       METHOD CHECK
    ======================= */
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new Exception('Method Not Allowed');
    }

    /* =======================
       SQL FUNCTION CALL
    ======================= */
    $sql = "
        SELECT *
        FROM analytics.fn_scheme_filter()
    ";

    $stmt = $read_db->prepare($sql);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* =======================
       RESPONSE
    ======================= */
    echo json_encode([
        'success' => $rows ? 1 : 0,
        'message' => $rows ? 'Scheme list fetched' : 'No schemes found',
        'data'    => $rows ? encryptData($rows) : [],
        'raw'     => $rows   // ?? debug
    ]);

} catch (Throwable $e) {

    http_response_code(400);
    echo json_encode([
        'success' => 0,
        'message' => $e->getMessage(),
        'data'    => []
    ]);

} finally {
    $read_db = null;
}
?>
