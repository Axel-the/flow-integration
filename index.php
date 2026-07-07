<?php
require_once __DIR__ . '/env-loader.php';
require_once __DIR__ . '/FlowService.php';

// Leer credenciales de variables de entorno (ideal para Render) o usar valores por defecto
$apiKey = getenv('FLOW_API_KEY') ?: 'TU_API_KEY_DE_SANDBOX';
$secretKey = getenv('FLOW_SECRET_KEY') ?: 'TU_SECRET_KEY_DE_SANDBOX';
$isSandbox = getenv('FLOW_SANDBOX') !== 'false'; // true por defecto

// Inicializar el servicio de Flow
$flow = new FlowService($apiKey, $secretKey, $isSandbox);

// Obtener la acción del query param
$action = $_GET['action'] ?? 'home';

// Determinar el protocolo y host para generar URLs absolutas (soporta SSL en proxies de Render/Cloudflare)
$protocol = 'http';
if (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
) {
    $protocol = 'https';
}
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$currentUrl = "$protocol://$host" . explode('?', $_SERVER['REQUEST_URI'])[0];

// =========================================================================
// ROUTING / ACCIONES
// =========================================================================

// ACCIÓN: Iniciar el pago
if ($action === 'pay') {
    try {
        $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_INT);
        if (!$amount || $amount <= 0) {
            throw new Exception("El monto del pago es obligatorio y debe ser un número entero mayor que cero.");
        }
        $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL) ?: 'cliente@prueba.com';
        $currency = filter_input(INPUT_POST, 'currency', FILTER_DEFAULT) ?: 'PEN';

        $paymentData = [
            'commerceOrder' => 'ORDER-' . time(),
            'subject' => 'Pago de Prueba',
            'amount' => $amount,
            'email' => $email,
            'urlConfirmation' => $currentUrl . '?action=confirm',
            'urlReturn' => $currentUrl . '?action=return',
            'currency' => $currency
        ];

        $result = $flow->createPayment($paymentData);
        // Redirigir al cliente al checkout de Flow
        header('Location: ' . $result['url'] . '?token=' . $result['token']);
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// ACCIÓN: Recibir Webhook de Confirmación (Servidor a Servidor)
if ($action === 'confirm') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo "Método no permitido";
        exit;
    }

    $token = $_POST['token'] ?? null;
    if (!$token) {
        http_response_code(400);
        echo "Token no especificado";
        exit;
    }

    try {
        $statusData = $flow->getPaymentStatus($token);
        
        // Registrar en un archivo local para visualizar la recepción del Webhook
        $logMessage = "[" . date('Y-m-d H:i:s') . "] Webhook recibido. Orden: " . $statusData['commerceOrder'] 
            . " | Monto: " . $statusData['amount'] . " " . $statusData['currency'] 
            . " | Estado Flow: " . $statusData['status'] . " (2 = Pagado)\n";
        
        file_put_contents(__DIR__ . '/payments.log', $logMessage, FILE_APPEND);
        
        echo "OK";
        exit;
    } catch (Exception $e) {
        file_put_contents(__DIR__ . '/payments.log', "[" . date('Y-m-d H:i:s') . "] Error Webhook: " . $e->getMessage() . "\n", FILE_APPEND);
        http_response_code(500);
        echo "Error: " . $e->getMessage();
        exit;
    }
}

