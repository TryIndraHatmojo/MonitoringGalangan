import { flexRender, getCoreRowModel, useReactTable, type ColumnDef } from '@tanstack/react-table';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

export function DataTable<T>({ data, columns, rowKey, empty, label }: {
    data: T[];
    columns: ColumnDef<T>[];
    rowKey: (item: T) => string;
    empty?: string;
    label: string;
}) {
    const table = useReactTable({ data, columns, getRowId: rowKey, getCoreRowModel: getCoreRowModel() });
    if (!data.length && empty) return <p className="empty">{empty}</p>;

    return <Table aria-label={label}>
        <TableHeader>{table.getHeaderGroups().map((group) =>
            <TableRow key={group.id}>{group.headers.map((header) =>
                <TableHead key={header.id} scope="col">
                    {header.isPlaceholder ? null : flexRender(header.column.columnDef.header, header.getContext())}
                </TableHead>
            )}</TableRow>
        )}</TableHeader>
        <TableBody>{table.getRowModel().rows.map((row) =>
            <TableRow key={row.id}>{row.getVisibleCells().map((cell) =>
                <TableCell key={cell.id}>
                    {flexRender(cell.column.columnDef.cell, cell.getContext())}
                </TableCell>
            )}</TableRow>
        )}</TableBody>
    </Table>;
}
