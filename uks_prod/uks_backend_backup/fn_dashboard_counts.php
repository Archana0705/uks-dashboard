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

    /* ----- Helper ----- */
    function emptyToNull($v) {
        return ($v === '' || $v === 'null') ? null : $v;
    }

    /* ----- Params ----- */
    $district_id = emptyToNull($p['district_id'] ?? null);
    $taluk_id    = emptyToNull($p['taluk_id'] ?? null);
    $village_id  = emptyToNull($p['village_id'] ?? null);
    $shopcode    = emptyToNull($p['shopcode'] ?? null);

    $gender      = emptyToNull($p['gender'] ?? null);
    $age_from    = emptyToNull($p['age_from'] ?? null);
    $age_to      = emptyToNull($p['age_to'] ?? null);

    $from_date   = emptyToNull($p['from_date'] ?? null);
    $to_date     = emptyToNull($p['to_date'] ?? null);

    $status      = emptyToNull($p['status'] ?? null); 
    // PENDING | COMPLETED | REJECTED | CLOSED

    /* ----- SQL ----- */
    $sql = "
        SELECT *
        FROM fn_dashboard_counts(
            :district_id,
            :taluk_id,
            :village_id,
            :shopcode,
            :gender,
            :age_from,
            :age_to,
            :from_date,
            :to_date,
            :status
        )
    ";

    $stmt = $read_db->prepare($sql);

    $stmt->bindValue(':district_id', $district_id, $district_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindValue(':taluk_id',    $taluk_id,    $taluk_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindValue(':village_id',  $village_id,  $village_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindValue(':shopcode',    $shopcode,    $shopcode === null ? PDO::PARAM_NULL : PDO::PARAM_STR);

    $stmt->bindValue(':gender',    $gender,    $gender === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':age_from',  $age_from,  $age_from === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindValue(':age_to',    $age_to,    $age_to === null ? PDO::PARAM_NULL : PDO::PARAM_INT);

    $stmt->bindValue(':from_date', $from_date, $from_date === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':to_date',   $to_date,   $to_date === null ? PDO::PARAM_NULL : PDO::PARAM_STR);

    $stmt->bindValue(':status',    $status,    $status === null ? PDO::PARAM_NULL : PDO::PARAM_STR);

    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => $rows ? 1 : 0,
        'message' => $rows ? 'Dashboard counts fetched' : 'No data found',
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
