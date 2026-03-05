import { Page } from '@/types/models/Page';
import { Workspace } from '@/types/models/Workspace';
import { FilterGroup } from '@/components/filters/FilterGroup';

interface Props {
    workspace: Workspace;
    selected: (string | number)[];
    onSelect: (id: string | number) =>  void
}

const PageFilter = ({ workspace, selected, onSelect }: Props) => {
    return <FilterGroup<Page>
        name={'Page'}
        getId={(item) => item.id}
        getLabel={(item) => item.name}
        selected={selected}
        onSelect={onSelect}
        options={workspace.pages ?? []}
    />
}

export default PageFilter;
