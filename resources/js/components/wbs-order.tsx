import { DndContext, KeyboardSensor, PointerSensor, closestCenter, useSensor, useSensors, type CollisionDetection } from '@dnd-kit/core';
import { SortableContext, arrayMove, sortableKeyboardCoordinates, useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { ArrowDown, ArrowUp, GripVertical } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';

type SortableNode = { id: string; parent_id: string | null; position: number; code: string; name: string };

const byPosition = (a: SortableNode, b: SortableNode) => a.position - b.position || a.code.localeCompare(b.code);
const siblingCollision: CollisionDetection = (args) => closestCenter({
    ...args,
    droppableContainers: args.droppableContainers.filter((container) =>
        container.data.current?.parentId === args.active.data.current?.parentId),
});

function groupNodes<T extends SortableNode>(nodes: T[]) {
    const groups = new Map<string | null, T[]>();
    for (const node of nodes) {
        const siblings = groups.get(node.parent_id) ?? [];
        siblings.push(node);
        groups.set(node.parent_id, siblings);
    }
    for (const siblings of groups.values()) siblings.sort(byPosition);
    return groups;
}

function SortableRow({ node, depth, siblings, move, children }: {
    node: SortableNode;
    depth: number;
    siblings: SortableNode[];
    move: (source: string, target: string) => void;
    children: ReactNode;
}) {
    const { attributes, listeners, setNodeRef, setActivatorNodeRef, transform, isDragging, isOver, active } = useSortable({
        id: node.id, data: { parentId: node.parent_id },
    });
    const index = siblings.findIndex((sibling) => sibling.id === node.id);
    const label = node.code || node.name || 'simpul baru';
    return <div ref={setNodeRef} data-wbs-node={label} className="wbs-sortable"
        data-over={Boolean(active && isOver && !isDragging)}
        style={{ paddingInlineStart: Math.min(depth, 4) * 12, transform: isDragging ? CSS.Translate.toString(transform) : undefined, zIndex: isDragging ? 1 : undefined }}>
        <div className="wbs-sort-controls">
            <Button type="button" variant="ghost" size="icon-sm" className="touch-none cursor-grab active:cursor-grabbing"
                ref={setActivatorNodeRef} {...attributes} {...listeners} aria-label={'Seret ' + label} disabled={siblings.length < 2}><GripVertical /></Button>
            <Button type="button" variant="ghost" size="icon-sm" aria-label={'Naikkan ' + label}
                disabled={index <= 0} onClick={() => move(node.id, siblings[index - 1].id)}><ArrowUp /></Button>
            <Button type="button" variant="ghost" size="icon-sm" aria-label={'Turunkan ' + label}
                disabled={index >= siblings.length - 1} onClick={() => move(node.id, siblings[index + 1].id)}><ArrowDown /></Button>
        </div>
        {children}
    </div>;
}

export function WbsOrder<T extends SortableNode>({ nodes, onChange, renderNode }: {
    nodes: T[]; onChange: (nodes: T[]) => void; renderNode: (node: T) => ReactNode;
}) {
    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
        useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
    );
    const groups = groupNodes(nodes);
    const order: { node: T; depth: number }[] = [];
    const visited = new Set<string>();
    function visit(node: T, depth: number) {
        if (visited.has(node.id)) return;
        visited.add(node.id);
        order.push({ node, depth });
        for (const child of groups.get(node.id) ?? []) visit(child, depth + 1);
    }
    for (const node of groups.get(null) ?? []) visit(node, 0);
    for (const node of nodes) visit(node, 0);

    function move(source: string, target: string) {
        const node = nodes.find((item) => item.id === source);
        if (!node || source === target) return;
        const siblings = groups.get(node.parent_id) ?? [];
        const from = siblings.findIndex((item) => item.id === source);
        const to = siblings.findIndex((item) => item.id === target);
        if (to < 0) return;
        const positions = new Map(arrayMove(siblings, from, to).map((item, index) => [item.id, index]));
        onChange(nodes.map((item) => positions.has(item.id) ? { ...item, position: positions.get(item.id)! } : item));
    }

    return <DndContext sensors={sensors} collisionDetection={siblingCollision}
        accessibility={{ screenReaderInstructions: { draggable: 'Tekan spasi untuk mengambil simpul, panah untuk memindahkan, lalu spasi untuk meletakkan. Tekan Escape untuk membatalkan.' } }}
        onDragEnd={({ active, over }) => { if (over) move(String(active.id), String(over.id)); }}>
        <SortableContext items={order.map(({ node }) => node.id)}>
            <div className="grid gap-2">
                {order.map(({ node, depth }) => <SortableRow key={node.id} node={node} depth={depth}
                    siblings={groups.get(node.parent_id) ?? []} move={move}>{renderNode(node)}</SortableRow>)}
            </div>
        </SortableContext>
    </DndContext>;
}
