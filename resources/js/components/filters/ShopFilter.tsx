import { Shop } from '@/types/models/Shop';
import { Workspace } from '@/types/models/Workspace';
import { EntityFilter } from '@/components/filters/EntityFilter';

const ShopFilter = ({ workspace }: { workspace: Workspace }) => {
    return (
        <EntityFilter<Shop>
            workspace={workspace}
            endpoint={'/shops'}
            getId={(p) => p.id}
            getLabel={(p) => p.name}
        />
    );
};

export default ShopFilter;
