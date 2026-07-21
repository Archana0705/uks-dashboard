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

    /* -- Inputs ------------------------------- */
    $district_id = emptyToNull($payload['district_id'] ?? null);
    $taluk_id    = emptyToNull($payload['taluk_id'] ?? null);
    $village_id  = emptyToNull($payload['village_id'] ?? null);
    $pds_id      = emptyToNull($payload['pds_id'] ?? null);

    /* -- SQL Call ----------------------------- */
    $sql = "
        SELECT *
        FROM analytics.get_district_taluk_village_shop_wise_counts(
            :district_id,
            :taluk_id,
            :village_id,
            :pds_id
        )
    ";

    $stmt = $read_db->prepare($sql);

    $stmt->bindValue(':district_id', $district_id, $district_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindValue(':taluk_id',    $taluk_id,    $taluk_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindValue(':village_id',  $village_id,  $village_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindValue(':pds_id',      $pds_id,      $pds_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);

    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* -- Response ----------------------------- */
    echo json_encode([
        'success' => $rows ? 1 : 0,
        'message' => $rows
            ? 'District / Taluk / Village / Shop wise counts fetched'
            : 'No data found',
        'data'    => $rows ? encryptData($rows) : [],
        'raw'     => $rows // for debugging
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
