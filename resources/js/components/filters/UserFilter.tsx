import { EntityFilter } from '@/components/filters/EntityFilter';
import { Workspace } from '@/types/models/Workspace';
import { User } from '@/types'

const UserFilter = ({ workspace }: { workspace: Workspace }) => {
    return (
        <EntityFilter<User>
            workspace={workspace}
            endpoint={'/users'}
            getId={(p) => p.id}
            getLabel={(p) => p.name}
        />
    );
};

export default UserFilter;
