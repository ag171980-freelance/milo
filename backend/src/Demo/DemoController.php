<?php

declare(strict_types=1);

namespace CopiaFacil\Demo;

use CopiaFacil\Support\Crypto;
use CopiaFacil\Support\RateLimit;
use CopiaFacil\Support\Request;
use CopiaFacil\Support\Response;
use PDO;

/**
 * POST /demo — un comercio pide la demo gratis de una semana.
 *
 * La solicitud queda en demo_requests y, si DEMO_NOTIFY_EMAIL está
 * configurado, se manda un aviso por mail (best effort: si el servidor
 * no tiene mail, la solicitud igual queda guardada). Se listan con
 * `php tools/solicitudes-demo.php` y se activan con
 * `php tools/nuevo-local.php … --prueba=7`.
 */
final class DemoController
{
    /** @param array<string,mixed> $config */
    public function __construct(
        private readonly PDO $db,
        private readonly Crypto $crypto,
        private readonly RateLimit $rateLimit,
        private readonly array $config,
    ) {
    }

    public function request(): void
    {
        $req = Request::json();

        // Campo trampa: invisible para personas, los bots lo completan.
        // Se responde como si estuviera todo bien para no darles pistas.
        if ($req->str('website', 200) !== '') {
            Response::json(['ok' => true], 201);
            return;
        }

        $ip = RateLimit::clientIp();
        $this->rateLimit->hit($this->crypto->hmac('demo|' . $ip), 5, 3600, 'Ya recibimos varias solicitudes desde tu conexión. Probá más tarde.');

        $data = [
            'name' => $req->str('name', 120),
            'shop_name' => $req->str('shopName', 120),
            'address' => $req->str('address', 200),
            'phone' => $req->str('phone', 40),
            'email' => mb_strtolower($req->str('email', 160)),
            'message' => $req->str('message', 500),
        ];

        $errors = [];
        if (mb_strlen($data['name']) < 2) {
            $errors['name'] = 'Poné tu nombre.';
        }
        if (mb_strlen($data['shop_name']) < 2) {
            $errors['shopName'] = 'Poné el nombre del local.';
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Necesitamos un mail válido para mandarte el acceso.';
        }
        if ($data['phone'] !== '' && preg_match('/^[0-9 +()-]{6,40}$/', $data['phone']) !== 1) {
            $errors['phone'] = 'Revisá el teléfono.';
        }
        if (!$req->bool('acceptPrivacy')) {
            $errors['acceptPrivacy'] = 'Tenés que aceptar la política de privacidad.';
        }
        if ($errors !== []) {
            Response::error(422, 'Revisá los datos.', 'datos_invalidos', ['campos' => $errors]);
            return;
        }

        $this->db->prepare(
            'INSERT INTO demo_requests (name, shop_name, address, phone, email, message, ip_hash)
             VALUES (:name, :shop_name, :address, :phone, :email, :message, :ip)',
        )->execute($data + ['ip' => $this->crypto->hmac('ip|' . $ip)]);

        $this->notify($data);

        Response::json(['ok' => true], 201);
    }

    /** @param array<string,string> $d */
    private function notify(array $d): void
    {
        $to = (string) ($this->config['demo_notify_email'] ?? '');
        if ($to === '' || !function_exists('mail')) {
            return;
        }

        // Sin saltos de línea en lo que va a cabeceras: evita inyección de headers.
        $clean = static fn (string $v): string => str_replace(["\r", "\n"], ' ', $v);
        $body = "Nueva solicitud de demo\n\n"
            . "Nombre: {$d['name']}\nLocal: {$d['shop_name']}\nDirección: {$d['address']}\n"
            . "Teléfono: {$d['phone']}\nMail: {$d['email']}\n\nMensaje:\n{$d['message']}\n";

        @mail(
            $to,
            '=?UTF-8?B?' . base64_encode('Demo pedida: ' . $clean($d['shop_name'])) . '?=',
            $body,
            "Content-Type: text/plain; charset=UTF-8\r\nReply-To: " . $clean($d['email']),
        );
    }
}
