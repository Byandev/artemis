import RegisteredUserController from '@/actions/App/Http/Controllers/Auth/RegisteredUserController';
import { login } from '@/routes';
import { Form, Head, usePage } from '@inertiajs/react';
import { Info, LoaderCircle } from 'lucide-react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';
import { Role } from '@/types/models/Role';

interface WorkspaceInvitation {
    id: number;
    workspace_id: number;
    email: string;
    role: Role;
    workspace: {
        name: string;
        slug: string;
    };
}

interface RegisterProps {
    invitation?: WorkspaceInvitation | null;
    invitationToken?: string | null;
    [key: string]: unknown;
}

export default function Register() {
    const { invitation, invitationToken } = usePage<RegisterProps>().props;

    return (
        <AuthLayout
            title="Create an account"
            description={
                invitation
                    ? `Join ${invitation.workspace.name}`
                    : 'Enter your details below to create your account'
            }
        >
            <Head title="Register" />

            {invitation && (
                <Alert className="mb-4">
                    <Info className="h-4 w-4" />
                    <AlertDescription>
                        <div>
                            You've been invited to join{' '}
                            <span>
                                <strong>{invitation.workspace.name}</strong>
                            </span>{' '}
                            as a {invitation.role?.name} Create your account to
                            accept the invitation.
                        </div>
                    </AlertDescription>
                </Alert>
            )}

            <Form
                {...RegisteredUserController.store.form()}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-5"
            >
                {({ processing, errors }) => (
                    <>
                        {invitationToken && (
                            <input type="hidden" name="invitation" value={invitationToken} />
                        )}

                        <div className="grid gap-4">
                            <div className="grid gap-1.5">
                                <Label htmlFor="name" className="text-[13px] font-medium text-gray-700 dark:text-gray-300">
                                    Full name
                                </Label>
                                <Input
                                    id="name"
                                    type="text"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="name"
                                    name="name"
                                    placeholder="Juan dela Cruz"
                                    className="h-10 text-[14px]"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="email" className="text-[13px] font-medium text-gray-700 dark:text-gray-300">
                                    Email address
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    required
                                    tabIndex={2}
                                    autoComplete="email"
                                    name="email"
                                    placeholder="email@example.com"
                                    defaultValue={invitation?.email || ''}
                                    readOnly={!!invitation}
                                    className="h-10 text-[14px]"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="password" className="text-[13px] font-medium text-gray-700 dark:text-gray-300">
                                    Password
                                </Label>
                                <Input
                                    id="password"
                                    type="password"
                                    required
                                    tabIndex={3}
                                    autoComplete="new-password"
                                    name="password"
                                    placeholder="At least 8 characters"
                                    className="h-10 text-[14px]"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="password_confirmation" className="text-[13px] font-medium text-gray-700 dark:text-gray-300">
                                    Confirm password
                                </Label>
                                <Input
                                    id="password_confirmation"
                                    type="password"
                                    required
                                    tabIndex={4}
                                    autoComplete="new-password"
                                    name="password_confirmation"
                                    placeholder="Re-enter your password"
                                    className="h-10 text-[14px]"
                                />
                                <InputError message={errors.password_confirmation} />
                            </div>

                            <Button
                                type="submit"
                                className="mt-2 h-10 w-full text-[14px] font-medium"
                                tabIndex={5}
                                data-test="register-user-button"
                            >
                                {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                Create account
                            </Button>
                        </div>

                        <p className="text-center text-[13px] text-gray-500 dark:text-gray-400">
                            Already have an account?{' '}
                            <TextLink
                                href={invitationToken ? login({ query: { invitation: invitationToken } }).url : login().url}
                                tabIndex={6}
                            >
                                Log in
                            </TextLink>
                        </p>
                    </>
                )}
            </Form>
        </AuthLayout>
    );
}
