<?php

namespace Modules\GencysERP\Support;

use Illuminate\Support\Str;
use Modules\GencysERP\Models\Intern;

/**
 * Resolves the free-text "Intern and Brand" cell the Gencys ERP gives us
 * (e.g. "Juan Dela Cruz - Acme Skincare") back to a local intern row.
 *
 * The whole workspace's interns are loaded once and indexed in memory, so a
 * sync of N pages costs one query rather than N.
 */
class InternResolver
{
    /** Separators the ERP uses between the intern and the brand. */
    private const SEPARATORS = ['-', '–', '—', '|', '/', '(', ':'];

    /** @var array<string,int> lowercased full_name => intern PK */
    private array $byName = [];

    /** @var array<string,int> lowercased username => intern PK */
    private array $byUsername = [];

    /** @var array<string,int> lowercased alias (other_names) => intern PK */
    private array $byOther = [];

    public function __construct(int $workspaceId)
    {
        Intern::query()
            ->where('workspace_id', $workspaceId)
            ->get(['id', 'full_name', 'username', 'other_names'])
            ->each(function (Intern $intern) {
                if ($key = $this->normalize($intern->full_name)) {
                    $this->byName[$key] = $intern->id;
                }

                if ($key = $this->normalize($intern->username)) {
                    $this->byUsername[$key] = $intern->id;
                }

                foreach ((array) $intern->other_names as $alias) {
                    // First intern to claim an alias keeps it; canonical
                    // name/username always win in resolve() regardless.
                    if (($key = $this->normalize($alias)) && ! isset($this->byOther[$key])) {
                        $this->byOther[$key] = $intern->id;
                    }
                }
            });
    }

    /**
     * Best-effort match. Tries the whole cell first, then the segment before the
     * brand separator. Returns null rather than guessing when nothing matches.
     */
    public function resolve(?string $internAndBrand): ?int
    {
        $candidates = array_filter([
            $this->normalize($internAndBrand),
            $this->normalize($this->leadingSegment($internAndBrand)),
        ]);

        foreach ($candidates as $candidate) {
            if ($id = $this->byName[$candidate] ?? $this->byUsername[$candidate] ?? $this->byOther[$candidate] ?? null) {
                return $id;
            }
        }

        return null;
    }

    /** "Juan Dela Cruz - Acme" => "Juan Dela Cruz" */
    private function leadingSegment(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        foreach (self::SEPARATORS as $separator) {
            if (str_contains($value, $separator)) {
                $value = Str::before($value, $separator);
            }
        }

        return $value;
    }

    /** Case- and whitespace-insensitive lookup key. */
    private function normalize(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $value = Str::lower(preg_replace('/\s+/', ' ', trim($value)));

        return $value === '' ? null : $value;
    }
}
