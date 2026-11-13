<?php

declare(strict_types=1);

namespace CopiaFacil\Support;

use JsonException;

/**
 * Los datos de la petición, ya validados en lo básico.
 *
 * Sirve para cuerpos JSON y para formularios multipart (el alta de un
 * pedido viaja como multipart porque lleva el archivo).
 */
final class Request
{
    /** @param array<string, mixed> $data */
    private function __construct(private readonly array $data)
    {
    }

    private static ?self $cache = null;

    public static function json(int $maxBytes = 65536): self
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return self::$cache = new self([]);
        }
        if (strlen($raw) > $maxBytes) {
            throw HttpException::badRequest('El cuerpo del pedido es demasiado grande.', 'cuerpo_grande');
        }

        try {
            $data = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw HttpException::badRequest('El cuerpo no es JSON válido.', 'json_invalido');
        }
        if (!is_array($data)) {
            throw HttpException::badRequest('Se esperaba un objeto JSON.', 'json_invalido');
        }

        return self::$cache = new self($data);
    }

    /** Los campos de un formulario multipart ($_POST). */
    public static function form(): self
    {
        return new self($_POST);
    }

    public function str(string $key, int $maxLength = 255, string $default = ''): string
    {
        $value = $this->data[$key] ?? null;
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            return $default;
        }

        // Fuera caracteres de control: ensucian tickets y logs.
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) $value) ?? '';

        return mb_substr(trim($clean), 0, $maxLength);
    }

    /** @param list<string> $allowed */
    public function enum(string $key, array $allowed, string $default): string
    {
        $value = $this->str($key, 40);

        return in_array($value, $allowed, true) ? $value : $default;
    }

    public function int(string $key, int $min, int $max, int $default): int
    {
        $value = $this->data[$key] ?? null;
        if (!is_numeric($value)) {
            return $default;
        }

        return max($min, min($max, (int) $value));
    }

    public function float(string $key): ?float
    {
        $value = $this->data[$key] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->data[$key] ?? null;
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return $value != 0;
        }
        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }

        return $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /** @return array<string,mixed> */
    public function obj(string $key): array
    {
        $value = $this->data[$key] ?? null;

        return is_array($value) ? $value : [];
    }
}
