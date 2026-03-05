import { Shop } from '@/types/models/Shop';
import { Workspace } from '@/types/models/Workspace';
import { EntityFilter } from '@/components/filters/EntityFilter';


interface Props {
    workspace: Workspace;
    selected: (string | number)[];
    onSelect: (id: string | number) => void;
}

const ShopFilter = ({ workspace, selected, onSelect }: Props) => {
    return (
        <EntityFilter<Shop>
            workspace={workspace}
            endpoint={'/shops'}
            getId={(p) => p.id}
            getLabel={(p) => p.name}
            selected={selected}
            onSelect={onSelect}
        />
    );
};

export default ShopFilter;
