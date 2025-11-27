import React from 'react'
import {
  DropdownMenu,
  DropdownMenuTrigger,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
} from "@/components/ui/dropdown-menu";
import { Button } from "@/components/ui/button";
import { ChevronsUpDown, PlusIcon, CheckIcon } from "lucide-react";
import { Workspace } from '@/types/models/Workspace';
import { Link } from '@inertiajs/react';

interface WorkspaceSwitcherProps {
    workspaces?: Workspace[];
    currentWorkspace?: Workspace;   
    onSwitch: (workspace: string) => void;
}

const WorkspaceSwitcher: React.FC<WorkspaceSwitcherProps> = ({ workspaces, currentWorkspace, onSwitch }) => {
  if (!currentWorkspace) {
    return null;
  }

  return (
    <DropdownMenu>
        <DropdownMenuTrigger asChild>
            <Button variant="outline" className="w-full justify-between">
                {currentWorkspace.name}
                <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent className="w-full">
            <DropdownMenuLabel>Switch workspace</DropdownMenuLabel>
            <DropdownMenuSeparator />

            {/* Workspace list (primary) */}
            {workspaces?.map((workspace) => (
                <DropdownMenuItem 
                    key={workspace.id} 
                    onClick={() => onSwitch(workspace.name)}
                    disabled={workspace.name === currentWorkspace.name}
                >
                    {workspace.name}
                    {workspace.name === currentWorkspace.name && (
                        <CheckIcon className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                    )}
                </DropdownMenuItem>
            ))}

            <DropdownMenuSeparator />

            {/* Workspace-specific actions */}
            <DropdownMenuItem>
                <Link href={`/workspaces/${currentWorkspace.slug}/members`} className="w-full flex items-center">
                    Manage members
                </Link>
            </DropdownMenuItem>

            {/* Global action */}
            <DropdownMenuItem>
                All workspaces
            </DropdownMenuItem>

            <DropdownMenuSeparator />

            {/* CTA */}
            <DropdownMenuItem>
                <Link href="/workspaces/create" className="w-full flex items-center">
                    <PlusIcon className="mr-2 h-4 w-4" /> New workspace
                </Link>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
  )
}

export default WorkspaceSwitcher