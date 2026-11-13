import { Badge } from '@/components/ui/badge';

const labels = {
    draft: 'Draft',
    running: 'Running',
    locked: 'Locked',
};

export default function GameStatusBadge({ status }) {
    return (
        <Badge variant={status === 'running' ? 'default' : 'secondary'}>
            {labels[status] ?? status}
        </Badge>
    );
}
