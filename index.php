<?php
echo "cURL: " . curl_version()["version"] . "<br>";
echo "SSL Version: " . curl_version()["ssl_version"] . "<br>";
?>



<?php
$ch = curl_init("https://api-pix-h.gerencianet.com.br");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
if (curl_errno($ch)) {
    echo "Erro: " . curl_error($ch);
} else {
    echo "Conseguiu conectar! Resposta: " . substr($response, 0, 200);
}
curl_close($ch);
?>






<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo "Usuário não autenticado";
    exit();
}

/**
 * CONFIGURAÇÕES DO GERENCIANET (EFI)
 * Troque pelas suas credenciais geradas no painel.
 */
$ambiente = "sandbox"; // 👉 troque para "producao" quando for usar em produção

$credenciais = [
    "sandbox" => [
        "client_id"     => "Client_Id_794153c06738365f80c8f81c7adc5df3c6fb1a60",
        "client_secret" => "Client_Secret_53b030ec1345a1adc4ee693650bb8b6a133e0006",
        "url"           => "https://api-pix-h.gerencianet.com.br"
    ],
    "producao" => [
        "client_id"     => "Client_Id_4afdb4d8669bb217f30625d791270f89fab243af",
        "client_secret" => "Client_Secret_23f72b6e369bc73bde15ba08c676510320ea216a",
        "url"           => "https://api-pix.gerencianet.com.br"
    ]
];

$config = $credenciais[$ambiente];

/**
 * DADOS DO USUÁRIO
 */
$usuarioId    = $_SESSION['usuario_id'];
$usuarioNome  = $_SESSION['usuario_nome'];
$usuarioEmail = $_SESSION['usuario_email'];

/**
 * 1. AUTENTICAR NA API
 */
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $config["url"] . "/oauth/token");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_USERPWD, $config["client_id"] . ":" . $config["client_secret"]);
curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/x-www-form-urlencoded"]);


$response = curl_exec($ch);
if (curl_errno($ch)) {
    echo "Erro cURL (auth): " . curl_error($ch);
    exit();
}
$auth = json_decode($response, true);
curl_close($ch);

if (!isset($auth["access_token"])) {
    echo "Erro ao autenticar na Gerencianet:<br>";
    print_r($auth);
    exit();
}

$accessToken = $auth["access_token"];

/**
 * 2. CRIAR COBRANÇA IMEDIATA (PIX)
 */
$payload = [
    "calendario" => [
        "expiracao" => 3600 // expira em 1h
    ],
    "devedor" => [
        "nome" => $usuarioNome,
        "cpf"  => "06676620146" // 👉 aqui precisa de CPF válido
    ],
    "valor" => [
        "original" => "2.99" // valor do pagamento
    ],
    "chave" => "6ee8acc4-ea8f-4967-9ef4-61d6709d7cf8", // 👉 sua chave PIX cadastrada na Gerencianet
    "solicitacaoPagador" => "Pagamento do Plano 30 dias - CineMovie (UserID: $usuarioId)"
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $config["url"] . "/v2/cob");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Bearer " . $accessToken
]);

$response = curl_exec($ch);
if (curl_errno($ch)) {
    echo "Erro cURL (cob): " . curl_error($ch);
    exit();
}
$cobranca = json_decode($response, true);
curl_close($ch);

if (!isset($cobranca["loc"]["id"])) {
    echo "Erro ao criar cobrança PIX:<br>";
    print_r($cobranca);
    exit();
}

/**
 * 3. GERAR QR CODE
 */
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $config["url"] . "/v2/loc/" . $cobranca["loc"]["id"] . "/qrcode");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer " . $accessToken
]);

$response = curl_exec($ch);
if (curl_errno($ch)) {
    echo "Erro cURL (qrcode): " . curl_error($ch);
    exit();
}
$qrcode = json_decode($response, true);
curl_close($ch);

// Retorna JSON com o QR Code (imagem base64 e copia e cola)
header("Content-Type: application/json");
echo json_encode($qrcode);
