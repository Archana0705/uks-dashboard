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

    /* -- Method Check ------------------------- */
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new Exception('Method Not Allowed');
    }

    /* -- Payload is optional here ------------- */
    if (!empty($_POST['data'])) {
        $payload = decryptData($_POST['data']);
        if ($payload === false) {
            throw new Exception('Invalid payload');
        }
    }

    /* -- SQL Call ----------------------------- */
    $sql = "
        SELECT *
        FROM analytics.fn_top5_schemes_by_state()
    ";

    $stmt = $read_db->prepare($sql);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* -- Response ----------------------------- */
    echo json_encode([
        'success' => $rows ? 1 : 0,
        'message' => $rows
            ? 'Top 5 schemes at state level fetched successfully'
            : 'No data found',
        'data'    => $rows ? encryptData($rows) : [],
        'raw'     => $rows   // for debugging
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
