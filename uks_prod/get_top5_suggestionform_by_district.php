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
       INPUT HANDLING
    ======================= */
    $district_id = null;

    if (!empty($_POST['data'])) {

        $p = decryptData($_POST['data']);
        if (!$p || !is_array($p)) {
            throw new Exception('Invalid payload');
        }

        if (
            !isset($p['district_id']) ||
            $p['district_id'] === '' ||
            $p['district_id'] === 'null' ||
            $p['district_id'] === null
        ) {
            $district_id = null;
        } else {
            $district_id = (int)$p['district_id'];
        }
    }

    /* =======================
       SQL CALL (IMPORTANT PART)
    ======================= */
    if ($district_id === null) {

        // 🔥 Call WITHOUT parameter
        $sql = "
            SELECT *
            FROM analytics.fn_top5_suggestionform_by_district()
        ";
        $stmt = $read_db->prepare($sql);

    } else {

        // 🔥 Call WITH parameter
        $sql = "
            SELECT *
            FROM analytics.fn_top5_suggestionform_by_district(:district_id)
        ";
        $stmt = $read_db->prepare($sql);
        $stmt->bindValue(':district_id', $district_id, PDO::PARAM_INT);
    }

    /* =======================
       EXECUTE
    ======================= */
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* =======================
       RESPONSE
    ======================= */
    echo json_encode([
        'success' => $rows ? 1 : 0,
        'message' => $rows
            ? 'Top 5 suggestion form data fetched'
            : 'No data found',
        'data' => $rows ? encryptData($rows) : [],
        'raw'  => $rows   // keep for debugging
    ]);

} catch (Throwable $e) {

    http_response_code(400);
    echo json_encode([
        'success' => 0,
        'message' => $e->getMessage(),
        'data' => []
    ]);

} finally {
    $read_db = null;
}
?>
