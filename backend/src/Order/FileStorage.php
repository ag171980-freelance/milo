<?php

declare(strict_types=1);

namespace CopiaFacil\Order;

use CopiaFacil\Support\HttpException;
use ZipArchive;

/**
 * Los archivos que suben los clientes.
 *
 * Viven fuera de public/: nunca se sirven por URL directa. El panel los
 * abre con un link firmado que vence en minutos (ver AdminController).
 * El tipo se decide mirando el contenido (finfo), no la extensión que
 * mandó el navegador.
 */
final class FileStorage
{
    /** mime real => extensión con la que se guarda */
    private const ALLOWED = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/msword' => 'doc',
    ];

    public function __construct(private readonly string $baseDir, private readonly int $maxBytes)
    {
    }

    /**
     * Valida y guarda el archivo subido.
     *
     * @param array<string,mixed>|null $upload la entrada de $_FILES
     * @return array{path:string, name:string, mime:string, size:int, pages:?int}
     */
    public function store(?array $upload, int $tenantId): array
    {
        if ($upload === null || !isset($upload['error'])) {
            throw new HttpException(422, 'Falta el archivo.', 'falta_archivo');
        }

        $error = (int) $upload['error'];
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE || (int) $upload['size'] > $this->maxBytes) {
            throw new HttpException(413, sprintf('El archivo supera los %d MB.', intdiv($this->maxBytes, 1048576)), 'archivo_grande');
        }
        if ($error !== UPLOAD_ERR_OK || !is_uploaded_file((string) $upload['tmp_name'])) {
            throw new HttpException(422, 'No pudimos recibir el archivo. Probá de nuevo.', 'archivo_fallo');
        }

        $tmp = (string) $upload['tmp_name'];
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: '';

        // Algunos .docx se detectan como zip genérico.
        if ($mime === 'application/zip' && $this->isDocx($tmp)) {
            $mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        }

        $ext = self::ALLOWED[$mime] ?? null;
        if ($ext === null) {
            throw new HttpException(415, 'Ese tipo de archivo no se puede imprimir acá. Subí un PDF, una foto (JPG o PNG) o un Word.', 'tipo_no_permitido');
        }

        $pages = $this->countPages($tmp, $ext);

        $relDir = $tenantId . '/' . date('Ymd');
        $dir = $this->baseDir . '/' . $relDir;
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new \RuntimeException('No se pudo crear la carpeta de archivos.');
        }

        $relPath = $relDir . '/' . bin2hex(random_bytes(16)) . '.' . $ext;
        if (!move_uploaded_file($tmp, $this->baseDir . '/' . $relPath)) {
            throw new \RuntimeException('No se pudo guardar el archivo.');
        }
        chmod($this->baseDir . '/' . $relPath, 0640);

        return [
            'path' => $relPath,
            'name' => self::cleanName((string) ($upload['name'] ?? 'archivo.' . $ext), $ext),
            'mime' => $mime,
            'size' => (int) $upload['size'],
            'pages' => $pages,
        ];
    }

    public function absolutePath(string $relPath): ?string
    {
        // Sólo rutas con la forma que genera store(): nada de ../
        if (preg_match('#^\d+/\d{8}/[a-f0-9]{32}\.(pdf|jpg|png|docx|doc)$#', $relPath) !== 1) {
            return null;
        }
        $abs = $this->baseDir . '/' . $relPath;

        return is_file($abs) ? $abs : null;
    }

    public function delete(string $relPath): void
    {
        $abs = $this->absolutePath($relPath);
        if ($abs !== null) {
            @unlink($abs);
        }
    }

    /**
     * Cuenta páginas cuando se puede. Null si no: el cliente las declara y
     * el local las ve antes de imprimir.
     */
    private function countPages(string $path, string $ext): ?int
    {
        if ($ext === 'jpg' || $ext === 'png') {
            return 1;
        }

        if ($ext === 'pdf') {
            $data = @file_get_contents($path);
            if ($data === false) {
                return null;
            }
            $n = preg_match_all('#/Type\s*/Page(?![s\w])#', $data);
            if ($n > 0) {
                return $n;
            }
            // PDFs con object streams comprimidos: se usa el /Count del árbol.
            if (preg_match_all('#/Count\s+(\d+)#', $data, $m) > 0) {
                $max = max(array_map('intval', $m[1]));
                return $max > 0 ? $max : null;
            }
            return null;
        }

        if ($ext === 'docx' && class_exists(ZipArchive::class)) {
            // Word guarda la cantidad de páginas de la última vez que se abrió.
            $zip = new ZipArchive();
            if ($zip->open($path) === true) {
                $xml = $zip->getFromName('docProps/app.xml');
                $zip->close();
                if (is_string($xml) && preg_match('#<Pages>(\d+)</Pages>#', $xml, $m) === 1 && (int) $m[1] > 0) {
                    return (int) $m[1];
                }
            }
        }

        return null;
    }

    private function isDocx(string $path): bool
    {
        if (!class_exists(ZipArchive::class)) {
            return false;
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return false;
        }
        $ok = $zip->locateName('word/document.xml') !== false;
        $zip->close();

        return $ok;
    }

    private static function cleanName(string $name, string $ext): string
    {
        $base = pathinfo($name, PATHINFO_FILENAME);
        $base = preg_replace('/[^\p{L}\p{N} ._()-]/u', '_', $base) ?? 'archivo';
        $base = mb_substr(trim($base, " ._"), 0, 150);

        return ($base === '' ? 'archivo' : $base) . '.' . $ext;
    }
}
