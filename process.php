<?php
/**
 * process.php
 * Payment Gateway - API Proxy Handler
 * Tanpa database, hanya meneruskan request ke Payment Gateway API
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Hanya terima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'msg' => 'Method not allowed']);
    exit;
}

// ==========================================
// KONFIGURASI — Ganti URL endpoint API Anda
// ==========================================
define('PAYMENT_API_URL', 'https://bayarin.cekstore.com/api/payment');
define('API_ID', '803b8a4ce56d8e5d');
define('API_KEY', 'aa5a04721c1e1d4a83ff57ecf3ea7eca5081d873004f2f51a8d798de6edc9e07');
// ==========================================

// Baca body JSON dari request Alpine/Axios
$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!$body) {
    http_response_code(400);
    echo json_encode(['success' => false, 'msg' => 'Request body tidak valid']);
    exit;
}

// --- Ambil field dari body ---
$api_id         = API_ID;
$api_key        = API_KEY;
$reference_id   = (string) rand(100000000, 999999999);
$bank_code      = trim($body['bank_code']      ?? '');
$amount         = trim($body['amount']         ?? '');
$customer_name  = trim($body['customer_name']  ?? '');
$customer_email = trim($body['customer_email'] ?? '');
$customer_phone = trim($body['customer_phone'] ?? '');
$payment_guide  = trim($body['payment_guide']  ?? 'false');
$item_details   = trim($body['item_details']   ?? '');

// --- Validasi wajib ---
$required = [
    'bank_code'      => $bank_code,
    'amount'         => $amount,
    'customer_name'  => $customer_name,
    'customer_email' => $customer_email,
    'customer_phone' => $customer_phone,
    'item_details'   => $item_details,
];

foreach ($required as $field => $value) {
    if ($value === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'msg' => "Field '$field' wajib diisi"]);
        exit;
    }
}

if (!is_numeric($amount) || intval($amount) < 1) {
    http_response_code(422);
    echo json_encode(['success' => false, 'msg' => 'Amount tidak valid']);
    exit;
}

if (!filter_var($customer_email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'msg' => 'Format email tidak valid']);
    exit;
}

// --- Generate Signature: md5(API_ID + API_KEY + REFERENCE_ID) ---
$signature = md5($api_id . $api_key . $reference_id);

// --- Siapkan payload untuk dikirim ke Payment API ---
$payload = [
    'api_id'         => $api_id,
    'api_key'        => $api_key,
    'signature'      => $signature,
    'reference_id'   => $reference_id,
    'bank_code'      => $bank_code,
    'amount'         => $amount,
    'customer_name'  => $customer_name,
    'customer_email' => $customer_email,
    'customer_phone' => $customer_phone,
    'payment_guide'  => $payment_guide,
    'item_details'   => $item_details,
];

// --- Kirim ke Payment Gateway via cURL ---
$ch = curl_init(PAYMENT_API_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Accept: application/json',
    ],
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response    = curl_exec($ch);
$http_code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error  = curl_error($ch);
curl_close($ch);

// --- Tangani error cURL ---
if ($curl_error) {
    http_response_code(400); // Diubah ke 400 agar tidak di-hijack oleh Cloudflare
    echo json_encode([
        'success' => false,
        'msg'     => 'Gagal menghubungi Payment Gateway: ' . $curl_error,
    ]);
    exit;
}

// --- Parse response dari Payment Gateway ---
$result = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400); // Diubah ke 400 agar tidak di-hijack oleh Cloudflare
    echo json_encode([
        'success' => false,
        'msg'     => 'Response Payment Gateway tidak valid (bukan JSON)',
        'raw'     => $response,
    ]);
    exit;
}

// --- Teruskan response ke frontend ---
// Jika HTTP code dari API adalah 5xx, kita ubah ke 400 agar Cloudflare tidak memblokirnya
if ($http_code >= 500) {
    http_response_code(400);
} else {
    http_response_code($http_code);
}
echo json_encode($result);
exit;