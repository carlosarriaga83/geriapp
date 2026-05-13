<?php
/**
 * GeriApp — Servicio Mailer
 *
 * Cliente SMTP nativo (sin dependencias externas).
 * Soporta TLS (STARTTLS), SSL directo y sin cifrado.
 * AUTH LOGIN y AUTH PLAIN.
 *
 * Uso rápido:
 *   $mail = Mailer::fromConfig($instId);
 *   $mail->send('dest@email.com', 'Asunto', '<p>HTML</p>');
 *   $mail->send('dest@email.com', 'Asunto', '<p>HTML</p>', '', 'copia@email.com');
 *
 * Uso con credenciales explícitas:
 *   $mail = new Mailer([...]);
 *   $mail->send(...);
 */
class Mailer
{
    private string $host;
    private int    $port;
    private string $enc;       // 'tls' | 'ssl' | 'none'
    private string $usuario;
    private string $password;
    private string $fromEmail;
    private string $fromNombre;
    private int    $timeout;

    /** @var resource|null  socket activo */
    private $sock = null;

    /** Traza de la sesión SMTP (para diagnóstico) */
    public array $log = [];

    /** @var array Pending attachments [['path'=>string, 'name'=>string, 'mime'=>string]] */
    private array $attachments = [];

    public function __construct(array $cfg)
    {
        $this->host       = $cfg['smtp_host']        ?? '';
        $this->port       = (int)($cfg['smtp_port']  ?? 587);
        $this->enc        = strtolower($cfg['smtp_encriptacion'] ?? 'tls');
        $this->usuario    = $cfg['smtp_usuario']      ?? '';
        $this->password   = $cfg['smtp_password']     ?? '';
        $this->fromEmail  = $cfg['smtp_from_email']   ?? '';
        $this->fromNombre = $cfg['smtp_from_nombre']  ?? 'GeriApp';
        $this->timeout    = (int)($cfg['smtp_timeout'] ?? $cfg['timeout'] ?? 20);
    }

    /**
     * Construye una instancia desde la configuración guardada en DB.
     */
    public static function fromConfig(int $instId): static
    {
        $cfg = Configuracion::getOrCreate($instId);
        return new static($cfg ?: []);
    }

    // ── Método principal ──────────────────────────────────────────────────────

    /**
     * Queue a file attachment for the next send() call.
     * Attachments are cleared after each send.
     */
    public function addAttachment(string $filePath, string $name = '', string $mimeType = ''): static
    {
        if (!$name) $name = basename($filePath);
        if (!$mimeType) $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
        $this->attachments[] = ['path' => $filePath, 'name' => $name, 'mime' => $mimeType];
        return $this;
    }

    /**
     * Envía un correo.
     *
     * @param string|array $to       'dest@mail.com' o ['a@b.com','c@d.com']
     * @param string       $subject  Asunto
     * @param string       $body     Cuerpo HTML
    * @param string       $textBody Cuerpo texto plano (opcional, se genera automáticamente)
    * @param string|array $cc       Copia visible opcional
     *
     * @return array ['ok'=>bool, 'error'=>string|null, 'log'=>array]
     */
    public function send(string|array $to, string $subject, string $body, string $textBody = '', string|array $cc = []): array
    {
        try {
            if (!$this->host)  throw new \RuntimeException('Host SMTP no configurado');
            if (!$this->fromEmail) throw new \RuntimeException('Correo remitente (From) no configurado');

            $recipients = $this->normalizeRecipients($to);
            if (empty($recipients)) throw new \RuntimeException('Sin destinatario');
            $ccRecipients = $this->normalizeRecipients($cc, $recipients);

            $this->connect();
            $this->ehlo();
            $this->authenticate();
            $this->sendMail($recipients, $subject, $body, $textBody, $ccRecipients);
            $this->quit();

            return ['ok' => true, 'error' => null, 'log' => $this->log];

        } catch (\Throwable $e) {
            $this->closeSocket();
            return ['ok' => false, 'error' => $e->getMessage(), 'log' => $this->log];
        }
    }

