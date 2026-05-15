import { FilterGroup } from '@/components/filters/FilterGroup';
import { Team } from '@/types/models/Team';
import { Workspace } from '@/types/models/Workspace';

interface Props {
    workspace: Workspace;
    selected: (string | number)[];
    onSelect: (id: string | number) => void;
}

const TeamFilter = ({ workspace, selected, onSelect }: Props) => {
    return (
        <FilterGroup<Team>
            name="Team"
            getId={(item) => item.id}
            getLabel={(item) => item.name}
            selected={selected}
            onSelect={onSelect}
            options={workspace.teams ?? []}
            searchable={false}
        />
    );
};

export default TeamFilter;
