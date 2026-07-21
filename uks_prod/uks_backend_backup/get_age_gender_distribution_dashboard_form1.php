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

    if (empty($_POST['data'])) {
        throw new Exception('Missing encrypted payload');
    }

    /* -- Decrypt Payload ---------------------- */
    $payload = decryptData($_POST['data']);
    if (!$payload || !is_array($payload)) {
        throw new Exception('Invalid payload');
    }

    /* -- Helper ------------------------------- */
    function emptyToNull($value) {
        return ($value === '' || $value === 'null') ? null : $value;
    }

    /* -- Input (optional filter) --------------- */
    // Pass district_id OR NULL
    $district_id = emptyToNull($payload['district_id'] ?? null);

    /* -- SQL Call ----------------------------- */
    $sql = "
        SELECT *
        FROM analytics.get_age_gender_distribution_json_arr(:district_id)
    ";

    $stmt = $read_db->prepare($sql);

    $stmt->bindValue(
        ':district_id',
        $district_id,
        $district_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT
    );

    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* -- Response ----------------------------- */
    echo json_encode([
        'success' => $rows ? 1 : 0,
        'message' => $rows
            ? 'Age & Gender distribution fetched'
            : 'No data found',
        'data'    => $rows ? encryptData($rows) : [],
        'raw'     => $rows // debugging
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
