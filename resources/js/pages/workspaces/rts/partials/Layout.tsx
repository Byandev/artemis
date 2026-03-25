import { Workspace } from '@/types/models/Workspace'
import { type PropsWithChildren } from 'react'
import RtsNavigation from './RtsNavigation'

interface RTSManagementLayoutProps {
    workspace: Workspace
}

const RTSManagementLayout = ({ workspace, children }: PropsWithChildren<RTSManagementLayoutProps>) => {
    return (
        <div className="mx-auto w-full max-w-(--breakpoint-2xl) p-4 md:p-6">
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <h2
                    className="text-[22px] font-semibold tracking-tight text-gray-900 dark:text-gray-100"
                    x-text="pageName"
                >
                    RTS Management
                </h2>
            </div>

            <div className="">
                <RtsNavigation workspace={workspace} />

                <div className="pt-4 dark:border-gray-800">
                    {children}
                </div>
            </div>
        </div>
    )
}

export default RTSManagementLayout