    /**
     * Prueba la conexión enviando un correo al destino indicado.
     */
    public function testConnection(string $destEmail): array
    {
        if (!filter_var($destEmail, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => "Correo destino inválido: {$destEmail}", 'log' => []];
        }

        $html = "
        <div style='font-family:-apple-system,sans-serif;max-width:520px;margin:0 auto;padding:32px 24px;'>
            <div style='font-size:22px;font-weight:700;color:#1e3a6e;margin-bottom:8px;'>✅ GeriApp — Prueba SMTP exitosa</div>
            <p style='color:#475569;font-size:14px;line-height:1.6;'>
                Si recibes este correo, la configuración SMTP de tu sistema GeriApp está funcionando correctamente.
            </p>
            <table style='width:100%;background:#f8fafc;border-radius:8px;padding:16px;margin:20px 0;font-size:13px;border-collapse:collapse;'>
                <tr><td style='padding:5px 10px;color:#64748b;'>Host</td><td style='padding:5px 10px;font-weight:500;'>{$this->host}:{$this->port}</td></tr>
                <tr><td style='padding:5px 10px;color:#64748b;'>Cifrado</td><td style='padding:5px 10px;font-weight:500;'>" . strtoupper($this->enc) . "</td></tr>
                <tr><td style='padding:5px 10px;color:#64748b;'>Usuario</td><td style='padding:5px 10px;font-weight:500;'>{$this->usuario}</td></tr>
                <tr><td style='padding:5px 10px;color:#64748b;'>Fecha/Hora</td><td style='padding:5px 10px;font-weight:500;'>" . date('d/m/Y H:i:s') . "</td></tr>
            </table>
            <p style='font-size:11px;color:#94a3b8;margin-top:24px;'>GeriApp · Sistema de gestión geriátrica</p>
        </div>";

        return $this->send($destEmail, '✅ GeriApp — Prueba de correo SMTP', $html);
    }

    // ── Sesión SMTP ───────────────────────────────────────────────────────────

    private function connect(): void
    {
        $useSSL = $this->enc === 'ssl';
        $scheme = $useSSL ? 'ssl' : 'tcp';
        $dsn    = "{$scheme}://{$this->host}:{$this->port}";

        $errno = 0; $errstr = '';

        if ($useSSL) {
            $cafile  = $this->findCaBundle();
            $sslOpts = [
                'verify_peer'       => true,
                'verify_peer_name'  => true,
                'allow_self_signed' => false,
                'peer_name'         => $this->host,
            ];
            if ($cafile) $sslOpts['cafile'] = $cafile;
            $ctx = stream_context_create(['ssl' => $sslOpts]);
            $this->sock = @stream_socket_client($dsn, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, $ctx);

            // Fallback without peer verification (dev / self-signed / missing CA bundle)
            if (!$this->sock) {
                $this->log[] = "⚠ SSL con verificación de certificado falló: {$errstr}. Reintentando sin verificación (entorno de desarrollo).…";
                $ctxNoVerify = stream_context_create(['ssl' => [
                    'verify_peer'      => false,
                    'verify_peer_name' => false,
                    'allow_self_signed'=> true,
                ]]);
                $this->sock = @stream_socket_client($dsn, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, $ctxNoVerify);
            }
        } else {
            $this->sock = @stream_socket_client($dsn, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT);
        }

        if (!$this->sock) {
            $hint = '';
            if ($useSSL && $this->port !== 465) {
                $hint = " (para SSL/SMTPS el puerto estándar es 465, no {$this->port})"; 
            } elseif (!$useSSL && $this->enc === 'tls' && $this->port !== 587) {
                $hint = " (para TLS/STARTTLS el puerto estándar es 587, no {$this->port})";
            }
            throw new \RuntimeException("No se pudo conectar a {$this->host}:{$this->port} — {$errstr} (código {$errno}){$hint}");
        }

        stream_set_timeout($this->sock, $this->timeout);
        $this->expect(220, 'Saludo inicial del servidor');
    }

