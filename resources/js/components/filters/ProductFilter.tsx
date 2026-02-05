import { Product } from '@/types/models/Product';
import { Workspace } from '@/types/models/Workspace';
import { EntityFilter } from '@/components/filters/EntityFilter';

const ProductFilter = ({ workspace }: { workspace: Workspace }) => {
    return (
        <EntityFilter<Product>
            workspace={workspace}
            endpoint={'/products'}
            getId={(p) => p.id}
            getLabel={(p) => p.code}
        />
    );
};

export default ProductFilter;
