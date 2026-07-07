<?php

// namespace App\Services; // Comentado para permitir ejecución standalone. Descomentar si usas autoloader PSR-4.

class FlowService {
    private $apiKey;
    private $secretKey;
    private $baseUrl;

    /**
     * Constructor de la clase
     * @param string $apiKey Credencial API Key obtenida de Flow
     * @param string $secretKey Credencial Secret Key obtenida de Flow
     * @param bool $isSandbox Define si opera en entorno de pruebas (true) o real (false)
     */
    public function __construct(string $apiKey, string $secretKey, bool $isSandbox = true) {
        $this->apiKey = $apiKey;
        $this->secretKey = $secretKey;
        $this->baseUrl = $isSandbox 
            ? 'https://sandbox.flow.cl/api' 
            : 'https://www.flow.cl/api';
    }

    /**
     * Genera la firma digital (s) para Flow.cl
     * 1. Ordena los parámetros alfabéticamente por clave (ksort).
     * 2. Concatena nombre y valor de cada parámetro: "key1value1key2value2...".
     * 3. Firma con HMAC-SHA256 usando el secretKey.
     */
    public function sign(array $params): string {
        ksort($params);
        $toSign = '';
        foreach ($params as $key => $val) {
            $toSign .= $key . $val;
        }
        return hash_hmac('sha256', $toSign, $this->secretKey);
    }

    /**
     * Crea una orden de pago en Flow.
     * @param array $paymentData Datos del pago:
     *   - commerceOrder: ID único en tu sistema
     *   - subject: Descripción de la orden
     *   - amount: Monto de la transacción
     *   - email: Email del pagador
     *   - urlConfirmation: URL del webhook en tu backend
     *   - urlReturn: URL de retorno en tu frontend
     *   - currency: (Opcional) CLP, PEN, USD (por defecto CLP)
     */
    public function createPayment(array $paymentData): array {
        $params = array_merge([
            'apiKey' => $this->apiKey,
            'currency' => 'CLP'
        ], $paymentData);

        // Agrega la firma digital a los parámetros
        $params['s'] = $this->sign($params);

        return $this->sendRequest('/payment/create', 'POST', $params);
    }

    /**
     * Obtiene el estado de un pago usando su token
     * @param string $token Token enviado por Flow
     */
    public function getPaymentStatus(string $token): array {
        $params = [
            'apiKey' => $this->apiKey,
            'token' => $token
        ];

        // Agrega la firma digital a los parámetros
        $params['s'] = $this->sign($params);

        return $this->sendRequest('/payment/getStatus', 'GET', $params);
    }

    /**
     * Envía la petición HTTP a la API de Flow
     */
    private function sendRequest(string $endpoint, string $method, array $params): array {
        $url = $this->baseUrl . $endpoint;
        $ch = curl_init();

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        } else {
            $url .= '?' . http_build_query($params);
            curl_setopt($ch, CURLOPT_URL, $url);
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \Exception("Error en CURL: " . $error);
        }

        curl_close($ch);

        $decoded = json_decode($response, true);
        
        // Manejo de códigos de respuesta HTTP de Flow (200 es exitoso)
        if ($httpCode !== 200) {
            $message = isset($decoded['message']) ? $decoded['message'] : $response;
            throw new \Exception("Flow API Error (Código HTTP $httpCode): " . $message);
        }

        return $decoded;
    }
}
