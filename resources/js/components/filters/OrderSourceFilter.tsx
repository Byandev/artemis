import { FilterGroup } from '@/components/filters/FilterGroup';

interface Props {
    options: string[];
    selected: (string | number)[];
    onSelect: (id: string | number) => void;
}

const OrderSourceFilter = ({ options, selected, onSelect }: Props) => {
    return (
        <FilterGroup<string>
            name={'Order Source'}
            getId={(item) => item}
            getLabel={(item) => item}
            selected={selected}
            onSelect={onSelect}
            options={options}
        />
    );
};

export default OrderSourceFilter;
