import { EntityFilter } from '@/components/filters/EntityFilter';
import { Team } from '@/types/models/Team';
import { Workspace } from '@/types/models/Workspace';

interface Props {
    workspace: Workspace;
    selected: (string | number)[];
    onSelect: (id: string | number) => void;
}

const TeamFilter = ({ workspace, selected, onSelect }: Props) => {
    return (
        <EntityFilter<Team>
            workspace={workspace}
            endpoint={'/teams'}
            getId={(p) => p.id}
            getLabel={(p) => p.name}
            selected={selected}
            onSelect={onSelect}
        />
    );
};

export default TeamFilter;
