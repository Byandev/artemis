import { EntityFilter } from '@/components/filters/EntityFilter';
import { Workspace } from '@/types/models/Workspace';
import { User } from '@/types'


interface Props {
    workspace: Workspace;
    selected: (string | number)[];
    onSelect: (id: string | number) => void;
}

const UserFilter = ({ workspace, selected, onSelect }: Props) => {
    return (
        <EntityFilter<User>
            workspace={workspace}
            endpoint={'/users'}
            getId={(p) => p.id}
            getLabel={(p) => p.name}
            selected={selected}
            onSelect={onSelect}
        />
    );
};

export default UserFilter;
