import { FilterGroup } from '@/components/filters/FilterGroup';
import { Workspace } from '@/types/models/Workspace';

interface Props {
    workspace: Workspace;
    selected: (string | number)[];
    onSelect: (id: string | number) => void;
}

const UserFilter = ({ workspace, selected, onSelect }: Props) => {
    return (
        <FilterGroup<{ id: number; name: string }>
            name="User"
            getId={(item) => item.id}
            getLabel={(item) => item.name}
            selected={selected}
            onSelect={onSelect}
            options={workspace.page_owners ?? []}
        />
    );
};

export default UserFilter;
