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

    /* =========================
       METHOD CHECK
    ========================= */
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new Exception('Method Not Allowed');
    }

    /* =========================
       INPUT (DEFAULT = 0)
    ========================= */
    $itypeid = 0;
    $district_id = 0;

    if (!empty($_POST['data'])) {
        $payload = decryptData($_POST['data']);

        if (!$payload || !is_array($payload)) {
            throw new Exception('Invalid payload');
        }

        if (array_key_exists('type_id', $payload)) {
            if (
                $payload['type_id'] === '' ||
                $payload['type_id'] === null ||
                $payload['type_id'] === 'null' ||
                is_array($payload['type_id'])
            ) {
                $itypeid = 0;
            } else {
                $itypeid = (int)$payload['type_id'];
            }
        }
        if (array_key_exists('district_id', $payload)) {
            if (
                $payload['district_id'] === '' ||
                $payload['district_id'] === null ||
                $payload['district_id'] === 'null' ||
                is_array($payload['district_id'])
            ) {
                $district_id = 0;
            } else {
                $district_id = (int)$payload['district_id'];
            }
        }
    }

    /* =========================
       FUNCTION CALL
    ========================= */
    $sql = "
        SELECT *
        FROM analytics.fn_schemecategory_count(:itypeid, :district_id)
    ";

    $stmt = $read_db->prepare($sql);
    $stmt->bindValue(':itypeid', $itypeid, PDO::PARAM_INT);
    $stmt->bindValue(':district_id', $district_id, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* =========================
       RESPONSE
    ========================= */
    echo json_encode([
        'success' => $rows ? 1 : 0,
        'message' => $rows ? 'Scheme category count fetched' : 'No data found',
        'data'    => $rows ? encryptData($rows) : [],
        'raw'     => $rows   // useful for debugging
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
