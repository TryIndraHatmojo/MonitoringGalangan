<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class WbsSnapshot
{
    public const MONTH = '/^\d{4}-(0[1-9]|1[0-2])$/D';

    public const EMPTY = ['mode' => 'item', 'nodes' => [], 'targets' => [], 'legacy' => [], 'lineage' => []];

    public static function validate(array $input, object $project, bool $submit = false): array
    {
        $data = Validator::make($input, [
            'mode' => 'required|in:item,legacy',
            'nodes' => 'present|array|list|max:2500',
            'nodes.*' => 'array:id,parent_id,type,code,name,weight,position,volume,unit,start_date,end_date,pic_id,source,method,stages,reviewer_role',
            'nodes.*.id' => 'required|uuid|distinct:ignore_case',
            'nodes.*.parent_id' => 'nullable|uuid',
            'nodes.*.type' => 'required|in:category,group,item',
            'nodes.*.code' => 'required|string|max:80',
            'nodes.*.name' => 'required|string|max:255',
            'nodes.*.weight' => 'required|numeric|decimal:0,6|min:0|max:100',
            'nodes.*.position' => 'required|integer|min:0|max:1000000',
            'nodes.*.volume' => 'nullable|numeric|decimal:0,6|min:0|max:999999999999',
            'nodes.*.unit' => 'nullable|string|max:40',
            'nodes.*.start_date' => 'nullable|date_format:Y-m-d',
            'nodes.*.end_date' => 'nullable|date_format:Y-m-d',
            'nodes.*.pic_id' => 'nullable|ulid',
            'nodes.*.source' => 'nullable|string|max:500',
            'nodes.*.method' => 'nullable|in:direct,stages',
            'nodes.*.stages' => 'sometimes|array|list|max:100',
            'nodes.*.stages.*' => 'array:name,weight',
            'nodes.*.stages.*.name' => 'required|string|max:180',
            'nodes.*.stages.*.weight' => 'required|numeric|decimal:0,6|min:0|max:100',
            'nodes.*.reviewer_role' => ['nullable', Rule::in(array_keys(Access::ROLES))],
            'targets' => 'present|array|list|max:40000',
            'targets.*' => 'array:item_id,period,value',
            'targets.*.item_id' => 'required|uuid',
            'targets.*.period' => ['required', 'regex:'.self::MONTH],
            'targets.*.value' => 'required|numeric|decimal:0,6|min:0|max:100',
            'legacy' => 'present|array|list|max:40000',
            'legacy.*' => 'array:category_id,period,value,source',
            'legacy.*.category_id' => 'nullable|uuid',
            'legacy.*.period' => ['required', 'regex:'.self::MONTH],
            'legacy.*.value' => 'required|numeric|decimal:0,6|min:0|max:100',
            'legacy.*.source' => 'required|string|max:500',
            'lineage' => 'present|array|list|max:5000',
            'lineage.*' => 'array:from_id,to_id,reason',
            'lineage.*.from_id' => 'required|uuid',
            'lineage.*.to_id' => 'required|uuid',
            'lineage.*.reason' => 'required|string|max:500',
        ])->validate();

        $nodes = [];
        $indices = [];
        $codes = [];
        foreach ($data['nodes'] as $i => &$node) {
            $path = "nodes.{$i}";
            $node += ['parent_id' => null, 'stages' => [], 'method' => null, 'pic_id' => null, 'reviewer_role' => null, 'start_date' => null, 'end_date' => null];
            $node['id'] = Str::lower($node['id']);
            $node['parent_id'] = $node['parent_id'] ? Str::lower($node['parent_id']) : null;
            $node['code'] = Str::upper(trim($node['code']));
            $node['weight'] = bcadd((string) $node['weight'], '0', 6);
            $node['position'] = (int) $node['position'];
            self::ensure($node['code'] !== '' && ! isset($codes[$node['code']]), "{$path}.code", 'Kode harus unik dalam struktur proyek.');
            $codes[$node['code']] = true;
            if (isset($node['volume'])) {
                $node['volume'] = bcadd((string) $node['volume'], '0', 6);
            }
            self::validateNode($node, $path, $project, $submit);
            $nodes[$node['id']] = $node;
            $indices[$node['id']] = $i;
        }
        unset($node);

        $weights = [];
        $children = [];
        foreach ($nodes as $id => $node) {
            $parent = $node['parent_id'];
            $path = 'nodes.'.$indices[$id];
            if ($parent !== null) {
                self::ensure(isset($nodes[$parent]) && $nodes[$parent]['type'] !== 'item', "{$path}.parent_id", 'Pilih induk kelompok di proyek ini.');
                $children[$parent] = true;
            }
            $key = $parent ?? 'root';
            $weights[$key] = bcadd($weights[$key] ?? '0', $node['weight'], 6);
        }
        self::projectWeights($nodes);
        if ($submit) {
            self::ensure($nodes !== [], 'nodes', 'Isi struktur pekerjaan sebelum pengajuan.');
            foreach ($nodes as $id => $node) {
                self::ensure($node['type'] === 'item' || isset($children[$id]), 'nodes.'.$indices[$id].'.parent_id', 'Kelompok harus memiliki pekerjaan turunan.');
            }
            foreach ($weights as $scope => $weight) {
                $label = $scope === 'root' ? 'kategori proyek' : $nodes[$scope]['code'];
                self::ensure(bccomp($weight, '100', 6) === 0, 'nodes', "Total bobot {$label} harus 100%. Saat ini {$weight}%.");
            }
        }

        $lastMonth = substr($project->target_date, 0, 7);
        $firstMonth = substr($project->start_date, 0, 7);
        self::validateTargets($data, $nodes, $firstMonth, $lastMonth, $submit);
        self::validateLegacy($data, $nodes, $firstMonth, $lastMonth, $submit);

        $links = [];
        foreach ($data['lineage'] as $index => &$link) {
            $link['from_id'] = Str::lower($link['from_id']);
            $link['to_id'] = Str::lower($link['to_id']);
            $key = $link['from_id'].'/'.$link['to_id'];
            self::ensure($link['from_id'] !== $link['to_id'] && ! isset($nodes[$link['from_id']])
                && ($nodes[$link['to_id']]['type'] ?? null) === 'item' && ! isset($links[$key]),
                "lineage.{$index}", 'Hubungkan item asal yang dihentikan ke item pengganti yang aktif.');
            $links[$key] = true;
        }

        return $data;
    }

    private static function validateNode(array &$node, string $path, object $project, bool $submit): void
    {
        self::ensure(($node['parent_id'] === null) === ($node['type'] === 'category'), "{$path}.parent_id", 'Simpul teratas harus berupa kategori.');
        self::ensure(! $submit || bccomp($node['weight'], '0', 6) > 0, "{$path}.weight", 'Bobot harus lebih dari nol.');

        if ($node['type'] !== 'item') {
            self::ensure($node['method'] === null && $node['stages'] === [], "{$path}.method", 'Metode pengukuran hanya berlaku pada item.');
        }
        foreach (['start_date', 'end_date'] as $field) {
            self::ensure($node[$field] === null || ($node[$field] >= $project->start_date && $node[$field] <= $project->target_date),
                "{$path}.{$field}", 'Tanggal berada di luar jadwal proyek.');
        }
        self::ensure(! $node['start_date'] || ! $node['end_date'] || $node['start_date'] <= $node['end_date'], "{$path}.end_date", 'Tanggal selesai mendahului tanggal mulai.');

        if ($node['type'] !== 'item') {
            return;
        }

        self::ensure(! $submit || ($node['start_date'] && $node['end_date'] && $node['method']), "{$path}.method", 'Lengkapi jadwal dan metode item.');
        $sum = '0';
        foreach ($node['stages'] as $i => &$stage) {
            $stage['weight'] = bcadd((string) $stage['weight'], '0', 6);
            self::ensure(! $submit || bccomp($stage['weight'], '0', 6) > 0, "{$path}.stages.{$i}.weight", 'Bobot tahap harus positif.');
            $sum = bcadd($sum, $stage['weight'], 6);
        }
        if ($submit && $node['method'] === 'stages') {
            self::ensure(bccomp($sum, '100', 6) === 0, "{$path}.stages", "Total bobot tahapan harus 100%. Saat ini {$sum}%.");
        } elseif ($node['method'] === 'direct') {
            self::ensure($node['stages'] === [], "{$path}.stages", 'Hapus tahapan dari item persentase langsung.');
        }
    }

    private static function validateTargets(array &$data, array $nodes, string $firstMonth, string $lastMonth, bool $submit): void
    {
        self::ensure($data['mode'] === 'item' || $data['targets'] === [], 'targets', 'Pakailah target agregat untuk baseline legacy.');
        $series = [];
        foreach ($data['targets'] as $index => &$target) {
            $id = $target['item_id'] = Str::lower($target['item_id']);
            $node = $nodes[$id] ?? null;
            $period = $target['period'];
            $path = "targets.{$index}";
            self::ensure($node && $node['type'] === 'item', "{$path}.item_id", 'Target harus terkait item yang diukur.');
            $from = $node['start_date'] ? substr($node['start_date'], 0, 7) : $firstMonth;
            $to = $node['end_date'] ? substr($node['end_date'], 0, 7) : $lastMonth;
            self::ensure($period >= $from && $period <= $to, "{$path}.period", 'Target harus berada dalam jadwal item.');
            self::ensure(! isset($series[$id][$period]), "{$path}.period", 'Target item dan periode sudah ada.');
            $series[$id][$period] = $target['value'] = bcadd((string) $target['value'], '0', 6);
        }
        self::monotonic($series, 'targets');
        if ($submit && $data['mode'] === 'item') {
            foreach ($nodes as $id => $node) {
                if ($node['type'] === 'item') {
                    $end = substr($node['end_date'], 0, 7);
                    self::ensure(bccomp($series[$id][$end] ?? '0', '100', 6) === 0, 'targets', "Isi target 100% pada bulan penyelesaian {$node['code']} ({$end}).");
                }
            }
        }
    }

    private static function validateLegacy(array &$data, array $nodes, string $firstMonth, string $lastMonth, bool $submit): void
    {
        self::ensure($data['mode'] === 'legacy' || $data['legacy'] === [], 'legacy', 'Pakailah target item untuk baseline ini.');
        if ($submit && $data['mode'] === 'legacy') {
            self::ensure($data['legacy'] !== [], 'legacy', 'Masukkan target legacy beserta sumbernya.');
        }
        $series = [];
        $byPeriod = [];
        $categories = array_filter($nodes, fn (array $node): bool => $node['type'] === 'category');
        foreach ($data['legacy'] as $index => &$target) {
            $target['category_id'] = isset($target['category_id']) ? Str::lower($target['category_id']) : null;
            $id = $target['category_id'] ?? 'project';
            $period = $target['period'];
            $path = "legacy.{$index}";
            self::ensure($id === 'project' || isset($categories[$id]), "{$path}.category_id", 'Pilih proyek atau kategori tingkat teratas.');
            self::ensure($period >= $firstMonth && $period <= $lastMonth, "{$path}.period", 'Periode legacy berada di luar jadwal proyek.');
            self::ensure(! isset($series[$id][$period]), "{$path}.period", 'Target agregat sudah ada untuk lingkup dan periode ini.');
            $value = $target['value'] = bcadd((string) $target['value'], '0', 6);
            self::ensure($id === 'project' || bccomp($value, $categories[$id]['weight'], 6) <= 0, "{$path}.value", 'Kontribusi kategori melebihi bobot proyek kategori.');
            $series[$id][$period] = $byPeriod[$period][$id] = $value;
        }
        self::monotonic($series, 'legacy');
        foreach ($byPeriod as $period => $targets) {
            $total = $targets['project'] ?? null;
            unset($targets['project']);
            if ($total === null) {
                continue;
            }
            $sum = array_reduce($targets, fn ($value, $next) => bcadd($value, $next, 6), '0');
            $delta = bcsub($total, $sum, 6);
            self::ensure(bccomp($delta, '-0.001', 6) >= 0
                && (count($targets) !== count($categories) || bccomp($delta, '0.001', 6) <= 0),
                'legacy', "Target proyek dan rincian kategori {$period} tidak cocok.");
        }
    }

    private static function monotonic(array $series, string $field): void
    {
        foreach ($series as $periods) {
            ksort($periods);
            $previous = '0';
            foreach ($periods as $period => $value) {
                self::ensure(bccomp($value, $previous, 6) >= 0, $field, "Target kumulatif turun pada {$period}.");
                $previous = $value;
            }
        }
    }

    /** @param array<string, array> $nodes */
    public static function projectWeights(array $nodes): array
    {
        $weights = [];
        $visiting = [];
        $visit = function (string $id) use (&$visit, &$weights, &$visiting, $nodes): string {
            if (isset($weights[$id])) {
                return $weights[$id];
            }
            self::ensure(! isset($visiting[$id]) && isset($nodes[$id]), 'nodes', 'Struktur WBS mengandung siklus atau induk tidak valid.');
            $visiting[$id] = true;
            $node = $nodes[$id];
            $weight = $node['parent_id']
                ? bcdiv(bcmul($visit($node['parent_id']), (string) $node['weight'], 24), '100', 18)
                : bcadd((string) $node['weight'], '0', 18);
            unset($visiting[$id]);

            return $weights[$id] = $weight;
        };
        foreach (array_keys($nodes) as $id) {
            $visit($id);
        }

        return $weights;
    }

    public static function physicalProgress(array $stages, array $completion): string
    {
        $sum = '0';
        foreach ($stages as $i => $stage) {
            $sum = bcadd($sum, bcdiv(bcmul((string) $stage['weight'], (string) $completion[$i], 12), '100', 12), 12);
        }

        return self::format($sum);
    }

    public static function format(string $value): string
    {
        return bcadd($value, '0.0005', 3);
    }

    public static function ensure(bool $condition, string $field, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }
}
