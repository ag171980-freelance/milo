<?php

declare(strict_types=1);

namespace CopiaFacil\Order;

/**
 * El único lugar que sabe cuánto sale un pedido.
 *
 * El front calcula lo mismo para mostrar el total mientras el cliente
 * elige, pero lo que se cobra sale siempre de acá, con los precios del
 * local leídos de la base en ese momento. Todo en centavos.
 *
 * Reglas:
 * - se cobra por carilla; doble faz tiene su propio precio por carilla;
 * - un archivo de una sola página nunca es doble faz;
 * - abrochado y anillado se cobran por copia.
 */
final class Pricing
{
    /**
     * @param array<string,int> $prices centavos, claves de ShopRepository::PRICE_COLUMNS
     * @return array{doble:bool, carillas:int, hojas:int, unit:int, print:int, finish:int, total:int}
     */
    public static function quote(array $prices, int $pages, int $copies, string $color, string $sides, string $finish): array
    {
        $pages = max(1, $pages);
        $copies = max(1, $copies);
        $doble = $sides === 'doble' && $pages > 1;

        $unit = $prices[($color === 'color' ? 'color' : 'bn') . ($doble ? 'Doble' : 'Simple')];
        $carillas = $pages * $copies;
        $hojas = ($doble ? intdiv($pages + 1, 2) : $pages) * $copies;

        $finishUnit = match ($finish) {
            'abrochado' => $prices['abrochado'],
            'anillado' => $prices['anillado'],
            default => 0,
        };

        $print = $unit * $carillas;
        $finishTotal = $finishUnit * $copies;

        return [
            'doble' => $doble,
            'carillas' => $carillas,
            'hojas' => $hojas,
            'unit' => $unit,
            'print' => $print,
            'finish' => $finishTotal,
            'total' => $print + $finishTotal,
        ];
    }
}
