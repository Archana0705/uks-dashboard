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
       INPUT
    ======================= */
    $district_id = null;
    $page  = 1;
    $limit = 10;

    if (!empty($_POST['data'])) {

        $p = decryptData($_POST['data']);
        if (!$p || !is_array($p)) {
            throw new Exception('Invalid payload');
        }

        // district_id (optional)
        if (
            array_key_exists('district_id', $p) &&
            $p['district_id'] !== '' &&
            $p['district_id'] !== 'null' &&
            $p['district_id'] !== null
        ) {
            $district_id = (int)$p['district_id'];
        }

        // pagination
        $page  = isset($p['page'])  && (int)$p['page']  > 0 ? (int)$p['page']  : 1;
        $limit = isset($p['limit']) && (int)$p['limit'] > 0 ? (int)$p['limit'] : 10;
    }

    $offset = ($page - 1) * $limit;

    /* =======================
       TOTAL COUNT
    ======================= */
    $countSql = "
        SELECT COUNT(*)
        FROM analytics.fn_suggestionformremarks_by_district(:district_id)
    ";

    $countStmt = $read_db->prepare($countSql);
    $countStmt->bindValue(
        ':district_id',
        $district_id,
        $district_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT
    );
    $countStmt->execute();
    $total_records = (int)$countStmt->fetchColumn();

    /* =======================
       DATA QUERY (PAGINATED)
    ======================= */
    $sql = "
        SELECT *
        FROM analytics.fn_suggestionformremarks_by_district(:district_id)
        ORDER BY district_id, rank_no
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $read_db->prepare($sql);

    $stmt->bindValue(
        ':district_id',
        $district_id,
        $district_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT
    );
    $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* =======================
       RESPONSE
    ======================= */
    echo json_encode([
        'success' => 1,
        'message' => 'Suggestion remarks fetched',
        'page'    => $page,
        'limit'   => $limit,
        'total_records' => $total_records,
        'total_pages'   => ceil($total_records / $limit),
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
