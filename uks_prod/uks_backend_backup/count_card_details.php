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

define('DATE_FORMAT', 'Y-m-d H:i:s.u');

try {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new Exception('Method Not Allowed');
    }

    if (empty($_POST['data'])) {
        throw new Exception('Missing encrypted payload');
    }

    $p = decryptData($_POST['data']);
    if (!$p || !is_array($p)) {
        throw new Exception('Invalid payload');
    }

   function emptyToNull($value) {
    return ($value === '' || $value === 'null') ? null : $value;
    }

    $district_id = emptyToNull($p['district_id'] ?? null);
    $taluk_id    = emptyToNull($p['taluk_id'] ?? null);
    $village_id  = emptyToNull($p['village_id'] ?? null);
    $shopcode    = emptyToNull($p['shopcode'] ?? null);
    $type_ids = $p['type_ids'] ?? null;
    $condition   = strtoupper($p['condition'] ?? 'OR');

   if ($type_ids === '' || $type_ids === 'null') {
    $type_ids = null;
    }

    if ($type_ids !== null && !is_array($type_ids)) {
        throw new Exception('type_ids must be an array');
    }

    $pgTypeIds = null;
    if (is_array($type_ids) && count($type_ids) > 0) {
        $pgTypeIds = '{' . implode(',', array_map('intval', $type_ids)) . '}';
    }
    /* ── SQL Call ───────────────────────── */
    $sql = "
        SELECT * FROM get_family_card_summary(
            :district_id,
            :taluk_id,
            :village_id,
            :shopcode,
            :type_ids,
            :condition
        )
    ";

    $stmt = $read_db->prepare($sql);

    $stmt->bindValue(':district_id', $district_id, $district_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindValue(':taluk_id',    $taluk_id,    $taluk_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindValue(':village_id',  $village_id,  $village_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindValue(':shopcode',    $shopcode,    $shopcode === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(
    ':type_ids',
    $pgTypeIds,
    $pgTypeIds === null ? PDO::PARAM_NULL : PDO::PARAM_STR
);
    // $stmt->bindValue(':type_ids', $pgTypeIds, PDO::PARAM_STR); // ✅ correct way
    $stmt->bindValue(':condition', $condition, PDO::PARAM_STR);

    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
      
    echo json_encode([
        'success' => $rows ? 1 : 0,
        'message' => $rows ? 'Family card details fetched' : 'No data found',
        'data'    => $rows ? encryptData($rows) : [],
        'raw'     => $rows
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
