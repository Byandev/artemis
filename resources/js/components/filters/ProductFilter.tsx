import { EntityFilter } from '@/components/filters/EntityFilter';
import { Product } from '@/types/models/Product';
import { Workspace } from '@/types/models/Workspace';

interface Props {
    workspace: Workspace;
    selected: (string | number)[];
    onSelect: (id: string | number) => void;
}

const ProductFilter = ({ workspace, selected, onSelect }: Props) => {
    return (
        <EntityFilter<Product>
            workspace={workspace}
            endpoint={'/products'}
            getId={(p) => p.id}
            getLabel={(p) => p.code}
            selected={selected}
            onSelect={onSelect}
        />
    );
};

export default ProductFilter;
