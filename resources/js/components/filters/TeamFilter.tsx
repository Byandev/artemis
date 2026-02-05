import { Workspace } from '@/types/models/Workspace';
import { useEffect, useState } from 'react';
import axios, { AxiosResponse } from 'axios';
import { Team } from '@/types/models/Team';
import { PaginatedData } from '@/types';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { useDebouncedState } from '@/hooks/use-debounced-state';

const TeamFilter = ({ workspace }: { workspace: Workspace }) => {
    const [teams, setTeams] = useState<Team[]>([]);
    const {
        value: search,
        setValue: setSearch,
        debounced,
    } = useDebouncedState('', { delay: 350 });

    useEffect(() => {
        axios
            .get(`/api/workspaces/${workspace.slug}/teams`, {
                params: {
                    'filter[search]': debounced,
                },
            })
            .then((response: AxiosResponse<PaginatedData<Team>>) =>
                setTeams(response.data.data)
            );
    }, [workspace.slug, debounced]);

    return (
        <div>
            <div className="mb-2 border-b pb-2">
                <Input
                    value={search}
                    placeholder="Search product"
                    className="text-xs placeholder:text-xs"
                    onChange={(e) => setSearch(e.target.value)}
                />
            </div>

            {teams.length > 0 ? (
                <div className="space-y-2">
                    {teams.map((team) => {
                        return (
                            <div
                                key={team.id}
                                className="flex items-center gap-x-2 text-xs"
                            >
                                <Checkbox
                                    id={team.id.toString()}
                                    name={team.id.toString()}
                                />
                                <Label
                                    htmlFor={team.id.toString()}
                                    className="text-xs"
                                >
                                    {team.name}
                                </Label>
                            </div>
                        );
                    })}
                </div>
            ) : (
                <p className="text-center text-xs">No Result</p>
            )}
        </div>
    );
}

export default TeamFilter;
