import { EntityFilter } from '@/components/filters/EntityFilter';
import { Team } from '@/types/models/Team';
import { Workspace } from '@/types/models/Workspace';

const TeamFilter = ({ workspace }: { workspace: Workspace }) => {
    return (
        <EntityFilter<Team>
            workspace={workspace}
            endpoint={'/teams'}
            getId={(p) => p.id}
            getLabel={(p) => p.name}
        />
    );
};

export default TeamFilter;
