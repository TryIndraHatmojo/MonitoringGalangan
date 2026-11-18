<?php

namespace App\Services;

use Illuminate\Support\Str;

final class WbsTemplates
{
    private const FERRY = [
        ['A', 'Pengadaan / Material', '73.845', 'Material'],
        ['B', 'Jasa Pembangunan', '23.403', 'Konstruksi'],
        ['C', 'Desain, Gambar Kerja & Final Drawing', '0.727', 'Drawing'],
        ['D', 'Jasa Pihak Lain', '2.025', null],
    ];

    private const TUG = [
        ['01', 'Design & Approval Drawing', '5', 'Drawing'],
        ['02', 'Work Preparation & General', '5', null],
        ['03', 'Hull Construction', '25', 'Konstruksi'],
        ['04', 'Hull Outfitting', '8', null],
        ['05', 'Piping & Valve', '6', null],
        ['06', 'Machinery Outfitting', '45', 'Material'],
        ['07', 'Electric Outfitting', '3.5', null],
        ['08', 'Painting & Corrosion Protection', '2', 'Pengecatan'],
        ['09', 'Spare Part & Inventory', '0.5', 'Material'],
    ];

    public static function methods(): array
    {
        return [
            'Material' => self::steps(['Pembayaran / pelunasan' => '80', 'On site' => '10', 'Digunakan' => '10']),
            'Konstruksi' => self::steps([
                'Fabrication · Marking' => '14', 'Fabrication · Cutting' => '19.25', 'Fabrication · Inspection' => '1.75',
                'Assembly · Fit-up' => '18', 'Assembly · Welding' => '24.75', 'Assembly · Inspection' => '2.25',
                'Erection · Fit-up' => '8', 'Erection · Welding' => '11', 'Erection · Inspection' => '1',
            ]),
            'Pengecatan' => self::steps([
                'Surface Preparation' => '40', 'Primer' => '20', 'Intermediate' => '15', 'Final' => '15', 'Inspeksi' => '10',
            ]),
            'Drawing' => self::steps([
                'Diterima / selesai' => '50', 'Owner Approved' => '25', 'Class Approved' => '12.5', 'Government Approved' => '12.5',
            ]),
        ];
    }

    public static function project(string $template, object $project, ?string $reviewer): array
    {
        $rows = $template === 'ferry' ? self::FERRY : self::TUG;
        $methods = self::methods();
        $nodes = [];
        foreach ($rows as $index => [$code, $name, $weight, $method]) {
            $id = (string) Str::uuid();
            $source = $template === 'ferry' ? 'Ferry · Resume!B'.(13 + $index).':C'.(13 + $index) : 'Tug · laporan_1!B'.(14 + $index).':C'.(14 + $index);
            $nodes[] = [
                'id' => $id, 'parent_id' => null, 'type' => 'category', 'code' => $code, 'name' => $name,
                'weight' => $weight, 'position' => $index, 'source' => $source,
            ];
            $nodes[] = [
                'id' => (string) Str::uuid(), 'parent_id' => $id, 'type' => 'item', 'code' => $code.'.01',
                'name' => $name.' · Item contoh', 'weight' => '100', 'position' => 0,
                'start_date' => $project->start_date, 'end_date' => $project->target_date,
                'method' => $method ? 'stages' : 'direct', 'stages' => $methods[$method] ?? [],
                'reviewer_role' => $reviewer,
            ];
        }

        return ['mode' => 'item', 'nodes' => $nodes, 'targets' => [], 'legacy' => [], 'lineage' => []];
    }

    private static function steps(array $weights): array
    {
        $stages = [];
        foreach ($weights as $name => $weight) {
            $stages[] = compact('name', 'weight');
        }

        return $stages;
    }
}