// ACCIÓN: Retorno del cliente (Pantalla de Éxito o Fallo)
$paymentResult = null;
if ($action === 'return') {
    $token = $_POST['token'] ?? $_GET['token'] ?? null;
    if ($token) {
        try {
            $paymentResult = $flow->getPaymentStatus($token);
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pasarela Flow</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-gradient: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
            --panel-bg: rgba(30, 41, 59, 0.7);
            --border-color: rgba(255, 255, 255, 0.1);
            --primary: #f59e0b;
            --primary-hover: #d97706;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --success: #10b981;
            --danger: #ef4444;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg-gradient);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            width: 100%;
            max-width: 550px;
            background: var(--panel-bg);
            backdrop-filter: blur(16px);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            text-align: center;
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 20px;
        }
        .badge.sandbox {
            background: rgba(245, 158, 11, 0.2);
            color: var(--primary);
            border: 1px solid rgba(245, 158, 11, 0.3);
        }
        .badge.production {
            background: rgba(16, 185, 129, 0.2);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        h1 {
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 8px;
            background: linear-gradient(to right, #ffffff, var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .subtitle {
            color: var(--text-muted);
            font-size: 1rem;
            margin-bottom: 30px;
        }

        .form-group {
            text-align: left;
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        input, select {
            width: 100%;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 14px 16px;
            color: var(--text-main);
            font-family: inherit;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        input:focus, select:focus {
            outline: none;
            border-color: var(--primary);
        }

        .btn {
            width: 100%;
            background: var(--primary);
            color: #0f172a;
            border: none;
            border-radius: 12px;
            padding: 16px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s, transform 0.2s;
            margin-top: 10px;
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
        }

        .btn:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
        }

        .btn:active {
            transform: translateY(0);
        }

        .error-box {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid var(--danger);
            color: #f87171;
            padding: 14px;
            border-radius: 12px;
            font-size: 0.9rem;
            margin-bottom: 20px;
            text-align: left;
        }

        /* Result Screen Styles */
        .status-icon {
            font-size: 4rem;
            margin-bottom: 20px;
        }

        .status-success { color: var(--success); }
        .status-failed { color: var(--danger); }

        .details-list {
            background: rgba(15, 23, 42, 0.4);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 30px;
            text-align: left;
            border: 1px solid var(--border-color);
        }

        .details-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .details-item:last-child {
            border-bottom: none;
        }

        .details-label {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .details-value {
            font-weight: 600;
            font-size: 0.95rem;
        }

        .btn-secondary {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-main);
            box-shadow: none;
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-main);
        }
    </style>
</head>
<body>

<div class="container">
    <?php if ($action === 'return' && $paymentResult): ?>
        <!-- PANTALLA DE RESULTADO -->
        <?php 
        $status = (int)$paymentResult['status']; 
        $isPaid = ($status === 2);
        ?>
        
        <div class="status-icon <?php echo $isPaid ? 'status-success' : 'status-failed'; ?>">
            <?php echo $isPaid ? '✓' : '✗'; ?>
        </div>

        <h1><?php echo $isPaid ? '¡Pago Exitoso!' : 'Pago Fallido'; ?></h1>
        <p class="subtitle">Orden procesada a través de Flow.cl</p>

        <div class="details-list">
            <div class="details-item">
                <span class="details-label">Nro. de Orden:</span>
                <span class="details-value"><?php echo htmlspecialchars($paymentResult['commerceOrder']); ?></span>
            </div>
            <div class="details-item">
                <span class="details-label">Flow ID:</span>
                <span class="details-value"><?php echo htmlspecialchars($paymentResult['flowOrder']); ?></span>
            </div>
            <div class="details-item">
                <span class="details-label">Monto:</span>
                <span class="details-value"><?php echo number_format($paymentResult['amount']); ?> <?php echo htmlspecialchars($paymentResult['currency']); ?></span>
            </div>
            <div class="details-item">
                <span class="details-label">Email Pagador:</span>
                <span class="details-value"><?php echo htmlspecialchars($paymentResult['payer']); ?></span>
            </div>
            <div class="details-item">
                <span class="details-label">Estado:</span>
                <span class="details-value" style="color: <?php echo $isPaid ? 'var(--success)' : 'var(--danger)'; ?>">
                    <?php 
                    switch($status) {
                        case 1: echo 'Pendiente'; break;
                        case 2: echo 'Pagado'; break;
                        case 3: echo 'Rechazado'; break;
                        case 4: echo 'Anulado'; break;
                        default: echo 'Desconocido';
                    }
                    ?>
                </span>
            </div>
        </div>

        <a href="<?php echo $currentUrl; ?>" class="btn btn-secondary">Volver al Inicio</a>

    <?php else: ?>
        <!-- FORMULARIO DE INICIO DE PAGO -->
        <span class="badge <?php echo $isSandbox ? 'sandbox' : 'production'; ?>">
            <?php echo $isSandbox ? 'Ambiente Sandbox (Pruebas)' : 'Ambiente Producción'; ?>
        </span>

        <?php if (!$isSandbox): ?>
            <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: #f87171; padding: 14px; border-radius: 12px; font-size: 0.9rem; margin-top: 15px; margin-bottom: 5px; text-align: center; line-height: 1.4;">
                <strong>⚠️ ATENCIÓN:</strong> Estás en modo de <strong>PRODUCCIÓN</strong>. Los pagos iniciados aquí serán reales y cobrarán dinero verdadero.
            </div>
        <?php endif; ?>

        <h1>Pasarela Flow</h1>
        <p class="subtitle">Simulación y pruebas de cobro</p>

        <?php if (isset($error)): ?>
            <div class="error-box">
                <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($apiKey === 'TU_API_KEY_DE_SANDBOX'): ?>
            <div class="error-box" style="background: rgba(245, 158, 11, 0.15); border-color: var(--primary); color: #fbd38d;">
                <strong>Aviso:</strong> Usando valores por defecto. Recuerda configurar tus variables de entorno <code>FLOW_API_KEY</code> y <code>FLOW_SECRET_KEY</code> en Render.
            </div>
        <?php endif; ?>

        <form action="?action=pay" method="POST">
            <div class="form-group">
                <label for="amount">Monto del Pago (en céntimos para PEN/USD)</label>
                <input type="number" id="amount" name="amount" required min="1">
            </div>

            <div class="form-group">
                <label for="currency">Moneda</label>
                <select id="currency" name="currency">
                    <option value="PEN" selected>PEN (Soles Peruanos)</option>
                    <option value="CLP">CLP (Pesos Chilenos)</option>
                    <option value="USD">USD (Dólares)</option>
                </select>
            </div>

            <div class="form-group">
                <label for="email">Correo del Comprador</label>
                <input type="email" id="email" name="email" required>
            </div>

            <button type="submit" class="btn">Pagar con Flow</button>
        </form>
    <?php endif; ?>
</div>

</body>
</html>
