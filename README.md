# Proyecto Demostrativo Flow.cl - RAPI GOD

Este repositorio es una aplicación web independiente (self-contained) en **PHP** lista para ser desplegada en **Render** u otros servicios de hosting, para probar la integración con la pasarela de pagos **Flow.cl**.

---

## 1. Estructura del Proyecto

* **[index.php](file:///C:/Users/Richard/Documents/trabajo/proyectos%20personales/RAPI%20GOD/index.php)**: El punto de entrada principal. Contiene la interfaz gráfica (diseño premium oscuro) y las rutas internas para:
  - Crear el pago (`action=pay`).
  - Recibir el webhook de confirmación (`action=confirm`).
  - Mostrar la pantalla de éxito o error al retornar el cliente (`action=return`).
* **[FlowService.php](file:///C:/Users/Richard/Documents/trabajo/proyectos%20personales/RAPI%20GOD/FlowService.php)**: Clase controladora encargada de firmar los parámetros vía HMAC-SHA256 y realizar las peticiones cURL a Flow.cl.
* **[test_flow.php](file:///C:/Users/Richard/Documents/trabajo/proyectos%20personales/RAPI%20GOD/test_flow.php)**: Script de prueba para consola/CLI.
* **`payments.log`**: Archivo de texto que se creará de forma dinámica cuando los webhooks de Flow golpeen tu servidor (así verás las notificaciones de pago confirmados en tiempo real).

---

## 2. Despliegue en Render (Paso a Paso)

Para subir el proyecto a **Render** y conectarlo con **GitHub**:

### Paso 1: Subir tu repositorio a GitHub
1. Inicializa git en esta carpeta y súbela a un repositorio (público o privado) en tu GitHub.

### Paso 2: Crear un Web Service en Render
1. Ve a tu panel de [Render](https://dashboard.render.com).
2. Haz clic en **New +** y selecciona **Web Service**.
3. Conecta tu cuenta de GitHub y selecciona el repositorio de este proyecto.
4. Completa la configuración básica:
   - **Name**: `rapi-god-flow`
   - **Language**: `PHP`
   - **Build Command**: *(Dejar en blanco)*
   - **Start Command**: *(Dejar en blanco, Render servirá el index.php automáticamente)*

### Paso 3: Configurar las Variables de Entorno en Render
En la pestaña de **Environment** de tu Web Service en Render, añade las siguientes variables de entorno para que el proyecto las lea de forma segura sin exponer tus llaves en GitHub:

```env
FLOW_API_KEY="TU_API_KEY_DE_FLOW"
FLOW_SECRET_KEY="TU_SECRET_KEY_DE_FLOW"
FLOW_SANDBOX="false"  # Configura en "false" para ambiente Real, o "true" para Sandbox
```

---

## 3. Proceso para realizar la Prueba de Pago

1. Una vez desplegado, ingresa a la URL provista por Render (ej: `https://rapi-god-flow.onrender.com`).
2. Verás la interfaz de pago premium de RAPI GOD.
3. Rellena el monto, selecciona la moneda (`CLP` o `PEN`) e ingresa tu email de cliente.
4. Haz clic en **Pagar con Flow**.
5. Serás redirigido a la pasarela oficial de Flow para completar el pago.
6. Al finalizar el pago, Flow notificará asíncronamente a tu servidor de Render (esta notificación quedará registrada en el archivo `payments.log` de tu servidor) y te redirigirá de vuelta a la pantalla de éxito en tu aplicación.
