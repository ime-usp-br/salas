<?php

/**
 * Teste de conexão com o Replicado USP
 *
 * Este script testa a conexão com o banco de dados replicado da USP
 * usando as credenciais definidas no arquivo .env
 *
 * Uso: php test_replicado.php
 */

require __DIR__ . '/vendor/autoload.php';

// Carrega variáveis de ambiente
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Lê configurações do .env
$host = $_ENV['REPLICADO_HOST'] ?? 'localhost';
$port = $_ENV['REPLICADO_PORT'] ?? '1433';
$database = $_ENV['REPLICADO_DATABASE'] ?? 'replicado';
$username = $_ENV['REPLICADO_USERNAME'] ?? '';
$password = $_ENV['REPLICADO_PASSWORD'] ?? '';

echo "Testando conexão com o Replicado USP...\n";
echo "Host: $host:$port\n";
echo "Database: $database\n";
echo "Username: " . substr($username, 0, 3) . "***\n\n";

try {
    // Tenta conectar usando dblib (FreeTDS)
    $dsn = "dblib:host=$host:$port;dbname=$database";

    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "✅ Conexão estabelecida com sucesso!\n\n";

    // Testa uma query simples (sem expor dados pessoais)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM PESSOA");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "Teste de query:\n";
    echo "  - Total de registros na tabela PESSOA: " . number_format($result['total'], 0, ',', '.') . "\n";

    // Testa mais uma query para verificar se consegue ler dados
    $stmt = $pdo->query("SELECT TOP 1 codpes FROM PESSOA WHERE codpes > 1000000 ORDER BY codpes");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        echo "  - Primeiro código de pessoa encontrado: " . $result['codpes'] . "\n";
    }

    echo "\n✅ Teste concluído com sucesso!\n";
    echo "✅ A extensão pdo_dblib (FreeTDS) está funcionando corretamente.\n";

} catch (PDOException $e) {
    echo "❌ Erro na conexão: " . $e->getMessage() . "\n";
    echo "\nDetalhes do erro:\n";
    echo "  Código: " . $e->getCode() . "\n";

    if (strpos($e->getMessage(), 'could not find driver') !== false) {
        echo "\n💡 Dica: A extensão pdo_dblib não está instalada.\n";
        echo "   Certifique-se de que o pacote php-sybase está instalado.\n";
    }

    exit(1);
}
