<?php

// Requerir cargador de entorno y servicio
require_once __DIR__ . '/env-loader.php';
require_once __DIR__ . '/FlowService.php';

use App\Services\FlowService;

// Obtener credenciales de variables de entorno
$apiKey = getenv('FLOW_API_KEY');
$secretKey = getenv('FLOW_SECRET_KEY');
$isSandbox = getenv('FLOW_SANDBOX') !== 'false'; // true por defecto

if (!$apiKey || !$secretKey) {
    echo "------------------------------------------------------------------------\n";
    echo "¡ATENCIÓN!\n";
    echo "No se encontraron las credenciales de Flow.cl en el entorno.\n";
    echo "1. Duplica el archivo .env.example y cámbiale el nombre a .env\n";
    echo "2. Rellena las variables FLOW_API_KEY y FLOW_SECRET_KEY con tus llaves.\n";
    echo "------------------------------------------------------------------------\n";
    exit(1);
}

// Instanciar el servicio (true para Sandbox, false para Producción)
$flow = new FlowService($apiKey, $secretKey, $isSandbox);

// Datos de prueba de la transacción
$paymentData = [
    'commerceOrder' => 'TEST-' . time(),                  // ID único de orden
    'subject' => 'Prueba de integración Flow PHP',       // Glosa/Descripción
    'amount' => 10,                                     // Monto
    'email' => 'clienteprueba@gmail.com',                  // Email ficticio
    'urlConfirmation' => 'https://httpbin.org/post',      // URL temporal para pruebas
    'urlReturn' => 'https://httpbin.org/get',             // URL temporal para pruebas
    'currency' => 'CLP'                                   // O 'PEN' si usas soles de Perú
];

try {
    echo "Iniciando solicitud a Flow Sandbox...\n";
    
    // Llamar al método para crear el pago
    $response = $flow->createPayment($paymentData);
    
    echo "------------------------------------------------------------\n";
    echo "¡CONEXIÓN EXITOSA CON SANDBOX DE FLOW.CL!\n";
    echo "------------------------------------------------------------\n";
    echo "Token de Transacción: " . $response['token'] . "\n";
    echo "Flow Order ID:        " . $response['flowOrder'] . "\n";
    echo "URL de Checkout:      " . $response['url'] . "?token=" . $response['token'] . "\n";
    echo "------------------------------------------------------------\n";
    echo "Copia y pega la URL de Checkout en tu navegador para realizar\n";
    echo "la simulación del pago usando las tarjetas de prueba.\n";
    echo "------------------------------------------------------------\n";

} catch (Exception $e) {
    echo "Error al conectar con Flow Sandbox: " . $e->getMessage() . "\n";
}
