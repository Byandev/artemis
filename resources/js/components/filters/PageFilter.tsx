import { Page } from '@/types/models/Page';
import { Workspace } from '@/types/models/Workspace';
import { EntityFilter } from '@/components/filters/EntityFilter';

interface Props {
    workspace: Workspace;
    selected: (string | number)[];
    onSelect: (id: string | number) =>  void
}

const PageFilter = ({ workspace, selected, onSelect }: Props) => {
    return <EntityFilter<Page>
        workspace={workspace}
        endpoint={'/pages'}
        getId={(p) => p.id}
        getLabel={(p) => p.name}
        selected={selected}
        onSelect={onSelect}
    />
}

export default PageFilter;
