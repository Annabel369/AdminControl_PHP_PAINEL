<?php

// 🔧 Configurações
define('RCON_HOST', '100.114.210.67'); // IP via Tailscale
define('RCON_PORT', 27018);            // Porta do jogo
define('RCON_PASSWORD', 'GZPWA3PyZ7zonPf');
define('RCON_TIMEOUT', 3);

function debug($msg) {
    echo "🛠️ [DEBUG] $msg<br>";
}

// 🔌 Conectar ao servidor
debug("Tentando conectar ao servidor RCON...");
$socket = @fsockopen(RCON_HOST, RCON_PORT, $errno, $errstr, RCON_TIMEOUT);

if (!$socket) {
    debug("Falha na conexão: $errstr (Erro $errno)");
    exit("❌ Conexão falhou.<br>");
}

stream_set_timeout($socket, 3, 0);
debug("Conexão estabelecida com sucesso.");

// ✏️ Função para escrever pacote RCON
function write_packet($socket, $id, $type, $body) {
    $packet = pack("VV", $id, $type) . $body . "\x00\x00";
    $size = strlen($packet);
    $packet = pack("V", $size) . $packet;
    fwrite($socket, $packet);
    debug("Pacote enviado: ID=$id, TYPE=$type, BODY=$body");
}

// 📥 Função para ler pacote RCON
function read_packet($socket) {
    $size_data = fread($socket, 4);
    if (strlen($size_data) < 4) {
        debug("Falha ao ler tamanho do pacote.");
        return false;
    }

    $size = unpack("V", $size_data)[1];
    $packet_data = fread($socket, $size);
    if (strlen($packet_data) < $size) {
        debug("Pacote incompleto recebido.");
        return false;
    }

    $packet = unpack("V1id/V1type/a*body", $packet_data);
    debug("Pacote recebido: ID={$packet['id']}, TYPE={$packet['type']}, BODY=" . htmlspecialchars($packet['body']));
    return $packet;
}

// 🔐 Autenticar com tolerância
$auth_id = rand(1, 1000);
write_packet($socket, $auth_id, 3, RCON_PASSWORD); // SERVERDATA_AUTH

$auth_success = false;
for ($i = 0; $i < 2; $i++) {
    $packet = read_packet($socket);
    if ($packet === false) break;
    if ($packet['type'] == 2 && $packet['id'] == $auth_id) {
        $auth_success = true;
        break;
    }
}

if (!$auth_success) {
    debug("Autenticação falhou. Verifique a senha RCON.");
    fclose($socket);
    exit("❌ Autenticação falhou.<br>");
}

debug("✅ Autenticado com sucesso!");

// 📤 Função para enviar comando e ler resposta
function executar_comando($socket, $comando) {
    $cmd_id = rand(2000, 3000);
    write_packet($socket, $cmd_id, 2, $comando); // SERVERDATA_EXECCOMMAND
    write_packet($socket, $cmd_id, 0, '');       // Pacote vazio para encerrar resposta

    $resposta = '';
    for ($i = 0; $i < 10; $i++) {
        $packet = read_packet($socket);
        if ($packet === false || $packet['body'] === '' || $packet['body'] === "\x01") {
            break;
        }
        $resposta .= $packet['body'];
    }

    echo "<br>📦 <strong>Comando:</strong> <code>$comando</code><br>";
    echo "✅ <strong>Resposta:</strong><pre>" . htmlspecialchars($resposta) . "</pre>";
}

// 🚀 Executar comandos
executar_comando($socket, "meta list");
executar_comando($socket, "css_plugins list");

// 🔌 Fechar conexão
fclose($socket);
debug("Conexão encerrada.");
?>