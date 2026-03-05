import { FilterGroup } from '@/components/filters/FilterGroup';
import { Shop } from '@/types/models/Shop';
import { Workspace } from '@/types/models/Workspace';

interface Props {
    workspace: Workspace;
    selected: (string | number)[];
    onSelect: (id: string | number) => void;
}

const ShopFilter = ({ workspace, selected, onSelect }: Props) => {
    return (
        <FilterGroup<Shop>
            name={'Shop'}
            getId={(item) => item.id}
            getLabel={(item) => item.name}
            selected={selected}
            onSelect={onSelect}
            options={workspace.shops ?? []}
        />
    );
};

export default ShopFilter;
