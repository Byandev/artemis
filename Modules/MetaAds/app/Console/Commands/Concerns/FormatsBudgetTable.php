<?php

namespace Modules\MetaAds\Console\Commands\Concerns;

/**
 * Shared fixed-width table rendering for the Discord budget report commands.
 * Produces an aligned monospace table (Label | Today | Diff plus a total row)
 * meant to sit inside a Discord code fence so columns line up.
 */
trait FormatsBudgetTable
{
    /**
     * Render an aligned fixed-width table: Label | Today | Diff, plus a total row.
     *
     * @param  array<int, array{name: string, today: string, diff: string}>  $rows
     * @param  array{name: string, today: string, diff: string}  $totalRow
     */
    private function renderTable(array $rows, array $totalRow, string $label = 'Item'): string
    {
        $maxName = 22;
        $all = array_merge($rows, [$totalRow]);

        $nameW = mb_strlen($label);
        $todayW = mb_strlen('Today');
        $diffW = mb_strlen('Diff');

        foreach ($all as $r) {
            $nameW = max($nameW, mb_strlen($this->truncate($r['name'], $maxName)));
            $todayW = max($todayW, mb_strlen($r['today']));
            $diffW = max($diffW, mb_strlen($r['diff']));
        }

        $line = fn (string $name, string $today, string $diff): string => $this->pad($this->truncate($name, $maxName), $nameW, false)
            .'  '.$this->pad($today, $todayW, true)
            .'  '.$this->pad($diff, $diffW, true);

        $sep = str_repeat('-', $nameW + $todayW + $diffW + 4);

        $out = [$line($label, 'Today', 'Diff'), $sep];
        foreach ($rows as $r) {
            $out[] = $line($r['name'], $r['today'], $r['diff']);
        }
        $out[] = $sep;
        $out[] = $line($totalRow['name'], $totalRow['today'], $totalRow['diff']);

        return implode("\n", $out);
    }

    /**
     * Format a budget delta as a signed amount, e.g. "+120.00" / "-50.00".
     * Plain ASCII so columns stay aligned inside the monospace code block.
     */
    private function formatDelta(float $diff): string
    {
        if (abs($diff) < 0.005) {
            return '0.00';
        }

        return ($diff > 0 ? '+' : '-').number_format(abs($diff), 2);
    }

    /**
     * Pad a string to the given display width (multibyte-safe), left or right.
     */
    private function pad(string $value, int $width, bool $alignRight): string
    {
        $gap = $width - mb_strlen($value);
        if ($gap <= 0) {
            return $value;
        }

        $padding = str_repeat(' ', $gap);

        return $alignRight ? $padding.$value : $value.$padding;
    }

    /**
     * Truncate an over-long name with an ellipsis, keeping a fixed display width.
     */
    private function truncate(string $value, int $max): string
    {
        return mb_strlen($value) > $max ? mb_substr($value, 0, $max - 1).'…' : $value;
    }
}
