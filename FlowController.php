<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\FlowService;
use Exception;

class FlowController extends Controller
{
    private $flowService;

    public function __construct()
    {
        // Instancia el servicio con tus credenciales guardadas en .env
        $this->flowService = new FlowService(
            config('services.flow.api_key'),
            config('services.flow.secret_key'),
            config('services.flow.sandbox', true)
        );
    }

    /**
     * Inicia el proceso de pago redirigiendo al cliente a Flow.cl
     */
    public function pay(Request $request)
    {
        // En un caso real obtendrías estos datos de tu base de datos o del carro
        $paymentData = [
            'commerceOrder' => 'ORDER-' . rand(1000, 9999),
            'subject' => 'Compra de prueba #2026',
            'amount' => 1500, // Monto
            'email' => 'cliente@dominio.com',
            'urlConfirmation' => route('flow.confirmation'), // Webhook
            'urlReturn' => route('flow.return'),           // Redirección browser
            'currency' => 'CLP'                             // O 'PEN' si estás en Perú
        ];

        try {
            $response = $this->flowService->createPayment($paymentData);
            
            // Flow devuelve la URL de redirección y un token temporal
            $redirectUrl = $response['url'] . '?token=' . $response['token'];
            
            return redirect($redirectUrl);
        } catch (Exception $e) {
            return back()->withErrors(['error' => 'No se pudo crear la transacción: ' . $e->getMessage()]);
        }
    }

    /**
     * Webhook asíncrono donde Flow notifica el resultado del pago (servidor a servidor).
     * IMPORTANTE: Debes desactivar la validación CSRF para esta ruta en bootstrap/app.php o en VerifyCsrfToken.php.
     */
    public function confirmation(Request $request)
    {
        // Recibe el token enviado por Flow vía POST
        $token = $request->input('token');

        if (!$token) {
            return response()->json(['error' => 'Token no recibido'], 400);
        }

        try {
            // Consulta el estado final a Flow
            $statusData = $this->flowService->getPaymentStatus($token);

            // Obtén la información útil
            $commerceOrder = $statusData['commerceOrder'];
            $status = (int) $statusData['status']; // 1: Pendiente, 2: Pagado, 3: Rechazado, 4: Anulado

            if ($status === 2) {
                // TODO: El pago fue EXÍTOSO. Actualiza tu base de datos aquí.
                // Ej: Order::where('order_id', $commerceOrder)->update(['status' => 'paid']);
                
                logger()->info("Pago exitoso recibido para la orden: " . $commerceOrder);
            } else {
                logger()->warning("Pago fallido o pendiente para la orden: " . $commerceOrder . " (Status: $status)");
            }

            return response()->json(['message' => 'Ok']);
        } catch (Exception $e) {
            logger()->error("Error procesando webhook de Flow: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * URL a la cual el cliente es redirigido en el navegador una vez terminado el pago.
     */
    public function return(Request $request)
    {
        $token = $request->input('token');

        if (!$token) {
            return view('payment.failed', ['error' => 'No se recibió un token válido.']);
        }

        try {
            $statusData = $this->flowService->getPaymentStatus($token);
            $status = (int) $statusData['status'];

            if ($status === 2) {
                return view('payment.success', [
                    'order' => $statusData['commerceOrder'],
                    'amount' => $statusData['amount']
                ]);
            } else {
                return view('payment.failed', [
                    'error' => 'El pago no se pudo completar o fue cancelado.'
                ]);
            }
        } catch (Exception $e) {
            return view('payment.failed', ['error' => $e->getMessage()]);
        }
    }
}