    private function ehlo(): void
    {
        $hostname = gethostname() ?: 'localhost';
        $this->cmd("EHLO {$hostname}");
        $this->expect(250, 'EHLO');

        // Upgradar a TLS con STARTTLS si el cifrado es 'tls'
        if ($this->enc === 'tls') {
            $this->cmd('STARTTLS');
            $this->expect(220, 'STARTTLS');

            $cafile  = $this->findCaBundle();
            $tlsOpts = [
                'verify_peer'       => true,
                'verify_peer_name'  => true,
                'allow_self_signed' => false,
                'peer_name'         => $this->host,
            ];
            if ($cafile) $tlsOpts['cafile'] = $cafile;
            stream_context_set_option($this->sock, ['ssl' => $tlsOpts]);

            $ok = @stream_socket_enable_crypto($this->sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if (!$ok) {
                // Fallback: relax certificate verification (dev environments)
                $this->log[] = '⚠ TLS con verificación falló, reintentando sin verificación…';
                stream_context_set_option($this->sock, ['ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ]]);
                $ok = @stream_socket_enable_crypto($this->sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            }
            if (!$ok) {
                throw new \RuntimeException('No se pudo negociar TLS con el servidor SMTP');
            }

            // Re-EHLO después de STARTTLS (requerido por RFC)
            $this->cmd("EHLO {$hostname}");
            $this->expect(250, 'EHLO post-TLS');
        }
    }

    private function authenticate(): void
    {
        if (!$this->usuario || !$this->password) return;

        // Intentar AUTH LOGIN primero, luego AUTH PLAIN
        $this->cmd('AUTH LOGIN');
        $code = $this->readCode();

        if ($code === 334) {
            // AUTH LOGIN: usuario y contraseña en base64
            $this->cmd(base64_encode($this->usuario));
            $this->expect(334, 'AUTH LOGIN usuario');
            $this->cmd(base64_encode($this->password));
            $this->expect(235, 'AUTH LOGIN contraseña');
        } elseif ($code === 504 || $code === 502) {
            // AUTH PLAIN como fallback
            $token = base64_encode("\0{$this->usuario}\0{$this->password}");
            $this->cmd("AUTH PLAIN {$token}");
            $this->expect(235, 'AUTH PLAIN');
        } else {
            throw new \RuntimeException("Autenticación rechazada por el servidor (código {$code})");
        }
    }

    private function sendMail(array $recipients, string $subject, string $htmlBody, string $textBody, array $ccRecipients = []): void
    {
        // MAIL FROM
        $this->cmd("MAIL FROM:<{$this->fromEmail}>");
        $this->expect(250, 'MAIL FROM');

        // RCPT TO — uno por uno
        foreach (array_merge($recipients, $ccRecipients) as $rcpt) {
            $this->cmd("RCPT TO:<{$rcpt}>");
            $this->expect(250, "RCPT TO {$rcpt}");
        }

        // DATA
        $this->cmd('DATA');
        $this->expect(354, 'DATA inicio');

        $msgId    = '<' . uniqid('geri.', true) . '@' . (explode('@', $this->fromEmail)[1] ?? 'geriapp.mx') . '>';
        $toHeader = implode(', ', $recipients);
        $ccHeader = implode(', ', $ccRecipients);
        $date     = date('r');
        $from     = $this->fromNombre
            ? '=?UTF-8?B?' . base64_encode($this->fromNombre) . '?= <' . $this->fromEmail . '>'
            : $this->fromEmail;
        $subj     = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        if (!$textBody) {
            $textBody = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>'], "\n", $htmlBody));
            $textBody = html_entity_decode(preg_replace('/\n{3,}/', "\n\n", $textBody), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        $altBoundary = 'MP_' . bin2hex(random_bytes(12));
        $hasAttachments = !empty($this->attachments);
        $mixedBoundary = $hasAttachments ? ('MX_' . bin2hex(random_bytes(12))) : '';

        $headers  = "Date: {$date}\r\n";
        $headers .= "From: {$from}\r\n";
        $headers .= "Reply-To: {$from}\r\n";
        $headers .= "To: {$toHeader}\r\n";
        if ($ccHeader !== '') {
            $headers .= "Cc: {$ccHeader}\r\n";
        }
        $headers .= "Subject: {$subj}\r\n";
        $headers .= "Message-ID: {$msgId}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        if ($hasAttachments) {
            $headers .= "Content-Type: multipart/mixed; boundary=\"{$mixedBoundary}\"\r\n";
        } else {
            $headers .= "Content-Type: multipart/alternative; boundary=\"{$altBoundary}\"\r\n";
        }
        $headers .= "X-Priority: 3\r\n";
        $headers .= "Importance: Normal\r\n";

        // Build alternative part (text + html)
        $altPart  = "--{$altBoundary}\r\n";
        $altPart .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $altPart .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $altPart .= chunk_split(base64_encode($textBody)) . "\r\n";
        $altPart .= "--{$altBoundary}\r\n";
        $altPart .= "Content-Type: text/html; charset=UTF-8\r\n";
        $altPart .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $altPart .= chunk_split(base64_encode($htmlBody)) . "\r\n";
        $altPart .= "--{$altBoundary}--\r\n";

        if ($hasAttachments) {
            // Wrap alternative in mixed
            $mime  = "--{$mixedBoundary}\r\n";
            $mime .= "Content-Type: multipart/alternative; boundary=\"{$altBoundary}\"\r\n\r\n";
            $mime .= $altPart;

            // Add attachments
            foreach ($this->attachments as $att) {
                if (!file_exists($att['path'])) continue;
                $encName = '=?UTF-8?B?' . base64_encode($att['name']) . '?=';
                $mime .= "--{$mixedBoundary}\r\n";
                $mime .= "Content-Type: {$att['mime']}; name=\"{$encName}\"\r\n";
                $mime .= "Content-Disposition: attachment; filename=\"{$encName}\"\r\n";
                $mime .= "Content-Transfer-Encoding: base64\r\n\r\n";
                $mime .= chunk_split(base64_encode(file_get_contents($att['path']))) . "\r\n";
            }
            $mime .= "--{$mixedBoundary}--\r\n";
        } else {
            $mime = $altPart;
        }

        // Clear attachments after building
        $this->attachments = [];

        // Escapar líneas que empiezan con '.'
        $message = $headers . "\r\n" . $mime;
        $message = preg_replace('/^\.$/m', '..', $message);

        $this->write($message . "\r\n.\r\n");
        $this->expect(250, 'DATA fin');
    }

    private function normalizeRecipients(string|array $recipients, array $exclude = []): array
    {
        $list = is_array($recipients) ? $recipients : [$recipients];
        $excludeMap = [];
        foreach ($exclude as $email) {
            $excludeMap[strtolower(trim((string)$email))] = true;
        }

        $clean = [];
        foreach ($list as $email) {
            $email = strtolower(trim((string)$email));
            if ($email === '' || isset($excludeMap[$email]) || isset($clean[$email])) continue;
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
            $clean[$email] = $email;
        }
        return array_values($clean);
    }

    private function quit(): void
    {
        try {
            $this->cmd('QUIT');
            $this->expect(221, 'QUIT');
        } catch (\Throwable) {
            // No fatal si ya cerró
        } finally {
            $this->closeSocket();
        }
    }

    // ── Bajo nivel ────────────────────────────────────────────────────────────

    private function cmd(string $cmd): void
    {
        // Ocultar contraseña en el log
        $logLine = (strpos($cmd, base64_encode($this->password)) !== false)
            ? preg_replace('/\S+/', '***', $cmd)
            : $cmd;
        $this->log[] = "→ {$logLine}";
        $this->write($cmd . "\r\n");
    }

    private function write(string $data): void
    {
        if (!$this->sock) throw new \RuntimeException('Socket no inicializado');
        if (fwrite($this->sock, $data) === false) {
            throw new \RuntimeException('Error al escribir en el socket SMTP');
        }
    }

    private function readLine(): string
    {
        if (!$this->sock) throw new \RuntimeException('Socket cerrado');
        $line = fgets($this->sock, 1024);
        if ($line === false) throw new \RuntimeException('El servidor SMTP cerró la conexión inesperadamente');
        $this->log[] = "← " . rtrim($line);
        return $line;
    }

    private function readCode(): int
    {
        $code = 0;
        do {
            $line = $this->readLine();
            $code = (int)substr($line, 0, 3);
            $cont = ($line[3] ?? ' ') === '-'; // '-' = continuación multilinea
        } while ($cont);
        return $code;
    }

    private function expect(int $expected, string $ctx): void
    {
        $code = $this->readCode();
        if ($code !== $expected) {
            throw new \RuntimeException(
                "Error SMTP en '{$ctx}': se esperaba {$expected}, se recibió {$code}"
            );
        }
    }

    private function closeSocket(): void
    {
        if ($this->sock) {
            fclose($this->sock);
            $this->sock = null;
        }
    }

    /**
     * Localiza el bundle de CA raíces (necesario para SSL/TLS en XAMPP Windows).
     */
    private function findCaBundle(): string
    {
        $candidates = [
            ini_get('curl.cainfo'),
            ini_get('openssl.cafile'),
            'C:/xampp/php/extras/ssl/cacert.pem',
            'C:/xampp/apache/conf/ssl.crt/ca-bundle.crt',
            'C:/xampp/php/cacert.pem',
            'C:/Windows/System32/curl-ca-bundle.crt',
            'C:/Program Files/Git/usr/ssl/certs/ca-bundle.crt',
            'C:/Program Files/Git/mingw64/ssl/certs/ca-bundle.crt',
            '/etc/ssl/certs/ca-certificates.crt',
            '/etc/pki/tls/certs/ca-bundle.crt',
            '/usr/local/share/certs/ca-root-nss.crt',
        ];
        foreach ($candidates as $f) {
            if ($f && file_exists($f)) return $f;
        }
        return '';  // PHP usará su bundle interno
    }
}
